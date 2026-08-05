<?php

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;

/**
 * A class to provide methods for setting up integration tests with a remote Nextcloud instance
 */
class SearchServiceIntegrationTestHelper {

    /** @var Client */
    private $client;

    /** @var array */
    private $admin;

    /** @var array */
    private $fileCounters = [];

    /** @var DateTimeImmutable */
    private $referenceTime;


    public function __construct(string $adminUser, string $adminPwd, string $nextcloudURL) {
        $this->client = new Client([
            'base_uri' => $nextcloudURL,
            'timeout'  => 20.0,
        ]);

        $this->admin = [
            'user' => $adminUser,
            'pwd' => $adminPwd
        ];

        $this->referenceTime = new DateTimeImmutable('now');

    }

    public function makeSearchRequest(
            array $user,
            string $content = '', 
            string $filename = '', 
            array $file_types = [], 
            ?DateTimeImmutable $after_date = null, 
            ?DateTimeImmutable $before_date = null, 
            string $start_folder = '', 
            array $exclude_folders = [], 
            int $page = 0, 
            int $size = 10, 
            string $sort = 'score', 
            string $sort_order = 'desc',
            bool $dump = false): array {

        $searchUrl = "/apps/filefinder/search";
        $query = http_build_query([
            'search_criteria' => [
                'content' => $content,
                'filename' => $filename,
                'file_types' => $file_types,
                'after_date' => $after_date?->format(DateTimeInterface::ISO8601_EXPANDED) ?? null,
                'before_date' => $before_date?->format(DateTimeInterface::ISO8601_EXPANDED) ?? null,
                'start_folder' => $start_folder,
                'exclude_folders' => $exclude_folders,
            ],
            'size' => $size,
            'page' => $page,
            'sort' => $sort,
            'sort_order' => $sort_order,
        ]);
        $response = $this->client->request('GET', $searchUrl, [
            'headers' => [
                'OCS-APIRequest' => 'true',
                'Accept' => 'application/json'
            ],
            'query' => $query,
            'auth' => [$user['user'], $user['pwd']],
            'http_errors' => false,
        ]);
        $status = $response->getStatusCode();
        $body = (string) $response->getBody(); 

        if ($status != 200) {
            throw new \RuntimeException("Search call resulted in error code $status: " . $body);
        }

        $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        if ($dump) {
            echo "result: ";
            var_dump($data);
        }
        return $data;
    }


    /**
     * Create a user in the Nextcloud instance
     * 
     * Since we need full functionality (file storage, sharing, etc.) we need to 
     * set it up via the REST API
     * 
     * @return an array with 'user' (username) and 'pwd' (password)
     */
    public function createUser() : array {
        $user = [
            'user' => 'testuser_' . uniqid(),
            'pwd' => 'abacab7969',
        ];

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

        // initialize file counters for the user
        $this->fileCounters[] = [
            'user' => $user['user'],
            'current' => 0,
            'old' => 0,
            'veryold' => 0,
        ];

        return $user;
    }


    /**
     * Delete a user in the Nextcloud instance
     * 
     * @param $user - username and password of the user to be deleted
     */
    public function deleteUser(array $user) {
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
     * upload all files and directories from the given directory to the given user on Nextcloud.
     * 
     * To enable deterministic tests for sorting by modification timestamp, each file and directory
     * gets its own manually computed time stamp. 
     * 
     * Since files are shared between users, each user needs to get its own offset (hence the parameter userCounter)
     * 
     * If the file starts with 'old_' (e.g. old_file_user1 ...) the modification time will be set
     * to a date one month ago.

     * 
     * @param $user - the username and password of the user for which the files should be created
     * @param $rootDir - the local path under which all files will be uploaded to Nextcloud
     */
    public function createTestFiles(array $user, string $rootDir): void {
        // walk through the folder with test files and upload them to the Nextcloud
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
                if (str_starts_with($fileInfo->getFilename(), 'old_')) {
                    $mdate = $this->getTimestamp($user, 'old');
                } else {
                    if (str_starts_with($fileInfo->getFilename(), 'veryold_')) {
                        $mdate = $this->getTimestamp($user, 'veryold');
                    } else {
                        $mdate = $this->getTimestamp($user, 'current');
                    }
                }
                $this->createFile(
                    $user,
                    $fileInfo->getPathname(),
                    $relativePath,
                    $mdate
                );
            }
        }
    }

    /**
     * delete all files for the given user in the Nextcloud via WebDAV
     * 
     * @param $user - the username and password of the user for whom all files should be deleted
     */
    public function deleteAllFiles(array $user) : void {
        $items = $this->listDirectory($user, "");
        foreach ($items as $item) {
            $this->deleteItem($user, $item);
        }
    }

    /**
     * Set up file sharing among the two users.
     * 
     * The destination can contain folder names. If these folders do not exist at the sharee, 
     * they will be created.
     * 
     * @param $sharer - the array with username and password of the sharer
     * @param $sharee - the array with username and password of the sharee
     * @param $src - the file path for the sharer
     * @param $desg - the file path for the sharee (including the file name)
     */
    public function setupFileSharing(array $sharer, array $sharee, string $src, ?string $dest) : void {
        $this->shareFile($sharer, $sharee, $src);
        if (isset($dest)) {
            $path_parts = explode('/', $dest);
            $path_acc = [];
            foreach (array_slice($path_parts, 0, -1) as $dir) {
                $path_acc[] = $dir;
                $path = implode('/', $path_acc);
                if (! $this->checkIfDirExists($sharee, $path)) {
                    $this->createDirectory($sharee, $path);
                }
            }
            $this->moveFile($sharee, $src, $dest);
        }
    }


    /* ====================== PROTECTED METHODS =============================== */

    /**
     * generate the WebDAV base URL
     */
    protected function getDAVUrl(array $user) : string {
        return "/remote.php/dav/files/" . $user['user'];
    }

    /**
     * Upload a file to Nextcloud
     * 
     * @param $user - an array with username and password for the Nextcloud user
     * @param $localFilePath - the path to the local file that should be uploaded
     * @param $targetFilePath - the path on the Nextcloud, including the filename
     * @param $mdate - a DateTime object to set an arbitrary modification time in the Nextcloud
     */
    protected function createFile(array $user, string $localFilePath, string $targetFilePath, ?DateTimeImmutable $mdate) : void {
        if (!file_exists($localFilePath)) {
            throw new \RuntimeException("Cannot find local file: " . $localFilePath);
        }

        $fileStream = fopen($localFilePath, 'r');
        if (!$fileStream) {
            throw new \RuntimeException("Cannot open local file: " . $localFilePath);
        }

        $headers = [ 'X-Requested-With' => 'XMLHttpRequest' ];
        if (isset($mdate)) {
            $headers['X-OC-MTime'] = "{$mdate->getTimestamp()}";
            $headers['X-OC-CTime'] = "{$mdate->getTimestamp()}";
        }

        try {
            $remoteUrl = $this->getDAVUrl($user) . '/' . $targetFilePath;
            $response = $this->client->request('PUT', $remoteUrl, [
                'auth' => [$user['user'], $user['pwd']], 
                'body' => $fileStream,
                'headers' => $headers,
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
     * Check if a directory exists for the given user via WebDAV
     */
    protected function checkIfDirExists(array $user, string $path) : bool {
        $remoteUrl = $this->getDAVUrl($user) . '/' . $path;
        if (!str_ends_with($remoteUrl, '/')) {
            $remoteUrl .= '/';
        }
        $bodyXML = <<<XML
        <?xml version="1.0" encoding="utf-8" ?>
        <D:propfind xmlns:D="DAV:">
        <D:displayname/>
        </D:propfind>
        XML;
        $response = $this->client->request('PROPFIND', $remoteUrl, [
            'auth' => [$user['user'], $user['pwd']], 
            'headers' => [
                'Content-Type' => 'application/xml',
                'Depth' => '0',
            ],
            'body' => $bodyXML,
            'http_errors' => false,
        ]);

        $statusCode = $response->getStatusCode();
        if (! in_array($statusCode, [207, 404])) {
            throw new \RuntimeException("Error checking for directory $path " . $e->getMessage());
        }
        return $statusCode == 207;
    }

    /**
     * Create a directory on the Nextcloud using WebDAV
     */
    protected function createDirectory(array $user, string $path): void {
        $remoteUrl = $this->getDAVUrl($user) . '/' . $path;

        $response = $this->client->request('MKCOL', $remoteUrl, [
            'auth' => [$user['user'], $user['pwd']], 
            'headers' => [ 'X-Requested-With' => 'XMLHttpRequest' ],
        ]);

        $statusCode = $response->getStatusCode();
        if (!($statusCode === 201 || $statusCode === 204)) {
            throw new \RuntimeException("Could not create directory: " . $path . " for " . $username . ": " . $response);
        };
    }

    /**
     * list the content of a directory via WebDAV
     */
    protected function listDirectory(array $user, string $path): array {
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
    protected function deleteItem(array $user, string $path) : void {
        $url = $this->getDAVUrl($user) . '/' . $path;
        $response = $this->client->request('DELETE', $url, [
            'auth' => [$user['user'], $user['pwd']]
        ]);
        if (! in_array($response->getStatusCode(), [204, 200])) {
            throw new \RuntimeException("deleteItem for " . $path . " failed: " . $e->getMessage());
        }
    }

    /**
     * Share a file from user $sharer with user $sharee via Nextcloud OCS API.
    */
    protected function shareFile(array $sharer, array $sharee, string $file): void {
        $response = $this->client->request('POST', '/ocs/v2.php/apps/files_sharing/api/v1/shares', [
            'auth' => [$sharer['user'], $sharer['pwd']],
            'headers' => [
                'OCS-APIRequest' => 'true',
                'Accept' => 'application/json',
            ],
            'form_params' => [
                'path'     => $file,
                'shareType'=> 0,
                'shareWith'=> $sharee['user'],
            ],
        ]);
                    
        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException("Sharing file $file from $sharer to $sharee failed: " . $response->getStatusCode());
        }
    }

    /**
     * Move a file from src to dest for the given user via WebDAV
     */
    protected function moveFile(array $user, string $src, string $dest ) : void {
        $davUrl = $this->getDAVUrl($user);
        $url = $davUrl . "/" . $src;
        $destUrl = $davUrl . "/" . $dest;
        if (!str_ends_with($destUrl, '/')) {
            $destUrl .= '/';
        }

        $response = $this->client->request('MOVE', $url, [
            'auth' => [$user['user'], $user['pwd']],
            'headers' => [
                'OCS-APIRequest' => 'true',
                'Destination' => $destUrl,
                'Overwrite' => "T"
            ]
        ]);
                    
        if (! in_array($response->getStatusCode(), [200, 201, 204])) {
            throw new \RuntimeException("Moving file from $src to $dest for {$user['user']} failed: " . $response->getStatusCode());
        }

    }

    /**
     * calculate creation / modification timestamp based on
     *   - the user 
     *   - the file type
     *   - the number of already existing entries for the file type 
     */
    protected function getTimestamp(array $user, string $fileClass) : DateTimeImmutable {

        $ts = $this->referenceTime;

        // subtract 35 minutes for each user. To do that we need to identify the index of the user in $fileCounters
        $userIdx = array_search($user['user'], array_column($this->fileCounters, 'user'), true);
        if ($userIdx > 0) {
            for ($i = 0; $i < $userIdx; $i++) {
                $ts = $ts->sub(new DateInterval('PT35M'));
            }
        }

        // subtract time delta based on file type
        switch ($fileClass) {
            case 'veryold':
                $ts = $ts->sub(new DateInterval('P3M'));
                break;
            case 'old':
                $ts = $ts->sub(new DateInterval('P1M'));
                break;
            case 'current':
            default:            
        }

        // subtract 1 minute for each file in each category
        for ($i = 0; $i < $this->fileCounters[$userIdx][$fileClass] ?? 0; $i++) {
            $ts = $ts->sub(new DateInterval('PT1M'));
        }

        // increase the respective counter
        $this->fileCounters[$userIdx][$fileClass] = ($this->fileCounters[$userIdx][$fileClass] ?? 0) + 1;

        return $ts;
    }
}
                        