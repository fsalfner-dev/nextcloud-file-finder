<?php
/**
 * SPDX-FileCopyrightText: 2026 Felix Salfner
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

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


/**
 * A search service to search for matching files using Nextcloud's internal file database
 */
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
                                private \OCP\IL10N $l,
                                LoggerInterface $logger) {
        $this->urlGenerator = $urlGenerator;
        $this->userSession = $userSession;
        $this->rootFolder = $rootFolder;
        $this->mimeTypeDetector = $mimeTypeDetector;
        $this->logger = $logger;
	}

    /**
     * The main method of the search service to perform a search for matching files by querying Nextcloud's database
     * 
     * The parameter search_criteria may contain the following keys:
	 *   - filename: String to search for filenames with wildcards
	 *   - file_types: an array of file types from TypeExtensionMapper to filter for
	 *   - before_date: an ISO representation of date to filter for
	 *   - after_date: an ISO representation of date to filter for
	 *   - exclude_folders: an array of Strings with folders to be excluded
	 *   - start_folder: a String with a folder to use as root folder for the search
	 * 
	 * It returns an array with the following keys:
	 *   - hits: the number of hits returned (not the total number of hits in the database)
	 *   - page: the current page number
	 *   - size: the current page size
	 *   - files: an array of files matching the search criteria
	 *       - content_type: the file's mime type
	 *       - name: the full path to the file as it appears for the user (not physical path)
	 *       - link: an URL to open the file
	 *       - modified_at: the modification date as returned by Node::getMtime()
	 *       - highlights: an empty array since the service does not support fulltext search
     * 
     * raises
     *   - ConfigException if Elasticsearch is not configured correctly 
     *   - QueryException if the search_criteria contain an error
     *   - ClientException if something goes wrong with the search
     * 
     * @param array $search_criteria - the specification of what to search for
	 * @param int $page - the page number 
	 * @param int $size - the page size
	 * @param string $sort - the sort criterion (score, modified, path)
	 * @param string $sort_order - the sort order (asc, desc)
	 * @return array
     */
	public function searchFiles(array $search_criteria, int $page, int $size, string $sort = 'path', string $sort_order = 'asc'): array {
		$this->logger->debug('SearchServiceFiles: searchFiles called with page=' . $page . ', size=' . $size . ', sort=' . $sort . ', sort_order=' . $sort_order);
		
        $user = $this->userSession->getUser();
        if (!$user) {
            $this->logger->error('SearchServiceFiles: Could not determine user from session');
            throw new ConfigException($this->l->t('could not determine user'));
        }
        $userID = $user->getUID();
        $this->logger->debug('SearchServiceFiles: User ID: ' . $userID);

        // build the search query
        try {
			$searchQuery = $this->buildQuery($search_criteria, $user, $size, $page, $sort, $sort_order);
			$this->logger->debug('SearchServiceFiles: Search query built successfully');
		} catch (Exception $e) {
			$this->logger->error('SearchServiceFiles: Error building search query: ' . $e->getMessage());
			throw $e;
		}
		
        // execute the query on the user's root folder
        try {
			$userFolder = $this->rootFolder->getUserFolder($userID);
			$resultNodes = $userFolder->search($searchQuery);
			$this->logger->debug('SearchServiceFiles: Search returned ' . count($resultNodes) . ' results');
		} catch (Exception $e) {
			$this->logger->error('SearchServiceFiles: Error executing search: ' . $e->getMessage());
			throw $e;
		}

        // parse the result by calling buildHit() on each hit
        $files = [];
        foreach ($resultNodes as $node) {
            try {
				$file = $this->buildHit($node, $userID);
				if ($file !== null) {
					$files[] = $file;
				}
			} catch (Exception $e) {
				$this->logger->error('SearchServiceFiles: Error building hit for node: ' . $e->getMessage());
				// Continue processing other nodes
			}
        }     
        $this->logger->debug('SearchServiceFiles: Processed ' . count($files) . ' file results');
        
        // return the parsed results
        return [
                'hits' => count($resultNodes),
				'page' => $page,
				'size' => $size,
				'files' => $files
			];
	}

    /**
     * parse the search parameters provided and turn them into a SearchQuery
     * 
     * $search_criteria is an array that can contain the following keys:
     *   - filename: wildcard search for filenames (including their path)
     *   - file_types: the array of file types which are mapped to file extensions
     *   - before_date: files modified before the specified date
     *   - after_date: files modified after the specified date
     *   - exclude_folders: exclude files which path starts with one of the provided exclusion folders
     *   - start_folder: only include files which path starts with the provided folder prefix 
     * 
     * Note: the 'content' key is ignored since fulltext search is not supported
     * 
     * @param array $search_criteria - the search and filter criteria
     * @param User $user - the current session's user
     * @param int $size - the number of results that should be returned
     * @param int $page - the number of the search result page (starting with 0)
     * @param string $sort_field
     * @param string $sort_order
     */
    private function buildQuery($search_criteria, $user, $size, $page, $sort_field, $sort_order) : ISearchQuery {
        $this->logger->debug('SearchServiceFiles: Building search query');
        $userFolder = $this->rootFolder->getUserFolder($user->getUID());
    
        // build the base of the query to match filenames by wildcard
        $filename = $search_criteria['filename'] ?? '';
        if ((!isset($search_criteria['filename']) || trim((string) $filename) === '')) {
            throw new QueryException($this->l->t('A search pattern needs to be provided'));
        }
        
        $filenameSearchterm = !str_starts_with($filename, '*') ? '*' . $filename : $filename;
        $filenameLikePattern = str_replace(['*', '?'], ['%', '_'], $filenameSearchterm);
        $this->logger->debug('SearchServiceFiles: Searching for paths with pattern: ' . $filenameLikePattern);
        $searchOperator = new SearchComparison(ISearchComparison::COMPARE_LIKE, 'path', $filenameLikePattern);

        // extend the query to only match documents for the specified file types (multi-selection allowed)
        $extensions = TypeExtensionMapper::getExtensionsForTypes($search_criteria['file_types'] ?? null);
        if ($extensions !== []) {
            $this->logger->debug('SearchServiceFiles: Adding filter for ' . count($extensions) . ' file extensions: ' . implode(', ', $extensions));
            // only modify the searchOperator if file extension filtering is needed
            $extensionOperators = [];
            foreach ($extensions as $extension) {
                $extensionLikePattern = '%.' . $extension;
                $extensionOperators[] = new SearchComparison(ISearchComparison::COMPARE_LIKE, 'name', $extensionLikePattern );
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
                $this->logger->debug('SearchServiceFiles: Added before_date filter: ' . $search_criteria['before_date']);
            } catch (Exception $e) {
                $this->logger->error('SearchServiceFiles: Invalid before_date provided: ' . $search_criteria['before_date'] . ', error: ' . $e->getMessage());
                throw new QueryException($this->l->t('invalid before date provided'));
            }
            $beforeOperator = new SearchComparison(ISearchComparison::COMPARE_LESS_THAN_EQUAL, 'mtime', $before_seconds);
            $searchOperator = new SearchBinaryOperator(ISearchBinaryOperator::OPERATOR_AND, [ $searchOperator, $beforeOperator ]);
        }
        if (isset($search_criteria['after_date'])) {
            try {
                $after_date = new DateTime($search_criteria['after_date']);
                $after_seconds = $after_date->getTimestamp();
                $this->logger->debug('SearchServiceFiles: Added after_date filter: ' . $search_criteria['after_date']);
            } catch (Exception $e) {
                $this->logger->error('SearchServiceFiles: Invalid after_date provided: ' . $search_criteria['after_date'] . ', error: ' . $e->getMessage());
                throw new QueryException($this->l->t('invalid after date provided'));
            }
            $afterOperator = new SearchComparison(ISearchComparison::COMPARE_GREATER_THAN_EQUAL, 'mtime', $after_seconds);
            $searchOperator = new SearchBinaryOperator(ISearchBinaryOperator::OPERATOR_AND, [ $searchOperator, $afterOperator ]);
        }

        // extend the query to exclude files and folders below the provided list of excluded
        // folders
        if (isset($search_criteria['exclude_folders']) && is_array($search_criteria['exclude_folders'])) {
            $this->logger->debug('SearchServiceFiles: Adding exclude_folders filter for ' . count($search_criteria['exclude_folders']) . ' folders');
            $excludeFolderOperators = [];
            foreach ($search_criteria['exclude_folders'] as $folder) {
                if (!is_string($folder)) {
                    $this->logger->error('SearchServiceFiles: Provided exclusion folder is not a string: ' . gettype($folder));
                    continue;
                }

                // exclude all files and folders under the folder
                $excludeFolderLikePattern = $userFolder->getName() . '/' . $folder . '%';
                $this->logger->debug('SearchServiceFiles: Excluding folder with pattern: ' . $excludeFolderLikePattern);
                $excludeFolderComparison = new SearchComparison(ISearchComparison::COMPARE_LIKE, 'path', $excludeFolderLikePattern);
                $excludeFolderOperators[] = new SearchBinaryOperator(ISearchBinaryOperator::OPERATOR_NOT, [$excludeFolderComparison]);

                // exclude the folder itself
                $excludeFolderLikePattern = $userFolder->getName() . '/' . substr($folder, 0, -1);
                $excludeFolderComparison = new SearchComparison(ISearchComparison::COMPARE_LIKE, 'path', $excludeFolderLikePattern);
                $excludeFolderOperators[] = new SearchBinaryOperator(ISearchBinaryOperator::OPERATOR_NOT, [$excludeFolderComparison]);
            }
            $excludedFoldersOperator = new SearchBinaryOperator(ISearchBinaryOperator::OPERATOR_AND, $excludeFolderOperators);
            $searchOperator = new SearchBinaryOperator(ISearchBinaryOperator::OPERATOR_AND, [ $searchOperator, $excludedFoldersOperator ]);
        }

        // extend the query to only show files beneath the start folder (root of the search)
        if (isset($search_criteria['start_folder']) && trim((string) $search_criteria['start_folder']) !== '') {
            $startFolderLikePattern = $userFolder->getName() . '/' . $search_criteria['start_folder'] . '%';
            $this->logger->debug('SearchServiceFiles: Added start_folder filter: ' . $search_criteria['start_folder']);
            $startFolderComparison = new SearchComparison(ISearchComparison::COMPARE_LIKE, 'path', $startFolderLikePattern);
            $searchOperator = new SearchBinaryOperator(ISearchBinaryOperator::OPERATOR_AND, [ $searchOperator, $startFolderComparison ]);
        }

        // generating the sort order 
        $order = ($sort_order === 'asc') ? 'asc' : 'desc';
        $this->logger->debug('SearchServiceFiles: Building sort with field=' . $sort_field . ', order=' . $order);
        
        switch ($sort_field) {
            case 'modified':
                // Sort by modification date
                $searchOrder = new SearchOrder($order, 'mtime');
                break;
            case 'score':
                // the files search service does not support filtering by score
                // fall back to path filtering
                $this->logger->debug('SearchServiceFiles: Score sorting not supported, falling back to path');
            case 'path':
                // Sort by file path
            default:
                $searchOrder = new SearchOrder($order, 'path');
        }
        
        // TODO: the +1 is not correct, but pagination does not work without it - bug in Nextcloud?
        $offset = ($page * $size) + 1;
        $this->logger->debug('SearchServiceFiles: Running search with size=' . $size . ' and offset=' . $offset);
        
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


    /**
     * turn the given file path into an array of info about the file to be used in displaying search results
     * 
     * The result is an array with the following keys:
     *   - content_type: the file's MIME type
     *   - name: the file's path and name relative to the user's home folder
     *   - icon_link: an URL to retrieve an icon for the file's mime type
     *   - modified_at: the file's modification time according to Node::getMtime()
     *   - highlights: an empty array since the service does not support fulltext search
     * 
     * @param Node $node - the file node of the search hit
     * @param string $userID - the current user's ID
     * @return array | null
     */
    private function buildHit($node, $userID) : ?array {
        try {
            $fileId = $node->getId();
            $path = $node->getPath();
            $this->logger->debug('SearchServiceFiles: Building hit for file ID: ' . $fileId . ', path: ' . $path);
            
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

            // generate an URL to open the file in a viewer and highlight it in a directory listing
            $url = $this->urlGenerator->linkToRouteAbsolute(
                'files.view.index',
                [
                    'dir'      => $parentFolder,
                    'openfile' => $fileId,
                    'fileid' => $fileId,
                    ]
                );

            // put everything together in an array
            return [
                'content_type' => $mimeType,
                'name' => $relativePath,
                'link' => $url,
                'icon_link' => $mimeTypeIcon,
                'modified_at' => $modification_ts,
                'highlights' => []
            ];
        } catch (\Exception $e) {
            $this->logger->error('SearchServiceFiles: Exception building hit: ' . $e->getMessage() . ', file: ' . $e->getFile() . ', line: ' . $e->getLine());
            return [
                'name' => $node->getPath(),
                'error' => $e->getMessage(),
            ];
        } catch (\Error $e) { 
            $this->logger->error('SearchServiceFiles: Error building hit: ' . $e->getMessage() . ', file: ' . $e->getFile() . ', line: ' . $e->getLine());
            return [
                'name' => $node->getPath(),
                'error' => $e->getMessage(),
            ];
        }
    }

}