<?php

use Cloudexus\Controller\DashboardController;
use Cloudexus\Controller\LoginController;
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

$router->get('/', fn() => header('Location: ' . Config::get('app.base_url') . '/login'));
$router->get('/login', fn() => (new LoginController())->show());
$router->post('/login', fn() => (new LoginController())->submit());
$router->get('/logout', fn() => (new LoginController())->logout());

$router->get('/dashboard', fn() => (new DashboardController())->show());

$router->get('/users', fn() => (new UserController())->list());
$router->get('/users/create', fn() => (new UserController())->createForm());
$router->post('/users/create', fn() => (new UserController())->create());
$router->get('/users/{id}/edit', fn($id) => (new UserController())->editForm((int) $id));
$router->post('/users/{id}', fn($id) => (new UserController())->update((int) $id));
$router->post('/users/{id}/delete', fn($id) => (new UserController())->delete((int) $id));

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
