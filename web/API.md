# Cloudexus REST API

Külső integrációhoz (pl. webshop-szinkron) készült REST API. JSON be- és kimenet,
token-alapú hitelesítés.

## Alap

- **Alap URL:** `https://<domain>/api` (pl. `https://cloudexus.levente.net/api`)
- **Formátum:** minden válasz JSON (`Content-Type: application/json; charset=utf-8`)
- **Karakterkódolás:** UTF-8

## Hitelesítés

Minden kérésnél kötelező egy API-token az `Authorization` fejlécben:

```
Authorization: Bearer <token>
```

A tokent az adminfelületen, az **API → API felhasználók** menüben lehet létrehozni,
újragenerálni, aktiválni/inaktiválni és törölni. Egy token teljes hozzáférést ad
(nincs külön jogosultsági szint). Ha a szerver a szabványos `Authorization` fejlécet
levágná, alternatívaként az `X-Api-Key: <token>` fejléc is használható.

Hiányzó vagy érvénytelen token esetén a válasz `401` (az API üzenetei angolul jönnek):

```json
{ "error": { "status": 401, "message": "Invalid or missing API token." } }
```

## Lapozás

A lista végpontok offset-alapú lapozást használnak:

- `?page=1` — oldalszám (alap: 1)
- `?per_page=50` — oldalméret (alap: 50, maximum: 200)

A válasz `data` + `meta` szerkezetű:

```json
{
  "data": [ { "...": "..." } ],
  "meta": { "page": 1, "per_page": 50, "total": 8423, "total_pages": 169 }
}
```

Egy elem lekérésekor a válasz `{ "data": { ... } }`.

## Szűrők

Minden lista végponton elérhető:

- `?q=` — szabadszavas keresés (a mezők végpontonként eltérnek)
- `?updated_since=YYYY-MM-DD HH:MM:SS` — csak az adott időpont óta módosult rekordok
  (delta-szinkronhoz; minden rekordnak van `updated_at` mezője)

## Hibaformátum

Az API válaszüzenetei (a hibaüzenetek is) **angol nyelvűek**:

```json
{ "error": { "status": 404, "message": "Product not found." } }
```

Használt státuszkódok: `200` OK, `201` létrehozva, `400/422` hibás kérés,
`401` hitelesítés hiányzik/érvénytelen, `404` nincs ilyen erőforrás.

---

# Végpontok

## Csak olvasható erőforrások (GET)

Ezek az erőforrások API-n keresztül **csak lekérdezhetők**; létrehozni/módosítani/törölni
az adminfelületen lehet őket.

### Termékek

| Metódus | Útvonal | Leírás |
|---|---|---|
| GET | `/api/products` | Terméklista. Szűrők: `q` (cikkszám/név/vonalkód), `category_id`, `status` (`active`/`inactive`), `updated_since` |
| GET | `/api/products/{id}` | Egy termék teljes adata (ID alapján) |
| GET | `/api/products/sku/{sku}` | Egy termék teljes adata (cikkszám alapján) |

Példa válasz (`GET /api/products/1`) — a részletes termék minden mezője:

```json
{
  "data": {
    "id": 1,
    "sku": "PRD-0001",
    "barcode": "5991269759143",
    "name": "Városi kerékpár 26\"",
    "short_description": "Városi kerékpár 26\" — kiváló minőségű termék raktárról.",
    "description": "A termék részletes (HTML) leírása.",
    "category_id": 1,
    "unit": "doboz",
    "price": "89900.00",
    "sale_price": null,
    "vat_rate": "27.00",
    "min_stock": "237.000",
    "is_active": 1,
    "is_webshop": 1,
    "width_mm": 512,
    "height_mm": 756,
    "depth_mm": 320,
    "weight_g": 17460,
    "created_at": "2026-07-24 16:44:35",
    "updated_at": "2026-07-24 16:44:35",
    "stock_qty": 220,
    "images": [
      { "id": 10, "product_id": 1, "path": "assets/uploads/products/abc.jpg", "is_primary": 1, "sort_order": 0 }
    ],
    "attributes": [
      { "id": 1886, "product_id": 1, "attr_name": "Gyártó", "attr_value": "Generic", "sort_order": 0 },
      { "id": 1887, "product_id": 1, "attr_name": "Garancia", "attr_value": "36 hónap", "sort_order": 1 }
    ],
    "category_ids": [1, 5],
    "related_ids": [4, 6],
    "substitute_ids": [2],
    "group_prices": {
      "1": { "customer_group_id": 1, "price": "3650.00", "sale_price": null },
      "3": { "customer_group_id": 3, "price": "3600.00", "sale_price": "3060.00" }
    }
  }
}
```

Mezők: `price`/`sale_price` nettó ár (ha `sale_price` ki van töltve, az az aktív ár);
`stock_qty` az összesített aktuális készlet; `images` a termékképek (a `path` lehet relatív
feltöltés vagy teljes URL); `attributes` a termékparaméterek; `category_ids` az összes
hozzárendelt kategória; `related_ids`/`substitute_ids` a kapcsolódó/helyettesítő termékek;
`group_prices` a vevőcsoportos árak `customer_group_id` szerint kulcsolva (fix `price` +
opcionális `sale_price`).

### Kategóriák

| Metódus | Útvonal | Leírás |
|---|---|---|
| GET | `/api/categories` | Kategórialista (fa, `parent_id`). Szűrők: `q`, `updated_since` |
| GET | `/api/categories/{id}` | Egy kategória |

### Egyéb törzsadat

| Metódus | Útvonal | Leírás |
|---|---|---|
| GET | `/api/parameter-names` | Paraméternevek. Szűrők: `q`, `updated_since` |
| GET | `/api/units` | Mennyiségi egységek. Szűrők: `q`, `updated_since` |
| GET | `/api/customer-groups` | Vevőcsoportok. Szűrők: `q`, `updated_since` |
| GET | `/api/customer-groups/{id}` | Egy vevőcsoport |
| GET | `/api/warehouses` | Raktárak. Szűrők: `q`, `status`, `updated_since` |

### Készlet

| Metódus | Útvonal | Leírás |
|---|---|---|
| GET | `/api/stock` | Aktuális készlet raktár/tárhely/termék bontásban. Szűrők: `q`, `warehouse_id`, `location_id`, `product_id` |

Példa válasz (`GET /api/stock`):

```json
{
  "data": [
    {
      "warehouse_id": 2,
      "warehouse_name": "Debreceni telephely",
      "location_id": 14,
      "location_code": "B-02-03",
      "product_id": 31,
      "sku": "PRD-0031",
      "product_name": "Irodai szék",
      "unit": "karton",
      "quantity": "42.000"
    }
  ],
  "meta": { "page": 1, "per_page": 50, "total": 298, "total_pages": 6 }
}
```

Tárhely nélküli mozgások `location_id`/`location_code` = `null` alatt jelennek meg. A
`quantity` az adott raktár+tárhely+termék aktuális készlete (bevét − kiadás).

### Számlák

| Metódus | Útvonal | Leírás |
|---|---|---|
| GET | `/api/invoices` | Számlalista. Szűrők: `q`, `partner_id`, `status`, `date_from`, `date_to`, `updated_since` |
| GET | `/api/invoices/{id}` | Egy számla a tételeivel |

Példa válasz (`GET /api/invoices/2`):

```json
{
  "data": {
    "id": 2,
    "invoice_number": "SZLA-2026-0002",
    "order_id": 3,
    "partner_id": 10,
    "partner_name": "Kelemen Kereskedés",
    "tax_number": "33206217-1-06",
    "warehouse_id": 1,
    "warehouse_name": "Központi raktár",
    "status": "paid",
    "issue_date": "2026-06-30",
    "due_date": "2026-07-08",
    "shipping_cost": "990.00",
    "payment_cost": "890.00",
    "total_amount": "508140.00",
    "created_at": "2026-07-24 16:44:35",
    "updated_at": "2026-07-24 16:44:35",
    "items": [
      { "id": 5, "invoice_id": 2, "product_id": 26, "quantity": "6.000", "unit_price": "6850.00", "line_total": "41100.00", "sku": "PRD-0026", "product_name": "Jóga szőnyeg", "unit": "csomag" }
    ]
  }
}
```

`status`: `unpaid` / `paid` / `cancelled`. A számla API-n keresztül csak olvasható.

### Árazás

| Metódus | Útvonal | Leírás |
|---|---|---|
| GET | `/api/pricing/effective?product_id=&partner_id=` | Egy termék effektív (partnerre/vevőcsoportra érvényes) ára |

Példa válasz (`GET /api/pricing/effective?product_id=1&partner_id=12`):

```json
{ "data": { "price": 80910, "is_sale": false } }
```

`price` a partnernek ténylegesen érvényes nettó egységár (a partner vevőcsoportjának fix/akciós
ára, ha van, egyébként a termék saját ára/akciós ára); `is_sale` = `true`, ha ez az ár akciós.
A `partner_id` elhagyható — akkor a termék listaára jön vissza.

## Teljes CRUD erőforrások

### Partnerek

| Metódus | Útvonal | Leírás |
|---|---|---|
| GET | `/api/partners` | Partnerlista. Szűrők: `q`, `type` (`customer`/`supplier`/`both`), `status`, `customer_group_id`, `updated_since` |
| GET | `/api/partners/{id}` | Egy partner a címeivel |
| GET | `/api/partners/tax/{adószám}` | Egy partner adószám alapján |
| POST | `/api/partners` | Új partner létrehozása |
| PUT | `/api/partners/{id}` | Partner módosítása |
| PUT | `/api/partners/tax/{adószám}` | Upsert: ha az adószám létezik, módosít, különben létrehoz |
| DELETE | `/api/partners/{id}` | Partner törlése |

**Partner törzs (POST/PUT):**

```json
{
  "name": "Példa Kft.",
  "type": "customer",
  "tax_number": "12345678-2-42",
  "email": "info@pelda.hu",
  "phone": "+36 30 123 4567",
  "customer_group_id": 2,
  "is_active": true,
  "addresses": [
    { "country": "Magyarország", "postal_code": "1111", "city": "Budapest", "street": "Példa utca 1.", "note": "2. emelet 3. ajtó" }
  ]
}
```

Mezők:

- `name` — **kötelező**.
- `type` — `customer` / `supplier` / `both` (alap: `customer`).
- `tax_number`, `email`, `phone` — opcionális.
- `customer_group_id` — vevőcsoport azonosító (0 vagy elhagyva = nincs).
- `is_active` — `true`/`false` (alap: `true`).
- `addresses` — opcionális címlista. Ha PUT-nál megadod, a partner címei **teljesen
  lecserélődnek** a megadott listára; ha kihagyod, a meglévő címek változatlanok maradnak.
  Egy cím akkor menthető, ha van `city`, `postal_code` és `street` (a `country` alapja
  „Magyarország", a `note` opcionális).

Példa válasz (`GET /api/partners/1`):

```json
{
  "data": {
    "id": 1,
    "type": "customer",
    "customer_group_id": 2,
    "customer_group_name": "VIP",
    "name": "Zöldkert Kft.",
    "tax_number": "81211777-1-33",
    "email": "info@zoldkert.hu",
    "phone": "+36 30 808 6329",
    "is_active": 1,
    "created_at": "2026-07-24 16:44:35",
    "updated_at": "2026-07-24 16:44:35",
    "addresses": [
      { "id": 1, "partner_id": 1, "country": "Magyarország", "postal_code": "1011", "city": "Budapest", "street": "Rákóczi utca 55.", "note": "2. emelet 14. ajtó", "created_at": "2026-07-24 16:44:35", "updated_at": "2026-07-24 16:44:35" },
      { "id": 2, "partner_id": 1, "country": "Magyarország", "postal_code": "4400", "city": "Nyíregyháza", "street": "Petőfi Sándor utca 5.", "note": null, "created_at": "2026-07-24 16:44:35", "updated_at": "2026-07-24 16:44:35" }
    ]
  }
}
```

(`customer_group_name` és a címek `id`-je csak a válaszban szerepel; létrehozáskor/módosításkor
nem kell megadni. Törlésnél a válasz: `{ "data": { "deleted": true, "id": 1 } }`.)

### Rendelések

| Metódus | Útvonal | Leírás |
|---|---|---|
| GET | `/api/orders` | Rendeléslista. Szűrők: `q`, `partner_id`, `status`, `date_from`, `date_to`, `updated_since` |
| GET | `/api/orders/{id}` | Egy rendelés a tételeivel és címeivel |
| POST | `/api/orders` | Új rendelés létrehozása |
| PUT | `/api/orders/{id}` | Rendelés módosítása |
| DELETE | `/api/orders/{id}` | Rendelés törlése |

**Rendelés törzs (POST/PUT):**

```json
{
  "partner_id": 12,
  "order_date": "2026-07-20",
  "status": "confirmed",
  "shipping_address_id": 5,
  "billing_address_id": 5,
  "shipping_cost": 1490,
  "payment_cost": 0,
  "items": [
    { "product_id": 1, "quantity": 3, "unit_price": 59990 }
  ]
}
```

Mezők:

- `partner_id` — **kötelező**, létező partner.
- `items` — **kötelező** (legalább egy tétel), mindegyikben `product_id`, `quantity`,
  `unit_price`.
- `order_date` — `YYYY-MM-DD` (alap: mai nap).
- `status` — `draft` / `confirmed` / `invoiced` / `cancelled` (alap: `confirmed`).
- `shipping_address_id`, `billing_address_id` — a partner egy-egy címének azonosítója
  (opcionális; 0 vagy elhagyva = nincs).
- `shipping_cost`, `payment_cost` — tetszőleges nettó összeg (alap: 0).
- A rendelésszám automatikusan generálódik; a `total_amount`-ot a szerver számolja
  (tételek + `shipping_cost` + `payment_cost`).
- PUT-nál az `items` csak akkor cserélődik le, ha megadod; egyébként a tételek maradnak.

Példa válasz (`GET /api/orders/19`) — a címmezők a kiválasztott címekből feloldva:

```json
{
  "data": {
    "id": 19,
    "order_number": "REND-2026-0019",
    "partner_id": 1,
    "partner_name": "Zöldkert Kft.",
    "status": "confirmed",
    "order_date": "2026-07-17",
    "shipping_address_id": 1,
    "billing_address_id": 1,
    "shipping_cost": "990.00",
    "payment_cost": "0.00",
    "total_amount": "183315.00",
    "created_at": "2026-07-24 16:44:35",
    "updated_at": "2026-07-24 16:44:35",
    "shipping_country": "Magyarország", "shipping_postal_code": "1011", "shipping_city": "Budapest", "shipping_street": "Rákóczi utca 55.", "shipping_note": "2. emelet 14. ajtó",
    "billing_country": "Magyarország", "billing_postal_code": "1011", "billing_city": "Budapest", "billing_street": "Rákóczi utca 55.", "billing_note": "2. emelet 14. ajtó",
    "items": [
      { "id": 53, "order_id": 19, "product_id": 47, "quantity": "1.000", "unit_price": "2415.00", "line_total": "2415.00", "sku": "PRD-0047", "product_name": "Öntözőkanna 10L", "unit": "karton" },
      { "id": 54, "order_id": 19, "product_id": 35, "quantity": "9.000", "unit_price": "19990.00", "line_total": "179910.00", "sku": "PRD-0035", "product_name": "Éjjeliszekrény", "unit": "szett" }
    ]
  }
}
```

(A `partner_name`, a feloldott `shipping_*`/`billing_*` címmezők, valamint a tételek `sku`/
`product_name`/`unit`/`line_total` mezői csak a válaszban szerepelnek. Törlésnél a válasz:
`{ "data": { "deleted": true, "id": 19 } }`.)

---

# Példák (curl)

Terméklista (2. oldal, 100 elem):

```bash
curl -H "Authorization: Bearer <token>" \
  "https://cloudexus.levente.net/api/products?page=2&per_page=100"
```

Csak a tegnap óta módosult termékek:

```bash
curl -H "Authorization: Bearer <token>" \
  "https://cloudexus.levente.net/api/products?updated_since=2026-07-19%2000:00:00"
```

Új partner:

```bash
curl -X POST -H "Authorization: Bearer <token>" -H "Content-Type: application/json" \
  -d '{"name":"Új Vevő Kft.","type":"customer","tax_number":"11111111-2-11"}' \
  "https://cloudexus.levente.net/api/partners"
```

Új rendelés:

```bash
curl -X POST -H "Authorization: Bearer <token>" -H "Content-Type: application/json" \
  -d '{"partner_id":12,"items":[{"product_id":1,"quantity":2,"unit_price":59990}],"shipping_cost":1490}' \
  "https://cloudexus.levente.net/api/orders"
```
