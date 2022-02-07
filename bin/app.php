#!/usr/bin/env php
<?php

use App\AppKernel;
use Symfony\Component\Console\Application;

require_once __DIR__ . "/../vendor/autoload.php";

$kernel = new AppKernel("cli", false);
$kernel->boot();

$container = $kernel->getContainer();
$application = $container->get(Application::class);

$application->run();
