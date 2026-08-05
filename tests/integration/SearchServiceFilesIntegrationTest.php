<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/SearchServiceIntegrationTestHelper.php';

use PHPUnit\Framework\TestCase;

/**
 * Integration tests for the Files interface (if no Elasticsearch is available)
 */
class SearchServiceFilesIntegrationTest extends TestCase {

    /** @var SearchServiceIntegrationTestHelper */
    private static $helper;

    /** @var array */
    private static $users = [];


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
    public static function setUpBeforeClass(): void {
        self::$helper = new SearchServiceIntegrationTestHelper('admin', 'admin', getenv('NEXTCLOUD_URL'));

        try {
            self::$users[] = self::$helper->createUser();
            self::$users[] = self::$helper->createUser();
            self::$helper->deleteAllFiles(self::$users[0]);
            self::$helper->deleteAllFiles(self::$users[1]);
            self::$helper->createTestFiles(self::$users[0], 'tests/data/user1', 0);     
            self::$helper->createTestFiles(self::$users[1], 'tests/data/user2', 1);
            
            $sharingSetup = [
                [ 'sharer' => 0, 'sharee' => 1, 'src' => 'file_user1_1.txt', 'dest' => 'Shared/Folder/file_user1_1.txt' ],
                [ 'sharer' => 0, 'sharee' => 1, 'src' => 'folder1_user1/file_user1_f1_1.txt' ],
                [ 'sharer' => 1, 'sharee' => 0, 'src' => 'file_user2_1.txt' ],
            ];
            foreach ($sharingSetup as $share) {
                self::$helper->setupFileSharing(self::$users[$share['sharer']], self::$users[$share['sharee']], $share['src'], $share['dest'] ?? null);
            }
            if (getenv('DEBUG') == 'true') {
                echo "test setup successfully created with users " . self::$users[0]['user'] . " and " . self::$users[1]['user'] . ' ... '  . PHP_EOL;
            }
        } catch (\Throwable $e) {
            throw $e;
        }
    }

    /**
     * remove the created test users
     */
    public static function tearDownAfterClass(): void {
        if (getenv('DEBUG') != 'true') {
            self::$helper->deleteUser(self::$users[0]);
            self::$helper->deleteUser(self::$users[1]);
        }
    }

    /**
     * Test if all files, including shared ones, are returned
     */
    public function testAllFiles(): void {
        // ---- tests for user 1
        $result = self::$helper->makeSearchRequest(self::$users[0], filename: '*', size: 100, sort: 'path', sort_order: 'asc');
        $this->checkSearchResult( $result, [
            // own files
            "file_user1_2.txt",
            "file_user1_1.txt",
            "folder1_user1/",
            "folder1_user1/file_user1_f1_3.pdf",
            "folder1_user1/file_user1_f1_3.odt",
            "folder1_user1/file_user1_f1_2.odt",
            "folder1_user1/file_user1_f1_1.txt",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/veryold_file_user1_f1-1_1.tif",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/file_user1_f1-1_1.png",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/file_user1_f1-1_1.odg",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/old_file_user1_f1-1_1.gif",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/file_user1_f1-1_1.bmp",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/",
            "folder1_user1/folder1-2_with a shorter name_user1/old_file_user1_f1-2_1.webp",
            "folder1_user1/folder1-2_with a shorter name_user1/file_user1_f1-2_1.tiff",
            "folder1_user1/folder1-2_with a shorter name_user1/veryold_file_user1_f1-2_1.svg",
            "folder1_user1/folder1-2_with a shorter name_user1/file_user1_f1-2_1.png",
            "folder1_user1/folder1-2_with a shorter name_user1/file_user1_f1-2_1.pdf",
            "folder1_user1/folder1-2_with a shorter name_user1/",
            // shared files
            "file_user2_1.txt",
        ], 'check for all files of user 1' );

        // ---- tests for user 2
        $result = self::$helper->makeSearchRequest(self::$users[1], filename: '*', size: 100);
        $this->checkSearchResult( $result, [
            // own files
            'file_user2_1.txt',
            'folder1_user2/',
            'folder1_user2/file_user2_f1_1.txt',

            // shared files
            'Shared/',
            'Shared/Folder/',
            'Shared/Folder/file_user1_1.txt',
            'file_user1_f1_1.txt'
        ], 'check for all files of user 2' );

    }

    /**
     * Test filename patterns
     */
    public function testFilenamePatterns(): void {
        // ----- Test for file extension pattern
        $result = self::$helper->makeSearchRequest(self::$users[0], filename: '*.png', size: 100 );
        $this->checkSearchResult( $result, [
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/file_user1_f1-1_1.png",
            "folder1_user1/folder1-2_with a shorter name_user1/file_user1_f1-2_1.png",
         ], 'test filename pattern for specific extension');

        // Check more complex pattern: fixed start
        $result = self::$helper->makeSearchRequest(self::$users[0], filename: 'folder*/*.png', size: 100 );
        $this->checkSearchResult( $result, [
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/file_user1_f1-1_1.png",
            "folder1_user1/folder1-2_with a shorter name_user1/file_user1_f1-2_1.png",
         ], 'test more complex filename pattern with fixed start' );

        // Check more complex pattern: asterisk start
        $result = self::$helper->makeSearchRequest(self::$users[0], filename: '*shorter name*', size: 100 );
        $this->checkSearchResult( $result, [
            "folder1_user1/folder1-2_with a shorter name_user1/old_file_user1_f1-2_1.webp",
            "folder1_user1/folder1-2_with a shorter name_user1/file_user1_f1-2_1.tiff",
            "folder1_user1/folder1-2_with a shorter name_user1/veryold_file_user1_f1-2_1.svg",
            "folder1_user1/folder1-2_with a shorter name_user1/file_user1_f1-2_1.png",
            "folder1_user1/folder1-2_with a shorter name_user1/file_user1_f1-2_1.pdf",
            "folder1_user1/folder1-2_with a shorter name_user1/",
         ], 'test more complex filename pattern with asterisk start' );
    }

    /**
     * Test filetype selection
     */
    public function testFiletypeSelection(): void {
        // -------- single list element
        $result = self::$helper->makeSearchRequest(self::$users[0], filename: '*', size: 100, file_types:['images'], sort: 'path', sort_order: 'asc');
        $this->checkSearchResult( $result, [
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/veryold_file_user1_f1-1_1.tif",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/file_user1_f1-1_1.png",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/old_file_user1_f1-1_1.gif",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/file_user1_f1-1_1.bmp",
            "folder1_user1/folder1-2_with a shorter name_user1/old_file_user1_f1-2_1.webp",
            "folder1_user1/folder1-2_with a shorter name_user1/file_user1_f1-2_1.tiff",
            "folder1_user1/folder1-2_with a shorter name_user1/veryold_file_user1_f1-2_1.svg",
            "folder1_user1/folder1-2_with a shorter name_user1/file_user1_f1-2_1.png",
         ], 'check for single file type');

        // -------- two list elements
        $result = self::$helper->makeSearchRequest(self::$users[0], filename: '*', size: 100, file_types:['images', 'pdfs'], sort: 'path', sort_order: 'asc');
        $this->checkSearchResult( $result, [
            "folder1_user1/file_user1_f1_3.pdf",                        
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/veryold_file_user1_f1-1_1.tif",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/file_user1_f1-1_1.png",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/old_file_user1_f1-1_1.gif",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/file_user1_f1-1_1.bmp",
            "folder1_user1/folder1-2_with a shorter name_user1/old_file_user1_f1-2_1.webp",
            "folder1_user1/folder1-2_with a shorter name_user1/file_user1_f1-2_1.tiff",
            "folder1_user1/folder1-2_with a shorter name_user1/veryold_file_user1_f1-2_1.svg",
            "folder1_user1/folder1-2_with a shorter name_user1/file_user1_f1-2_1.png",
            "folder1_user1/folder1-2_with a shorter name_user1/file_user1_f1-2_1.pdf",
         ], 'check multiple file types' );

        // -------- empty list should return all files
        $result = self::$helper->makeSearchRequest(self::$users[0], filename: '*', size: 100, file_types:[], sort: 'path', sort_order: 'asc');
        $this->checkSearchResult( $result, [
            "file_user1_2.txt",
            "file_user1_1.txt",
            "folder1_user1/",
            "folder1_user1/file_user1_f1_3.pdf",
            "folder1_user1/file_user1_f1_3.odt",
            "folder1_user1/file_user1_f1_2.odt",
            "folder1_user1/file_user1_f1_1.txt",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/veryold_file_user1_f1-1_1.tif",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/file_user1_f1-1_1.png",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/file_user1_f1-1_1.odg",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/old_file_user1_f1-1_1.gif",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/file_user1_f1-1_1.bmp",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/",
            "folder1_user1/folder1-2_with a shorter name_user1/old_file_user1_f1-2_1.webp",
            "folder1_user1/folder1-2_with a shorter name_user1/file_user1_f1-2_1.tiff",
            "folder1_user1/folder1-2_with a shorter name_user1/veryold_file_user1_f1-2_1.svg",
            "folder1_user1/folder1-2_with a shorter name_user1/file_user1_f1-2_1.png",
            "folder1_user1/folder1-2_with a shorter name_user1/file_user1_f1-2_1.pdf",
            "folder1_user1/folder1-2_with a shorter name_user1/",
            "file_user2_1.txt",
         ], 'check empty filetype list' );
    }


    /**
     * Test folder exclusion
     */
    public function testFolderExclusion(): void {
        // Exclude one folder on first level
        $result = self::$helper->makeSearchRequest(self::$users[0], filename: '*', size: 100, exclude_folders: ['folder1_user1'], sort: 'path', sort_order: 'asc');
        $this->checkSearchResult( $result, [
            "file_user1_2.txt",
            "file_user1_1.txt",
            "file_user2_1.txt",
         ], 'exclude one folder on first level' );

        // Exclude one folder on second level
        $result = self::$helper->makeSearchRequest(self::$users[0], filename: '*', size: 100, exclude_folders: ['folder1_user1/folder1-2_with a shorter name_user1'], sort: 'path', sort_order: 'asc');
        $this->checkSearchResult( $result, [
            "file_user1_2.txt",
            "file_user1_1.txt",
            "folder1_user1/",
            "folder1_user1/file_user1_f1_3.pdf",
            "folder1_user1/file_user1_f1_3.odt",
            "folder1_user1/file_user1_f1_2.odt",
            "folder1_user1/file_user1_f1_1.txt",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/veryold_file_user1_f1-1_1.tif",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/file_user1_f1-1_1.png",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/file_user1_f1-1_1.odg",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/old_file_user1_f1-1_1.gif",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/file_user1_f1-1_1.bmp",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/",
            "file_user2_1.txt",
         ], 'exclude one folder on second level' );

        // Exclude two folders on second level
        $result = self::$helper->makeSearchRequest(self::$users[0], filename: '*', size: 100, exclude_folders: ['folder1_user1/folder1-2_with a shorter name_user1', 'folder1_user1/folder1-1_with a very long name and spaces for_user1'], sort: 'path', sort_order: 'asc');
        $this->checkSearchResult( $result, [
            "file_user1_2.txt",
            "file_user1_1.txt",
            "folder1_user1/",
            "folder1_user1/file_user1_f1_3.pdf",
            "folder1_user1/file_user1_f1_3.odt",
            "folder1_user1/file_user1_f1_2.odt",
            "folder1_user1/file_user1_f1_1.txt",
            "file_user2_1.txt",
         ], 'exclude two folders on second level' );
    }

    /**
     * Test start folder
     */
    public function testStartFolder(): void {
        // ----- Test start folder on first level
        $result = self::$helper->makeSearchRequest(self::$users[0], filename: '*', size: 100, start_folder: 'folder1_user1', sort: 'path', sort_order: 'asc');
       $this->checkSearchResult( $result, [
            "folder1_user1/",
            "folder1_user1/file_user1_f1_3.pdf",
            "folder1_user1/file_user1_f1_3.odt",
            "folder1_user1/file_user1_f1_2.odt",
            "folder1_user1/file_user1_f1_1.txt",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/veryold_file_user1_f1-1_1.tif",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/file_user1_f1-1_1.png",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/file_user1_f1-1_1.odg",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/old_file_user1_f1-1_1.gif",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/file_user1_f1-1_1.bmp",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/",
            "folder1_user1/folder1-2_with a shorter name_user1/old_file_user1_f1-2_1.webp",
            "folder1_user1/folder1-2_with a shorter name_user1/file_user1_f1-2_1.tiff",
            "folder1_user1/folder1-2_with a shorter name_user1/veryold_file_user1_f1-2_1.svg",
            "folder1_user1/folder1-2_with a shorter name_user1/file_user1_f1-2_1.png",
            "folder1_user1/folder1-2_with a shorter name_user1/file_user1_f1-2_1.pdf",
            "folder1_user1/folder1-2_with a shorter name_user1/",
        ], 'start folder on first level' );

        // ----- Test start folder on second level
        $result = self::$helper->makeSearchRequest(self::$users[0], filename: '*', size: 100, start_folder: 'folder1_user1/folder1-2_with a shorter name_user1', sort: 'path', sort_order: 'asc');
        $this->checkSearchResult( $result, [
            "folder1_user1/folder1-2_with a shorter name_user1/",
            "folder1_user1/folder1-2_with a shorter name_user1/old_file_user1_f1-2_1.webp",
            "folder1_user1/folder1-2_with a shorter name_user1/file_user1_f1-2_1.tiff",
            "folder1_user1/folder1-2_with a shorter name_user1/veryold_file_user1_f1-2_1.svg",
            "folder1_user1/folder1-2_with a shorter name_user1/file_user1_f1-2_1.png",
            "folder1_user1/folder1-2_with a shorter name_user1/file_user1_f1-2_1.pdf",
        ], 'start folder on second level');
    }

    /**
     * Test date filtering
     */
    public function testDateFiltering(): void {
        // In setUp we have created the following setup:
        //   - files with prefix "veryold_"  have a creation date 3 months ago
        //   - files with prefix "old_" have a creation date 1 month ago
        //   - all other files have a creation date of today

        $today = new DateTimeImmutable('now');
        
        // ----- Test 1: Before-Date earlier than any file (4 months ago)
        $before_date = $today->sub(new DateInterval('P4M'));
        $result = self::$helper->makeSearchRequest(self::$users[0], filename: '*', size: 100, before_date: $before_date, sort: 'path', sort_order: 'asc');
        $this->checkSearchResult($result, [], 'before date earlier than any file');

        // ----- Test 2: Before-Date between veryold_ and old_  (2 months ago)
        $before_date = $today->sub(new DateInterval('P2M'));
        $result = self::$helper->makeSearchRequest(self::$users[0], filename: '*', size: 100, before_date: $before_date, sort: 'path', sort_order: 'asc');
        $this->checkSearchResult( $result, [
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/veryold_file_user1_f1-1_1.tif",
            "folder1_user1/folder1-2_with a shorter name_user1/veryold_file_user1_f1-2_1.svg",
        ], 'before date between very old and old files' );

        // ----- Test 3: Before-Date after but before today  (2 weeks ago)
        $before_date = $today->sub(new DateInterval('P2W'));
        $result = self::$helper->makeSearchRequest(self::$users[0], filename: '*', size: 100, before_date: $before_date, sort: 'path', sort_order: 'asc');
        $this->checkSearchResult( $result, [
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/veryold_file_user1_f1-1_1.tif",
            "folder1_user1/folder1-2_with a shorter name_user1/veryold_file_user1_f1-2_1.svg",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/old_file_user1_f1-1_1.gif",
            "folder1_user1/folder1-2_with a shorter name_user1/old_file_user1_f1-2_1.webp",
        ], 'before date after old but before today' );

        // ----- Test 4: Before-Date in the future
        $before_date = $today->add(new DateInterval('P1W'));
        $result = self::$helper->makeSearchRequest(self::$users[0], filename: '*', size: 100, before_date: $before_date, sort: 'path', sort_order: 'asc');
        $this->checkSearchResult( $result, [
            "file_user1_2.txt",
            "file_user1_1.txt",
            "folder1_user1/",
            "folder1_user1/file_user1_f1_3.pdf",
            "folder1_user1/file_user1_f1_3.odt",
            "folder1_user1/file_user1_f1_2.odt",
            "folder1_user1/file_user1_f1_1.txt",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/veryold_file_user1_f1-1_1.tif",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/file_user1_f1-1_1.png",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/file_user1_f1-1_1.odg",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/old_file_user1_f1-1_1.gif",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/file_user1_f1-1_1.bmp",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/",
            "folder1_user1/folder1-2_with a shorter name_user1/old_file_user1_f1-2_1.webp",
            "folder1_user1/folder1-2_with a shorter name_user1/file_user1_f1-2_1.tiff",
            "folder1_user1/folder1-2_with a shorter name_user1/veryold_file_user1_f1-2_1.svg",
            "folder1_user1/folder1-2_with a shorter name_user1/file_user1_f1-2_1.png",
            "folder1_user1/folder1-2_with a shorter name_user1/file_user1_f1-2_1.pdf",
            "folder1_user1/folder1-2_with a shorter name_user1/",
            "file_user2_1.txt",
        ], 'before date in the future' );

        // ----- Test 5: After-Date earlier than any file (4 months ago)
        $after_date = $today->sub(new DateInterval('P4M'));
        $result = self::$helper->makeSearchRequest(self::$users[0], filename: '*', size: 100, after_date: $after_date, sort: 'path', sort_order: 'asc');
        
        // all files should be returned
        $this->checkSearchResult( $result, [
            "file_user1_2.txt",
            "file_user1_1.txt",
            "folder1_user1/",
            "folder1_user1/file_user1_f1_3.pdf",
            "folder1_user1/file_user1_f1_3.odt",
            "folder1_user1/file_user1_f1_2.odt",
            "folder1_user1/file_user1_f1_1.txt",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/veryold_file_user1_f1-1_1.tif",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/file_user1_f1-1_1.png",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/file_user1_f1-1_1.odg",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/old_file_user1_f1-1_1.gif",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/file_user1_f1-1_1.bmp",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/",
            "folder1_user1/folder1-2_with a shorter name_user1/old_file_user1_f1-2_1.webp",
            "folder1_user1/folder1-2_with a shorter name_user1/file_user1_f1-2_1.tiff",
            "folder1_user1/folder1-2_with a shorter name_user1/veryold_file_user1_f1-2_1.svg",
            "folder1_user1/folder1-2_with a shorter name_user1/file_user1_f1-2_1.png",
            "folder1_user1/folder1-2_with a shorter name_user1/file_user1_f1-2_1.pdf",
            "folder1_user1/folder1-2_with a shorter name_user1/",
            "file_user2_1.txt",
        ], 'after date before very old files' );


        // ----- Test 6: After-Date between veryold_ and old_  (2 months ago)
        $after_date = $today->sub(new DateInterval('P2M'));
        $result = self::$helper->makeSearchRequest(self::$users[0], filename: '*', size: 100, after_date: $after_date, sort: 'path', sort_order: 'asc');
        $this->checkSearchResult( $result, [
            "file_user1_2.txt",
            "file_user1_1.txt",
            "folder1_user1/",
            "folder1_user1/file_user1_f1_3.pdf",
            "folder1_user1/file_user1_f1_3.odt",
            "folder1_user1/file_user1_f1_2.odt",
            "folder1_user1/file_user1_f1_1.txt",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/file_user1_f1-1_1.png",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/file_user1_f1-1_1.odg",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/old_file_user1_f1-1_1.gif",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/file_user1_f1-1_1.bmp",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/",
            "folder1_user1/folder1-2_with a shorter name_user1/old_file_user1_f1-2_1.webp",
            "folder1_user1/folder1-2_with a shorter name_user1/file_user1_f1-2_1.tiff",
            "folder1_user1/folder1-2_with a shorter name_user1/file_user1_f1-2_1.png",
            "folder1_user1/folder1-2_with a shorter name_user1/file_user1_f1-2_1.pdf",
            "folder1_user1/folder1-2_with a shorter name_user1/",
            "file_user2_1.txt",
        ], 'after date between very old and old files' );

        // ----- Test 7: After-Date after old_ but before today  (2 weeks ago)
        $after_date = $today->sub(new DateInterval('P2W'));
        $result = self::$helper->makeSearchRequest(self::$users[0], filename: '*', size: 100, after_date: $after_date, sort: 'path', sort_order: 'asc');
        $this->checkSearchResult( $result, [
            "file_user1_2.txt",
            "file_user1_1.txt",
            "folder1_user1/",
            "folder1_user1/file_user1_f1_3.pdf",
            "folder1_user1/file_user1_f1_3.odt",
            "folder1_user1/file_user1_f1_2.odt",
            "folder1_user1/file_user1_f1_1.txt",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/file_user1_f1-1_1.png",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/file_user1_f1-1_1.odg",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/file_user1_f1-1_1.bmp",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/",
            "folder1_user1/folder1-2_with a shorter name_user1/file_user1_f1-2_1.tiff",
            "folder1_user1/folder1-2_with a shorter name_user1/file_user1_f1-2_1.png",
            "folder1_user1/folder1-2_with a shorter name_user1/file_user1_f1-2_1.pdf",
            "folder1_user1/folder1-2_with a shorter name_user1/",
            "file_user2_1.txt",
        ], 'after date after old files but before today' );

        // ----- Test 8: After-Date in the future
        $after_date = $today->add(new DateInterval('P1W'));
        $result = self::$helper->makeSearchRequest(self::$users[0], filename: '*', size: 100, after_date: $after_date, sort: 'path', sort_order: 'asc');
        $this->checkSearchResult($result, [], 'after date in the future');
    }

    /**
     * Test sorting
     */
    public function testSorting(): void {
        // The file interface allows to sort by 'path', or 'modified'
        // 
        // PLEASE NOTE:
        //   - Sorting seems to work case sensitive in Nextcloud
        //   - Shared files do not seem to be sorted into the search results and always come last
        //   - Also because of the problems with pagination (see next test), we always query all files

        // ----- Test 1: sort by path ascending for user 1
        $result = self::$helper->makeSearchRequest(self::$users[0], filename: '*', size: 100, sort: 'path', sort_order: 'asc');
        $this->checkSearchResult( $result, [
            "file_user1_1.txt",
            "file_user1_2.txt",
            "folder1_user1/",
            "folder1_user1/file_user1_f1_1.txt",
            "folder1_user1/file_user1_f1_2.odt",
            "folder1_user1/file_user1_f1_3.odt",
            "folder1_user1/file_user1_f1_3.pdf",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/file_user1_f1-1_1.bmp",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/file_user1_f1-1_1.odg",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/file_user1_f1-1_1.png",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/old_file_user1_f1-1_1.gif",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/veryold_file_user1_f1-1_1.tif",
            "folder1_user1/folder1-2_with a shorter name_user1/",
            "folder1_user1/folder1-2_with a shorter name_user1/file_user1_f1-2_1.pdf",
            "folder1_user1/folder1-2_with a shorter name_user1/file_user1_f1-2_1.png",
            "folder1_user1/folder1-2_with a shorter name_user1/file_user1_f1-2_1.tiff",
            "folder1_user1/folder1-2_with a shorter name_user1/old_file_user1_f1-2_1.webp",
            "folder1_user1/folder1-2_with a shorter name_user1/veryold_file_user1_f1-2_1.svg",
            //TODO: Nextcloud file sorting does not seem to include shared files in the search order
            "file_user2_1.txt",
         ], 'sort by path ascending for user 1', checkOrder: true );

        // ----- Test 2: sort by path ascending for user 2
        $result = self::$helper->makeSearchRequest(self::$users[1], filename: '*', size: 100, sort: 'path', sort_order: 'asc');
        $this->checkSearchResult( $result, [
            "Shared/",
            "Shared/Folder/",
            "file_user2_1.txt",
            "folder1_user2/",
            "folder1_user2/file_user2_f1_1.txt",
            // TODO: Nextcloud does not seem to include shared files into the search results
            "Shared/Folder/file_user1_1.txt",
            "file_user1_f1_1.txt",
         ], 'sort by path ascending for user 2', checkOrder: true);


        // ----- Test 3: sort by path descending for user 1
        $result = self::$helper->makeSearchRequest(self::$users[0], filename: '*', size: 100, sort: 'path', sort_order: 'desc');
        $this->checkSearchResult( $result, [
            "folder1_user1/folder1-2_with a shorter name_user1/veryold_file_user1_f1-2_1.svg",
            "folder1_user1/folder1-2_with a shorter name_user1/old_file_user1_f1-2_1.webp",
            "folder1_user1/folder1-2_with a shorter name_user1/file_user1_f1-2_1.tiff",
            "folder1_user1/folder1-2_with a shorter name_user1/file_user1_f1-2_1.png",
            "folder1_user1/folder1-2_with a shorter name_user1/file_user1_f1-2_1.pdf",
            "folder1_user1/folder1-2_with a shorter name_user1/",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/veryold_file_user1_f1-1_1.tif",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/old_file_user1_f1-1_1.gif",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/file_user1_f1-1_1.png",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/file_user1_f1-1_1.odg",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/file_user1_f1-1_1.bmp",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/",
            "folder1_user1/file_user1_f1_3.pdf",
            "folder1_user1/file_user1_f1_3.odt",
            "folder1_user1/file_user1_f1_2.odt",
            "folder1_user1/file_user1_f1_1.txt",
            "folder1_user1/",
            "file_user1_2.txt",
            "file_user1_1.txt",
            //TODO: File search in Nextcloud does not seem to sort shared files into the list ...
            "file_user2_1.txt",
            ], 'sort by path descending for user 1', checkOrder: true );

        // ----- Test 4: sort by path descending for user 2
        $result = self::$helper->makeSearchRequest(self::$users[1], filename: '*', size: 100, sort: 'path', sort_order: 'desc');
        $this->checkSearchResult( $result, [
            "folder1_user2/file_user2_f1_1.txt",
            "folder1_user2/",
            "file_user2_1.txt",
            "Shared/Folder/",
            "Shared/",
            //TODO: File search in Nextcloud does not seem to sort shared files into the list
            "Shared/Folder/file_user1_1.txt",
            "file_user1_f1_1.txt",
        ], 'sort by path descending for user 2', checkOrder: true );

        // ---- Test 5: sort by modified ascending for user 1
        $result = self::$helper->makeSearchRequest(self::$users[0], filename: '*', size: 100, sort: 'modified', sort_order: 'asc');
        $this->checkSearchResult( $result, [
            "folder1_user1/folder1-2_with a shorter name_user1/veryold_file_user1_f1-2_1.svg",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/veryold_file_user1_f1-1_1.tif",
            "folder1_user1/folder1-2_with a shorter name_user1/old_file_user1_f1-2_1.webp",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/old_file_user1_f1-1_1.gif",
            "file_user2_1.txt",
            "folder1_user1/folder1-2_with a shorter name_user1/file_user1_f1-2_1.pdf",
            "folder1_user1/folder1-2_with a shorter name_user1/file_user1_f1-2_1.tiff",
            "folder1_user1/folder1-2_with a shorter name_user1/file_user1_f1-2_1.png",
            "folder1_user1/file_user1_f1_3.pdf",
            "folder1_user1/file_user1_f1_2.odt",
            "folder1_user1/file_user1_f1_3.odt",
            "folder1_user1/file_user1_f1_1.txt",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/file_user1_f1-1_1.odg",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/file_user1_f1-1_1.png",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/file_user1_f1-1_1.bmp",
            "file_user1_2.txt",
            "file_user1_1.txt",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/",
            "folder1_user1/",
            "folder1_user1/folder1-2_with a shorter name_user1/",
            ], 'sort by modified ascending for user 1', checkOrder: true );

        // ---- Test 6: sort by modified descending for user 1
        $result = self::$helper->makeSearchRequest(self::$users[0], filename: '*', size: 100, sort: 'modified', sort_order: 'desc');
        $this->checkSearchResult( $result, [
            "folder1_user1/",
            "folder1_user1/folder1-2_with a shorter name_user1/",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/",
            "file_user1_1.txt",
            "file_user1_2.txt",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/file_user1_f1-1_1.bmp",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/file_user1_f1-1_1.png",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/file_user1_f1-1_1.odg",
            "folder1_user1/file_user1_f1_1.txt",
            "folder1_user1/file_user1_f1_3.odt",
            "folder1_user1/file_user1_f1_2.odt",
            "folder1_user1/file_user1_f1_3.pdf",
            "folder1_user1/folder1-2_with a shorter name_user1/file_user1_f1-2_1.png",
            "folder1_user1/folder1-2_with a shorter name_user1/file_user1_f1-2_1.tiff",
            "folder1_user1/folder1-2_with a shorter name_user1/file_user1_f1-2_1.pdf",
            "file_user2_1.txt",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/old_file_user1_f1-1_1.gif",
            "folder1_user1/folder1-2_with a shorter name_user1/old_file_user1_f1-2_1.webp",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/veryold_file_user1_f1-1_1.tif",
            "folder1_user1/folder1-2_with a shorter name_user1/veryold_file_user1_f1-2_1.svg",
            ], 'sort by modified descending for user 1', checkOrder: true );


        // ---- Test sorting with filtering ...
        $result = self::$helper->makeSearchRequest(self::$users[0], filename: '*.png', size: 100, sort: 'modified', sort_order: 'asc');
        $this->checkSearchResult( $result, [
            "folder1_user1/folder1-2_with a shorter name_user1/file_user1_f1-2_1.png",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/file_user1_f1-1_1.png",
        ], 'sort by path descending for user 2', checkOrder: true );
    }


    /**
     * Test pagination
     */
    public function testPagination(): void {
        /* THERE SEEMS TO BE A BUG IN FILE SEARCH PAGINATION ... skip the test for now */
        $this->markTestSkipped('Pagination does not work properly for files in Nextcloud');

        // CHECK THE FIRST PAGE
        $result = self::$helper->makeSearchRequest(self::$users[0], filename: '*', size: 10, page:0, sort: 'path', sort_order: 'asc');
        $this->checkSearchResult( $result, [
            "file_user1_1.txt",
            "file_user1_2.txt",
            "folder1_user1/",
            "folder1_user1/file_user1_f1_1.txt",
            "folder1_user1/file_user1_f1_2.odt",
            "folder1_user1/file_user1_f1_3.odt",
            "folder1_user1/file_user1_f1_3.pdf",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/file_user1_f1-1_1.bmp",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/old_file_user1_f1-1_1.gif",
         ], 'first page test');

        // CHECK THE SECOND PAGE
        $result = self::$helper->makeSearchRequest(self::$users[0], filename: '*', size: 10, page: 1, sort: 'path', sort_order: 'asc', dump: true);
        $this->checkSearchResult($result, [
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/file_user1_f1-1_1.odg",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/file_user1_f1-1_1.png",
            "folder1_user1/folder1-1_with a very long name and spaces for_user1/veryold_file_user1_f1-1_1.tif",
            "folder1_user1/folder1-2_with a shorter name_user1/",
            "folder1_user1/folder1-2_with a shorter name_user1/file_user1_f1-2_1.pdf",
            "folder1_user1/folder1-2_with a shorter name_user1/file_user1_f1-2_1.png",
            "folder1_user1/folder1-2_with a shorter name_user1/veryold_file_user1_f1-2_1.svg",
            "folder1_user1/folder1-2_with a shorter name_user1/file_user1_f1-2_1.tiff",
            "folder1_user1/folder1-2_with a shorter name_user1/old_file_user1_f1-2_1.webp",
            "file_user2_1.txt"        
         ], 'second page test' );
    }

    // ================== PRIVATE FUNCTIONS

    private function checkSearchResult(array $result, array $expectedFiles, string $message, bool $checkOrder = false) : void {
        // Check if the total number of hits is the expected value
        $this->assertEquals($result['hits'], count($expectedFiles));

        // Check if the list of files are identical
        $files = array_column($result['files'], 'name');
        if ($checkOrder) {
            $this->assertEquals( $expectedFiles, $files, $message );
        } else {
            $this->assertEqualsCanonicalizing( $expectedFiles, $files, $message );
        }
    }
        
}
                        