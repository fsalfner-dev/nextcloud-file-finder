<?php

declare(strict_types=1);

namespace OCA\FileFinder\Service;

use DateTime;
use Psr\Log\LoggerInterface;

use OC\Files\Search\SearchComparison;
use OC\Files\Search\SearchBinaryOperator;
use OC\Files\Search\SearchOrder;
use OC\Files\Search\SearchQuery;


use OCP\IAppConfig;
use OCP\IURLGenerator;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\FileInfo;
use OCP\Files\IMimeTypeDetector;
use OCP\Files\IRootFolder;
use OCP\IUserSession;
use OCP\IDBConnection;
use OCP\Files\Search\ISearchQuery;
use OCP\Files\Search\ISearchBinaryOperator;
use OCP\Files\Search\ISearchComparison;


use OCA\FileFinder\Exceptions\QueryException;
use OCA\FileFinder\Exceptions\ConfigException;
use OCA\FileFinder\Service\TypeExtensionMapper;


class SearchServiceFiles  {

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
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

	public function __construct(string $appName,
                                IURLGenerator $urlGenerator,
                                IRootFolder $rootFolder,
                                IUserSession $userSession,
                                IMimeTypeDetector $mimeTypeDetector,
                                LoggerInterface $logger) {
        $this->urlGenerator = $urlGenerator;
        $this->userSession = $userSession;
        $this->rootFolder = $rootFolder;
        $this->mimeTypeDetector = $mimeTypeDetector;
        $this->logger = $logger;
	}

	public function searchFiles(array $search_criteria, int $page, int $size, string $sort = 'path', string $sort_order = 'asc'): array {
        $user = $this->userSession->getUser();
        if (!$user) {
            throw new ConfigException('could not determine user');
        }
        $userID = $user->getUID();

        $searchQuery = $this->buildQuery($search_criteria, $user, $size, $page, $sort, $sort_order);
        $userFolder = $this->rootFolder->getUserFolder($userID);
        $resultNodes = $userFolder->search($searchQuery);

        $files = [];
        foreach ($resultNodes as $node) {
            $files[] = $this->buildHit($node, $userID);
        }
        return [
                'hits' => count($resultNodes),
				'page' => $page,
				'size' => $size,
				'files' => $files
			];
	}

    private function buildQuery($search_criteria, $user, $size, $page, $sort_field, $sort_order) : ISearchQuery {
        // build the base of the query to match filenames by wildcard
        $filename = $search_criteria['filename'] ?? '';
        if ((!isset($search_criteria['filename']) || trim((string) $filename) === '')) {
            throw new QueryException('A search pattern needs to be provided');
        }
        // the path field always contains the full path. Hence a query "Lease*.pdf" would only
        // match for documents in the root directory. To also return documents from 
        // subfolders, we make sure that there is an asterisk at the beginning to account for 
        // the directory name 
        $filenameSearchterm = !str_starts_with($filename, '*') ? '*' . $filename : $filename;
        $filenameLikePattern = str_replace(['*', '?'], ['%', '_'], $filenameSearchterm);
        $this->logger->debug('searching for paths with the pattern: ' . $filenameLikePattern);
        $searchOperator = new SearchComparison(ISearchComparison::COMPARE_LIKE, 'path', $filenameLikePattern);

        // extend the query to only match documents for the specified file types (multi-selection allowed)
        // file type matching is performed based on file extensions not on mime-types
        $extensions = TypeExtensionMapper::getExtensionsForTypes($search_criteria['file_types'] ?? null);
        if ($extensions !== []) {
            // only modify the searchOperator if file extension filtering is needed
            $extensionOperators = [];
            foreach ($extensions as $extension) {
                $extensionLikePattern = '%.' . $extension;
                $extensionOperators[] = new SearchComparison(ISearchComparison::COMPARE_LIKE, 'name', $extensionLikePattern );
                $this->logger->debug('adding filter for extension: ' . $extension);
                }
            $extensionTerm = new SearchBinaryOperator(ISearchBinaryOperator::OPERATOR_OR, $extensionOperators);
            $searchOperator = new SearchBinaryOperator(ISearchBinaryOperator::OPERATOR_AND, [$searchOperator, $extensionTerm]);
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
            $beforeOperator = new SearchComparison(ISearchComparison::COMPARE_LESS_THAN_EQUAL, 'mtime', $before_seconds);
            $searchOperator = new SearchBinaryOperator(ISearchBinaryOperator::OPERATOR_AND, [ $searchOperator, $beforeOperator ]);
        }
        if (isset($search_criteria['after_date'])) {
            try {
                $after_date = new DateTime($search_criteria['after_date']);
                $after_seconds = $after_date->getTimestamp();
            } catch (Exception $e) {
                throw new QueryException('invalid after date provided');
            }
            $afterOperator = new SearchComparison(ISearchComparison::COMPARE_GREATER_THAN_EQUAL, 'mtime', $after_seconds);
            $searchOperator = new SearchBinaryOperator(ISearchBinaryOperator::OPERATOR_AND, [ $searchOperator, $afterOperator ]);
        }

        // extend the query to exclude files and folders below the provided list of excluded
        // folders
        if (isset($search_criteria['exclude_folders']) && is_array($search_criteria['exclude_folders'])) {
            $excludeFolderOperators = [];
            $userFolder = $this->rootFolder->getUserFolder($user->getUID());
            foreach ($search_criteria['exclude_folders'] as $folder) {
                if (!is_string($folder)) {
                    $this->logger->error('provided exclusion folder is not a string: ' . $folder);
                    continue;
                }

                // exclude all files and folders under the folder
                $excludeFolderLikePattern = $userFolder->getName() . '/' . $folder . '%';
                $this->logger->debug('excluding folder ' . $folder . ' with pattern: ' . $excludeFolderLikePattern);
                $excludeFolderComparison = new SearchComparison(ISearchComparison::COMPARE_LIKE, 'path', $excludeFolderLikePattern);
                $excludeFolderOperators[] = new SearchBinaryOperator(ISearchBinaryOperator::OPERATOR_NOT, [$excludeFolderComparison]);

                // exclude the folder itself
                // note that $folder ends with a trailing slash
                $excludeFolderLikePattern = $userFolder->getName() . '/' . substr($folder, 0, -1);
                $this->logger->debug('excluding folder ' . $folder . ' with pattern: ' . $excludeFolderLikePattern);
                $excludeFolderComparison = new SearchComparison(ISearchComparison::COMPARE_LIKE, 'path', $excludeFolderLikePattern);
                $excludeFolderOperators[] = new SearchBinaryOperator(ISearchBinaryOperator::OPERATOR_NOT, [$excludeFolderComparison]);
            }
            $excludedFoldersOperator = new SearchBinaryOperator(ISearchBinaryOperator::OPERATOR_AND, $excludeFolderOperators);
            $searchOperator = new SearchBinaryOperator(ISearchBinaryOperator::OPERATOR_AND, [ $searchOperator, $excludedFoldersOperator ]);
        }

        // generating the sort order 
        $order = ($sort_order === 'asc') ? 'asc' : 'desc';        
        switch ($sort_field) {
            case 'modified':
                // Sort by modification date
                $searchOrder = new SearchOrder($order, 'mtime');
                break;
            case 'score':
                // the files search service does not support filtering by score
                // fall back to path filtering
            case 'path':
                // Sort by file path
            default:
                $searchOrder = new SearchOrder($order, 'path');
        }
        $offset = $page * $size;
        $this->logger->debug('running search with size=' . $size . ' and offset=' . $offset);
        $searchQuery = new SearchQuery(
            $searchOperator,                    // ISearchOperator
            $size,                              // int limit
            $offset,                            // int offset
            [ $searchOrder ],                   // order
            $user,                              // IUser
            false                               // bool limitToHome
        );
        return $searchQuery;
    }


    private function buildHit($node, $userID) : ?array {
        try {
            $fileId = $node->getId();
            $path = $node->getPath();
            $userFolder = $this->rootFolder->getUserFolder($userID);
            $relativePath = $userFolder->getRelativePath($path);
            if (str_starts_with($relativePath, '/')) {
                $relativePath = substr($relativePath, 1);
            }

            // add a trailing slash to folders
            if ($node->getType() === FileInfo::TYPE_FOLDER) {
                $relativePath = $relativePath . '/';
            }

            $modification_ts = $node->getMtime();
            $mimeType = $node->getMimetype();
            $mimeTypeIcon = $this->mimeTypeDetector->mimeTypeIcon($mimeType);

            $parentFolder = dirname($path);
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
                'name' => $relativePath,
                'link' => $url,
                'icon_link' => $mimeTypeIcon,
                'modified_at' => $modification_ts,
                'highlights' => []
            ];
        } catch (\Exception $e) {
            return [
                'name' => $node->getPath(),
                'error' => $e->getMessage(),
            ];
        } catch (\Error $e) { 
            return [
                'name' => $node->getPath(),
                'error' => $e->getMessage(),
            ];
        }
    }

}
