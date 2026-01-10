<?php

declare(strict_types=1);

namespace OCA\FileFinder\Controller;

use OCA\FileFinder\AppInfo\Application;
use OCA\FileFinder\Service\FileFinderService;
use OCA\FullTextSearch_Elasticsearch\Exceptions\ConfigurationException;

use OCA\FileFinder\Exceptions\QueryException;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\FrontpageRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\JSONResponse;
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
	 * @var FileFinderService
	 */
	private $fileFinderService;

	public function __construct(string $appName,
								IRequest $request,
								FileFinderService $fileFinderService,
								?string $userId) {
		parent::__construct($appName, $request);
		$this->fileFinderService = $fileFinderService;
		$this->userId = $userId;
	}

	#[NoCSRFRequired]
	#[NoAdminRequired]
	#[OpenAPI(OpenAPI::SCOPE_IGNORE)]
	#[FrontpageRoute(verb: 'GET', url: '/')]
	public function index(): TemplateResponse {
		return new TemplateResponse(
			Application::APP_ID,
			'index',
		);
	}


	/**
	 * This is the API endpoint to search Elasticsearch with the given parameters
	 * It returns a JSONResponse with the search result
	 *
	 * @param string $content - the search term for searching the content
	 * @param string $filename - the search term for the filename
	 * @param int $page - the page number 
	 * @return JSONResponse
	 */
	#[NoAdminRequired]
	#[FrontpageRoute(verb: 'GET', url: '/search')]
	public function searchFiles(?string $content, ?string $filename, int $page, int $size): JSONResponse {
		try {
			$result = $this->fileFinderService->searchFiles($content, $filename, $page, $size);
			return new JSONResponse($result);
		} catch ( ConfigurationException $e ) {
			return new JSONResponse([
				'error_message' => $e->getMessage()
			], Http::STATUS_SERVICE_UNAVAILABLE);
		} catch ( QueryException $e) {
			return new JSONResponse([
				'error_message' => $e->getMessage()
			], Http::STATUS_EXPECTATION_FAILED);
		}

	}

}
