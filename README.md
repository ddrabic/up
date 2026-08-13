# UPP – WooCommerce REST v3 importer

Vanjska PHP 8.1 aplikacija učitava `uploads/datoteka.json`, validira cijeli ulaz
prije mrežnog poziva i sinkronizira proizvode isključivo kroz WooCommerce REST
API `/wp-json/wc/v3/`. Ne uključuje WordPress datoteke i ne pristupa njegovoj
bazi ili PHP API-jima.

## Instalacija

```bash
composer install
cp config.example.php config.local.php
```

Konstanta `UPP_TARGET_DOMAIN` autoritativna je ciljna domena. Zadana vrijednost
je `https://dinamic.hr`; u Docker kontejneru postavite
`UPP_TARGET_DOMAIN=https://dinamic.loc`. Prijelazni `WOO_URL` i dalje je podržan,
ali nova varijabla ima prednost. U ignoriranom `config.local.php` postavite
Read/Write consumer key i secret ili koristite `WOO_CONSUMER_KEY` i
`WOO_CONSUMER_SECRET`. SSL provjera u produkciji mora ostati
uključena; lokalno isključivanje mora biti eksplicitno samo u lokalnoj
konfiguraciji.

Web tok je: prijava → upload JSON-a → pregled → pokretanje importa → rezultat.
Ulazni JSON ostaje nepromijenjen, a rezultati i sažetak zapisuju se u ignoriranu
mapu `logs/`.

## Testovi

```bash
composer test
```

Redovni testovi koriste fake/mocked gateway i ne pristupaju webshopu. Ručni
staging test opisan je u [docs/STAGING_TEST.md](docs/STAGING_TEST.md), a inventura
Legacy migracije u [docs/REST_V3_MIGRACIJA.md](docs/REST_V3_MIGRACIJA.md).
