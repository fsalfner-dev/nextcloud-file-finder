<?php

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
	 * @var IServerContainer
	 */
	private IServerContainer $serviceContainer;

	public function __construct(string $appName,
								IRequest $request,
								IAppManager $appManager,
								IServerContainer $container,
								IInitialState $initialStateService,
								?string $userId) {
		parent::__construct($appName, $request);
		$this->appManager = $appManager;
		$this->serviceContainer = $container;
		$this->initialStateService = $initialStateService;
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
		if ($this->fulltextsearchAvailable()) {
            $service = $this->serviceContainer->get(\OCA\FileFinder\Service\SearchServiceElastic::class);
        } else {
            $service = $this->serviceContainer->get(\OCA\FileFinder\Service\SearchServiceFiles::class);
        }
		try {
			$result = $service->searchFiles($search_criteria, $page, $size, $sort, $sort_order);
			return new JSONResponse($result);
		} catch ( ConfigException $e ) {
			return new JSONResponse([
				'error_message' => $e->getMessage()
			], Http::STATUS_SERVICE_UNAVAILABLE);
		} catch ( QueryException $e) {
			return new JSONResponse([
				'error_message' => $e->getMessage()
			], Http::STATUS_EXPECTATION_FAILED);
		}

	}

	private function fulltextsearchAvailable() : bool {
		// check if the Fulltextsearch Elasticsearch app is installed
		if (!$this->appManager->isInstalled('fulltextsearch_elasticsearch')) {
			return false;
		}

		// the app is installed ... check if it is configured
		$elastic_index = $this->appConfig->getValueString('fulltextsearch_elasticsearch', 'elastic_index');
		if ($elastic_index !== '') {
			return true;
		} else {
			return false;
		}
	}

}
