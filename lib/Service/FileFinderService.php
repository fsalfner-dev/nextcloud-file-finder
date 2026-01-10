<?php

declare(strict_types=1);

namespace OCA\FileFinder\Service;

use OCP\IAppConfig;
use OCP\IURLGenerator;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\IMimeTypeDetector;
use OCP\Files\IRootFolder;
use OCP\IUserSession;

use OCA\FullTextSearch_Elasticsearch\AppInfo\Application as ElasticApp;
use OCA\FullTextSearch_Elasticsearch\ConfigLexicon;
use OCA\FullTextSearch_Elasticsearch\Exceptions\ConfigurationException;
use OCA\FullTextSearch_Elasticsearch\Exceptions\ClientException;
use OCA\FullTextSearch_Elasticsearch\Vendor\Elastic\Elasticsearch\ClientBuilder;
use OCA\FullTextSearch_Elasticsearch\Vendor\Elastic\Elasticsearch\Client;

use OCA\FileFinder\Exceptions\QueryException;


class FileFinderService  {

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

	public function __construct(string $appName,
								IAppConfig $appConfig,
                                IURLGenerator $urlGenerator,
                                IRootFolder $rootFolder,
                                IUserSession $userSession,
                                IMimeTypeDetector $mimeTypeDetector,
                                ConfigLexicon $configLexicon) {
		$this->appConfig = $appConfig;
        $this->urlGenerator = $urlGenerator;
        $this->userSession = $userSession;
        $this->rootFolder = $rootFolder;
        $this->mimeTypeDetector = $mimeTypeDetector;
	}

	public function searchFiles(?string $content, ?string $filename, int $page, int $size): array {
        if ((!isset($content) || empty(trim($content))) && (!isset($filename) || empty(trim($filename)))) {
            throw new QueryException('Either content or filename needs to be provided');
        }
		$client = $this->buildClient();
        $index = $this->getElasticIndex();
        $user = $this->userSession->getUser()->getUID();

        $params = [
            'index' => $index,
            'body' => [
                'size' => $size,
                'from' => $page * $size,
                'query' => [
                    'bool' => [
                        'filter' => [
                            [ 'regexp' => [ 'title.keyword' => '.+' ] ],
                            [ 'exists' => [ 'field' => 'share_names.' . $user ]]
                        ]
                    ]
                ]
            ]
        ];
        if ((isset($content) && !empty(trim($content)))) {
            $params['body']['query']['bool']['must'] = [ 'match' => [ 'content' => $content] ];
            $params['body']['highlight'] = ['fields' => [ 
                'content' => [ 
                    'type' => 'plain',
                    'fragmenter' => 'span']]];
        }
        
        if ((isset($filename) && !empty(trim($filename)))) {
            $params['body']['query']['bool']['filter'][] = [ 'wildcard' => [ 'title.keyword' => $filename ]];
        }
//        return ['query' => $params];
        $response = $client->search($params);
        if ($response->getStatusCode() != 200) {
            throw new ClientException($response->getBody());
        }
        $result = $response->asArray();

        $no_of_hits = $result['hits']['total'];
		if (is_array($no_of_hits)) {
			$no_of_hits = $no_of_hits['value'];
		}
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


    private function buildClient() : Client {

        // identify the host of the elastic service
		$elastic_strHost = $this->appConfig->getValueString(ElasticApp::APP_NAME, ConfigLexicon::ELASTIC_HOST, '');
		if ($elastic_strHost === '') {
			throw new QueryException('Your ElasticSearchPlatform is not configured properly');
		}
        $elastic_hosts = array_map('trim', explode(',', $elastic_strHost));
		$elastic_hosts = array_map([$this, 'cleanHost'], $elastic_hosts);
		$cb = ClientBuilder::create()
			->setHosts($elastic_hosts)
			->setRetries(3);

        // adjust for allowing self-signed certificates
        $self_signed_certs_allowed = $this->appConfig->getValueBool(ElasticApp::APP_NAME, ConfigLexicon::ALLOW_SELF_SIGNED_CERT, false);
        $cb->setSSLVerification(!$self_signed_certs_allowed);

        $this->configureAuthentication($cb, $elastic_hosts);

        return $cb->build();
    }

	private function configureAuthentication(ClientBuilder $cb, array $hosts): void {
		foreach ($hosts as $host) {
			$user = parse_url($host, PHP_URL_USER) ?? '';
			$pass = parse_url($host, PHP_URL_PASS) ?? '';

			if ($user !== '' || $pass !== '') {
				$cb->setBasicAuthentication($user, $pass);
				return;
			}
		}
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
            throw new ConfigurationException('Your ElasticSearchPlatform is not configured properly');
		}
        return $elastic_index;
    }

    private function buildHit($hit, $user) : ?array {
        $fileIdParts = explode(":", $hit['_id']);
        $title = $hit['_source']['title'];
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
