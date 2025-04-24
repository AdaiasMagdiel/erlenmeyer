<?php

use AdaiasMagdiel\Erlenmeyer\App;

it('can instantiate an Erlenmeyer application', function () {
    new App();
    expect(true)->toBeTrue();
});

it('can create an Erlenmeyer application with logs directory', function () {
    $logDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "app_test" . DIRECTORY_SEPARATOR;
    $logFile = $logDir . DIRECTORY_SEPARATOR . 'info.log';

    $app = new App(logDir: $logDir);

    expect(is_dir($logDir))->toBeTrue();
    expect(is_file($logFile))->toBeTrue();
    expect(filesize($logFile) > 0)->toBeTrue(0);

    unlink($logFile);
    rmdir($logDir);
});
