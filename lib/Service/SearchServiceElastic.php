<?php

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

        $query = $this->buildQuery($search_criteria, $user);
        $this->logger->debug('SearchServiceElastic: Query built successfully');
        
        $highlighting = $this->addHighlighting($search_criteria);
        $sortClause = $this->buildSort($sort, $sort_order);
        
        $params = [
            'index' => $index,
            'body' => [
                'size' => $size,
                'from' => $page * $size,
                'query' => $query,
            ]
        ];
        if ($highlighting !== null) {
            $params['body']['highlight'] = $highlighting;
        }
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
        
        $result = $response->asArray();
        $this->logger->debug('SearchServiceElastic: Search response received and parsed');

        $no_of_hits = $result['hits']['total'];
		if (is_array($no_of_hits)) {
			$no_of_hits = $no_of_hits['value'];
		}
        $this->logger->debug('SearchServiceElastic: Total hits found: ' . $no_of_hits);
        
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

        return [
				'query' => $params,
                'hits' => $no_of_hits,
				'page' => $page,
				'size' => $size,
                'result' => $result,
				'files' => $files
			];
	}

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

    private function buildQuery($search_criteria, $user) : array {
        $content = $search_criteria['content'] ?? '';
        $filename = $search_criteria['filename'] ?? '';
        if ((!isset($search_criteria['content']) || trim((string) $content) === '') && (!isset($search_criteria['filename']) || trim((string) $filename) === '')) {
            throw new QueryException($this->l->t('Either content or filename needs to be provided'));
        }
        
        $this->logger->debug('SearchServiceElastic: Building query with content search: ' . (!empty($content) ? 'yes' : 'no') . ', filename search: ' . (!empty($filename) ? 'yes' : 'no'));

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
            $this->logger->debug('SearchServiceElastic: Added content match clause for: ' . $content);
        }

        // extend query to only return files matching the filename wildcard
        if (isset($search_criteria['filename']) && trim((string) $filename) !== '') {
            $filename_searchterm = !str_starts_with($filename, '*') ? '*' . $filename : $filename;
            $query['bool']['filter'][] = [ 'wildcard' => [ 'title.keyword' => $filename_searchterm ] ];
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
                $query['bool']['must_not'][] = ['prefix' => [ 'title.keyword' => ['value' => $folder]]];
                $query['bool']['must_not'][] = ['term' => [ 'title.keyword' => ['value' => substr($folder,0,-1)]]];
            }
        }

        // extend the query to only show files beneath the start folder (root of the search)
        if (isset($search_criteria['start_folder']) && trim((string) $search_criteria['start_folder']) !== '') {
            $query['bool']['filter'][] = ['prefix' => [ 'title.keyword' => ['value' => $search_criteria['start_folder']]]];
            $this->logger->debug('SearchServiceElastic: Added start_folder filter: ' . $search_criteria['start_folder']);
        }
        return $query;
    }

    private function buildSort(string $sort, string $sort_order = 'desc'): ?array {
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
                return [
                    ['title.keyword' => ['order' => $order]],
                    '_score' // Secondary sort by relevance
                ];
            default:
                $this->logger->debug('SearchServiceElastic: Unknown sort parameter, using default');
                return null;
        }
    }

    private function buildClient() : Client {

        // identify the host of the elastic service
		$elastic_strHost = $this->appConfig->getValueString(ElasticApp::APP_NAME, ConfigLexicon::ELASTIC_HOST, '');
		if ($elastic_strHost === '') {
			$this->logger->error('SearchServiceElastic: ElasticSearch host not configured');
			throw new QueryException($this->l->t('Your ElasticSearchPlatform is not configured properly'));
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

	private function cleanHost(string $host): string {
		if ($host === '/') {
			return $host;
		}
		return trim(rtrim($host, '/'));
	}

    private function getElasticIndex() : string {
		$elastic_index = $this->appConfig->getValueString(ElasticApp::APP_NAME, ConfigLexicon::ELASTIC_INDEX);
		if ($elastic_index === '') {
            $this->logger->error('SearchServiceElastic: ElasticSearch index not configured');
            throw new ConfigException($this->l->t('Your ElasticSearchPlatform is not configured properly'));
		}
		$this->logger->debug('SearchServiceElastic: ElasticSearch index: ' . $elastic_index);
        return $elastic_index;
    }

    private function buildHit($hit, $user, $userFolder) : ?array {
        try {
            $fileIdParts = explode(":", $hit['_id']);
            $fileId = $fileIdParts[1];
            $filePath = $hit['_source']['share_names'][$user];
            
            if ($filePath === null) {
                $this->logger->debug('SearchServiceElastic: File path is null for hit ID: ' . $hit['_id']);
                return null;
            }
            
            $this->logger->debug('SearchServiceElastic: Processing hit with file ID: ' . $fileId . ', path: ' . $filePath);
            
            $node = $userFolder->get($filePath);
            $modification_ts = $node->getMtime();
            $mimeType = $node->getMimetype();
            $mimeTypeIcon = $this->mimeTypeDetector->mimeTypeIcon($mimeType);
            $title = $hit['_source']['title'];
            $parentFolder = dirname($filePath);

             
            // add a trailing slash to folders
            if ($node->getType() === FileInfo::TYPE_FOLDER) {
                $title = $title . '/';
            }

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