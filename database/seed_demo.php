<?php

/**
 * Fills the database with lots of realistic Hungarian demo/test data across
 * every module, so dashboards, charts, and lists never look empty.
 *
 * Truncates all business tables (not users) and rebuilds them from scratch,
 * so it's safe to re-run any time you want a fresh demo dataset.
 *
 * Usage: php database/seed_demo.php
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use Cloudexus\Core\Config;
use Cloudexus\Core\DatabaseConnection;
use Cloudexus\Model\Cash\CashVoucherModel;
use Cloudexus\Model\Core\CategoryModel;
use Cloudexus\Model\Core\CustomerGroupModel;
use Cloudexus\Model\Core\PartnerModel;
use Cloudexus\Model\Core\ProductModel;
use Cloudexus\Model\Core\StockMovementModel;
use Cloudexus\Model\Core\StocktakingModel;
use Cloudexus\Model\Core\WarehouseModel;
use Cloudexus\Model\Purchasing\IncomingInvoiceModel;
use Cloudexus\Model\Purchasing\PurchaseOrderModel;
use Cloudexus\Model\Sales\InvoiceModel;
use Cloudexus\Model\Sales\OrderModel;

Config::load(dirname(__DIR__) . '/config/config.ini');
$pdo = DatabaseConnection::get();

function randDate(int $daysAgoMax, int $daysAgoMin = 0): string
{
    return date('Y-m-d', strtotime('-' . rand($daysAgoMin, $daysAgoMax) . ' days'));
}

echo "Truncating business tables...\n";
$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach ([
    'partner_activities', 'todos', 'warehouse_locations', 'stocktaking_items', 'stocktakings',
    'cash_vouchers', 'incoming_invoice_items', 'incoming_invoices',
    'purchase_order_items', 'purchase_orders', 'invoice_items', 'invoices',
    'order_items', 'orders', 'stock_movements', 'product_group_prices', 'products', 'categories',
    'partners', 'warehouses', 'customer_groups',
] as $table) {
    $pdo->exec("TRUNCATE TABLE $table");
}
$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

$categoryModel = new CategoryModel();
$customerGroupModel = new CustomerGroupModel();
$productModel = new ProductModel();
$partnerModel = new PartnerModel();
$warehouseModel = new WarehouseModel();
$stockModel = new StockMovementModel();
$orderModel = new OrderModel();
$invoiceModel = new InvoiceModel();
$poModel = new PurchaseOrderModel();
$incomingInvoiceModel = new IncomingInvoiceModel();
$cashModel = new CashVoucherModel();

// ---------------------------------------------------------------------------
// Categories + products
// ---------------------------------------------------------------------------
echo "Seeding categories and products...\n";

$catalog = [
    'Kerékpár' => [
        ['Városi kerékpár 26"', 89900], ['Városi kerékpár 28"', 99900],
        ['Hegyikerékpár 27.5"', 149900], ['Elektromos kerékpár', 599900],
        ['Gyerek kerékpár 20"', 54900], ['Országúti kerékpár', 349900],
    ],
    'Elektronika' => [
        ['Vezeték nélküli egér', 7990], ['Mechanikus billentyűzet', 24990],
        ['USB-C töltő 65W', 8990], ['Bluetooth fejhallgató', 19990],
        ['Powerbank 20000mAh', 14990], ['24" monitor', 59990],
    ],
    'Irodai kellék' => [
        ['A4 nyomtatópapír (500 lap)', 2490], ['Golyóstoll (12 db)', 1990],
        ['Iratrendező', 990], ['Post-it jegyzettömb', 690],
        ['Irattartó doboz', 1490], ['Vonalkód nyomtató', 89990],
    ],
    'Élelmiszer' => [
        ['Bio zabpehely 1kg', 990], ['Extra szűz olívaolaj 1L', 3490],
        ['Kávékapszula (30 db)', 4290], ['Tea válogatás', 1890],
        ['Müzliszelet (24 db)', 3290], ['Ásványvíz 1.5L (6 db)', 990],
    ],
    'Sportfelszerelés' => [
        ['Futócipő', 24990], ['Jóga szőnyeg', 6990],
        ['Súlyzókészlet 20kg', 34990], ['Fitness gumiszalag szett', 4990],
        ['Kerékpáros sisak', 12990], ['Sportkulacs 750ml', 2990],
    ],
    'Bútor' => [
        ['Irodai szék', 39990], ['Íróasztal 120cm', 49990],
        ['Könyvespolc', 29990], ['Ruhásszekrény', 89990],
        ['Éjjeliszekrény', 19990], ['Konferenciaasztal', 129990],
    ],
    'Játék' => [
        ['Építőjáték 500db', 14990], ['Társasjáték családi', 9990],
        ['Puzzle 1000db', 4990], ['Távirányítós autó', 12990],
        ['Rajzkészlet', 3990], ['Plüss figura', 3490],
    ],
    'Kertészet' => [
        ['Kerti slag 20m', 6990], ['Fűnyíró elektromos', 79990],
        ['Kerti szerszámkészlet', 17990], ['Virágföld 20L', 1990],
        ['Öntözőkanna 10L', 2490], ['Kerti bútor szett', 149990],
    ],
];

$categoryIds = [];
$products = [];
$productIdsByParent = []; // parentName => [productId, ...]
$skuCounter = 1;

foreach ($catalog as $categoryName => $items) {
    $categoryIds[$categoryName] = $categoryModel->create(['name' => $categoryName, 'parent_id' => null]);

    $units = ['db', 'doboz', 'csomag', 'szett', 'karton'];
    foreach ($items as [$name, $price]) {
        $sku = 'PRD-' . str_pad((string) $skuCounter++, 4, '0', STR_PAD_LEFT);
        // Minden negyedik termékre akciós ár kerül (10-25% kedvezmény).
        $salePrice = rand(1, 100) <= 25 ? round($price * (1 - rand(10, 25) / 100), -1) : null;
        $productId = $productModel->create([
            'sku' => $sku,
            // EAN-13-szerű demo vonalkód (599 = magyar prefix)
            'barcode' => '599' . str_pad((string) rand(0, 9999999999), 10, '0', STR_PAD_LEFT),
            'name' => $name,
            'short_description' => $name . ' — kiváló minőségű termék raktárról.',
            'description' => "A(z) $name részletes leírása. Tartós, megbízható termék, amely megfelel a piaci elvárásoknak. "
                . 'Ideális választás vállalati és lakossági felhasználásra egyaránt.',
            'category_id' => $categoryIds[$categoryName],
            'unit' => $units[array_rand($units)],
            'price' => $price,
            'sale_price' => $salePrice ?? '',
            'vat_rate' => 27,
            // A termékek felénél riasztási szint is van, hogy az alacsony készlet widget éljen.
            'min_stock' => rand(0, 1) ? rand(50, 250) : 0,
            'width_mm' => rand(50, 800),
            'height_mm' => rand(50, 800),
            'depth_mm' => rand(50, 600),
            'weight_g' => rand(100, 20000),
            'is_active' => 1,
            'is_webshop' => rand(1, 100) <= 85 ? 1 : 0,
            'attr_name' => ['Gyártó', 'Garancia', 'Származási ország'],
            'attr_value' => [
                ['Bosch', 'Makita', 'Samsung', 'Generic', 'Xiaomi'][array_rand([0, 1, 2, 3, 4])],
                [12, 24, 36][array_rand([0, 1, 2])] . ' hónap',
                ['Magyarország', 'Németország', 'Kína', 'Lengyelország'][array_rand([0, 1, 2, 3])],
            ],
        ]);
        $products[] = ['id' => $productId, 'price' => $price];
        $productIdsByParent[$categoryName][] = $productId;
    }
}

// Néhány alkategória, hogy a kategória-útvonal (Szülő > Gyerek) megjelenjen.
$subcategories = [
    'Kerékpár' => ['Városi kerékpár', 'Hegyi kerékpár', 'Elektromos kerékpár'],
    'Elektronika' => ['Számítástechnika', 'Audió eszközök'],
    'Bútor' => ['Irodabútor', 'Otthon'],
];
$subCount = 0;
$subIdsByParent = []; // parentName => [childCategoryId, ...]
foreach ($subcategories as $parentName => $children) {
    foreach ($children as $childName) {
        $childId = $categoryModel->create(['name' => $childName, 'parent_id' => $categoryIds[$parentName]]);
        $subIdsByParent[$parentName][] = $childId;
        // Egy-két unoka szint is, hogy többszintű útvonal is legyen.
        if ($childName === 'Számítástechnika') {
            $categoryModel->create(['name' => 'Perifériák', 'parent_id' => $childId]);
            $subCount++;
        }
        $subCount++;
    }
}

// Termékek szétosztása az alkategóriákba, hogy a Top kategóriák változatosabb
// legyen (pl. külön "Kerékpár > Városi kerékpár" sor is szerepeljen).
$reassignStmt = $pdo->prepare('UPDATE products SET category_id = :cat WHERE id = :id');
foreach ($subIdsByParent as $parentName => $childIds) {
    foreach ($productIdsByParent[$parentName] ?? [] as $index => $productId) {
        // A szülő termékeinek nagy részét egy-egy alkategóriába soroljuk,
        // néhányat viszont a szülőn hagyunk.
        if ($index === 0) {
            continue;
        }
        $reassignStmt->execute(['cat' => $childIds[array_rand($childIds)], 'id' => $productId]);
    }
}

echo count($products) . " products in " . (count($categoryIds) + $subCount) . " categories (incl. subcategories).\n";

// ---------------------------------------------------------------------------
// Customer groups
// ---------------------------------------------------------------------------
echo "Seeding customer groups...\n";

$customerGroupIds = [];
foreach ([
    ['Viszonteladó', 'Nagy tételben rendelő kereskedő partnerek, kedvezményes árakkal.'],
    ['VIP', 'Kiemelt, hosszú távú partnerek egyedi árakkal.'],
    ['Nagykereskedő', 'Nagykereskedelmi partnerek, tömeges rendelésekhez.'],
] as [$name, $description]) {
    $customerGroupIds[$name] = $customerGroupModel->create(['name' => $name, 'description' => $description]);
}

// Néhány terméknek vevőcsoportos fix ára is van (a vevőcsoport-listánk minden csoportja).
$groupPriceCount = 0;
foreach ($products as $product) {
    if (rand(1, 100) > 20) {
        continue;
    }
    $groupIds = [];
    $groupPrices = [];
    $groupSalePrices = [];
    foreach ($customerGroupIds as $groupId) {
        if (rand(0, 1) === 0) {
            continue;
        }
        $groupPrice = round($product['price'] * (1 - rand(5, 20) / 100), -1);
        $groupIds[] = $groupId;
        $groupPrices[] = $groupPrice;
        $groupSalePrices[] = rand(1, 100) <= 30 ? round($groupPrice * (1 - rand(5, 15) / 100), -1) : '';
        $groupPriceCount++;
    }
    if ($groupIds) {
        $productModel->saveGroupPrices($product['id'], $groupIds, $groupPrices, $groupSalePrices);
    }
}
echo "$groupPriceCount vevőcsoport-ár sor.\n";

// ---------------------------------------------------------------------------
// Partners
// ---------------------------------------------------------------------------
echo "Seeding partners...\n";

$customerNames = [
    'Zöldkert Kft.', 'Bicikli World Kft.', 'Nagy Élelmiszer Zrt.', 'Digitál Pont Kft.',
    'Otthon Áruház Kft.', 'Sport Élet Bt.', 'Iroda Center Kft.', 'Kreatív Játék Kft.',
    'Napsugár Óvoda', 'Kelemen Kereskedés',
];
$supplierNames = [
    'Global Import Kft.', 'EuroTrade Zrt.', 'Beszállító Plus Kft.', 'KerítésTech Kft.', 'AgroForrás Kft.',
];
$bothNames = ['Multi Trade Kft.', 'Regionális Nagyker Zrt.'];

$partners = ['customer' => [], 'supplier' => []];
$customerGroupNames = array_keys($customerGroupIds);

foreach ($customerNames as $i => $name) {
    // Minden harmadik vevő tartozik valamelyik vevőcsoportba, a többi sima áron vásárol.
    $groupId = $i % 3 === 0 ? $customerGroupIds[$customerGroupNames[array_rand($customerGroupNames)]] : null;

    $id = $partnerModel->create([
        'type' => 'customer', 'customer_group_id' => $groupId, 'name' => $name,
        'tax_number' => sprintf('%08d-1-%02d', rand(10000000, 99999999), rand(1, 44)),
        'email' => 'info@' . strtolower(preg_replace('/[^a-z0-9]/i', '', $name)) . '.hu',
        'phone' => '+36 30 ' . rand(100, 999) . ' ' . rand(1000, 9999),
        'address' => 'Budapest, Példa utca ' . ($i + 1) . '.',
        'is_active' => 1,
    ]);
    $partners['customer'][] = $id;
}

foreach ($supplierNames as $i => $name) {
    $id = $partnerModel->create([
        'type' => 'supplier', 'name' => $name,
        'tax_number' => sprintf('%08d-2-%02d', rand(10000000, 99999999), rand(1, 44)),
        'email' => 'sales@' . strtolower(preg_replace('/[^a-z0-9]/i', '', $name)) . '.hu',
        'phone' => '+36 20 ' . rand(100, 999) . ' ' . rand(1000, 9999),
        'address' => 'Debrecen, Ipar körút ' . ($i + 1) . '.',
        'is_active' => 1,
    ]);
    $partners['supplier'][] = $id;
}

foreach ($bothNames as $i => $name) {
    $id = $partnerModel->create([
        'type' => 'both', 'name' => $name,
        'tax_number' => sprintf('%08d-2-%02d', rand(10000000, 99999999), rand(1, 44)),
        'email' => 'kapcsolat@' . strtolower(preg_replace('/[^a-z0-9]/i', '', $name)) . '.hu',
        'phone' => '+36 70 ' . rand(100, 999) . ' ' . rand(1000, 9999),
        'address' => 'Szeged, Nagyker utca ' . ($i + 1) . '.',
        'is_active' => 1,
    ]);
    $partners['customer'][] = $id;
    $partners['supplier'][] = $id;
}

echo count($partners['customer']) . " customer-capable, " . count($partners['supplier']) . " supplier-capable partners.\n";

// ---------------------------------------------------------------------------
// Warehouses
// ---------------------------------------------------------------------------
echo "Seeding warehouses...\n";

$warehouseIds = [];
foreach ([
    ['Központi raktár', 'Budapest, Raktár utca 1.'],
    ['Debreceni telephely', 'Debrecen, Logisztikai park 4.'],
    ['Szegedi raktár', 'Szeged, Ipari zóna 8.'],
] as [$name, $address]) {
    $warehouseIds[] = $warehouseModel->create(['name' => $name, 'address' => $address, 'is_active' => 1]);
}

// Tárhelyek / polcok raktáranként (sor A-C, állvány 1-3, polc 1-4)
$locationStmt = $pdo->prepare(
    'INSERT INTO warehouse_locations (warehouse_id, code, name, is_active, created_at) VALUES (:wid, :code, :name, 1, NOW())'
);
$locationCount = 0;
$locationsByWarehouse = []; // whId => [locationId, ...]
foreach ($warehouseIds as $whId) {
    foreach (['A', 'B', 'C'] as $row) {
        foreach (range(1, 3) as $rack) {
            foreach (range(1, 4) as $shelf) {
                $code = sprintf('%s-%02d-%02d', $row, $rack, $shelf);
                $locationStmt->execute([
                    'wid' => $whId,
                    'code' => $code,
                    'name' => sprintf('%s sor, %d. állvány, %d. polc', $row, $rack, $shelf),
                ]);
                $locationsByWarehouse[$whId][] = (int) $pdo->lastInsertId();
                $locationCount++;
            }
        }
    }
}
echo "$locationCount warehouse locations.\n";

// Egy adott raktár véletlen tárhelye (a mozgások polcra könyveléséhez).
$randomLocation = function (int $whId) use ($locationsByWarehouse): ?int {
    $list = $locationsByWarehouse[$whId] ?? [];
    return $list ? $list[array_rand($list)] : null;
};

// ---------------------------------------------------------------------------
// Opening stock + random stock movements (last 60 days)
// ---------------------------------------------------------------------------
echo "Seeding stock movements...\n";

$balances = []; // "warehouseId-productId" => qty
$movementCount = 0;

foreach ($products as $product) {
    // Opening stock in the main warehouse, ~55-60 days ago.
    $openingQty = rand(40, 300);
    $stockModel->create([
        'warehouse_id' => $warehouseIds[0],
        'location_id' => $randomLocation($warehouseIds[0]),
        'product_id' => $product['id'],
        'type' => 'in',
        'quantity' => $openingQty,
        'note' => 'Nyitókészlet',
        'created_by' => null,
        'created_at' => randDate(60, 55) . ' 08:00:00',
    ]);
    $balances[$warehouseIds[0] . '-' . $product['id']] = $openingQty;
    $movementCount++;

    // A handful of extra in/out movements over the following weeks.
    for ($i = 0; $i < rand(2, 6); $i++) {
        $warehouseId = $warehouseIds[array_rand($warehouseIds)];
        $key = $warehouseId . '-' . $product['id'];
        $balance = $balances[$key] ?? 0;

        $type = ($balance > 10 && rand(0, 1)) ? 'out' : 'in';
        $qty = $type === 'in' ? rand(10, 80) : min($balance, rand(1, 20));

        if ($type === 'out' && $qty <= 0) {
            continue;
        }

        $stockModel->create([
            'warehouse_id' => $warehouseId,
            'location_id' => $randomLocation($warehouseId),
            'product_id' => $product['id'],
            'type' => $type,
            'quantity' => $qty,
            'note' => $type === 'in' ? 'Utánrendelés' : 'Kiszállítás',
            'created_by' => null,
            'created_at' => randDate(50, 1) . ' ' . sprintf('%02d:%02d:00', rand(8, 17), rand(0, 59)),
        ]);

        $balances[$key] = $balance + ($type === 'in' ? $qty : -$qty);
        $movementCount++;
    }
}

echo "$movementCount stock movements booked.\n";

// ---------------------------------------------------------------------------
// Sales: orders + invoices (+ some paid via cash vouchers)
// ---------------------------------------------------------------------------
echo "Seeding sales orders and invoices...\n";

$orderCount = 0;
$invoiceCount = 0;
$paidCount = 0;

for ($i = 0; $i < 130; $i++) {
    $orderDate = randDate(30, 0);
    $itemCount = rand(1, 6);
    $items = [];

    for ($j = 0; $j < $itemCount; $j++) {
        $product = $products[array_rand($products)];
        $items[] = [
            'product_id' => $product['id'],
            'quantity' => rand(1, 12),
            'unit_price' => round($product['price'] * (rand(95, 105) / 100)),
        ];
    }

    $orderId = $orderModel->create([
        'order_number' => $orderModel->nextOrderNumber(),
        'partner_id' => $partners['customer'][array_rand($partners['customer'])],
        'status' => 'confirmed',
        'order_date' => $orderDate,
        'created_by' => null,
    ], $items);
    $orderCount++;

    // ~75% of orders get invoiced.
    if (rand(1, 100) <= 75) {
        $order = $orderModel->findById($orderId);
        $dueDate = date('Y-m-d', strtotime($orderDate . ' +8 days'));

        $invoiceId = $invoiceModel->create([
            'invoice_number' => $invoiceModel->nextInvoiceNumber(),
            'order_id' => $orderId,
            'partner_id' => $order['partner_id'],
            'status' => 'unpaid',
            'issue_date' => $orderDate,
            'due_date' => $dueDate,
            'created_by' => null,
        ], array_map(fn($item) => [
            'product_id' => $item['product_id'],
            'quantity' => $item['quantity'],
            'unit_price' => $item['unit_price'],
        ], $order['items']));
        $invoiceCount++;

        // ~65% of invoices get paid via a cash voucher (bevétel + settlement).
        if (rand(1, 100) <= 65) {
            $cashModel->create([
                'voucher_number' => $cashModel->nextVoucherNumber(),
                'type' => 'bevetel',
                'amount' => $order['total_amount'],
                'partner_id' => $order['partner_id'],
                'invoice_id' => $invoiceId,
                'note' => 'Számla kiegyenlítése',
                'voucher_date' => date('Y-m-d', strtotime($dueDate . ' -' . rand(0, 6) . ' days')),
                'created_by' => null,
            ]);
            $paidCount++;
        }
    }
}

echo "$orderCount sales orders, $invoiceCount invoices ($paidCount paid via pénztár).\n";

// ---------------------------------------------------------------------------
// Purchasing: purchase orders + incoming invoices (auto stock-in)
// ---------------------------------------------------------------------------
echo "Seeding purchase orders and incoming invoices...\n";

$poCount = 0;
$incomingCount = 0;
$incomingPaidCount = 0;

for ($i = 0; $i < 45; $i++) {
    $orderDate = randDate(45, 0);
    $itemCount = rand(1, 4);
    $items = [];

    for ($j = 0; $j < $itemCount; $j++) {
        $product = $products[array_rand($products)];
        $items[] = [
            'product_id' => $product['id'],
            'quantity' => rand(10, 60),
            'unit_price' => round($product['price'] * (rand(55, 75) / 100)),
        ];
    }

    $poId = $poModel->create([
        'po_number' => $poModel->nextPoNumber(),
        'partner_id' => $partners['supplier'][array_rand($partners['supplier'])],
        'status' => 'confirmed',
        'order_date' => $orderDate,
        'created_by' => null,
    ], $items);
    $poCount++;

    // ~70% of purchase orders get an incoming invoice (with automatic stock-in).
    if (rand(1, 100) <= 70) {
        $po = $poModel->findById($poId);
        $dueDate = date('Y-m-d', strtotime($orderDate . ' +8 days'));

        $incomingId = $incomingInvoiceModel->create([
            'invoice_number' => $incomingInvoiceModel->nextInvoiceNumber(),
            'purchase_order_id' => $poId,
            'partner_id' => $po['partner_id'],
            'warehouse_id' => $warehouseIds[0],
            'issue_date' => $orderDate,
            'due_date' => $dueDate,
            'created_by' => null,
        ], array_map(fn($item) => [
            'product_id' => $item['product_id'],
            'quantity' => $item['quantity'],
            'unit_price' => $item['unit_price'],
        ], $po['items']));
        $incomingCount++;

        // ~50% of incoming invoices get paid via a cash voucher (kiadás + settlement).
        if (rand(1, 100) <= 50) {
            $cashModel->create([
                'voucher_number' => $cashModel->nextVoucherNumber(),
                'type' => 'kiadas',
                'amount' => $po['total_amount'],
                'partner_id' => $po['partner_id'],
                'incoming_invoice_id' => $incomingId,
                'note' => 'Beszállítói számla kiegyenlítése',
                'voucher_date' => date('Y-m-d', strtotime($dueDate . ' -' . rand(0, 6) . ' days')),
                'created_by' => null,
            ]);
            $incomingPaidCount++;
        }
    }
}

echo "$poCount purchase orders, $incomingCount incoming invoices ($incomingPaidCount paid via pénztár).\n";

// ---------------------------------------------------------------------------
// A few standalone cash vouchers (petty cash, unrelated to invoices).
// ---------------------------------------------------------------------------
echo "Seeding standalone cash vouchers...\n";

$standaloneNotes = ['Készpénzes vásárlás', 'Irodai apróbeszerzés', 'Üzemanyag', 'Postai költség', 'Takarítás'];

for ($i = 0; $i < 30; $i++) {
    $cashModel->create([
        'voucher_number' => $cashModel->nextVoucherNumber(),
        'type' => rand(0, 1) ? 'bevetel' : 'kiadas',
        'amount' => rand(5, 200) * 1000,
        'partner_id' => null,
        'note' => $standaloneNotes[array_rand($standaloneNotes)],
        'voucher_date' => randDate(30, 0),
        'created_by' => null,
    ]);
}

// ---------------------------------------------------------------------------
// Leltárak (stocktakings) — néhány raktárra, kis eltérésekkel
// ---------------------------------------------------------------------------
echo "Seeding stocktakings...\n";

$stocktakingModel = new StocktakingModel();
$stocktakingCount = 0;

foreach ([$warehouseIds[0], $warehouseIds[1]] as $whId) {
    $sheet = $stockModel->stockSheet($whId);
    if (!$sheet) {
        continue;
    }

    // Kb. 12 véletlen termék leltározása, részben eltéréssel.
    shuffle($sheet);
    $subset = array_slice($sheet, 0, 12);

    $items = [];
    foreach ($subset as $row) {
        $book = (float) $row['book_quantity'];
        // ~40% eséllyel van eltérés (+/- pár darab), egyébként pontos.
        $counted = rand(1, 100) <= 40 ? max(0, $book + rand(-4, 4)) : $book;
        $items[] = [
            'product_id' => (int) $row['product_id'],
            'book_quantity' => $book,
            'counted_quantity' => $counted,
        ];
    }

    $stocktakingModel->book($whId, 'Időszaki leltár', $items, null);
    $stocktakingCount++;
}

echo "$stocktakingCount stocktakings.\n";

// ---------------------------------------------------------------------------
// Teendők (CRM todos)
// ---------------------------------------------------------------------------
echo "Seeding todos...\n";

$todoModel = new \Cloudexus\Model\Crm\TodoModel();
$todoTitles = [
    'Ajánlat visszaküldése', 'Szállítói egyeztetés', 'Lejárt számla behajtása',
    'Raktár átrendezése', 'Új termékek felvitele', 'Havi zárás előkészítése',
    'Vevő visszahívása', 'Árlista frissítése', 'Leltár egyeztetés', 'Csomagolóanyag rendelés',
];

foreach ($todoTitles as $i => $title) {
    $pdo->prepare(
        'INSERT INTO todos (title, is_done, due_date, partner_id, created_at)
         VALUES (:title, :done, :due, :partner, NOW())'
    )->execute([
        'title' => $title,
        'done' => $i % 4 === 0 ? 1 : 0,
        'due' => rand(0, 1) ? date('Y-m-d', strtotime('+' . rand(-3, 14) . ' days')) : null,
        'partner' => rand(0, 1) ? $partners['customer'][array_rand($partners['customer'])] : null,
    ]);
}

echo count($todoTitles) . " todos.\n";

// ---------------------------------------------------------------------------
// Partner kapcsolattörténet (CRM aktivitások)
// ---------------------------------------------------------------------------
echo "Seeding partner activities...\n";

$activityTypes = ['call', 'email', 'meeting', 'note', 'offer'];
$activitySubjects = [
    'Ajánlatkérés egyeztetése', 'Visszahívás a rendelésről', 'Árajánlat kiküldve',
    'Szerződés megbeszélés', 'Reklamáció kezelése', 'Fizetési emlékeztető',
    'Új termékek bemutatása', 'Szállítási időpont egyeztetés', 'Éves keretszerződés',
];
$activityCount = 0;
$activityStmt = $pdo->prepare(
    'INSERT INTO partner_activities (partner_id, type, subject, note, activity_date, created_by, created_at)
     VALUES (:pid, :type, :subject, :note, :adate, NULL, NOW())'
);
foreach (array_unique($partners['customer']) as $partnerId) {
    for ($k = 0; $k < rand(2, 6); $k++) {
        $activityStmt->execute([
            'pid' => $partnerId,
            'type' => $activityTypes[array_rand($activityTypes)],
            'subject' => $activitySubjects[array_rand($activitySubjects)],
            'note' => 'Rövid feljegyzés a kapcsolatfelvételről és a megbeszélt teendőkről.',
            'adate' => date('Y-m-d H:i:s', strtotime('-' . rand(0, 60) . ' days -' . rand(0, 23) . ' hours')),
        ]);
        $activityCount++;
    }
}
echo "$activityCount partner activities.\n";

echo "\nDone. Demo data ready.\n";
