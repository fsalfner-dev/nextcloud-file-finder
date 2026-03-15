<?php

namespace OCA\NoteBook\Tests;

use OCA\FileFinder\AppInfo\Application;

class NoteServiceTest extends \Test\TestCase {

	public function testDummy() {
		$app = new Application();
		$this->assertEquals('filefinder', $app::APP_ID);
	}
}