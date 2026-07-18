# ☁️ Cloudexus

**Felhő alapú ügyviteli rendszer nagy raktárkészlettel dolgozó vállalkozások számára.**

Online számlázás, vonalkód-központú készlet- és raktárkezelés, beszerzés és pénztár — egyetlen letisztult, modern felületen. A Cloudexus az *1 telepítés = 1 cég* modellt követi: minden vállalat saját, elkülönített példányt futtat, a felhasználók pedig ugyanazon cég munkatársai.

![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?logo=php&logoColor=white)
![MariaDB](https://img.shields.io/badge/MariaDB-11.4-003545?logo=mariadb&logoColor=white)
![Twig](https://img.shields.io/badge/Twig-3.x-8BC34A?logo=symfony&logoColor=white)
![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-7952B3?logo=bootstrap&logoColor=white)
![Status](https://img.shields.io/badge/St%C3%A1tusz-akt%C3%ADv%20fejleszt%C3%A9s-blue)

---

## ✨ Fő funkciók

### 📊 Vezérlőpult
- Valós idejű forgalmi grafikon (Chart.js) az elmúlt 10 nap rendeléseiről
- Kintlevőség és kötelezettség kártyák **lejárt / nem lejárt** bontásban, egy kattintásra szűrt listával
- Top termékkategóriák 30 napos értékesítési érték szerint
- Legutóbbi számlák gyorsáttekintés

### 📦 Törzsadatok
- Terméktörzs cikkszámmal, kategóriafával, mennyiségi egységgel, árral és élő készletadattal
- Partnertörzs (vevő / szállító / mindkettő) adószámmal és elérhetőségekkel
- Kereshető, szűrhető, lapozható listák minden modulban

### 🏭 Készletkezelés
- Több raktár és telephely kezelése
- Raktári bevét és kiadás bizonylatolása (túladás elleni védelemmel)
- **Raktárközi átadás** tranzakcionális ki-/bevét párokkal
- Élő raktárkészlet-összesítő raktár- és termékszűrővel
- A készlet mindig a mozgásokból számolódik — konstrukciójából adódóan konzisztens

### 🧾 Értékesítés
- Vevői rendelések dinamikus tételsorokkal, automatikus árkitöltéssel és élő végösszeg-számítással
- Számlázás önállóan vagy **egy kattintással rendelésből** (tételek előtöltve)
- Opcionális automatikus raktári kiadás számlázáskor, készletellenőrzéssel
- Számla-életciklus: fizetésre vár → kifizetve / stornózva, lejárt kiemeléssel

### 🚚 Beszerzés
- Szállítói rendelések és bejövő számlák
- Bejövő számla rögzítésekor **automatikus raktári bevét** a választott raktárba
- Rendelésből előtöltött számlázás a vevői oldallal azonos élménnyel

### 💰 Pénztár
- Bevételi és kiadási pénztárbizonylatok
- Számlához kapcsolt bizonylat = automatikus kiegyenlítés (a számla kifizetetté válik)
- Élő pénzkészlet-egyenleg

### ⚙️ Rendszer
- Bejelentkezés felhasználónévvel vagy e-maillel, bcrypt jelszó-hash
- Szerepkör-alapú jogosultságok (admin / felhasználó), admin felhasználókezelés
- Saját profil és jelszóváltás
- CSRF-védelem minden űrlapon, HttpOnly + SameSite session süti
- Magyar nyelvű felület

---

## 🛠️ Technológia

| Réteg | Megoldás |
|---|---|
| Backend | PHP 8.4, egyedi könnyűsúlyú MVC (framework nélkül) |
| Adatbázis | MySQL / MariaDB, PDO prepared statement-ekkel |
| Sablonozás | [Twig 3](https://twig.symfony.com/) |
| Frontend | Bootstrap 5.3 + Bootstrap Icons + egyedi CSS design-rendszer |
| Grafikonok | Chart.js 4 |
| Levelezés | PHPMailer (tranzakciós e-mailekhez) |

A publikus dokumentumgyökér kizárólag a `web/` mappa — minden alkalmazáskód, konfiguráció és futásidejű adat azon kívül él.

## 🚀 Telepítés

**Követelmények:** PHP ≥ 8.4 (`pdo_mysql`), MySQL/MariaDB, Composer, Apache (mod_rewrite).

```bash
# 1. Klónozás
git clone https://github.com/szabolevi98/Cloudexus.git
cd Cloudexus

# 2. Függőségek
composer install

# 3. Konfiguráció
cp config/config.ini.dist config/config.ini
#    → állítsd be az adatbázis-elérést és a base_url-t

# 4. Adatbázis-séma
php database/migrate.php

# 5. Admin felhasználó
php database/create_admin.php admin titkosjelszo

# 6. (Opcionális) Gazdag demo adatok
php database/seed_demo.php
```

Ezután nyisd meg a `config.ini`-ben beállított `base_url`-t (pl. `http://localhost/Cloudexus/web`), és jelentkezz be.

> A `database/seed_demo.php` bármikor újrafuttatható: minden üzleti táblát kiürít (a felhasználókat nem), és valósághű magyar demo adatokkal tölti fel — 48 termék, 19 partner, 3 raktár, 200+ készletmozgás, 45 rendelés számlákkal és kiegyenlítésekkel.

## 📁 Projektstruktúra

```
Cloudexus/
├── config/              # config.ini (gitignore-olt) + .dist sablon
├── database/
│   ├── core/            # sorszámozott SQL migrációk (01_core.sql, …)
│   ├── migrate.php      # migrációfuttató
│   ├── create_admin.php # kezdő admin létrehozása
│   └── seed_demo.php    # újrafuttatható demo-adat generátor
├── src/
│   ├── Core/            # Config, DB, Router, Session, Auth, Csrf, Paginator, …
│   ├── Controller/      # egy controller / erőforrás
│   ├── Model/           # modulonként: Core, Account, Sales, Purchasing, Cash
│   └── View/
│       ├── Twig/        # sablonok (közös layout + modul-nézetek)
│       └── Css/         # rétegzett forrás-CSS (base/layout/components/pages)
├── var/                 # cache + naplók (gitignore-olt)
└── web/                 # publikus gyökér: index.php front controller + assetek
```

## 🗺️ Roadmap

A teljes funkciótérkép a [FEATURES.md](FEATURES.md)-ben él.

**Kész:**

- [x] Bejelentkezés, szerepkör-alapú jogosultság, felhasználó- és profilkezelés
- [x] Törzsadatok: termékek (vonalkóddal, minimum készlettel), kategóriák, partnerek
- [x] Készletkezelés: bevét, kiadás, raktárközi átadás, raktárkészlet-áttekintés
- [x] Vonalkód gyűjtő (kézi leolvasós tömeges készletrögzítés)
- [x] Leltározás készletkorrekcióval
- [x] Értékesítés: vevői rendelés → számlázás, nyomtatható számla
- [x] Beszerzés: szállítói rendelés → bejövő számla automatikus bevéttel
- [x] Pénztár: bizonylatok, számla-kiegyenlítés, pénzkészlet
- [x] CRM: teendők (feladatlista határidővel, partnerhez/felelőshöz kötve)
- [x] Vezérlőpult: forgalmi grafikon, kintlevőség/kötelezettség, top kategóriák, alacsony készlet, teendők
- [x] Keresés, szűrők, lapozás, CSV export a listákban
- [x] CSRF-védelem, cégadat-beállítások

**Következő lépcsők:**

- [ ] Tárhely / polc szintű helykódok
- [ ] CRM bővítés: ügyfélkapcsolat-történet, hívás/e-mail napló
- [ ] Szállítólevelek és fuvarszervezés
- [ ] NAV Online Számla adatszolgáltatás
- [ ] Futárszolgálat-integrációk (GLS, MPL, Foxpost, …)
- [ ] Webshop-integrációk + REST API

## 📄 Licenc

Minden jog fenntartva. © 2026 Cloudexus
