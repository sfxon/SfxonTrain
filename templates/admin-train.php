<?php

declare(strict_types=1);

use OCP\Util;

Util::addScript(OCA\SfxonTrain\AppInfo\Application::APP_ID, OCA\SfxonTrain\AppInfo\Application::APP_ID . '-adminTrain');
Util::addStyle(OCA\SfxonTrain\AppInfo\Application::APP_ID, OCA\SfxonTrain\AppInfo\Application::APP_ID . '-adminTrain');

?>

<div id="sfxontrain-adminTrain"></div>