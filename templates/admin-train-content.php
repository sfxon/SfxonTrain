<?php

declare(strict_types=1);

use OCP\Util;

Util::addScript(OCA\SfxonTrain\AppInfo\Application::APP_ID, OCA\SfxonTrain\AppInfo\Application::APP_ID . '-adminTrainContent');
Util::addStyle(OCA\SfxonTrain\AppInfo\Application::APP_ID, OCA\SfxonTrain\AppInfo\Application::APP_ID . '-adminTrainContent');

?>

<div id="sfxontrain-adminTrainContent"></div>