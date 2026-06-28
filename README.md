# 📦 UPP - JSON Import za WooCommerce

> Automatizovani sistem za import proizvoda iz JSON datoteke u WooCommerce bazu podataka

## ✨ Karakteristike

- ✅ AJAX-osnovan frontend sa progresivnom obradom
- ✅ Batch import - obradi proizvode jedan po jedan (sigurno i sigurno)
- ✅ Automatsko ažuriranje zaliha
- ✅ Mapiranje atributa (Veličina, Spol, Brand, itd.)
- ✅ Kategorizacija proizvoda
- ✅ Validacija JSON datoteka
- ✅ Logging svih operacija
- ✅ Sigurna autentifikacija sa Session-om
- ✅ SSL verifikacija za WooCommerce API

---

## 🚀 Brzi početak

### 1. Postavljanje okruženja

```bash
# Kloniraj ili preuzmi projekt
cd /home/ddrabic/PROJEKTI/upp

# Kopiraj .env.example u .env
cp .env.example .env

# Uredi .env sa tvojim WooCommerce kredencijalima
nano .env
```

### 2. Generiši WooCommerce REST API kredencijale

1. Uđi u WordPress Admin → **WooCommerce** → **Settings** → **REST API** → **Gener Key/Secret**
2. Kopiraj `Consumer Key` i `Consumer Secret` u `.env` datoteku
3. Postavi permission na `Read/Write`

### 3. Pokreni aplikaciju

Otvori u pretraživaču:
```
https://dinamic.hr/upp/
```

---

## 📋 Struktura projekta (nakon čišćenja)

```
upp/
├── index.php                # Početna stranica
├── upload.php              # Upload handler sa validacijom
├── import_03x.php          # Frontend AJAX import
├── import_03.php           # Backend API endpoint
├── pregled_json.php        # Prikaz datoteke (jqGrid tabela)
├── get_json.php            # JSON API endpoint
├── login.php               # Login forma
├── login_check.php         # Session validacija
├── logout.php              # Logout
├── rest.php                # REST API (simple JSON endpoint)
├── style.css               # CSS stilovi
│
├── lib/
│   ├── upplib.php          # Pomoćne funkcije
│   └── Objekti.php         # wcImport klasa (glavna logika)
│
├── plugins/
│   ├── pqgrid.min.js/css  # Alternativna tabela
│   └── trirand/            # jqGrid plugini
│
├── test/                   # Test datoteke
├── uploads/
│   └── datoteka.json       # Upload folder za JSON
│
├── vendor/                 # Composer dependencies
├── .env.example            # Template za .env datoteku
├── .gitignore              # Git ignore datoteka
├── ISSUES.md               # Registrovani problemi
└── README.md               # Ova datoteka
```

---

## 🔄 Tok Izvršavanja

### Upload Faza
```
1. Korisnik ide na: index.php
2. Odabira JSON datoteku
3. Forma POST-uje u: upload.php
4. upload.php:
   ✓ Proverava veličinu (max 50MB)
   ✓ Proverava MIME tip (application/json)
   ✓ Validira JSON strukturu
   ✓ Proverava obavezna polja (kodRobe, nazivRobe, MPC, stanje)
   ✓ Sprema u: uploads/datoteka.json
   ✓ Redirektuje na: import_03x.php
```

### Import Faza
```
1. import_03x.php se učitava
   - jQuery AJAX gumb "Importiraj podatke"
   
2. Korisnik klikne na gumb
   - Počinje AJAX loop: broj=0, broj=1, broj=2...
   
3. Za svaki zapis:
   - Šalje POST u: import_03.php?broj=X
   - import_03.php:
     • Učitava Objekti.php (wcImport klasa)
     • $shop->connect() - povežite se na WooCommerce
     • $shop->obradiZapis($broj) - obradi 1 proizvod
     • Vraća status kao prvi karakter
   
4. Status kodovi:
   + = Dodan novi proizvod
   = = Ažuriran postojeći
   - = Nije ažuriran (greška)
   ? = Nije pronađen (SKU ne postoji)
   < = Preskočen (neaktivan bez stanja)
   ! = Neaktivan (ali već postoji)
   ~ = KRAJ datoteke
   # = GREŠKA

5. import_03x.php prikazuje rezultate u tabeli
   - Nakon svakog zapisa: broj++
   - Nakon svakih 100 zapisa: sleep(10s)
   - Nastavlja dok se ne obradi sva datoteka
```

### Obrada Zapisa
```
wcImport::obradiZapis($broj):

1. Učitaj proizvod iz JSON-a
2. Pronađi po SKU-u (kodRobe) u WooCommerce
   ├─ Pronađen → STATUS_UPDATE
   ├─ Nije pronađen → 
   │  ├─ Ako aktivan ili ima stanje → STATUS_INSERT
   │  └─ Ako neaktivan i bez stanja → STATUS_SKIP
   └─ Greška → return false

3. Popuni podatke (ako nije SKIP):
   ├─ SKU, naziv, opis
   ├─ Cena = MPC * (1 - popust/100)
   ├─ Zaliha (managing_stock, in_stock, stock_quantity)
   ├─ Atributi (Veličina, Spol, Brand)
   └─ Kategorije

4. POST (insert) ili PUT (update) u WooCommerce
5. Ažuriraj PorukaObrade u JSON-u
6. Vrati status: +, =, -, ?, <
```

---

## 📋 JSON Format

```json
[
  {
    "kodRobe": "RK-26-550",
    "nazivRobe": "Rock Machine Revolver 26 550",
    "jedinicaMjere": "kom",
    "MPC": 299.99,
    "popust": 15.0,
    "artiklNaAkciji": 1,
    "artiklNaRasprodaji": 0,
    "stanje": 12,
    "aktivan": 1,
    "gdjeSeNalazi": "skladiste1",
    "velicinaRame": "L",
    "velicinaKotaca": "26",
    "spol": "m",
    "kodGrupe": "0810",
    "kodGrupe2": "1085",
    "brand": "RM",
    "PorukaObrade": "Ažurirano - simple"
  }
]
```

### Obavezna polja
- `kodRobe` - SKU proizvoda
- `nazivRobe` - Naziv
- `MPC` - Osnovna cena
- `stanje` - Količina na zalihi

### Mapiranje u WooCommerce
| JSON | WooCommerce |
|------|-------------|
| kodRobe | sku |
| nazivRobe | title + description |
| MPC | regular_price |
| popust | sale_price = MPC × (1 - popust/100) |
| stanje | stock_quantity |
| aktivan | in_stock, managing_stock |
| kodGrupe | category ID |
| velicinaRame | attribute (Veličina) |
| velicinaKotaca | attribute (Veličina kotača) |
| spol | attribute (Spol) |
| brand | attribute (Proizvođač) |

---

## 🔧 Konfiguracija

### .env datoteka (OBAVEZNO, NIKADA NE COMMITUJ!)

```bash
# Kopiraj .env.example → .env
cp .env.example .env

# Uredi sa tvojim vrednostima
WOO_URL=https://dinamic.hr
WOO_CONSUMER_KEY=ck_xxxxxxxxxxxxxxxxxxxxxxxxxxxx
WOO_CONSUMER_SECRET=cs_xxxxxxxxxxxxxxxxxxxxxxxxxxxx
```

### Login Kredencijali

Login se nalazi u `login_check.php`:
- Provera sesije: `$_SESSION['username']`
- Ako nije ulogovan → redirektuj na `login.php`

---

## 🔐 Sigurnost

### ✅ Implementovane mjere
- SSL verifikacija za WooCommerce API (`verify_ssl: true`)
- Session-based login
- JSON validacija pre import-a
- MIME type provera
- Maksimalna veličina datoteke (50MB)
- Validacija obaveznih polja

### ⚠️ Napomene za produkciju

1. **Nikada ne commituj .env datoteku**
   - Sadrži WooCommerce kredencijale
   - Koristi `.gitignore`

2. **Regeneriši Consumer Key/Secret**
   - Stari kredencijali su bili vidljivi u kodu
   - Kreiraj nove u WordPress Admin

3. **Omogući SSL certifikat**
   - `verify_ssl: true` zahteva validan SSL
   - Ako ima problema: https://curl.se/docs/caextract.html

4. **Postavi file permissions**
   ```bash
   chmod 755 /home/ddrabic/PROJEKTI/upp
   chmod 755 /home/ddrabic/PROJEKTI/upp/uploads
   chmod 644 /home/ddrabic/PROJEKTI/upp/upp_log.txt
   ```

---

## 📊 Logging

Sve operacije se pišu u `upp_log.txt`:

```
artikl_Found: pronađen je zapis
artikl_Found: artikl_type=ARTIKL_SIMPLE
obradiZapis: status=STATUS_UPDATE
obradiZapis: INSERT artikla, operacija +
```

Za debug:
```php
tail -f upp_log.txt
```

---

## 🐛 Registrovani problemi (ISSUES.md)

Vidi `ISSUES.md` za:
1. Typo u Objekti.php - Ispravlje ✅
2. Standardizovana JSON putanja ✅
3. WooCommerce kredencijali → .env ✅
4. Validacija JSON datoteke ✅
5. SSL verifikacija ✅

---

## 🧪 Testiranje

### Testni JSON
```json
[
  {
    "kodRobe": "TEST-001",
    "nazivRobe": "Test proizvod",
    "jedinicaMjere": "kom",
    "MPC": 99.99,
    "popust": 0,
    "stanje": 10,
    "aktivan": 1,
    "gdjeSeNalazi": "skladiste",
    "velicinaRame": "",
    "velicinaKotaca": "",
    "spol": "",
    "kodGrupe": "0810",
    "kodGrupe2": "",
    "brand": ""
  }
]
```

### Procedure testiranja
1. Upload test JSON-a
2. Prikazi pregled (pregled_json.php)
3. Klikni "Importiraj podatke"
4. Prati status u real-time tabeli
5. Proverite WooCommerce za novi proizvod
6. Proverite upp_log.txt za detalje

---

## 📞 Troubleshooting

### Gre šaka: "WooCommerce kredencijali nisu postavljeni"
- Provera da li postoji `.env` datoteka
- Provera da li su `WOO_CONSUMER_KEY` i `WOO_CONSUMER_SECRET` popunjeni

### Greška: "JSON nije validan"
- Proverite JSON strukturu sa https://jsonlint.com/
- Provera encoding-a (UTF-8)
- Proverite obavezna polja

### Greška: "Datoteka je prevelika"
- Max 50MB limit
- Podeli datoteku na manje delove
- Upload u više grupa

### Import je spora
- Normalno je - antiThrottling: 10s pause nakon svakih 100 zapisa
- Za 1000 zapisa: ~2-3 minuta
- Za 10000 zapisa: ~20-30 minuta

### SSL greška
- Ako koristiš self-signed cert: onemogući SSL privremeno
- Za produkciju: nabavi validan cert

---

## 📝 Verzija

- **Verzija**: 3.0 (čišćeno i sigurno)
- **Datum čišćenja**: Juni 2026
- **Status**: Proizvodnja

---

## 📄 Licenca

Privatan projekat - dinamic.hr

---

## ✅ Checklist pre produkcije

- [ ] Kopiraj `.env.example` u `.env`
- [ ] Popuni `.env` sa WooCommerce kredencijalima
- [ ] Regeneriši Consumer Key/Secret u WordPress Admin
- [ ] Test upload sa manjom datotekom
- [ ] Proverite `upp_log.txt` za greške
- [ ] Proverite WooCommerce za nove proizvode
- [ ] Postavite file permissions na `uploads/` folder
- [ ] Dodaj `.env` u `.gitignore`
- [ ] Commit promene (bez `.env` datoteke!)
