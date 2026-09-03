<?php
/**
 * University Management System - front controller.
 *
 * Every request that is not a real file on disk is routed through here
 * by public/.htaccess.
 */
declare(strict_types=1);

define('UMS_START', microtime(true));

$basePath = dirname(__DIR__);

require_once $basePath . '/app/Core/App.php';

$app = new App\Core\App($basePath);

// Route definitions.
$router = $app->router();
require $basePath . '/routes/web.php';

$app->run();
