<?php

declare(strict_types=1);

use OCP\Util;

Util::addScript(OCA\SfxonTrain\AppInfo\Application::APP_ID, OCA\SfxonTrain\AppInfo\Application::APP_ID . '-dashboard');
Util::addStyle(OCA\SfxonTrain\AppInfo\Application::APP_ID, OCA\SfxonTrain\AppInfo\Application::APP_ID . '-dashboard');

?>

<div id="sfxontrain"></div>
