<?php

use Cloudexus\Controller\Api\CategoryApiController;
use Cloudexus\Controller\Api\CustomerGroupApiController;
use Cloudexus\Controller\Api\InvoiceApiController;
use Cloudexus\Controller\Api\OrderApiController;
use Cloudexus\Controller\Api\ParameterNameApiController;
use Cloudexus\Controller\Api\PartnerApiController;
use Cloudexus\Controller\Api\PricingApiController;
use Cloudexus\Controller\Api\ProductApiController;
use Cloudexus\Controller\Api\StockApiController;
use Cloudexus\Controller\Api\UnitApiController;
use Cloudexus\Controller\Api\WarehouseApiController;
use Cloudexus\Controller\ApiUserController;
use Cloudexus\Controller\CashVoucherController;
use Cloudexus\Controller\CategoryController;
use Cloudexus\Controller\CustomerGroupController;
use Cloudexus\Controller\DashboardController;
use Cloudexus\Controller\IncomingInvoiceController;
use Cloudexus\Controller\InvoiceController;
use Cloudexus\Controller\LocaleController;
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

// UI language: the user's saved choice (cx_locale cookie) or the config default.
\Cloudexus\Core\Lang::init(
    (string) Config::get('app.default_locale', 'hu'),
    array_map('trim', explode(',', (string) Config::get('app.available_locales', 'hu,en'))),
    $_COOKIE['cx_locale'] ?? null
);

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
    echo \Cloudexus\Core\Lang::get('errors.unexpected');
});

// The REST API (/api/*) authenticates with a bearer token, not the session
// cookie, so it must bypass session start and the form CSRF gate.
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$scriptBase = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
if ($scriptBase !== '' && str_starts_with($requestPath, $scriptBase)) {
    $requestPath = substr($requestPath, strlen($scriptBase));
}
$isApiRequest = $requestPath === '/api' || str_starts_with($requestPath, '/api/');

if (!$isApiRequest) {
    Session::start();

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !Csrf::validate($_POST['_token'] ?? null)) {
        http_response_code(403);
        exit(\Cloudexus\Core\Lang::get('errors.invalid_session'));
    }
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

$router->get('/lang/{code}', fn($code) => (new LocaleController())->switch($code));

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

$router->get('/api-docs', fn() => (new ApiUserController())->docs());
$router->get('/api-users', fn() => (new ApiUserController())->list());
$router->post('/api-users/create', fn() => (new ApiUserController())->create());
$router->post('/api-users/{id}', fn($id) => (new ApiUserController())->update((int) $id));
$router->post('/api-users/{id}/toggle', fn($id) => (new ApiUserController())->toggle((int) $id));
$router->post('/api-users/{id}/regenerate', fn($id) => (new ApiUserController())->regenerate((int) $id));
$router->post('/api-users/{id}/delete', fn($id) => (new ApiUserController())->delete((int) $id));

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

// ---------------------------------------------------------------------------
// REST API (/api/*) — bearer-token auth, JSON. Read-only catalog, full CRUD on
// partners and orders. See web/API.md for the full documentation.
// ---------------------------------------------------------------------------
$router->get('/api/products', fn() => (new ProductApiController())->index());
$router->get('/api/products/sku/{sku}', fn($sku) => (new ProductApiController())->showBySku(rawurldecode($sku)));
$router->get('/api/products/{id}', fn($id) => (new ProductApiController())->show((int) $id));

$router->get('/api/categories', fn() => (new CategoryApiController())->index());
$router->get('/api/categories/{id}', fn($id) => (new CategoryApiController())->show((int) $id));

$router->get('/api/parameter-names', fn() => (new ParameterNameApiController())->index());
$router->get('/api/units', fn() => (new UnitApiController())->index());

$router->get('/api/customer-groups', fn() => (new CustomerGroupApiController())->index());
$router->get('/api/customer-groups/{id}', fn($id) => (new CustomerGroupApiController())->show((int) $id));

$router->get('/api/warehouses', fn() => (new WarehouseApiController())->index());
$router->get('/api/stock', fn() => (new StockApiController())->index());
$router->get('/api/pricing/effective', fn() => (new PricingApiController())->effective());

$router->get('/api/invoices', fn() => (new InvoiceApiController())->index());
$router->get('/api/invoices/{id}', fn($id) => (new InvoiceApiController())->show((int) $id));

$router->get('/api/partners', fn() => (new PartnerApiController())->index());
$router->get('/api/partners/tax/{tax}', fn($tax) => (new PartnerApiController())->showByTax(rawurldecode($tax)));
$router->put('/api/partners/tax/{tax}', fn($tax) => (new PartnerApiController())->upsertByTax(rawurldecode($tax)));
$router->get('/api/partners/{id}', fn($id) => (new PartnerApiController())->show((int) $id));
$router->post('/api/partners', fn() => (new PartnerApiController())->create());
$router->put('/api/partners/{id}', fn($id) => (new PartnerApiController())->update((int) $id));
$router->delete('/api/partners/{id}', fn($id) => (new PartnerApiController())->delete((int) $id));

$router->get('/api/orders', fn() => (new OrderApiController())->index());
$router->get('/api/orders/{id}', fn($id) => (new OrderApiController())->show((int) $id));
$router->post('/api/orders', fn() => (new OrderApiController())->create());
$router->put('/api/orders/{id}', fn($id) => (new OrderApiController())->update((int) $id));
$router->delete('/api/orders/{id}', fn($id) => (new OrderApiController())->delete((int) $id));

$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
