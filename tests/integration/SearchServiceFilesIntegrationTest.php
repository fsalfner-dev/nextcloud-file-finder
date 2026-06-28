<?php

namespace OCA\FileFinder\Tests;

use Test\TestCase;
use OCP\Files\IRootFolder;
use OCP\Files\Folder;
use OCP\Files\File;
use OCP\Http\Client\IClientService;


class SearchServiceFilesIntegrationTest extends TestCase {

    private $client;

    /** @var string */
    private $baseUrl = 'http://';

    /** @var string */
    private $admin_user = 'admin';

    /** @var string */
    private $admin_pwd = 'admin';

    /** @var string */
    private $user1;

    /** @var string */
    private $password1;

    /** @var string */
    private $user2;

    /** @var string */
    private $password2;

    protected function setUp(): void {
        parent::setUp();

        $clientService = \OC::$server->get(IClientService::class);
        $this->client = $clientService->newClient();

        $this->user1 = 'testuser_' . uniqid();
        $this->password1 = 'password123';

        $this->user2 = 'testuser_' . uniqid();
        $this->password2 = 'password123';

        try {
            $this->createUser($this->user1, $this->password1);
            $this->createUser($this->user2, $this->password2);

            $this->createFile($this->user1, $this->password1, 'apps-extra/filefinder/tests/data/test1.txt', 'test2.txt');
        } catch (\Throwable $e) {
            throw $e;
        }
    }

    public function testCreateUser(): void {
        $this->assertEquals(200, 200);
    }

    protected function tearDown(): void {
        try {
//            $this->client->delete("/ocs/v1.php/cloud/users/" . $this->user1);
//            $this->client->delete("/ocs/v1.php/cloud/users/" . $this->user2);
        } catch (\Throwable $e) {}

        parent::tearDown();
    }

    /**
     * Create a user in the Nextcloud instance
     * 
     * Since we need full functionality (file storage, sharing, etc.) we need to 
     * set it up via the REST API
     */
    private function createUser(string $username, string $password) {
        $response = $this->client->post($this->baseUrl . '/ocs/v1.php/cloud/users', [
            'headers' => [
                'OCS-APIRequest' => 'true',
                'Authorization' => 'Basic ' . base64_encode($this->admin_user . ':' . $this->admin_pwd)
            ],
            'json' => [
                'userid' => $username,
                'password' => $password,
            ],
        ]);
        if (!$response->getStatusCode() == 200) {
            throw new \RuntimeException("Creation of user returned error error code");
        }
        $responseXML = simplexml_load_string($response->getBody());
        if (($responseXML == false) || (!$responseXML->meta->status == 'ok')) {
            throw new \RuntimeException("Creation of user did not return ok");
        }
        print_r('Successfully created user ' . $username);
    }

    private function createFile(string $username, string $password, string $localFilePath, string $targetFilePath) {
        if (!file_exists($localFilePath)) {
            print_r('pwd' . getcwd() . '\n');
            throw new \RuntimeException("Cannot find local file: " . $localFilePath);
        }

        $fileStream = fopen($localFilePath, 'r');
        if (!$fileStream) {
            throw new \RuntimeException("Cannot open local file: " . $localFilePath);
        }

        try {
            $remoteUrl = $this->baseUrl . '/remote.php/dav/files/' . $username . '/' . $targetFilePath;
            $response = $this->client->put($remoteUrl, [
                'auth' => [$username, $password], 
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
}

