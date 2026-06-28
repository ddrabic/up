# ANALIZA PROJEKTA

## Pregled projekta

Projekt je mala PHP aplikacija za upload jedne JSON datoteke i postupni import artikala u WooCommerce web shop. Glavni tok se oslanja na PHP sesiju za pristup, upload uvijek sprema datoteku pod fiksnim imenom `uploads/datoteka.json`, a import se izvodi redom, jedan JSON zapis po jedan AJAX zahtjev.

Glavna import logika nalazi se u klasi `wc\wcImport` u `lib/Objekti.php`. Frontend za import je `import_03x.php`, a backend endpoint koji obrađuje jedan zapis je `import_03.php`.

Projekt sadrži i dokumentaciju (`README.md`, `QUICK_START.txt`, `CHANGELOG.md`, `ISSUES.md`), logove (`error_log`, `upp_log.txt`), test datoteke u `test/`, vendor biblioteku `automattic/woocommerce` i lokalne JS/CSS plugine za prikaz tablice.

## Tok rada aplikacije

1. Korisnik otvara `index.php`.
2. `index.php` uključuje `login_check.php`; ako nema sesije, korisnik se preusmjerava na `login.php`.
3. `login.php` prikazuje formu za korisničko ime i lozinku.
4. `login_accept.php` provjerava hardkodirane podatke i postavlja `$_SESSION['username']`.
5. Na početnoj stranici korisnik bira JSON datoteku.
6. Forma šalje upload na `upload.php`.
7. `upload.php` validira upload i sprema datoteku kao `uploads/datoteka.json`.
8. Korisnik može otvoriti `pregled_json.php`, koji preko jqGrid-a zove `get_json.php`.
9. Korisnik otvara `import_03x.php` i klikom pokreće import.
10. JavaScript u `import_03x.php` šalje jedan AJAX POST prema `import_03.php` s parametrom `broj`.
11. `import_03.php` instancira `wcImport`, spaja se na WooCommerce i zove `obradiZapis($broj)`.
12. `wcImport` čita JSON, pronalazi jedan artikl, provjerava postoji li u WooCommerceu po SKU-u, radi insert/update/skip i zapisuje `PorukaObrade` natrag u JSON.
13. Frontend poveća `broj` i ponovi AJAX poziv dok backend ne vrati status koji prekida obradu.

## Ključne datoteke

`index.php`

- Zaštićena početna stranica.
- Sadrži linkove na pregled JSON-a i import.
- Sadrži upload formu koja šalje `multipart/form-data` na `upload.php`.
- Koristi hardkodirane URL-ove `https://dinamic.hr/upp/pregled_json.php` i `https://dinamic.hr/upp/import_03x.php`.

`login.php`

- HTML forma za login.
- Uključuje Bootstrap s CDN-a.
- Ne radi provjeru, samo šalje POST na `login_accept.php`.

`login_accept.php`

- Pokreće sesiju i provjerava `$_POST['username']` i `$_POST['password']`.
- Koristi hardkodirane login podatke: korisnik `importuser`, lozinka je zapisana direktno u kodu.
- Uspješna prijava postavlja `$_SESSION['username']`.
- Nema rate limiting, password hashing, CSRF zaštitu ni redirect nakon POST-a.

`login_check.php`

- Pokreće sesiju.
- Ako `$_SESSION['username']` nije postavljen, šalje `Location: login.php`.
- Ne poziva `exit` nakon headera, što može biti problem u nekim tokovima.

`logout.php`

- Uništava sesiju i vraća korisnika na `login.php`.

`upload.php`

- Zaštićen preko `login_check.php`.
- Prima upload iz forme.
- Provjerava PHP upload error.
- Ograničava veličinu na 50 MB.
- Provjerava MIME tip `application/json`.
- Parsira JSON i provjerava da je rezultat array.
- Provjerava obavezna polja `kodRobe`, `nazivRobe`, `MPC`, `stanje`.
- Sprema datoteku lokalno u `__DIR__ . '/uploads/datoteka.json'`.
- Uvijek pregazi prethodnu datoteku istim imenom.

`pregled_json.php`

- Zaštićena stranica za prikaz JSON datoteke.
- Koristi jqGrid.
- Podatke dohvaća s relativnog endpointa `get_json.php`.
- Link za download koristi javni URL `https://dinamic.hr/upp/uploads/datoteka.json`.
- Uključuje stare vanjske JS/CSS resurse, uključujući jQuery 1.10.1.

`get_json.php`

- Trebao bi vratiti sadržaj JSON-a za jqGrid.
- Trenutno čita `./uploads/datoteka.json`.
- Problem: `require_once "upplib.php"` je zakomentiran, ali se poziva `parse_json($json)`.
- Dodatni problem: `parse_json()` očekuje putanju datoteke, a kod joj prosljeđuje sadržaj JSON-a iz `file_get_contents`.
- Ako nema nekog globalnog include mehanizma izvan ovog projekta, ovaj endpoint može završiti fatalnom greškom ili pokušajem čitanja datoteke čije je ime cijeli JSON sadržaj.

`import_03x.php`

- Zaštićena frontend stranica za pokretanje importa.
- Koristi jQuery 3.3.1 s Google CDN-a.
- Sadrži JavaScript petlju `Prebaci()`.
- Svaki artikl obrađuje zasebnim AJAX POST zahtjevom.
- AJAX URL je hardkodiran na `https://dinamic.hr/upp/import_03.php`.
- Nakon svakih 100 zapisa radi blokirajući busy-wait `sleep(10000)`.
- Na grešku pokušava retry logiku, ali sadrži bug: `Prebaci;` ne poziva funkciju, nego samo referencira ime funkcije.

`import_03.php`

- Zaštićeni backend endpoint za obradu jednog zapisa.
- Uključuje `lib/Objekti.php`.
- Stvara `new \wc\wcImport()`.
- Za svaki HTTP poziv radi novo spajanje na WooCommerce.
- Prima `broj` iz POST-a ili GET-a i cast-a ga u integer.
- Postavlja `$shop->json_datoteka = __DIR__ . "/uploads/datoteka.json"`.
- Poziva `$shop->obradiZapis($broj)` i vraća kratku status poruku.

`lib/Objekti.php`

- Glavna poslovna logika.
- Definira namespace `wc`, funkciju `add_log()` i klasu `wcImport`.
- Uključuje `lib/upplib.php`, Composer autoload i WordPress category include datoteke iz `$_SERVER['DOCUMENT_ROOT']`.
- Konstruktor čita `WOO_URL`, `WOO_CONSUMER_KEY`, `WOO_CONSUMER_SECRET` iz environment varijabli, s fallbackom za URL `https://dinamic.hr`.
- `connect()` kreira `Automattic\WooCommerce\Client`.
- `artikl_Found()` učitava JSON zapis i traži WooCommerce proizvod po SKU-u.
- `obradiZapis()` odlučuje insert/update/skip, popunjava payload i zapisuje rezultat obrade u JSON.

`lib/upplib.php`

- Pomoćne funkcije za čitanje i pisanje JSON-a.
- Globalna `$json_datoteka` default vrijednost je lokalna putanja `__DIR__ . '/../uploads/datoteka.json'`.
- `parse_json($file)` čita datoteku, dekodira JSON i na grešku radi `die()`.
- `ZapisiDatoteku($json)` zapisuje cijeli JSON natrag u datoteku.
- Sadrži starije/helper funkcije za mapiranje proizvoda koje trenutačni import uglavnom ne koristi direktno.

`rest.php`

- Jednostavan endpoint koji čita `uploads/datoteka.json` i vraća sirovi JSON.
- Nije zaštićen loginom.
- Ne validira pristup ni sadržaj.

`Export_products.php`

- Zaštićena pomoćna skripta za dohvat proizvoda iz WooCommercea.
- Sadrži hardkodirane WooCommerce API ključeve i `verify_ssl => false`.
- Sprema rezultat u `uploads/svi_artikli.json`.
- Nije dio glavnog import toka, ali je sigurnosno bitna.

`test/`

- Sadrži test skripte za WordPress kategorije, product API i `phpinfo()`.
- `test/product.php` sadrži hardkodirane WooCommerce API ključeve i `verify_ssl => false`.
- `test/php_info.php` javno prikazuje `phpinfo()` ako je dostupno preko weba.

`vendor/`

- Composer dependency.
- Instaliran je `automattic/woocommerce` verzija `1.3.0` iz 2017.

`plugins/`

- Lokalni frontend plugini za jqGrid/pqGrid.
- Sadrži i `datafeedr-woocommerce-importer.1.2.31.zip`, koji nije uključen u aktivni PHP tok.

`uploads/datoteka.json`

- Radna JSON datoteka.
- Upload je pregazi.
- Import u nju zapisuje `PorukaObrade`.
- Datoteka je dostupna preko javnih linkova u UI-u.

`error_log` i `upp_log.txt`

- Log datoteke.
- `upp_log.txt` je vrlo velik i zapisuje detalje obrade.
- `error_log` sadrži stare greške za pokušaje pisanja na `www.dinamic.hr/upp/uploads/datoteka.json`, što potvrđuje da je ranije korišten URL/string umjesto lokalne putanje za pisanje.

## Trenutni način importa

Import je redni i sinkroniziran preko browsera.

Frontend:

- `import_03x.php` drži početni indeks u inputu `#broj`.
- Klik na gumb poziva `Prebaci()`.
- `Prebaci()` šalje POST `broj=N` na `https://dinamic.hr/upp/import_03.php`.
- Nakon uspjeha frontend interpretira prvi znak odgovora:
  - `<` preskočen
  - `+` dodan
  - `=` ažuriran
  - `-` nije ažuriran
  - `?` nije pronađen
  - `~` kraj
  - `#` greška
- Ako je prvi znak u skupu `?+-!=#<`, frontend nastavlja s idućim brojem.

Backend za jedan zapis:

1. `import_03.php` učita `wcImport`.
2. `wcImport::connect()` kreira WooCommerce REST client.
3. `obradiZapis($broj)` zove `artikl_Found($broj)`.
4. `artikl_Found()` zove `ucitaj_ArtiklFromJSON($broj)`.
5. `ucitaj_ArtiklFromJSON()` svaki put parsira cijelu JSON datoteku i uzima element `$broj`.
6. `artikl_Found()` traži proizvod u WooCommerceu po `kodRobe` kao SKU.
7. Ako proizvod postoji, postavlja update status.
8. Ako ne postoji, ovisno o `aktivan` i `stanje`, radi insert ili skip.
9. `popuni_Polja()` priprema payload za WooCommerce:
   - za insert postavlja `sku`, `title`, `short_description`, `description`, status `draft`
   - postavlja zalihu iz `stanje` i `aktivan`
   - postavlja `regular_price`, `price`, `sale_price`
   - za insert pokušava dodati kategorije i atribute
10. Insert koristi `$this->woocommerce->post('products/', $this->data)`.
11. Update koristi `$this->woocommerce->put('products/' . id, $this->data)`.
12. Rezultat se zapisuje u `$this->datoteka_JSON[$broj]['PorukaObrade']`.
13. `ZapisiDatoteku($this->datoteka_JSON)` zapisuje cijelu JSON datoteku natrag na disk.

## Lokalna putanja prema JSON-u i URL prema JSON-u

Lokalne putanje ili relativne datotečne putanje:

- `upload.php`: `__DIR__.'/uploads/' . 'datoteka.json'` za spremanje uploada.
- `import_03.php`: `__DIR__ . "/uploads/datoteka.json"` za import.
- `lib/Objekti.php`: default `__DIR__ . '/../uploads/datoteka.json'`.
- `lib/upplib.php`: globalni default `__DIR__ . '/../uploads/datoteka.json'`.
- `get_json.php`: `./uploads/datoteka.json`.
- `rest.php`: `uploads/datoteka.json`.
- `Export_products.php`: zapis u `__DIR__ . "/uploads/svi_artikli.json"`.

Javni URL-ovi ili web putanje:

- `index.php`: `https://dinamic.hr/upp/pregled_json.php`, `https://dinamic.hr/upp/import_03x.php`.
- `pregled_json.php`: CSS/JS resursi s `https://www.dinamic.hr/upp/plugins/...`, HOME link `https://dinamic.hr/upp/index.php`, download `https://dinamic.hr/upp/uploads/datoteka.json`.
- `import_03x.php`: AJAX `https://dinamic.hr/upp/import_03.php`, download `/upp/uploads/datoteka.json`.
- `upload.php`: navigacijski linkovi `/upp/index.php`, `import_03x.php`, `pregled_json.php`.
- Dokumentacija i `error_log` spominju staru putanju `www.dinamic.hr/upp/uploads/datoteka.json` koja se koristila kao string za `file_put_contents`, što je pogrešno jer nije lokalna putanja.

Zaključak: aktivni import sada uglavnom koristi lokalnu putanju, ali frontend i navigacija su jako vezani na domenu `dinamic.hr`. Pregled/download JSON-a izlaže datoteku preko javnog URL-a.

## Problemi s performansama

Trenutni import je spor iz više razloga:

1. Jedan artikl = jedan HTTP AJAX zahtjev iz browsera.
2. Jedan AJAX zahtjev = novo PHP izvršavanje.
3. Svako PHP izvršavanje ponovno kreira WooCommerce client i radi novo spajanje.
4. Svaki artikl ponovno čita i parsira cijeli `uploads/datoteka.json`.
5. Nakon obrade svakog artikla cijeli JSON se ponovno serializira i zapisuje na disk zbog `PorukaObrade`.
6. Za svaki artikl se radi najmanje jedan WooCommerce GET po SKU-u.
7. Za insert/update se radi dodatni WooCommerce POST ili PUT.
8. Za insert kategorije se dohvaćaju preko WooCommerce API-ja u `getCategoryIdByCode()`, potencijalno više puta po artiklu.
9. Frontend namjerno pauzira 10 sekundi nakon svakih 100 zapisa.
10. JavaScript `sleep()` je busy-wait, blokira browser tab umjesto da koristi `setTimeout`.
11. Logiranje je izrazito detaljno i `upp_log.txt` je vrlo velik, što može usporiti I/O i otežati održavanje.

Najveći algoritamski problem je kombinacija "parsiranje cijele datoteke + zapis cijele datoteke" za svaki pojedini red. Za N artikala to stvara približno N puta čitanje cijele datoteke i N puta pisanje cijele datoteke.

## Sigurnosni problemi

Hardkodirani login:

- `login_accept.php` sadrži hardkodirani username i lozinku.
- Lozinka nije hashirana.
- Koristi se jednostavna usporedba stringova.
- Nema rate limita, lockouta, CSRF zaštite ni audit loga prijave.

WooCommerce ključevi:

- `Export_products.php` sadrži hardkodirani Consumer Key i Consumer Secret.
- `test/product.php` sadrži hardkodirani Consumer Key i Consumer Secret.
- `ISSUES.md` i `CHANGELOG.md` dokumentiraju stare pune ključeve, pa su oni i dalje prisutni u projektu kao tekst.
- Glavni `lib/Objekti.php` više nema hardkodirane ključeve, ali koristi prazne fallback vrijednosti za key/secret ako environment nije postavljen.
- Preporuka je smatrati sve ključeve koji su ikad bili u ovom direktoriju kompromitiranima i rotirati ih u WooCommerceu.

SSL:

- Glavni `wcImport::connect()` koristi `verify_ssl => true`.
- `Export_products.php` i `test/product.php` koriste `verify_ssl => false`.

Javna dostupnost podataka:

- `uploads/datoteka.json` je linkan direktno za download.
- `rest.php` vraća JSON bez login provjere.
- `uploads/index.html` postoji, ali ne štiti direktan pristup poznatom imenu datoteke.

Test i debug datoteke:

- `test/php_info.php` izlaže `phpinfo()`.
- Test datoteke uključuju WordPress i WooCommerce kontekst i mogu otkriti konfiguraciju ili omogućiti neplanirane pozive.

Upload:

- Upload validacija postoji, što je dobro.
- MIME provjera samo na `application/json` može biti prestroga za neke legitimne JSON uploadove, ali sigurnosno je bolja od nikakve provjere.
- Datoteka se sprema pod fiksnim imenom i pregazi prethodnu; nema audit traila ni izolacije po korisniku/sesiji.

Sesije:

- `login_check.php` ne zove `exit` nakon redirecta.
- Nema regeneracije session ID-a nakon login-a.
- Nema eksplicitne provjere session timeouta.

## Tehnički dug

- Stara WooCommerce PHP biblioteka: `automattic/woocommerce` verzija `1.3.0` iz 2017.
- Kod koristi stare WooCommerce API obrasce:
  - endpoint `products/`
  - `filter => ['sku' => ...]`
  - payload omotan u `['product' => ...]`
  - odgovor se očekuje kao `['products']`
- Dio koda miješa WooCommerce REST API i direktno uključivanje WordPress core datoteka iz `$_SERVER['DOCUMENT_ROOT']`.
- `get_json.php` je vjerojatno pokvaren zbog zakomentiranog includea i pogrešnog poziva `parse_json()`.
- `Objekti.php` ima rizike s indeksima: na više mjesta se očekuje `products[0]`, ali drugdje se pristupa `products["type"]`.
- `obradiZapis()` za skip granu postavlja `$operacija='<'`, ali u toj grani ranije već vraća za tipičan skip; preostali kod je teško čitljiv.
- U update grani koristi se `$greske += '*0*'`, što je numerički operator, ne string konkatenacija.
- `popuni_Polja()` može koristiti `$this->data['product']['title']` kod updatea prije nego što je polje postavljeno u toj metodi.
- Kategorije i brand mapping su hardkodirani u velikim arrayevima u klasi.
- Logiranje je globalna funkcija i piše relativno u `upp_log.txt`.
- Nema automatiziranih testova.
- Nema jasnog config layera, iako postoji `.env.example`; kod ne učitava `.env` datoteku samostalno.
- Dokumentacija djelomično opisuje željeno ili ranije stanje, pa nije potpuno pouzdan izvor istine za trenutni kod.
- `upp_20260628.zip`, veliki logovi i uploadani JSON nalaze se u radnom direktoriju projekta.

## Stara WooCommerce biblioteka

Da, projekt koristi staru WooCommerce biblioteku.

- Instalirani paket: `automattic/woocommerce`.
- Verzija: `1.3.0`.
- Datum paketa u `vendor/composer/installed.json`: 2017-06-06.
- Učitavanje: `lib/Objekti.php` i `Export_products.php` koriste `vendor/autoload.php`.
- Klasa: `Automattic\WooCommerce\Client`.

Aktivni import koristi tu biblioteku u `lib/Objekti.php`:

- `new Client($url, $consumerKey, $consumerSecret, [...])`
- `$this->woocommerce->get('products/', $params)`
- `$this->woocommerce->post('products/', $this->data)`
- `$this->woocommerce->put('products/' . $id, $this->data)`
- `$this->woocommerce->get('products/categories/')`

Ovaj stil odgovara starijem WooCommerce REST API wrapperu i starijim response/payload strukturama. Modernizacija bi trebala provjeriti aktualnu WooCommerce REST API verziju shopa i prijeći na podržani oblik endpointa i payloadova.

## Preporuke za sljedeću fazu

1. Ne dirati poslovna pravila dok se prvo ne stabilizira infrastruktura importa.
2. Rotirati sve WooCommerce API ključeve koji su prisutni u kodu ili dokumentaciji.
3. Izbaciti hardkodirani login i prebaciti autentifikaciju na sigurniji mehanizam ili barem konfiguraciju izvan koda s hashiranom lozinkom.
4. Zaštititi `rest.php`, `uploads/datoteka.json` i test datoteke ili ih ukloniti iz web root-a.
5. Popraviti `get_json.php` tako da pravilno uključuje helper i parsira lokalnu datoteku, ili jednostavno vraća validirani JSON.
6. Standardizirati sve URL-ove i putanje kroz jednu konfiguraciju.
7. Zamijeniti browser-driven import server-side jobom koji obrađuje batch zapisa.
8. Učitati JSON jednom po batchu, a rezultate obrade zapisivati u zasebnu log/result datoteku ili bazu, ne prepisivati cijeli JSON nakon svakog artikla.
9. Keširati WooCommerce kategorije i druge lookup podatke za vrijeme importa.
10. Razmotriti WooCommerce batch endpoint ili barem batch obradu s više artikala po zahtjevu.
11. Uvesti strukturirani log s rotacijom umjesto velikog `upp_log.txt`.
12. Nadograditi WooCommerce PHP biblioteku i prilagoditi endpoint/payload strukturu nakon provjere kompatibilnosti sa shopom.
13. Dodati mali set testnih JSON datoteka i automatizirane provjere za validaciju, mapiranje i odluke insert/update/skip.
