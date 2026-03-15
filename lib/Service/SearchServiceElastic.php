<?php
/**
 * SPDX-FileCopyrightText: 2026 Felix Salfner
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\FileFinder\Service;

use DateTime;
use Psr\Log\LoggerInterface;

use OCP\IAppConfig;
use OCP\IURLGenerator;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\FileInfo;
use OCP\Files\IRootFolder;
use OCP\Files\IMimeTypeDetector;
use OCP\IUserSession;


use OCA\FullTextSearch_Elasticsearch\AppInfo\Application as ElasticApp;
use OCA\FullTextSearch_Elasticsearch\ConfigLexicon;
use OCA\FullTextSearch_Elasticsearch\Exceptions\ConfigurationException;
use OCA\FullTextSearch_Elasticsearch\Exceptions\ClientException;
use OCA\FullTextSearch_Elasticsearch\Vendor\Elastic\Elasticsearch\ClientBuilder;
use OCA\FullTextSearch_Elasticsearch\Vendor\Elastic\Elasticsearch\Client;

use OCA\FileFinder\Exceptions\QueryException;
use OCA\FileFinder\Exceptions\ConfigException;
use OCA\FileFinder\Service\TypeExtensionMapper;


/**
 * A search service to search for matching files using the Elasticsearch service
 */
class SearchServiceElastic  {

    /**
     * @var IURLGenerator
     */
    private IURLGenerator $urlGenerator;

    /**
     * @var IUserSession
     */
    private IUserSession $userSession;

    /**
     * @var IRootFolder
     */
    private IRootFolder $rootFolder;

    /**
     * @var IMimeTypeDetector
     */
    private IMimeTypeDetector $mimeTypeDetector;

    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

	public function __construct(string $appName,
								IAppConfig $appConfig,
                                IURLGenerator $urlGenerator,
                                IUserSession $userSession,
                                IRootFolder $rootFolder,
                                IMimeTypeDetector $mimeTypeDetector,
                                ConfigLexicon $configLexicon,
                                private \OCP\IL10N $l,
                                LoggerInterface $logger) {
		$this->appConfig = $appConfig;
        $this->urlGenerator = $urlGenerator;
        $this->userSession = $userSession;
        $this->rootFolder = $rootFolder;
        $this->mimeTypeDetector = $mimeTypeDetector;
        $this->logger = $logger;
	}

    /**
     * The main method of the search service to perform a search for matching files by querying Elasticsearch
     * 
     * The parameter search_criteria may contain the following keys:
	 *   - filename: String to search for filenames with wildcards
	 *   - content: String with content to be matched by ElasticSearch
	 *   - file_types: an array of file types from TypeExtensionMapper to filter for
	 *   - before_date: an ISO representation of date to filter for
	 *   - after_date: an ISO representation of date to filter for
	 *   - exclude_folders: an array of Strings with folders to be excluded
	 *   - start_folder: a String with a folder to use as root folder for the search
	 * 
	 * It returns an array with the following keys:
	 *   - hits: the total number of hits found by Elasticsearch
	 *   - page: the current page number
	 *   - size: the current page size
	 *   - files: an array of files matching the search criteria
	 *       - content_type: the file's mime type
	 *       - name: the full path to the file as it appears for the user (not physical path)
	 *       - link: an URL to open the file
	 *       - modified_at: the modification date as returned by Node::getMtime()
	 *       - highlights: an array of Strings with context and search terms highlighted with <em></em>
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
	public function searchFiles(array $search_criteria, int $page, int $size, string $sort = 'score', string $sort_order = 'desc'): array {
        $this->logger->debug('SearchServiceElastic: searchFiles called with page=' . $page . ', size=' . $size . ', sort=' . $sort . ', sort_order=' . $sort_order);
		
		try {
			$client = $this->buildClient();
			$this->logger->debug('SearchServiceElastic: Elasticsearch client built successfully');
		} catch (Exception $e) {
			$this->logger->error('SearchServiceElastic: Failed to build Elasticsearch client: ' . $e->getMessage());
			throw $e;
		}
		
        $index = $this->getElasticIndex();
        $this->logger->debug('SearchServiceElastic: Using Elasticsearch index: ' . $index);
        
        try {
			$user = $this->userSession->getUser()->getUID();
			$this->logger->debug('SearchServiceElastic: Retrieved user: ' . $user);
		} catch (Exception $e) {
			$this->logger->error('SearchServiceElastic: Could not retrieve user session: ' . $e->getMessage());
			throw $e;
		}

        // in several steps we construct $params as a nested array specify the Elastic search query
        // $query specifies the search query but $params contains other parameters such as page size, sorting, etc.
        $query = $this->buildQuery($search_criteria, $user);
        $this->logger->debug('SearchServiceElastic: Query built successfully');
        
        // build the base structure for the query
        $params = [
            'index' => $index,
            'body' => [
                'size' => $size,
                'from' => $page * $size,
                'query' => $query,
            ]
        ];

        // add a specification for highlighting (returning Strings where found search terms are highlighted)
        $highlighting = $this->addHighlighting($search_criteria);
        if ($highlighting !== null) {
            $params['body']['highlight'] = $highlighting;
        }

        // add the specification for sorting
        $sortClause = $this->buildSort($sort, $sort_order, $user);
        if ($sortClause !== null) {
            $params['body']['sort'] = $sortClause;
        }
        
        $this->logger->debug('SearchServiceElastic: Executing search request');
        try {
			$response = $client->search($params);
		} catch (Exception $e) {
			$this->logger->error('SearchServiceElastic: Elasticsearch search request failed: ' . $e->getMessage());
			throw $e;
		}
		
        if ($response->getStatusCode() != 200) {
            $this->logger->error('SearchServiceElastic: Elasticsearch returned non-200 status code: ' . $response->getStatusCode() . ', body: ' . $response->getBody());
            throw new ClientException($response->getBody());
        }
        
        // search results have been received -> build the response structure
        $result = $response->asArray();

        // determine the total number of hits from Elastic's response
        $no_of_hits = $result['hits']['total'];
		if (is_array($no_of_hits)) {
			$no_of_hits = $no_of_hits['value'];
		}
        $this->logger->debug('SearchServiceElastic: Total hits found: ' . $no_of_hits);
        
        // process the files found by Elasticsearch (mainly by calling buildHit() on each one)
		$files = [];
        if ($user !== null) {
            try {
				$userFolder = $this->rootFolder->getUserFolder($user);
				foreach ($result['hits']['hits'] as $hit) {
					$file = $this->buildHit($hit, $user, $userFolder);
					if ($file !== null) {
						$files[] = $file;
					}
				}
				$this->logger->debug('SearchServiceElastic: Processed ' . count($files) . ' file hits');
			} catch (Exception $e) {
				$this->logger->error('SearchServiceElastic: Error processing search results: ' . $e->getMessage());
				throw $e;
			}
        } else {
			$this->logger->error('SearchServiceElastic: User is null, cannot process results');
		}

        // return everything
        return [
				'query' => $params,
                'hits' => $no_of_hits,
				'page' => $page,
				'size' => $size,
                'result' => $result,
				'files' => $files
			];
	}

    /**
     * return an array specifying how Elasticsearch returns fulltext search hits as highlighted match in a context window
     */
    private function addHighlighting($search_criteria) : ?array {
        $content = $search_criteria['content'];
        if ((isset($content) && !empty(trim($content)))) {
            $this->logger->debug('SearchServiceElastic: Adding highlighting for content search');
            return ['fields' => [ 
                'content' => [ 
                    'type' => 'plain',
                    'fragmenter' => 'span']]];
        } else {
            return null;
        }
    }

    /**
     * parse the search_criteria and translate them into Elasticsearch query specifications
     * 
     * It handles the fields:
     *   - content: search terms for fulltext search in the content of files
     *   - filename: wildcard search for filenames (including their path)
     *   - file_types: the array of file types which are mapped to file extensions
     *   - before_date: files modified before the specified date
     *   - after_date: files modified after the specified date
     *   - exclude_folders: exclude files which path starts with one of the provided exclusion folders
     *   - start_folder: only include files which path starts with the provided folder prefix 
     */
    private function buildQuery($search_criteria, $user) : array {
        $content = $search_criteria['content'] ?? '';
        $filename = $search_criteria['filename'] ?? '';
        if ((!isset($search_criteria['content']) || trim((string) $content) === '') && (!isset($search_criteria['filename']) || trim((string) $filename) === '')) {
            throw new QueryException($this->l->t('Either content or filename needs to be provided'));
        }
        
        $this->logger->debug('SearchServiceElastic: Building query with content search: ' . (!empty($content) ? 'yes' : 'no') . ', filename search: ' . (!empty($filename) ? 'yes' : 'no'));

        // base query to make sure only documents that the user can see are returned
        $share_key = 'share_names.' . $user;
        $share_keyword = $share_key . '.keyword';
        $query = [
            'bool' => [
                'filter' => [
                    [ 'regexp' => [ 'title.keyword' => '.+' ] ],
                    [ 'exists' => [ 'field' => $share_key ]]
                ]
            ]
        ];

        // extend query to find matches based on file content
        if (isset($search_criteria['content']) && trim((string) $content) !== '') {
            $query['bool']['must'] = [ 'match' => [ 'content' => $content ] ];
            $this->logger->debug('SearchServiceElastic: Added content match clause for: ' . $content);
        }

        // extend query to only return files matching the filename wildcard
        if (isset($search_criteria['filename']) && trim((string) $filename) !== '') {
            $filename_searchterm = !str_starts_with($filename, '*') ? '*' . $filename : $filename;
            $query['bool']['filter'][] = [ 'wildcard' => [ $share_keyword => $filename_searchterm ] ];
            $this->logger->debug('SearchServiceElastic: Added filename wildcard clause for: ' . $filename_searchterm);
        }

        // extend the query to only match documents for the specified file types (multi-selection allowed)
        $extensions = TypeExtensionMapper::getExtensionsForTypes($search_criteria['file_types'] ?? null);
        if ($extensions !== []) {
            $pattern = '.*\.(' . implode('|', $extensions) . ')';
            $query['bool']['filter'][] = [ 'regexp' => [ 'title.keyword' => [ 'value' => $pattern, 'case_insensitive' => true ] ] ];
            $this->logger->debug('SearchServiceElastic: Added file type filter for extensions: ' . implode(', ', $extensions));
        }

        // extend the query to only return files where the modification date matches the
        // provided dates in before_date and after_date
        if (isset($search_criteria['before_date'])) {
            try {
                $before_date = new DateTime($search_criteria['before_date']);
                $before_seconds = $before_date->getTimestamp();
                $query['bool']['filter'][] = [ 'range' => ['lastModified' => [ 'lt' => $before_seconds ] ] ];
                $this->logger->debug('SearchServiceElastic: Added before_date filter: ' . $search_criteria['before_date']);
            } catch (Exception $e) {
                $this->logger->error('SearchServiceElastic: Invalid before_date provided: ' . $search_criteria['before_date'] . ', error: ' . $e->getMessage());
                throw new QueryException($this->l->t('invalid before date provided'));
            }
        }
        if (isset($search_criteria['after_date'])) {
            try {
                $after_date = new DateTime($search_criteria['after_date']);
                $after_seconds = $after_date->getTimestamp();
                $query['bool']['filter'][] = [ 'range' => ['lastModified' => [ 'gt' => $after_seconds ] ] ];
                $this->logger->debug('SearchServiceElastic: Added after_date filter: ' . $search_criteria['after_date']);
            } catch (Exception $e) {
                $this->logger->error('SearchServiceElastic: Invalid after_date provided: ' . $search_criteria['after_date'] . ', error: ' . $e->getMessage());
                throw new QueryException($this->l->t('invalid after date provided'));
            }
        }

        // extend the query to exclude files and folders below the provided list of excluded
        // folders
        if (isset($search_criteria['exclude_folders']) && is_array($search_criteria['exclude_folders'])) {
            $this->logger->debug('SearchServiceElastic: Adding exclude_folders filter for ' . count($search_criteria['exclude_folders']) . ' folders');
            $query['bool']['must_not'] = [];
            foreach ($search_criteria['exclude_folders'] as $folder) {
                if (!is_string($folder)) {
                    $this->logger->error('SearchServiceElastic: Exclude folder is not a string: ' . gettype($folder));
                    continue;
                }
                $query['bool']['must_not'][] = ['prefix' => [ $share_keyword => ['value' => $folder]]];
                $query['bool']['must_not'][] = ['term' => [ $share_keyword => ['value' => substr($folder,0,-1)]]];
            }
        }

        // extend the query to only show files beneath the start folder (root of the search)
        if (isset($search_criteria['start_folder']) && trim((string) $search_criteria['start_folder']) !== '') {
            $query['bool']['filter'][] = ['prefix' => [ $share_keyword => ['value' => $search_criteria['start_folder']]]];
            $this->logger->debug('SearchServiceElastic: Added start_folder filter: ' . $search_criteria['start_folder']);
        }
        return $query;
    }

    /**
     * build the Elasticsearch query parts to specify sorting of results
     * 
     * The $sort parameter may be one of the following:
     *   - 'score' : Sort by the matching score used by Elasticsearch
     *   - 'modified' : Sort by modification date
     *   - 'path' : Sort by full path alphabetically
     * 
     * The $sort_order may be 'asc' or 'desc'
     * 
     * @param $sort String - the sort field
     * @param $sort_order String - the sort order
     * @param $userID String - the user ID
     */
    private function buildSort(string $sort, string $sort_order = 'desc', string $userID ): ?array {
        // Validate sort_order
        $order = ($sort_order === 'asc') ? 'asc' : 'desc';
        
        $this->logger->debug('SearchServiceElastic: Building sort clause for sort=' . $sort . ', order=' . $order);
        
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
                // TODO: check if switch to share name instead of title.keyword
                return [
                    [ 'share_names.' . $userID . '.keyword' => ['order' => $order]],
                    '_score' // Secondary sort by relevance
                ];
            default:
                $this->logger->debug('SearchServiceElastic: Unknown sort parameter, using default');
                return null;
        }
    }

    /**
     * return an Elasticsearch client instance
     * 
     * May throw a ConfigException if no Elasticsearch host can be found
     * 
     * @return Elasticsearch\Client 
     */
    private function buildClient() : Client {

        // identify the host of the elastic service
		$elastic_strHost = $this->appConfig->getValueString(ElasticApp::APP_NAME, ConfigLexicon::ELASTIC_HOST, '');
		if ($elastic_strHost === '') {
			$this->logger->error('SearchServiceElastic: ElasticSearch host not configured');
			throw new ConfigException($this->l->t('Your ElasticSearchPlatform is not configured properly'));
		}
		$this->logger->debug('SearchServiceElastic: ElasticSearch host configured: ' . $elastic_strHost);
        
        $elastic_hosts = array_map('trim', explode(',', $elastic_strHost));
		$elastic_hosts = array_map([$this, 'cleanHost'], $elastic_hosts);
		$cb = ClientBuilder::create()
			->setHosts($elastic_hosts)
			->setRetries(3);

        // adjust for allowing self-signed certificates
        $self_signed_certs_allowed = $this->appConfig->getValueBool(ElasticApp::APP_NAME, ConfigLexicon::ALLOW_SELF_SIGNED_CERT, false);
        $cb->setSSLVerification(!$self_signed_certs_allowed);
        $this->logger->debug('SearchServiceElastic: SSL verification: ' . ($self_signed_certs_allowed ? 'disabled' : 'enabled'));

        $this->configureAuthentication($cb, $elastic_hosts);

        return $cb->build();
    }

    /**
     * Configure basic authentication for the Elasticsearch client by extracting username and password from host URLs
     * 
     * @param ClientBuilder $cb - the Elasticsearch Clientbuilder instance
     * @param array $hosts - the list of Elasticsearch hosts
     * @return Nothing, modifies $cb
     */
	private function configureAuthentication(ClientBuilder $cb, array $hosts): void {
		$this->logger->debug('SearchServiceElastic: Configuring authentication for ' . count($hosts) . ' hosts');
		foreach ($hosts as $host) {
			$user = parse_url($host, PHP_URL_USER) ?? '';
			$pass = parse_url($host, PHP_URL_PASS) ?? '';

			if ($user !== '' || $pass !== '') {
				$cb->setBasicAuthentication($user, $pass);
				$this->logger->debug('SearchServiceElastic: Basic authentication configured for user: ' . $user);
				return;
			}
		}
		$this->logger->debug('SearchServiceElastic: No authentication credentials found in hosts');
	}

    /**
     * internal function to extract the hostname
     */
	private function cleanHost(string $host): string {
		if ($host === '/') {
			return $host;
		}
		return trim(rtrim($host, '/'));
	}

    /**
     * obtain the name of the index configured in the fulltextsearch_elasticsearch app from configuration
     * Throws a ConfigException if it cannot be obtained
     */
    private function getElasticIndex() : string {
		$elastic_index = $this->appConfig->getValueString(ElasticApp::APP_NAME, ConfigLexicon::ELASTIC_INDEX);
		if ($elastic_index === '') {
            $this->logger->error('SearchServiceElastic: ElasticSearch index not configured');
            throw new ConfigException($this->l->t('Your ElasticSearchPlatform is not configured properly'));
		}
		$this->logger->debug('SearchServiceElastic: ElasticSearch index: ' . $elastic_index);
        return $elastic_index;
    }

    /**
     * turn the given file path into an array of info about the file to be used in displaying search results
     * 
     * The variable $userFolder is provided for performance reasons since the function is called for each hit
     * 
     * The result is an array with the following keys:
     *   - content_type: the file's MIME type
     *   - name: the file's path and name relative to the user's home folder
     *   - icon_link: an URL to retrieve an icon for the file's mime type
     *   - modified_at: the file's modification time according to Node::getMtime()
     *   - highlights: an array of highlighted text snippets with context. The string contains HTML <em></em> elements
     * 
     * @param string $hit - the full path of the file as returned from the Elasticsearch index (title.keyword)
     * @param User $user - the current user
     * @param Folder $userFolder - the current user's home folder
     * @return array | null
     */
    private function buildHit($hit, $user, $userFolder) : ?array {
        try {
            // extract the file node's ID from the search hit returned by Elasticsearch
            $fileIdParts = explode(":", $hit['_id']);
            $fileId = $fileIdParts[1];
            $this->logger->debug('SearchServiceElastic: Processing hit with file ID: ' . $fileId . ', path: ' . $filePath);

            // extract the file path as it appears for the user (shared files might have a different path for $user)
            $filePath = $hit['_source']['share_names'][$user];
            if ($filePath === null) {
                $this->logger->debug('SearchServiceElastic: File path is null for hit ID: ' . $hit['_id']);
                return null;
            }
            
            // get the file node's metadata
            $node = $userFolder->get($filePath);
            $modification_ts = $node->getMtime();
            $mimeType = $node->getMimetype();
            $mimeTypeIcon = $this->mimeTypeDetector->mimeTypeIcon($mimeType);
            // TODO: check if not $filePath should be used here
            $title = $filePath;
            $parentFolder = dirname($filePath);

             
            // add a trailing slash to folders
            if ($node->getType() === FileInfo::TYPE_FOLDER) {
                $title = $title . '/';
            }

            // generate a link to the file that tries to open the file in a viewer and 
            // highlight the file in a directory listing
            $url = $this->urlGenerator->linkToRouteAbsolute(
                'files.view.index',
                [
                    'dir'      => $parentFolder,
                    'openfile' => $fileId,
                    'fileid' => $fileId,
                    ]
                );

            // return the hit's metadata in an array
            return [
                'content_type' => $mimeType,
                'name' => $title,
                'link' => $url,
                'icon_link' => $mimeTypeIcon,
                'modified_at' => $modification_ts,
                'highlights' => (array_key_exists('highlight', $hit)) ? $hit['highlight'] : []
            ];
        } catch (\Exception $e) {
            $this->logger->error('SearchServiceElastic: Exception processing hit: ' . $e->getMessage() . ', file: ' . $e->getFile() . ', line: ' . $e->getLine());
            return [
                'name' => $title ?? 'Unknown',
                'error' => $e->getMessage(),
            ];
        } catch (\Error $e) { 
            $this->logger->error('SearchServiceElastic: Error processing hit: ' . $e->getMessage() . ', file: ' . $e->getFile() . ', line: ' . $e->getLine());
            return [
                'name' => $title ?? 'Unknown',
                'error' => $e->getMessage(),
            ];
        }
    }

}