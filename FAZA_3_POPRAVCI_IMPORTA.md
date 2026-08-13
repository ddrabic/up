# Faza 3 - Popravci trenutnog importa

## Sto je promijenjeno

- `import_03.php` koristi lokalnu putanju `__DIR__ . "/uploads/datoteka.json"` za import.
- Prije spajanja na WooCommerce provjerava se da JSON datoteka postoji, da je citljiva i da je validan JSON.
- Ako `uploads/datoteka.json` ne postoji, nije citljiv ili nije validan JSON, import se zaustavlja prije obrade i vraca jasnu poruku korisniku.
- `lib/upplib.php` sada ima `validate_json_file()` i `parse_json()` vise ne prekida proces s nejasnim `die()` porukama.
- WooCommerce REST API pozivi u `wcImport` idu kroz male wrapper metode koje hvataju greske, zapisuju ih u log i vracaju kontrolirani neuspjeh postojecem toku importa.
- Poruke gresaka prije logiranja prolaze kroz sanitizaciju da se ne zapisuju consumer key, consumer secret, tokeni, lozinke ili Authorization headeri.

## Sto nije promijenjeno

- Nije uveden batch import.
- Nije uveden queue, cron ili nova arhitektura.
- Nije mijenjan postojeci ekran importa.
- Nije mijenjana WooCommerce payload struktura.
- Nisu mijenjane insert/update/skip odluke.
- Nije mijenjano mapiranje artikala.

## Validacija JSON datoteke

Import sada staje prije WooCommerce spajanja ako:

- `uploads/datoteka.json` ne postoji,
- putanja nije lokalna datoteka,
- datoteka nije citljiva,
- sadrzaj nije validan JSON,
- JSON nije niz zapisa,
- JSON je prazan.

Upload i dalje provjerava osnovne uvjete: velicinu, `.json` ekstenziju, MIME tip, `json_decode()` rezultat i obavezna polja `kodRobe`, `nazivRobe`, `MPC`, `stanje`.

## Trailing comma

Trailing comma nije dopusten u JSON formatu. Primjer nevalidnog JSON-a:

```json
[
  {
    "kodRobe": "ABC123",
    "nazivRobe": "Test artikl",
    "MPC": 10,
    "stanje": 1,
  }
]
```

Zarez nakon zadnjeg polja `"stanje": 1` nije validan. Ispravno:

```json
[
  {
    "kodRobe": "ABC123",
    "nazivRobe": "Test artikl",
    "MPC": 10,
    "stanje": 1
  }
]
```

## WooCommerce REST greske

Ako WooCommerce REST API vrati gresku:

- greska se zapisuje u `upp_log.txt`,
- poruka se dodaje u rezultat obrade artikla,
- korisnik dobiva kontroliranu poruku kroz postojeci rezultat importa,
- PHP proces se ne prekida bez jasne poruke.

Osjetljivi podaci se prije logiranja redigiraju.
