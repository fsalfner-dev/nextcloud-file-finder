<?php
/**
 * SPDX-FileCopyrightText: 2026 Felix Salfner
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use OCP\Util;

Util::addScript(OCA\FileFinder\AppInfo\Application::APP_ID, OCA\FileFinder\AppInfo\Application::APP_ID . '-main');
Util::addStyle(OCA\FileFinder\AppInfo\Application::APP_ID, OCA\FileFinder\AppInfo\Application::APP_ID . '-main');

?>

<div id="filefinder"></div>
