#!/usr/bin/env php
<?php

use App\AppKernel;
use Symfony\Component\Console\Application;

require_once __DIR__ . "/../vendor/autoload.php";

$kernel = new AppKernel("cli", false);
$kernel->boot();

$container = $kernel->getContainer();
$application = $container->get(Application::class);
assert($application instanceof Application);
$application->setName('gog-downloader');
$application->setVersion(
    file_exists(__DIR__ . '/appversion')
    ? file_get_contents(__DIR__ . '/appversion')
    : 'dev-version',
);

$application->run();
