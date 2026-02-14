<?php

declare(strict_types=1);

namespace OCA\FileFinder\Service;

use DateTime;
use OCP\IAppConfig;
use OCP\IURLGenerator;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IMimeTypeDetector;
use OCP\Files\IRootFolder;
use OCP\IUserSession;
use OCP\Search\ISearchService;

use OCA\FileFinder\Exceptions\QueryException;
use OCA\FileFinder\Exceptions\ConfigException;


class SearchServiceFiles  {

    /** @var array<string, string[]> */
    private const FILE_TYPE_EXTENSIONS = [
        'images' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg', 'ico', 'heic'],
        'music' => ['mp3', 'ogg', 'flac', 'wav', 'm4a', 'aac', 'wma'],
        'pdfs' => ['pdf'],
        'spreadsheets' => ['xls', 'xlsx', 'ods', 'csv', 'numbers'],
        'documents' => ['doc', 'docx', 'odt', 'txt', 'rtf', 'md'],
        'videos' => ['mp4', 'webm', 'mkv', 'avi', 'mov', 'wmv'],
    ];

    /**
	 * @var IAppConfig
	 */
	private IAppConfig $appConfig;

    /**
     * @var IURLGenerator
     */
    private IURLGenerator $urlGenerator;

    /**
     * @var IRootFolder
     */
    private IRootFolder $rootFolder;

    /**
     * @var IUserSession
     */
    private IUserSession $userSession;

    /**
     * @var IMimeTypeDetector
     */
    private IMimeTypeDetector $mimeTypeDetector;

    /**
     * @var ISearchService
     */
    private ISearchService $searchService;

	public function __construct(string $appName,
								IAppConfig $appConfig,
                                IURLGenerator $urlGenerator,
                                IRootFolder $rootFolder,
                                IUserSession $userSession,
                                IMimeTypeDetector $mimeTypeDetector,
                                ISearchService $searchService) {
		$this->appConfig = $appConfig;
        $this->urlGenerator = $urlGenerator;
        $this->userSession = $userSession;
        $this->rootFolder = $rootFolder;
        $this->mimeTypeDetector = $mimeTypeDetector;
        $this->searchService = $searchService;
	}

	public function searchFiles(array $search_criteria, int $page, int $size, string $sort = 'score', string $sort_order = 'desc'): array {
        $user = $this->userSession->getUser();
        if (!$user) {
            throw new ConfigException('could not determine user');
        }

        

        $query = $this->buildQuery($search_criteria, $user);
        $sortClause = $this->buildSort($sort, $sort_order);
        $params = [
            'index' => $index,
            'body' => [
                'size' => $size,
                'from' => $page * $size,
                'query' => $query,
            ]
        ];
        if ($sortClause !== null) {
            $params['body']['sort'] = $sortClause;
        }

        $search_result = $this->searchService->search($user, 'files', $query);

        $result = [];
        $no_of_hits = 2;
        $files = [];
        if ($user !== null) {
            foreach ($result['hits']['hits'] as $hit) {
                $file = $this->buildHit($hit, $user);
                if ($file !== null) {
                    $files[] = $file;
                }
            }
        }

        return [
				'query' => $params,
                'hits' => $no_of_hits,
				'page' => $page,
				'size' => $size,
                'result' => $result,
				'files' => $files
			];
	}

    private function buildQuery($search_criteria, $user) : array {
        $filename = $search_criteria['filename'] ?? '';
        if ((!isset($search_criteria['filename']) || trim((string) $filename) === '')) {
            throw new QueryException('A search pattern needs to be provided');
        }

        // base query to make sure only documents that the user can see are returned
        $query = [
            'bool' => [
                'filter' => [
                    [ 'regexp' => [ 'title.keyword' => '.+' ] ],
                    [ 'exists' => [ 'field' => 'share_names.' . $user ]]
                ]
            ]
        ];

        // extend query to find matches based on file content
        if (isset($search_criteria['content']) && trim((string) $content) !== '') {
            $query['bool']['must'] = [ 'match' => [ 'content' => $content ] ];
        }

        // extend query to only return files matching the filename wildcard
        if (isset($search_criteria['filename']) && trim((string) $filename) !== '') {
            // title.keyword always contains the full path. Hence a query "Lease*.pdf" would only
            // match for documents in the root directory. To also return documents from 
            // subfolders, we make sure that there is an asterisk at the beginning to account for 
            // the directory name 
            $filename_searchterm = !str_starts_with($filename, '*') ? '*' . $filename : $filename;
            $query['bool']['filter'][] = [ 'wildcard' => [ 'title.keyword' => $filename_searchterm ] ];
        }

        // extend the query to only match documents for the specified file types (multi-selection allowed)
        // file type matching is performed based on file extensions not on mime-types, since the 
        // mime-types in Elasticsearch are too unreliable
        $extensions = $this->getMergedExtensionsForTypes($search_criteria['file_types'] ?? null);
        if ($extensions !== []) {
            $pattern = '.*\.(' . implode('|', $extensions) . ')';
            $query['bool']['filter'][] = [ 'regexp' => [ 'title.keyword' => [ 'value' => $pattern, 'case_insensitive' => true ] ] ];
        }

        // extend the query to only return files where the modification date matches the
        // provided dates in before_date and after_date
        if (isset($search_criteria['before_date'])) {
            try {
                $before_date = new DateTime($search_criteria['before_date']);
                $before_seconds = $before_date->getTimestamp();
            } catch (Exception $e) {
                throw new QueryException('invalid before date provided');
            }
            $query['bool']['filter'][] = [ 'range' => ['lastModified' => [ 'lt' => $before_seconds ] ] ];
        }
        if (isset($search_criteria['after_date'])) {
            try {
                $after_date = new DateTime($search_criteria['after_date']);
                $after_seconds = $after_date->getTimestamp();
            } catch (Exception $e) {
                throw new QueryException('invalid after date provided');
            }
            $query['bool']['filter'][] = [ 'range' => ['lastModified' => [ 'gt' => $after_seconds ] ] ];
        }

        // extend the query to exclude files and folders below the provided list of excluded
        // folders
        if (isset($search_criteria['exclude_folders']) && is_array($search_criteria['exclude_folders'])) {
            $query['bool']['must_not'] = [];
            foreach ($search_criteria['exclude_folders'] as $folder) {
                if (!is_string($folder)) {
                    continue;
                }
                $query['bool']['must_not'][] = ['prefix' => [ 'title.keyword' => ['value' => $folder]]];
            }
        }
        return $query;
    }

    /**
     * Returns extensions for a single known type, or empty array for unknown.
     *
     * @return string[]
     */
    private function getExtensionsForType(string $type): array {
        return self::FILE_TYPE_EXTENSIONS[$type] ?? [];
    }

    /**
     * Merges extensions for all given types (OR semantics). Unknown types are ignored.
     *
     * @param mixed $fileTypes array of type keys, or null/empty
     * @return string[] deduplicated, lowercase extensions
     */
    private function getMergedExtensionsForTypes($fileTypes): array {
        if (!is_array($fileTypes) || $fileTypes === []) {
            return [];
        }
        $merged = [];
        foreach ($fileTypes as $t) {
            if (!is_string($t)) {
                continue;
            }
            foreach ($this->getExtensionsForType($t) as $ext) {
                $merged[$ext] = true;
            }
        }
        return array_keys($merged);
    }

    private function buildSort(string $sort, string $sort_order = 'desc'): ?array {
        // Validate sort_order
        $order = ($sort_order === 'asc') ? 'asc' : 'desc';
        
        switch ($sort) {
            case 'score':
                // Default Elasticsearch relevance score (no explicit sort needed)
                return null;
            case 'modified':
                // Sort by modification date
                return [
                    ['lastModified' => ['order' => $order]],
                    '_score' // Secondary sort by relevance
                ];
            case 'path':
                // Sort by file path
                return [
                    ['title.keyword' => ['order' => $order]],
                    '_score' // Secondary sort by relevance
                ];
            default:
                return null;
        }
    }


    private function buildHit($hit, $user) : ?array {
        $fileIdParts = explode(":", $hit['_id']);
        $title = $hit['_source']['title'];
        $modification_ts = $hit['_source']['lastModified'];
        try {
            $fileId = $fileIdParts[1];
            $filePath = $hit['_source']['share_names'][$user];
            if ($filePath === null) {
                return null;
            }
            $parentFolder = dirname($filePath);

            $mimeType = $this->mimeTypeDetector->detect($filePath);
            $hitMimeType = $hit['_source']['attachment']['content_type'];
            $mimeTypeIcon = $this->mimeTypeDetector->mimeTypeIcon($mimeType);

            $url = $this->urlGenerator->linkToRouteAbsolute(
                'files.view.index',
                [
                    'dir'      => $parentFolder,
                    'openfile' => $fileId,
                    'fileid' => $fileId,
                    ]
                );
            return [
                'content_type' => $mimeType,
                'name' => $title,
                'link' => $url,
                'icon_link' => $mimeTypeIcon,
                'modified_at' => $modification_ts,
                'highlights' => (array_key_exists('highlight', $hit)) ? $hit['highlight'] : []
            ];
        } catch (\Exception $e) {
            return [
                'name' => $title,
                'error' => $e->getMessage(),
            ];
        } catch (\Error $e) { 
            return [
                'name' => $title,
                'error' => $e->getMessage(),
            ];
        }
    }

}
