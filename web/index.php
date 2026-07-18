<?php

use Cloudexus\Controller\CategoryController;
use Cloudexus\Controller\DashboardController;
use Cloudexus\Controller\LoginController;
use Cloudexus\Controller\PartnerController;
use Cloudexus\Controller\ProductController;
use Cloudexus\Controller\UserController;
use Cloudexus\Core\Config;
use Cloudexus\Core\Router;
use Cloudexus\Core\Session;

require dirname(__DIR__) . '/vendor/autoload.php';

Config::load(dirname(__DIR__) . '/config/config.ini');
date_default_timezone_set(Config::get('app.timezone', 'Europe/Budapest'));

set_error_handler(function (int $level, string $message, string $file, int $line): bool {
    \Cloudexus\Core\Logger::error($message, ['file' => $file, 'line' => $line]);
    return false;
});

Session::start();

$router = new Router();

/**
 * Registers the standard list / create / edit / update / delete route set
 * for a resource controller, e.g. registerCrud($router, '/products', ProductController::class).
 */
function registerCrud(Router $router, string $basePath, string $controllerClass): void
{
    $router->get($basePath, fn() => (new $controllerClass())->list());
    $router->get($basePath . '/create', fn() => (new $controllerClass())->createForm());
    $router->post($basePath . '/create', fn() => (new $controllerClass())->create());
    $router->get($basePath . '/{id}/edit', fn($id) => (new $controllerClass())->editForm((int) $id));
    $router->post($basePath . '/{id}', fn($id) => (new $controllerClass())->update((int) $id));
    $router->post($basePath . '/{id}/delete', fn($id) => (new $controllerClass())->delete((int) $id));
}

$router->get('/', fn() => header('Location: ' . Config::get('app.base_url') . '/login'));
$router->get('/login', fn() => (new LoginController())->show());
$router->post('/login', fn() => (new LoginController())->submit());
$router->get('/logout', fn() => (new LoginController())->logout());

$router->get('/dashboard', fn() => (new DashboardController())->show());

registerCrud($router, '/users', UserController::class);
registerCrud($router, '/categories', CategoryController::class);
registerCrud($router, '/products', ProductController::class);
registerCrud($router, '/partners', PartnerController::class);

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
