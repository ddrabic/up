# Rezultat faze 1

## Ishod

Vanjska PHP aplikacija modernizirana je na WooCommerce REST API v3. Aktivni kod
više ne uključuje WordPress datoteke/funkcije, ne koristi Legacy REST omotače i
ne mijenja izvorni JSON. Poslovna logika odvojena je od REST adaptera kako bi se
u fazi 2 mogao dodati WooCommerce CRUD adapter bez promjene parsera, validatora,
normalizatora, zalihe i mapiranja.

Ciljna trgovina razrješava se jednom kroz konstantu `UPP_TARGET_DOMAIN`: zadano
`https://dinamic.hr`, odnosno `https://dinamic.loc` kada je tako postavljeno u
Docker okruženju.

## Promijenjene datoteke

- Composer i projekt: `composer.json`, `composer.lock`, `phpunit.xml`,
  `.gitignore`, `.htaccess`, `README.md`, `config.example.php`.
- Nova jezgra: `src/Config`, `src/Domain`, `src/Import`, `src/Inventory`,
  `src/Mapping`, `src/WooCommerce` i `src/Logging`.
- Web tok: `index.php`, `upload.php`, `import_03.php`, `import_03x.php`,
  `get_json.php`, `pregled_json.php`, `Export_products.php`, `login.php`,
  `login_accept.php`, `login_check.php` i novi `lib/web.php`.
- Stari `lib/Objekti.php` sveden je na namjerno neupotrebljiv deprecated omotač,
  a `lib/upplib.php` na read-only kompatibilne funkcije.
- Uklonjene su stare web-test datoteke koje su uključivale WordPress i
  `phpinfo()`. Dodani su `tests/Unit`, `tests/Support`, `tests/fixtures` i ručno
  pokretani `tests/Integration/StagingConnectionTest.php`.
- Dokumentacija: `docs/REST_V3_MIGRACIJA.md`, `docs/STAGING_TEST.md` i ovaj
  izvještaj.

## Uklonjene Legacy strukture

- `filter => ['sku' => ...]` zamijenjen je v3 parametrom `sku`.
- Uklonjeni su odgovor `['products']` i payload omotač `['product']`.
- `title`, `managing_stock`, `in_stock` i read-only `price` zamijenjeni su s
  `name`, `manage_stock`, `stock_status`, `regular_price` i `sale_price`.
- Uklonjeni su trailing slash endpointi, WordPress includeovi, `get_terms()`,
  oznaka `#0#`, mutacija `PorukaObrade` i Legacy mapiranja unutar `wcImport`.
- Samo `RestWooCommerceGateway` poznaje `Automattic\WooCommerce\Client`.

## Zaliha

Skalar ili numerički string postaje `totalStock` bez promjene predznaka. Objekt
skladišta validira svaku količinu i zbraja sva skladišta, odnosno samo kodove iz
`stock_included_warehouses` kada je popis konfiguriran. Izvorno
`stockByWarehouse` ostaje dostupan domeni; `gdjeSeNalazi` se ne koristi za
izračun. Fixture potvrđuje 90 za `200X50Z` i 16 za `ASPECT 950=M`.

## Poslovne pretpostavke

- Novi proizvodi po zadanoj postavci nastaju kao `draft`; aktivnom postojećem
  proizvodu status se ne mijenja.
- Neaktivan postojeći proizvod bez zalihe skriva se i prebacuje u `draft`;
  nepostojeći se preskače. Neaktivan proizvod s pozitivnom zalihom nije mijenjan.
- Šifra kategorije uvijek je string. Eksplicitna mapa `"102"` pokriva poznati
  slučaj izgubljene vodeće nule; nema automatskog rekonstruiranja.
- Nepoznata kategorija/brand daje upozorenje i ponašanje je konfigurabilno.
- `parentProductId` je jedini trenutno podržani pouzdani signal roditelja nove
  varijacije. Naziv i SKU ne analiziraju se heuristički.
- Batch endpoint nije uključen dok pojedinačni, idempotentni tok ne bude potvrđen
  na stagingu.

## Provjere

- `automattic/woocommerce` je 3.1.1; Composer datoteke su valjane.
- PHPUnit: **21 test, 66 assertiona, 0 grešaka**. Jedan staging test je
  očekivano preskočen jer staging varijable nisu postavljene.
- Sintaksna provjera svih projektnih PHP datoteka prolazi.
- Ciljana pretraga aktivnog PHP koda ne nalazi `wc-api/v*`, `wp-load.php`,
  `wp-includes`, `get_terms(`, `WC_Product`, Legacy omotače ili `PorukaObrade`.
- SHA-256 ulazne datoteke prije i nakon testa ostao je
  `ddb01d970c105589d4a8b08292190a79c7e50f097cbbed1189b52a44a4d1af27`.

## Za fazu 2

Faza 2 nije započeta. Preostaje zaseban WordPress/WooCommerce plugin koji će
implementirati gateway pomoću WooCommerce CRUD objekata i WordPress API-ja, uz
ponovnu uporabu slojeva Domain, Import, Inventory i Mapping. Prije produkcije
treba ručno izvršiti staging test, potvrditi stvarne slugove kategorija i obaviti
probni import s ograničenim Read/Write ključem.

## `git diff --stat`

Statistika praćenih datoteka prikazuje se na završetku rada naredbom
`git diff --stat`; nove nepraćene datoteke vidljive su zasebno u `git status`.
