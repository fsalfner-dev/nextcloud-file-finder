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
	 * This is the API endpoint to search Elasticsearch with the given parameters
	 * It returns a JSONResponse with the search result
	 *
	 * @param string $search_criteria - the specification of what to search for
	 * @param int $page - the page number 
	 * @param string $sort - the sort criterion (score, modified, path)
	 * @param string $sort_order - the sort order (asc, desc)
	 * @return JSONResponse
	 */
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/search')]
	public function searchFiles(array $search_criteria, int $page, int $size, string $sort = 'score', string $sort_order = 'desc'): JSONResponse {
		$this->logger->debug('PageController: searchFiles endpoint called with page=' . $page . ', size=' . $size . ', sort=' . $sort . ', sort_order=' . $sort_order);
		
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