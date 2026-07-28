<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;

/**
 * Integration tests for the Files interface (if no Elasticsearch is available)
 */
class SearchServiceFilesIntegrationTest extends TestCase {

    private $client;

    /** @var string */
    private $baseUrl;

    private $admin = [
        'user' => 'admin',
        'pwd' => 'admin'
    ];

    private $users = [];

    public static function findAllProvider(): array {
        return [
            [0, 'user1_file1.txt', 'test' ]
        ];
    }

    /**
     * creating the test setup:
     *   - two random users are created
     *   - all Nextcloud default files are deleted
     *   - the file and directories from tests/data are uploaded to Nextcloud
     */
    protected function setUp(): void {
        parent::setUp();

        $this->baseUrl = getenv('NEXTCLOUD_URL');
        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'timeout'  => 5.0,
        ]);

        $this->users[] = [
            'user' => 'testuser_' . uniqid(),
            'pwd' => 'abacab7969',
        ];

        $this->users[] = [
            'user' => 'testuser_' . uniqid(),
            'pwd' => 'abacab7969',
        ];

        try {
            $this->createUser($this->users[0]);
            $this->createUser($this->users[1]);
            $this->deleteAllFiles();
            $this->createTestFiles();                    
            echo "test setup successfully created with users " . $this->users[0]['user'] . " and " . $this->users[1]['user'] . ' ... ';
        } catch (\Throwable $e) {
            throw $e;
        }
    }

    /**
     * Dummy test until setup works
     */
    public function testCreateUser(): void {
        $this->assertEquals(200, 200);
    }

    /**
     * remove files and users
     */
    protected function tearDown(): void {
        $this->deleteUser($this->users[0]);
        $this->deleteUser($this->users[1]);
        parent::tearDown();
    }

    /**
     * Create a user in the Nextcloud instance
     * 
     * Since we need full functionality (file storage, sharing, etc.) we need to 
     * set it up via the REST API
     */
    private function createUser(array $user) {

        $payload = [
            'userid' => $user['user'],
            'password' => $user['pwd'],
        ];

        $response = $this->client->request('POST', '/ocs/v2.php/cloud/users', [
            'auth' => [$this->admin['user'], $this->admin['pwd']],
            'headers' => [
                'OCS-APIRequest' => 'true',
                'Accept'         => 'application/json',
                'Content-Type'   => 'application/json',
            ],
            'json' => $payload, 
        ]);

        if (!$response->getStatusCode() == 200) {
            throw new \RuntimeException("Creation of user returned error error code");
        }

        $body = (string) $response->getBody();
        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid JSON: ' . json_last_error_msg());
        }
        if ($data['ocs']['meta']['statuscode'] != 200) {
            throw new \RuntimeException("Creation of user did not return ok");
        }
    }


    /**
     * Delete a user in the Nextcloud instance
     */
    private function deleteUser(array $user) {
        $url = "/ocs/v2.php/cloud/users/" . $user['user'];
        $response = $this->client->request('DELETE', $url, [
            'auth' => [$this->admin['user'], $this->admin['pwd']],
            'headers' => [
                'OCS-APIRequest' => 'true',
                'Accept'         => 'application/json',
                'Content-Type'   => 'application/json',
            ]
        ]);

        if (!$response->getStatusCode() == 200) {
            throw new \RuntimeException("Deletion of user returned error error code");
        }
    }

    /**
     * upload all files and directories from tests/data to Nextcloud
     * 
     * tests/data/ contains two subdirectories, one for each user
     * the contents of each directory are uploaded to the two created users
     */
    protected function createTestFiles(): void {
        // walk through the folder with test files for all users and upload them to the Nextcloud
        foreach ([0,1] as $index) {
            $user = $this->users[$index];
            $rootDir = 'tests/data/user' . $index + 1;
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(
                    $rootDir,
                    FilesystemIterator::SKIP_DOTS
                ),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $fileInfo) {
                $relativePath = $iterator->getSubPathname();
                if ($fileInfo->isDir()) {
                    $this->createDirectory($user, $relativePath);
                } else {
                    $this->createFile(
                        $user,
                        $fileInfo->getPathname(),
                        $relativePath
                    );
                }
            }
        }
    }

    /**
     * Create a directory on the Nextcloud using WebDAV
     */
    private function createDirectory(array $user, string $path): void {
        $remoteUrl = '/remote.php/dav/files/' . $user['user'] . '/' . $path;
        $response = $this->client->request('MKCOL', $remoteUrl, [
            'auth' => [$user['user'], $user['pwd']], 
            'headers' => [
                'X-Requested-With' => 'XMLHttpRequest',
            ]
        ]);

        $statusCode = $response->getStatusCode();
        if (!($statusCode === 201 || $statusCode === 204)) {
            throw new \RuntimeException("Could not create directory: " . $path . " for " . $username . ": " . $response);
        };
    }

    /**
     * Upload a file to Nextcloud
     */
    private function createFile(array $user, string $localFilePath, string $targetFilePath) {
        if (!file_exists($localFilePath)) {
            throw new \RuntimeException("Cannot find local file: " . $localFilePath);
        }

        $fileStream = fopen($localFilePath, 'r');
        if (!$fileStream) {
            throw new \RuntimeException("Cannot open local file: " . $localFilePath);
        }

        try {
            $remoteUrl = '/remote.php/dav/files/' . $user['user'] . '/' . $targetFilePath;
            $response = $this->client->request('PUT', $remoteUrl, [
                'auth' => [$user['user'], $user['pwd']], 
                'body' => $fileStream,
                'headers' => [
                    'X-Requested-With' => 'XMLHttpRequest',
                ]
            ]);

            $statusCode = $response->getStatusCode();
            if (!($statusCode === 201 || $statusCode === 204)) {
                throw new \RuntimeException("Response: " . $response);
            };

        } catch (\Throwable $e) {
            if (is_resource($fileStream)) {
                fclose($fileStream);
            }
            throw new \RuntimeException("WebDAV-Upload failed: " . $e->getMessage());
        }
    }

    /**
     * delete all files for both test users in the Nextcloud via WebDAV
     */
    private function deleteAllFiles() : void {
        foreach ([0,1] as $index) {
            $user = $this->users[$index];
            $items = $this->listDirectory($user, "");
            foreach ($items as $item) {
                $this->deleteItem($user, $item);
            }
        }
    }

    /**
     * generate the WebDAV base URL
     */
    private function getDAVUrl(array $user) : string {
        return "/remote.php/dav/files/" . $user['user'];
    }

    /**
     * list the content of a directory via WebDAV
     */
    private function listDirectory(array $user, string $path): array {
        $xml = <<<XML
        <?xml version="1.0"?>
        <d:propfind xmlns:d="DAV:">
            <d:prop>
                <d:resourcetype/>
            </d:prop>
        </d:propfind>
        XML;

        $prefix = $this->getDAVUrl($user);
        $url =   $prefix . '/' . $path;
        $response = $this->client->request('PROPFIND', $url, [
            'headers' => [
                'Depth' => '1',
                'Content-Type' => 'application/xml',
            ],
            'auth' => [$user['user'], $user['pwd']],
            'body' => $xml,
        ]);

        if ($response->getStatusCode() >= 400) {
            throw new \RuntimeException("WebDAV listing: {$response->getStatusCode()}");
        }

        $xml = simplexml_load_string((string)$response->getBody());
        $xml->registerXPathNamespace('d', 'DAV:');
        $items = [];
        $responses = $xml->xpath('//d:response');

        foreach ($responses as $responseNode) {
            $href = (string)array_first($responseNode->xpath('.//d:href'));
            $path = substr($href, strlen($prefix)+1);
            if (strlen($path) > 1) {
                $items[] = $path;
            }
        }
        return $items;
    }

    /**
     * deletes a single item via WebDAV
     */
    private function deleteItem(array $user, string $path) : void {
        $url = $this->getDAVUrl($user) . '/' . $path;
        $response = $this->client->request('DELETE', $url, [
            'auth' => [$user['user'], $user['pwd']]
        ]);
        if (! in_array($response->getStatusCode(), [204, 200])) {
            throw new \RuntimeException("deleteItem for " . $path . " failed: " . $e->getMessage());
        }
    }

}

