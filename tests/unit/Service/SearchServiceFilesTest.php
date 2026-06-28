<?php

namespace OCA\FileFinder\Tests;

use OC\Files\FileInfo;
use OC\Files\View;
use OC\Files\Storage\Home;
use OC\Files\Mount\HomeMountPoint;
use OCP\IUser;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Server;
use OCP\IConfig;
use Test\TestCase;
use Test\Traits\UserTrait;
use OCP\Share\IManager;
use OCP\Share\IShare;

/**
 * @group DB
 */

class SearchServiceFilesTest extends TestCase {

/*   private $user1 = 'user-one';
    private $user2 = 'user-two';
    private $password = 'password123';
    private $fileName = '/testfile.txt';

    protected function setUp(): void {
        parent::setUp();
        
        // 1. Create two users
        $userManager = \OC::$server->getUserManager();
        $this->user1 = $userManager->createUser($this->user1, $this->password);
        $this->user2 = $userManager->createUser($this->user2, $this->password);
    }

    protected function tearDown(): void {
        // Clean up users
        $this->user1->delete();
        $this->user2->delete();
        parent::tearDown();
    }

    public function testShareFileBetweenUsers() {
        // 2. Add files to the user account of the first user
        // Switch to user1 view
        $view = new View('/' . $this->user1->getUID() . '/files');
        $view->file_put_contents($this->fileName, 'test content');

        // Verify file exists
        $this->assertTrue($view->file_exists($this->fileName));

        // 3. Share with the second user
        $shareManager = \OC::$server->getShareManager();
        
        // Get the node for the file
        $fileNode = \OC::$server->getRootFolder()->getUserFolder($this->user1->getUID())->get($this->fileName);
        
        // Create the share
        $share = $shareManager->newShare();
        $share->setNode($fileNode)
            ->setShareType(IManager::SHARE_TYPE_USER)
            ->setShareWith($this->user2->getUID())
            ->setPermissions(IManager::PERMISSION_READ | IManager::PERMISSION_SHARE)
            ->setSharedBy($this->user1->getUID())
            ->setSharedWith($this->user1->getUID()); // Optional, set who created it

        $sharedItem = $shareManager->createShare($share);
        
        // Assertion: Verify share was created
        $this->assertEquals($this->user2->getUID(), $sharedItem->getSharedWith());
        $this->assertEquals(IShare::TYPE_USER, $sharedItem->getShareType());
    }
}
$this->createUser('foo', 'foo');
$this->config = $this->getMockBuilder(IConfig::class)->getMock();
public function testCreateFile() {
    $this->loginAsUser('foo');
    $rootFolder = Server::get(IRootFolder::class);
    $this->assertNotNull($rootFolder);
}
    public function testUserToUserShare() {
        $userManager = \OC::$server->getUserManager();
        $userManager->createUser('user1', 'pass');
        $userManager->createUser('user2', 'pass');

        // USER 1
        \OC_User::setUserId('user1');
        \OC\Files\Filesystem::init('user1', '/user1/files');

        $root = \OC::$server->getRootFolder();
        $user1Folder = $root->getUserFolder('user1');

        $file = $user1Folder->newFile('test.txt');
        $file->putContent('hello world');

        // SHARE
        $shareManager = \OC::$server->getShareManager();

        $share = $shareManager->newShare();
        $share->setNode($file);
        $share->setShareType(\OCP\Share::SHARE_TYPE_USER);
        $share->setSharedWith('user2');
        $share->setSharedBy('user1');
        $share->setPermissions(\OCP\Constants::PERMISSION_READ);

        $shareManager->createShare($share);

        // USER 2
        \OC_User::setUserId('user2');
        \OC\Files\Filesystem::init('user2', '/user2/files');

        $user2Folder = $root->getUserFolder('user2');
        $nodes = $user2Folder->getDirectoryListing();

        $this->assertCount(1, $nodes);
        $this->assertEquals('test.txt', $nodes[0]->getName());
        $this->assertEquals('hello world', $nodes[0]->getContent());
    }
*/
    private $container;
    private $storage;
    
    private $config;

    protected function setUp(): void {
        parent::setUp();
        $app = new \OCA\FileFinder\AppInfo\Application();
        $this->container = $app->getContainer();
        $this->storage = $this->getMockBuilder('OCP\Files\Folder')->disableOriginalConstructor()->getMock();


    }


	public function testDummy() {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')
			->willReturn('foo');
		$user->method('getHome')
			->willReturn('foo');
		$storage = new Home(['user' => $user]);

		$fileInfo = new FileInfo(
			'',
			$storage,
			'',
			[],
			new HomeMountPoint($user, $storage, '/foo/files')
		);
		$this->assertFalse($fileInfo->isMounted());
	}

}

