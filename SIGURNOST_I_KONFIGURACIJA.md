# Sigurnost i konfiguracija

## Sto je promijenjeno

- Dodan je konfiguracijski sloj kroz `config.example.php`, `config.local.php` i `lib/config.php`.
- Login vise ne koristi plain-text lozinku iz koda, nego korisnicko ime i hash lozinke iz konfiguracije.
- Login forma ima osnovni CSRF token, a uspjesan login poziva `session_regenerate_id(true)`.
- `login_check.php` nakon redirecta sada prekida izvrsavanje s `exit`.
- WooCommerce URL, Consumer Key, Consumer Secret i SSL postavka citaju se iz konfiguracije.
- Uklonjeni su stvarni WooCommerce API kljucevi iz PHP datoteka i dokumentacije.
- `verify_ssl => false` uklonjen je iz WooCommerce poziva; default je `true`.
- `rest.php` i `get_json.php` sada zahtijevaju login.
- Direktni linkovi za download JSON-a zamijenjeni su linkom na `rest.php`.
- `uploads/.htaccess` blokira listing direktorija, PHP izvrsavanje i direktni pristup JSON datotekama.
- Test/debug skripte u `test/` su onemogucene po defaultu preko konfiguracije.
- Upload dodatno provjerava da je ekstenzija `.json` i blokira PHP/HTML/JS tipove.
- `.gitignore` je prosiren za lokalnu konfiguraciju, uploadane JSON datoteke, logove, arhive i backup datoteke.

## Postavljanje konfiguracije

Za lokalnu/produkcijsku konfiguraciju koristi se `config.local.php`. Ta datoteka je u `.gitignore` i ne smije se commitati.

Ako treba kreirati novu lokalnu konfiguraciju, kopiraj primjer:

```bash
cp config.example.php config.local.php
```

Zatim uredi vrijednosti u `config.local.php`.

## Primjer konfiguracije

```php
<?php

return [
    'app_username' => 'importuser',
    'app_password_hash' => '$2y$10$replaceWithRealPasswordHash',
    'target_domain' => 'https://dinamic.hr',
    'woocommerce_consumer_key' => 'ck_xxx',
    'woocommerce_consumer_secret' => 'cs_xxx',
    'woocommerce_verify_ssl' => true,
    'enable_test_scripts' => false,
];
```

`UPP_TARGET_DOMAIN` je autoritativna ciljna domena. Zadana je
`https://dinamic.hr`, a u Docker kontejneru koristi se
`UPP_TARGET_DOMAIN=https://dinamic.loc`. `WOO_URL` ostaje samo prijelazni alias.
`WOO_CONSUMER_KEY` i `WOO_CONSUMER_SECRET` imaju prednost pred lokalnom konfiguracijom.

## Datoteke koje se ne smiju commitati

- `config.local.php`
- `.env`, `.env.local`, `.env.*.local`
- `uploads/*.json`
- `logs/`
- `imports/`
- `upp_log.txt`
- `error_log`
- ZIP/TAR arhive projekta
- backup datoteke poput `*.bak`, `*.backup`, `*.old`, `*.orig`

## Promjena login lozinke

Generiraj novi hash:

```bash
php -r 'echo password_hash("NOVA_LOZINKA", PASSWORD_DEFAULT), PHP_EOL;'
```

Dobiveni hash upisi u `config.local.php` pod `app_password_hash`. Plain-text lozinku nemoj zapisivati u projekt.

## Rotacija WooCommerce API kljuceva

Svi dosadasnji WooCommerce API kljucevi koji su bili u ovom projektu trebaju se smatrati kompromitiranima.

Postupak:

1. U WooCommerce administraciji opozovi stare REST API kljuceve.
2. Generiraj novi Consumer Key i Consumer Secret s potrebnim `Read/Write` ovlastima.
3. Upisi nove vrijednosti u `config.local.php` ili u environment varijable.
4. Provjeri da se novi kljucevi ne pojavljuju u PHP, MD ili TXT datotekama.

## Testne i debug datoteke

- `test/php_info.php` vise ne poziva `phpinfo()` i po defaultu vraca HTTP 403.
- `test/product.php` vise ne sadrzi stvarne API kljuceve i po defaultu vraca HTTP 403.
- `test/kategorija.php` i `test/kategorija2.php` po defaultu vracaju HTTP 403.
- Ako se test skripte bas moraju koristiti lokalno, `enable_test_scripts` se moze privremeno postaviti na `true` u `config.local.php`.

## Ostalo za Fazu 3

- Ne koristiti browser kao kontroler dugog importa; uvesti server-side batch/job pristup.
- Smanjiti broj WooCommerce API poziva po artiklu.
- Ucitavati JSON jednom po batchu umjesto za svaki zapis.
- Rezultate obrade zapisivati u zasebnu result/log strukturu umjesto prepisivanja cijelog JSON-a nakon svakog artikla.
- Modernizirati WooCommerce API wrapper i endpoint strukturu tek nakon provjere kompatibilnosti shopa.
- Dodati automatizirane testove za validaciju JSON-a i odluke insert/update/skip.
