<?php

use Cloudexus\Controller\CashVoucherController;
use Cloudexus\Controller\CategoryController;
use Cloudexus\Controller\DashboardController;
use Cloudexus\Controller\IncomingInvoiceController;
use Cloudexus\Controller\InvoiceController;
use Cloudexus\Controller\LoginController;
use Cloudexus\Controller\OrderController;
use Cloudexus\Controller\PartnerController;
use Cloudexus\Controller\ProductController;
use Cloudexus\Controller\ProfileController;
use Cloudexus\Controller\PurchaseOrderController;
use Cloudexus\Controller\StockController;
use Cloudexus\Controller\UserController;
use Cloudexus\Controller\WarehouseController;
use Cloudexus\Core\Config;
use Cloudexus\Core\Csrf;
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !Csrf::validate($_POST['_token'] ?? null)) {
    http_response_code(403);
    exit('Érvénytelen vagy lejárt munkamenet. Frissítsd az oldalt, majd próbáld újra.');
}

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

$router->get('/profile', fn() => (new ProfileController())->show());
$router->post('/profile', fn() => (new ProfileController())->update());

registerCrud($router, '/users', UserController::class);
registerCrud($router, '/categories', CategoryController::class);
registerCrud($router, '/products', ProductController::class);
registerCrud($router, '/partners', PartnerController::class);
registerCrud($router, '/warehouses', WarehouseController::class);

$router->get('/stock', fn() => (new StockController())->overview());
$router->get('/stock/in', fn() => (new StockController())->inList());
$router->post('/stock/in/create', fn() => (new StockController())->inCreate());
$router->get('/stock/out', fn() => (new StockController())->outList());
$router->post('/stock/out/create', fn() => (new StockController())->outCreate());
$router->get('/stock/transfer', fn() => (new StockController())->transferForm());
$router->post('/stock/transfer', fn() => (new StockController())->transferCreate());
$router->get('/stock/barcode', fn() => (new StockController())->barcodeForm());
$router->get('/stock/barcode/lookup', fn() => (new StockController())->barcodeLookup());
$router->post('/stock/barcode', fn() => (new StockController())->barcodeSubmit());

$router->get('/orders', fn() => (new OrderController())->list());
$router->get('/orders/create', fn() => (new OrderController())->createForm());
$router->post('/orders/create', fn() => (new OrderController())->create());
$router->get('/orders/{id}', fn($id) => (new OrderController())->show((int) $id));
$router->post('/orders/{id}/cancel', fn($id) => (new OrderController())->cancel((int) $id));
$router->post('/orders/{id}/delete', fn($id) => (new OrderController())->delete((int) $id));

$router->get('/invoices', fn() => (new InvoiceController())->list());
$router->get('/invoices/create', fn() => (new InvoiceController())->createForm());
$router->post('/invoices/create', fn() => (new InvoiceController())->create());
$router->get('/invoices/{id}', fn($id) => (new InvoiceController())->show((int) $id));
$router->get('/invoices/{id}/print', fn($id) => (new InvoiceController())->printView((int) $id));
$router->post('/invoices/{id}/mark-paid', fn($id) => (new InvoiceController())->markPaid((int) $id));
$router->post('/invoices/{id}/cancel', fn($id) => (new InvoiceController())->cancel((int) $id));
$router->post('/invoices/{id}/delete', fn($id) => (new InvoiceController())->delete((int) $id));

$router->get('/purchase-orders', fn() => (new PurchaseOrderController())->list());
$router->get('/purchase-orders/create', fn() => (new PurchaseOrderController())->createForm());
$router->post('/purchase-orders/create', fn() => (new PurchaseOrderController())->create());
$router->get('/purchase-orders/{id}', fn($id) => (new PurchaseOrderController())->show((int) $id));
$router->post('/purchase-orders/{id}/cancel', fn($id) => (new PurchaseOrderController())->cancel((int) $id));
$router->post('/purchase-orders/{id}/delete', fn($id) => (new PurchaseOrderController())->delete((int) $id));

$router->get('/incoming-invoices', fn() => (new IncomingInvoiceController())->list());
$router->get('/incoming-invoices/create', fn() => (new IncomingInvoiceController())->createForm());
$router->post('/incoming-invoices/create', fn() => (new IncomingInvoiceController())->create());
$router->get('/incoming-invoices/{id}', fn($id) => (new IncomingInvoiceController())->show((int) $id));
$router->post('/incoming-invoices/{id}/mark-paid', fn($id) => (new IncomingInvoiceController())->markPaid((int) $id));
$router->post('/incoming-invoices/{id}/cancel', fn($id) => (new IncomingInvoiceController())->cancel((int) $id));
$router->post('/incoming-invoices/{id}/delete', fn($id) => (new IncomingInvoiceController())->delete((int) $id));

$router->get('/cash', fn() => (new CashVoucherController())->list());
$router->get('/cash/create', fn() => (new CashVoucherController())->createForm());
$router->post('/cash/create', fn() => (new CashVoucherController())->create());
$router->post('/cash/{id}/delete', fn($id) => (new CashVoucherController())->delete((int) $id));

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
