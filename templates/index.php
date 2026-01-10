<?php

declare(strict_types=1);

use OCP\Util;

Util::addScript(OCA\FileFinder\AppInfo\Application::APP_ID, OCA\FileFinder\AppInfo\Application::APP_ID . '-main');
Util::addStyle(OCA\FileFinder\AppInfo\Application::APP_ID, OCA\FileFinder\AppInfo\Application::APP_ID . '-main');

?>

<div id="filefinder"></div>
