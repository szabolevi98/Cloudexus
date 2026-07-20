<?php

use Cloudexus\Controller\CashVoucherController;
use Cloudexus\Controller\CategoryController;
use Cloudexus\Controller\CustomerGroupController;
use Cloudexus\Controller\DashboardController;
use Cloudexus\Controller\IncomingInvoiceController;
use Cloudexus\Controller\InvoiceController;
use Cloudexus\Controller\LocationController;
use Cloudexus\Controller\LoginController;
use Cloudexus\Controller\OrderController;
use Cloudexus\Controller\PartnerController;
use Cloudexus\Controller\ParameterNameController;
use Cloudexus\Controller\PricingController;
use Cloudexus\Controller\ProductController;
use Cloudexus\Controller\ProfileController;
use Cloudexus\Controller\PurchaseOrderController;
use Cloudexus\Controller\SettingsController;
use Cloudexus\Controller\UnitController;
use Cloudexus\Controller\StockController;
use Cloudexus\Controller\StocktakingController;
use Cloudexus\Controller\TodoController;
use Cloudexus\Controller\UserController;
use Cloudexus\Controller\WarehouseController;
use Cloudexus\Core\Config;
use Cloudexus\Core\Csrf;
use Cloudexus\Core\Router;
use Cloudexus\Core\Session;

require dirname(__DIR__) . '/vendor/autoload.php';

Config::load(dirname(__DIR__) . '/config/config.ini');
date_default_timezone_set(Config::get('app.timezone', 'Europe/Budapest'));

// Never leak PHP notices/warnings into responses (they would corrupt JSON,
// CSV downloads and redirects). Everything is logged to var/log instead.
ini_set('display_errors', '0');
ini_set('html_errors', '0');
error_reporting(E_ALL);

set_error_handler(function (int $level, string $message, string $file, int $line): bool {
    \Cloudexus\Core\Logger::error($message, ['file' => $file, 'line' => $line]);
    return true;
});

set_exception_handler(function (\Throwable $e): void {
    \Cloudexus\Core\Logger::error('Uncaught: ' . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
    http_response_code(500);
    echo 'Váratlan hiba történt. Kérjük, próbáld újra később.';
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

$router->get('/products/export', fn() => (new ProductController())->export());
$router->get('/products/search', fn() => (new ProductController())->search());
$router->get('/partners/export', fn() => (new PartnerController())->export());
$router->get('/categories/search', fn() => (new CategoryController())->search());
$router->get('/param-names/search', fn() => (new ParameterNameController())->search());
$router->get('/pricing/effective', fn() => (new PricingController())->effective());

$router->post('/products/{id}/images/{imageId}/delete', fn($id, $imageId) => (new ProductController())->deleteImage((int) $id, (int) $imageId));
$router->post('/products/{id}/images/{imageId}/primary', fn($id, $imageId) => (new ProductController())->setPrimaryImage((int) $id, (int) $imageId));

registerCrud($router, '/users', UserController::class);
registerCrud($router, '/categories', CategoryController::class);
registerCrud($router, '/products', ProductController::class);
registerCrud($router, '/partners', PartnerController::class);
$router->get('/partners/{id}', fn($id) => (new PartnerController())->show((int) $id));
$router->post('/partners/{id}/activities', fn($id) => (new PartnerController())->addActivity((int) $id));
$router->post('/partners/{id}/activities/{aid}/delete', fn($id, $aid) => (new PartnerController())->deleteActivity((int) $id, (int) $aid));
$router->post('/partners/{id}/addresses', fn($id) => (new PartnerController())->addAddress((int) $id));
$router->post('/partners/{id}/addresses/{aid}', fn($id, $aid) => (new PartnerController())->updateAddress((int) $id, (int) $aid));
$router->post('/partners/{id}/addresses/{aid}/delete', fn($id, $aid) => (new PartnerController())->deleteAddress((int) $id, (int) $aid));
registerCrud($router, '/warehouses', WarehouseController::class);
registerCrud($router, '/locations', LocationController::class);

$router->get('/settings/company', fn() => (new SettingsController())->company());
$router->post('/settings/company', fn() => (new SettingsController())->companyUpdate());

$router->get('/parameter-names', fn() => (new ParameterNameController())->list());
$router->post('/parameter-names/create', fn() => (new ParameterNameController())->create());
$router->post('/parameter-names/{id}', fn($id) => (new ParameterNameController())->update((int) $id));
$router->post('/parameter-names/{id}/delete', fn($id) => (new ParameterNameController())->delete((int) $id));

$router->get('/units', fn() => (new UnitController())->list());
$router->post('/units/create', fn() => (new UnitController())->create());
$router->post('/units/{id}', fn($id) => (new UnitController())->update((int) $id));
$router->post('/units/{id}/delete', fn($id) => (new UnitController())->delete((int) $id));

$router->get('/customer-groups', fn() => (new CustomerGroupController())->list());
$router->post('/customer-groups/create', fn() => (new CustomerGroupController())->create());
$router->post('/customer-groups/{id}', fn($id) => (new CustomerGroupController())->update((int) $id));
$router->post('/customer-groups/{id}/delete', fn($id) => (new CustomerGroupController())->delete((int) $id));

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

$router->get('/stocktaking', fn() => (new StocktakingController())->list());
$router->get('/stocktaking/create', fn() => (new StocktakingController())->createForm());
$router->post('/stocktaking/create', fn() => (new StocktakingController())->create());
$router->get('/stocktaking/{id}', fn($id) => (new StocktakingController())->show((int) $id));

$router->get('/todos', fn() => (new TodoController())->list());
$router->post('/todos/create', fn() => (new TodoController())->create());
$router->post('/todos/{id}/toggle', fn($id) => (new TodoController())->toggle((int) $id));
$router->post('/todos/{id}/delete', fn($id) => (new TodoController())->delete((int) $id));

$router->get('/orders', fn() => (new OrderController())->list());
$router->get('/orders/create', fn() => (new OrderController())->createForm());
$router->post('/orders/create', fn() => (new OrderController())->create());
$router->get('/orders/{id}', fn($id) => (new OrderController())->show((int) $id));
$router->post('/orders/{id}/cancel', fn($id) => (new OrderController())->cancel((int) $id));
$router->post('/orders/{id}/delete', fn($id) => (new OrderController())->delete((int) $id));

$router->get('/invoices', fn() => (new InvoiceController())->list());
$router->get('/invoices/export', fn() => (new InvoiceController())->export());
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
