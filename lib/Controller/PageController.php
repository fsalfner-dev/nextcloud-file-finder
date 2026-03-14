<?php
/**
 * SPDX-FileCopyrightText: 2026 Felix Salfner
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

namespace OCA\FileFinder\Controller;

use OCA\FileFinder\AppInfo\Application;
use OCA\FileFinder\Exceptions\QueryException;
use OCA\FileFinder\Exceptions\ConfigException;

use OCP\IServerContainer;
use OCP\App\IAppManager;
use OCP\IAppConfig;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IRequest;
use Psr\Log\LoggerInterface;


/**
 * @psalm-suppress UnusedClass
 */
class PageController extends Controller {

	/**
	 * @var string|null
	 */
	private $userId;

	/**
	 * @var IInitialState
	 */
	private $initialStateService;

	/**
	 * @var IAppManager
	 */
	private IAppManager $appManager;

    /**
	 * @var IAppConfig
	 */
	private IAppConfig $appConfig;

	/**
	 * @var IServerContainer
	 */
	private IServerContainer $serviceContainer;

	/**
	 * @var LoggerInterface
	 */
	private LoggerInterface $logger;

	public function __construct(string $appName,
								IRequest $request,
								IAppManager $appManager,
								IAppConfig $appConfig,
								IServerContainer $container,
								IInitialState $initialStateService,
								LoggerInterface $logger,
								?string $userId) {
		parent::__construct($appName, $request);
		$this->appManager = $appManager;
		$this->appConfig = $appConfig;
		$this->serviceContainer = $container;
		$this->initialStateService = $initialStateService;
		$this->logger = $logger;
		$this->userId = $userId;
	}

	/**
	 * The main route returning the page.
	 * 
	 * The frontend needs to know if fulltext search is available, so it can show/hide the input field
	 * for the fulltext content to be searched for.
	 * 
	 * This info is provided to the frontend via the InitialState service.
	 */
	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[OpenAPI(OpenAPI::SCOPE_IGNORE)]
	#[FrontpageRoute(verb: 'GET', url: '/')]
	public function index(): TemplateResponse {
		$initialState = [
			'fulltextsearch_available' => $this->fulltextsearchAvailable(),
		];
		$this->logger->debug('PageController: Fulltext search available: ' . ($initialState['fulltextsearch_available'] ? 'yes' : 'no'));
		$this->initialStateService->provideInitialState('initial_state', $initialState);

		return new TemplateResponse(
			Application::APP_ID,
			'index',
		);
	}


	/**
	 * This is the API endpoint to search for files with the given parameters
	 * It returns a JSONResponse with the search result
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
	 * The JSONResponse contains the keys:
	 *   - hits: the number of hits returned by the backend (some search services return the total number, others only for the current page)
	 *   - page: the current page number
	 *   - size: the current page size
	 *   - files: an array of files matching the search criteria
	 *       - content_type: the file's mime type
	 *       - name: the full path to the file as it appears for the user (not physical path)
	 *       - link: an URL to open the file
	 *       - modified_at: the modification date as returned by Node::getMtime()
	 *       - highlights: (only if supported by the search service) an array of Strings with context and search terms highlighted with <em></em>
	 * 
	 * Returns an error message if the search backend raises a QueryException
	 * 
	 * If fulltext search with ElasticSearch is available, SearchServiceElastic is used, if not
	 * SearchServiceFiles is used as a fallback, which does not support the 'content' search parameter 
	 *
	 * @param array $search_criteria - the specification of what to search for
	 * @param int $page - the page number 
	 * @param int $size - the page size
	 * @param string $sort - the sort criterion (score, modified, path)
	 * @param string $sort_order - the sort order (asc, desc)
	 * @return JSONResponse
	 */
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/search')]
	public function searchFiles(array $search_criteria, int $page, int $size, string $sort = 'score', string $sort_order = 'desc'): JSONResponse {
		$this->logger->debug('PageController: searchFiles endpoint called with page=' . $page . ', size=' . $size . ', sort=' . $sort . ', sort_order=' . $sort_order);
		
		// instantiate the search service
		// we cannot use dependency injection here since it would result in an error if fulltextsearch_elasticsearch
		// is not installed in the Nextcloud instance
		try {
			if ($this->fulltextsearchAvailable()) {
				$this->logger->debug('PageController: Using SearchServiceElastic');
				$service = $this->serviceContainer->get(\OCA\FileFinder\Service\SearchServiceElastic::class);
			} else {
				$this->logger->debug('PageController: Using SearchServiceFiles');
				$service = $this->serviceContainer->get(\OCA\FileFinder\Service\SearchServiceFiles::class);
			}
		} catch (Exception $e) {
			$this->logger->error('PageController: Error retrieving search service: ' . $e->getMessage());
			return new JSONResponse([
				'error_message' => 'Internal server error'
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
		
		// invoke the search and return the result or handle exceptions by converting them to JSON error messages
		try {
			$result = $service->searchFiles($search_criteria, $page, $size, $sort, $sort_order);
			$this->logger->debug('PageController: Search completed successfully, found ' . $result['hits'] . ' hits');
			return new JSONResponse($result);
		} catch ( ConfigException $e ) {
			$this->logger->error('PageController: Configuration error during search: ' . $e->getMessage());
			return new JSONResponse([
				'error_message' => $e->getMessage()
			], Http::STATUS_SERVICE_UNAVAILABLE);
		} catch ( QueryException $e) {
			$this->logger->error('PageController: Query error during search: ' . $e->getMessage());
			return new JSONResponse([
				'error_message' => $e->getMessage()
			], Http::STATUS_EXPECTATION_FAILED);
		} catch ( Exception $e) {
			$this->logger->error('PageController: Unexpected error during search: ' . $e->getMessage() . ', file: ' . $e->getFile() . ', line: ' . $e->getLine());
			return new JSONResponse([
				'error_message' => 'An unexpected error occurred'
			], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/**
	 * Test if fulltext search is available in the Nextcloud instance
	 * 
	 * The function first checks, if the app is installed and if so, if the 
	 * search index is configured.
	 * 
	 * Note that this is just an indication if the app is available - it might 
	 * still fail later when building the Elasticsearch client
	 */
	private function fulltextsearchAvailable() : bool {
		$this->logger->debug('PageController: Checking if fulltext search is available');
		
		// check if the Fulltextsearch Elasticsearch app is installed
		if (!$this->appManager->isInstalled('fulltextsearch_elasticsearch')) {
			$this->logger->debug('PageController: Fulltext search app not installed');
			return false;
		}
		$this->logger->debug('PageController: Fulltext search app is installed');

		// the app is installed ... check if it is configured
		$elastic_index = $this->appConfig->getValueString('fulltextsearch_elasticsearch', 'elastic_index');
		if ($elastic_index !== '') {
			$this->logger->debug('PageController: Elasticsearch index is configured');
			return true;
		} else {
			$this->logger->debug('PageController: Elasticsearch index is not configured');
			return false;
		}
	}

}