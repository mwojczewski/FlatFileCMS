<?php

declare(strict_types=1);

use FlatFileCms\Core\Application;
use FlatFileCms\Http\Request;

/** @var Application $application */
$application = require dirname(__DIR__) . '/bootstrap/app.php';
$application->handle(Request::fromGlobals())->send();
