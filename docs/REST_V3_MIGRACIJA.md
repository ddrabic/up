# Migracija vanjske aplikacije na WooCommerce REST API v3

## Zatečeno stanje

Aktivni import bio je koncentriran u `wc\wcImport` i koristio biblioteku
`automattic/woocommerce` 1.3.0. Klijent nije imao postavljen `version`, pozivi su
koristili trailing slash i Legacy filtre/omotače, a `lib/Objekti.php` izravno je
uključivao WordPress datoteke. Svaki HTTP korak ponovno je učitavao cijeli JSON i
na kraju u njega upisivao `PorukaObrade`.

| Legacy poziv/pretpostavka | REST v3 zamjena |
| --- | --- |
| `GET products/` + `filter[sku]` | `GET products?sku=...&per_page=100` |
| odgovor `['products'][0]` | izravna kolekcija proizvoda; egzaktna provjera SKU-a |
| `POST products/` s `['product' => ...]` | `POST products` s izravnim payloadom |
| `PUT products/{id}` s `['product' => ...]` | `PUT products/{id}` s izravnim payloadom |
| `GET products/categories/` za svaki zapis | paginirani `GET products/categories`, cache po slugu |
| `title` | `name` |
| `managing_stock` | `manage_stock` |
| `in_stock` | `stock_status` (`instock`/`outofstock`) |
| `price` | ne šalje se; koriste se `regular_price` i `sale_price` |
| lokalni `get_terms()` | isključivo REST `products/categories` |

## Strukture zahtjeva i odgovora

Konstanta `UPP_TARGET_DOMAIN` definira ciljnu domenu (`https://dinamic.hr`, a u
Docker testu `https://dinamic.loc`). Konfigurirani API prefiks je `wc/v3`, pa biblioteka gradi
`/wp-json/wc/v3/`. Osnovni URL ne smije sadržavati REST putanju. V3 kolekcije
nisu omotane ključevima `products` ili `product`. Produktni payload je izravan,
novčani iznosi su decimalni stringovi, atributi koriste `options`, a kategorije
su niz objekata s `id`.

## WordPress ovisnosti

Aktivna uključivanja `wp-includes/category.php` i
`category-template.php`, `get_terms()` te testni `wp-load.php` eksperimenti
uklanjaju se. Vanjska aplikacija ne koristi WordPress funkcije, bazu ni
WooCommerce PHP CRUD klase.

## Zaliha i ulazni JSON

Stari `stanje` je broj/numerički string. Novi oblik je objekt skladišta. Novi
normalizator nikad ne cast-a objekt u broj, nego validira svaku količinu i zbraja
sva ili samo konfigurirana skladišta. `gdjeSeNalazi` nije izvor zalihe. Cijela
datoteka validira se prije prve API operacije, uključujući obvezna polja i duple
SKU-ove, te ostaje nepromijenjena.

## Rizici i pretpostavke

- Šifre kategorija tretiraju se kao stringovi. Izgubljena vodeća nula (npr.
  broj `102`) ne rekonstruira se; potreban je eksplicitan unos `"102"` u mapi.
- Slugovi starih kategorija izvedeni su iz postojećih naziva; moraju odgovarati
  stvarnim slugovima staging/produkcijske trgovine.
- Nepoznata kategorija po zadanoj se postavci izostavlja, uz upozorenje. Može se
  konfigurirati preskakanje cijelog proizvoda.
- Nepoznat brand po zadanoj se postavci izostavlja, uz upozorenje. Automatsko
  stvaranje globalnih atributa/termina nije uključeno.
- Bez `parentProductId` nije moguće pouzdano stvoriti varijaciju; SKU/naziv se ne
  koriste heuristički.
- Ponavljanje importa je idempotentno po SKU-u, ali duplikati SKU-a koji već
  postoje u WooCommerceu prekidaju obradu tog zapisa kao kritična greška.
- Retry vrijedi samo za 429, 502, 503, 504 i transportni timeout, najviše tri
  pokušaja. Create se ponavlja samo kada gateway može sigurno utvrditi privremenu
  grešku; nakon nejasnog prekida preporučuje se provjera SKU-a prije novog importa.
