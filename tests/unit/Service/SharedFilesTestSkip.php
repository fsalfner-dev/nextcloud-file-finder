<?php

namespace OCA\FileFinder\Tests;

use OCP\Files\IRootFolder;
use OCA\FileFinder\AppInfo\Application;
use OCP\Share\IManager;
use OCP\IUser;
use OCP\IUserManager;

class SharedFilesTest extends \Test\TestCase {
	private IRootFolder $rootFolder;
	private IManager $shareManager;
	private IUserManager $userManager;
	
	private string $user1 = 'testuser1';
	private string $user2 = 'testuser2';
	private IUser $userObj1;
	private IUser $userObj2;

	protected function setUp(): void {
		parent::setUp();
		
		$this->rootFolder = \OC::$server->get(IRootFolder::class);
		$this->shareManager = \OC::$server->get(IManager::class);
		$this->userManager = \OC::$server->get(IUserManager::class);
		
		// Create test users
		$this->userObj1 = $this->userManager->createUser($this->user1, 'password1');
		$this->userObj2 = $this->userManager->createUser($this->user2, 'password2');
	}

	protected function tearDown(): void {
		// Clean up users
		if ($this->userManager->userExists($this->user1)) {
			$this->userManager->get($this->user1)->delete();
		}
		if ($this->userManager->userExists($this->user2)) {
			$this->userManager->get($this->user2)->delete();
		}
		parent::tearDown();
	}

	/**
	 * Test setup with two users sharing files
	 */
	public function testMultiUserFileSharing(): void {
		// Create files for user1
		$user1Folder = $this->rootFolder->getUserFolder($this->user1);
		$sharedFolder = $user1Folder->newFolder('shared_docs');
		$file1 = $sharedFolder->newFile('document1.txt');
		$file1->putContent('This is user1\'s document');
		
		// Share the folder with user2
		$share = $this->shareManager->newShare();
		$share->setNode($sharedFolder);
		$share->setShareType(\OCP\Share\IShare::TYPE_USER);
		$share->setSharedWith($this->user2);
		$share->setSharedBy($this->user1);
		$share->setPermissions(\OCP\Constants::PERMISSION_READ);
		$this->shareManager->createShare($share);
		
		// Verify user2 can see the shared file
		$user2Folder = $this->rootFolder->getUserFolder($this->user2);
		$this->assertTrue($user2Folder->nodeExists('shared_docs'));
		
		$sharedNode = $user2Folder->get('shared_docs');
		$this->assertTrue($sharedNode->nodeExists('document1.txt'));
	}

	/**
	 * Test shared folder with multiple files
	 */
	public function testSharedFolderWithMultipleFiles(): void {
		// User1 creates multiple files
		$user1Folder = $this->rootFolder->getUserFolder($this->user1);
		$shareFolder = $user1Folder->newFolder('team_project');
		
		$file1 = $shareFolder->newFile('report.txt');
		$file1->putContent('Project Report');
		
		$file2 = $shareFolder->newFile('notes.md');
		$file2->putContent('# Project Notes');
		
		// Share with user2 with edit permissions
		$share = $this->shareManager->newShare();
		$share->setNode($shareFolder);
		$share->setShareType(\OCP\Share\IShare::TYPE_USER);
		$share->setSharedWith($this->user2);
		$share->setSharedBy($this->user1);
		$share->setPermissions(
			\OCP\Constants::PERMISSION_READ | 
			\OCP\Constants::PERMISSION_UPDATE
		);
		$this->shareManager->createShare($share);
		
		// User2 sees the shared files
		$user2Folder = $this->rootFolder->getUserFolder($this->user2);
		$sharedNode = $user2Folder->get('team_project');
		
		$this->assertTrue($sharedNode->nodeExists('report.txt'));
		$this->assertTrue($sharedNode->nodeExists('notes.md'));
	}

	/**
	 * Test bidirectional sharing - both users share with each other
	 */
	public function testBidirectionalSharing(): void {
		// User1 creates and shares a folder
		$user1Folder = $this->rootFolder->getUserFolder($this->user1);
		$user1Shared = $user1Folder->newFolder('from_user1');
		$file1 = $user1Shared->newFile('user1_file.txt');
		$file1->putContent('Created by user1');
		
		// User2 creates and shares a folder
		$user2Folder = $this->rootFolder->getUserFolder($this->user2);
		$user2Shared = $user2Folder->newFolder('from_user2');
		$file2 = $user2Shared->newFile('user2_file.txt');
		$file2->putContent('Created by user2');
		
		// Both share with each other
		$share1 = $this->shareManager->newShare();
		$share1->setNode($user1Shared);
		$share1->setShareType(\OCP\Share\IShare::TYPE_USER);
		$share1->setSharedWith($this->user2);
		$share1->setSharedBy($this->user1);
		$share1->setPermissions(\OCP\Constants::PERMISSION_READ);
		$this->shareManager->createShare($share1);
		
		$share2 = $this->shareManager->newShare();
		$share2->setNode($user2Shared);
		$share2->setShareType(\OCP\Share\IShare::TYPE_USER);
		$share2->setSharedWith($this->user1);
		$share2->setSharedBy($this->user2);
		$share2->setPermissions(\OCP\Constants::PERMISSION_READ);
		$this->shareManager->createShare($share2);
		
		// Verify both users can see each other's shares
		$user1Folder->clearCache();
		$user2Folder->clearCache();
		
		$this->assertTrue($user1Folder->nodeExists('from_user2'));
		$this->assertTrue($user2Folder->nodeExists('from_user1'));
	}

	/**
	 * Test group sharing
	 */
	public function testGroupSharing(): void {
		$groupManager = \OC::$server->get(\OCP\IGroupManager::class);
		
		// Create a test group
		$group = $groupManager->createGroup('testgroup');
		$group->addUser($this->userObj1);
		$group->addUser($this->userObj2);
		
		// User1 creates and shares with group
		$user1Folder = $this->rootFolder->getUserFolder($this->user1);
		$sharedFolder = $user1Folder->newFolder('group_docs');
		$file = $sharedFolder->newFile('shared_with_group.txt');
		$file->putContent('This is shared with the group');
		
		$share = $this->shareManager->newShare();
		$share->setNode($sharedFolder);
		$share->setShareType(\OCP\Share\IShare::TYPE_GROUP);
		$share->setSharedWith('testgroup');
		$share->setSharedBy($this->user1);
		$share->setPermissions(\OCP\Constants::PERMISSION_READ);
		$this->shareManager->createShare($share);
		
		// User2 (group member) can see the shared folder
		$user2Folder = $this->rootFolder->getUserFolder($this->user2);
		$this->assertTrue($user2Folder->nodeExists('group_docs'));
		
		// Clean up
		$group->delete();
	}

	/**
	 * Test that file content can be searched after sharing
	 */
	public function testSearchableSharedContent(): void {
		$user1Folder = $this->rootFolder->getUserFolder($this->user1);
		$sharedFolder = $user1Folder->newFolder('searchable');
		$file = $sharedFolder->newFile('unique_keyword.txt');
		$file->putContent('This file contains UNIQUEKEYWORD that we will search for');
		
		// Share with user2
		$share = $this->shareManager->newShare();
		$share->setNode($sharedFolder);
		$share->setShareType(\OCP\Share\IShare::TYPE_USER);
		$share->setSharedWith($this->user2);
		$share->setSharedBy($this->user1);
		$share->setPermissions(\OCP\Constants::PERMISSION_READ);
		$this->shareManager->createShare($share);
		
		// User2 should have access to the file
		$user2Folder = $this->rootFolder->getUserFolder($this->user2);
		$file = $user2Folder->get('searchable/unique_keyword.txt');
		$this->assertStringContainsString('UNIQUEKEYWORD', $file->getContent());
	}

	/**
	 * Test the app loads correctly
	 */
	public function testAppId(): void {
		$app = new Application();
		$this->assertEquals('filefinder', $app::APP_ID);
	}
}