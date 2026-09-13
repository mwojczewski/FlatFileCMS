<?php

declare(strict_types=1);

use FlatFileCms\Core\Application;
use FlatFileCms\Http\Request;

$projectRoot = dirname(__DIR__);
require "{$projectRoot}/vendor/autoload.php";

$request = Request::fromGlobals();
/** @var Application $application */
$application = require "{$projectRoot}/bootstrap/app.php";
$application->handle($request)->send();
