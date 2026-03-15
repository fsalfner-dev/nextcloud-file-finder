<?php

namespace OCA\FileFinder\Tests;

use OCA\FileFinder\AppInfo\Application;

class SearchServiceFilesTest extends \Test\TestCase {

	public function testDummy() {
		$app = new Application();
		$this->assertEquals('filefinder', $app::APP_ID);
	}
}