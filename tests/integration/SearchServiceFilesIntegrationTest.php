<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/SearchServiceIntegrationTestHelper.php';

use PHPUnit\Framework\TestCase;

/**
 * Integration tests for the Files interface (if no Elasticsearch is available)
 */
class SearchServiceFilesIntegrationTest extends TestCase {

    /** @var SearchServiceIntegrationTestHelper */
    private $helper;

    /** @var array */
    private $users = [];


    /**
     * creating the test setup:
     *   - two random users are created
     *   - all Nextcloud default files are deleted
     *   - the file and directories from tests/data are uploaded to Nextcloud
     *   - file sharing between the two users is set up
     * 
     * NOTE: The environment variable "NEXTCLOUD_URL" needs to be set to work properly
     * 
     * You can enable debug output with the environment variable DEBUG=true
     */
    protected function setUp(): void {
        parent::setUp();

        $this->helper = new SearchServiceIntegrationTestHelper('admin', 'admin', getenv('NEXTCLOUD_URL'));

        try {
            $this->users[] = $this->helper->createUser();
            $this->users[] = $this->helper->createUser();
            $this->helper->deleteAllFiles($this->users[0]);
            $this->helper->deleteAllFiles($this->users[1]);
            $this->helper->createTestFiles($this->users[0], 'tests/data/user1');     
            $this->helper->createTestFiles($this->users[1], 'tests/data/user2');
            
            $sharingSetup = [
                [ 'sharer' => 0, 'sharee' => 1, 'src' => 'file_user1_1.txt', 'dest' => 'Shared/Folder/file_user1_1.txt' ],
                [ 'sharer' => 0, 'sharee' => 1, 'src' => 'folder1_user1/file_user1_f1_1.txt' ],
                [ 'sharer' => 1, 'sharee' => 0, 'src' => 'file_user2_1.txt' ],
            ];
            foreach ($sharingSetup as $share) {
                $this->helper->setupFileSharing($this->users[$share['sharer']], $this->users[$share['sharee']], $share['src'], $share['dest'] ?? null);
            }
            if (getenv('DEBUG') == 'true') {
                echo "test setup successfully created with users " . $this->users[0]['user'] . " and " . $this->users[1]['user'] . ' ... \n';
            }
        } catch (\Throwable $e) {
            throw $e;
        }
    }

    /**
     * remove the created test users
     */
    protected function tearDown(): void {
        if (getenv('DEBUG') != 'true') {
            $this->helper->deleteUser($this->users[0]);
            $this->helper->deleteUser($this->users[1]);
        }
        parent::tearDown();
    }

    /**
     * Test if all files, including shared ones, are returned
     */
    public function testAllFiles(): void {
        // ---- tests for user 1
        $result = $this->helper->makeSearchRequest($this->users[0], filename: '*', size: 100, sort: 'path', sort_order: 'asc');

        // Check if the total number of hits is the expected value
        $this->assertEquals($result['hits'], 20);

        // Check if all files for user 1 appear in the list
        $names = array_column($result['files'], 'name');
        $this->assertEqualsCanonicalizing( [
            // own files
            "file_user1_2.txt",
            "file_user1_1.txt",
            "folder1_user1/",
            "folder1_user1/file_user1_f1_3.pdf",
            "folder1_user1/file_user1_f1_3.odt",
            "folder1_user1/file_user1_f1_2.odt",
            "folder1_user1/file_user1_f1_1.txt",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/file_user1_f1-1_1.tif",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/file_user1_f1-1_1.png",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/file_user1_f1-1_1.odg",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/file_user1_f1-1_1.gif",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/file_user1_f1-1_1.bmp",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/",
            "folder1_user1/folder1-2_with a shorter name_user1/file_user1_f1-2_1.webp",
            "folder1_user1/folder1-2_with a shorter name_user1/file_user1_f1-2_1.tiff",
            "folder1_user1/folder1-2_with a shorter name_user1/file_user1_f1-2_1.svg",
            "folder1_user1/folder1-2_with a shorter name_user1/file_user1_f1-2_1.png",
            "folder1_user1/folder1-2_with a shorter name_user1/file_user1_f1-2_1.pdf",
            "folder1_user1/folder1-2_with a shorter name_user1/",
            // shared files
            "file_user2_1.txt",
        ], $names );

        // ---- tests for user 2
        $result = $this->helper->makeSearchRequest($this->users[1], filename: '*', size: 100);

        // Check if the total number of hits is the expected value
        $this->assertEquals($result['hits'], 7);

        // Check if all files for user 2 appear in the list
        $names = array_column($result['files'], 'name');
        $this->assertEqualsCanonicalizing( [
            // own files
            'file_user2_1.txt',
            'folder1_user2/',
            'folder1_user2/file_user2_f1_1.txt',

            // shared files
            'Shared/',
            'Shared/Folder/',
            'Shared/Folder/file_user1_1.txt',
            'file_user1_f1_1.txt'
        ], $names );

    }

    /**
     * Test pagination
     */
    public function testPagination(): void {
        /* THERE SEEMS TO BE A BUG IN FILE SEARCH PAGINATION ... skip the test for now */
        $this->markTestSkipped('Pagination does not work properly for files in Nextcloud');

        // CHECK THE FIRST PAGE
        $result = $this->helper->makeSearchRequest($this->users[0], filename: '*', size: 10, page:0, sort: 'path', sort_order: 'asc', dump: true);

        // Check if the total number of hits is the expected value
        $this->assertEquals($result['hits'], 10);

        // Check if the first 10 files appear in the list
        $names = array_column($result['files'], 'name');
        $this->assertEqualsCanonicalizing( [
            "file_user1_1.txt",
            "file_user1_2.txt",
            "folder1_user1/",
            "folder1_user1/file_user1_f1_1.txt",
            "folder1_user1/file_user1_f1_2.odt",
            "folder1_user1/file_user1_f1_3.odt",
            "folder1_user1/file_user1_f1_3.pdf",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/file_user1_f1-1_1.bmp",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/file_user1_f1-1_1.gif",
         ], $names );

        // CHECK THE SECOND PAGE
        $result = $this->helper->makeSearchRequest($this->users[0], filename: '*', size: 10, page: 1, sort: 'path', sort_order: 'asc', dump: true);

        // Check if the total number of hits is the expected value
        $this->assertEquals($result['hits'], 10);

        // Check if the first 10 files appear in the list
        $names = array_column($result['files'], 'name');
        $this->assertEqualsCanonicalizing( [
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/file_user1_f1-1_1.odg",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/file_user1_f1-1_1.png",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/file_user1_f1-1_1.tif",
            "folder1_user1/folder1-2_with a shorter name_user1/",
            "folder1_user1/folder1-2_with a shorter name_user1/file_user1_f1-2_1.pdf",
            "folder1_user1/folder1-2_with a shorter name_user1/file_user1_f1-2_1.png",
            "folder1_user1/folder1-2_with a shorter name_user1/file_user1_f1-2_1.svg",
            "folder1_user1/folder1-2_with a shorter name_user1/file_user1_f1-2_1.tiff",
            "folder1_user1/folder1-2_with a shorter name_user1/file_user1_f1-2_1.webp",
            "file_user2_1.txt"        
         ], $names );
    }
        
}
                        