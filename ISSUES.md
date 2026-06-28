# 🐛 REGISTROVANI PROBLEMI - UPP JSON IMPORT

> **Status**: Aktivni problemi koji zahtevaju rešenje  
> **Datum**: Juni 1, 2026  
> **Prioritet**: Medium-High

---

## 1️⃣ **Typo u Objekti.php - Pogrešna referenca na $artikl_JSON['products']**

### 📍 **Lokacija**: [lib/Objekti.php](lib/Objekti.php#L870-L875)

### 🔴 **Problem**
U metodi `obradiZapis()`, kada se proizvod uspešno **doda** (INSERT), koristi se pogrešna promenljiva:

```php
// ❌ NEISPRAVNO (linija ~872)
elseif ($operacija=='+'):
    $this->datoteka_JSON[$broj]['PorukaObrade'] = trim(
        'Dodano - ' . $this->artikl_JSON['products'][0]['type'] . ' ' . $greske
    );
```

**Problem**: `$this->artikl_JSON` je **NEMA** polja `['products']` - to je samo jedan proizvod (array ili object).  
Trebalo bi koristiti `$this->artikl_Woo` što je pronađeni WooCommerce proizvod sa `['products']` strukturom.

### ✅ **Rešenje**
```php
elseif ($operacija=='+'):
    $this->datoteka_JSON[$broj]['PorukaObrade'] = trim(
        'Dodano - ' . $this->artikl_Woo['products'][0]['type'] . ' ' . $greske
    );
```

### ⚠️ **Uticaj**
- Poruka obrade je **pogrešna** za nove proizvode
- Korisnik ne vidi pravi tip proizvoda koji je dodan (simple/variable)
- Log datoteka (upp_log.txt) će imati pogrešne poruke

---

## 2️⃣ **Putanja do JSON datoteke nije robustna - Hardkodovana URL putanja**

### 📍 **Lokacija**: [lib/upplib.php](lib/upplib.php#L10), [import_03.php](import_03.php#L30), [lib/Objekti.php](lib/Objekti.php#L59)

### 🔴 **Problem**
Putanja do JSON datoteke je hardkodovana kao domena URL:

```php
// ❌ NEISPRAVNO (upplib.php)
$json_datoteka="www.dinamic.hr/upp/uploads/datoteka.json";

// ✓ ISPRAVLJENO (import_03.php)
$shop->json_datoteka = __DIR__ . "/uploads/datoteka.json";

// ❌ NEISPRAVNO (Objekti.php)
$json_datoteka="/upp/uploads/datoteka.json",
```

**Problemi**:
- Ako se aplikacija premesti na drugu domenu - **sve puca**
- Ako se promeni struktura direktorijuma - **sve puca**
- HTTP zahtev umesto lokalnog fajla - **sporije** (network I/O)
- Neće raditi na lokalnoj mašini bez interneta
- Relativne putanje su nepredvidive

### ✅ **Rešenje - Standardizovati putanju**
```php
// ✓ ISPRAVNO
$json_datoteka = __DIR__ . '/../uploads/datoteka.json';

// Ili sa $_SERVER:
$json_datoteka = $_SERVER["DOCUMENT_ROOT"] . "/upp/uploads/datoteka.json";

// Ili sa realpath za bezbedan pristup:
$json_datoteka = realpath(__DIR__ . '/../uploads/datoteka.json');
```

### ⚠️ **Uticaj**
- 🔴 KRITIČNO: Aplikacija je **depozitivna od domena**
- 🟡 Sporiji pristup JSON-u (mrežni zahtevi umesto lokalnih)
- 🟡 Nema kompatibilnosti sa dev okruženjem

---

## 3️⃣ **WooCommerce kredencijali su hardkodirani - Bezbednosni rizik**

### 📍 **Lokacija**: [import_03.php](import_03.php#L9-L13), [lib/Objekti.php](lib/Objekti.php#L55-L57)

### 🔴 **Problem**
Consumer Key i Secret su direktno upisani u kodu:

```php
// ❌ KRITIČNO NEBEZBEDNO
$woocommerce = new Client(
    'https://dinamic.hr',
    'ck_REDACTED_EXAMPLE',  // ← IZLOŽENO!
    'cs_REDACTED_EXAMPLE',  // ← IZLOŽENO!
    ['verify_ssl' => false]  // ← SSL NIJE VERIFIKOVAN!
);
```

**Problemi**:
- 🔴 Kredencijali su vidljivi u Git historiji
- 🔴 Dostupni svakome ko ima pristup kodu
- 🔴 Ako se GitHub/GitLab hacke, svi su kompromitirani
- 🟡 Nema mogućnosti za različita okruženja (dev/staging/prod)
- 🟡 SSL verifikacija je **disabled** (`verify_ssl => false`)

### ✅ **Rešenje - Okružne varijable ili config datoteka**

**Opcija 1: Okružne varijable (.env)**
```bash
# .env
WOO_URL=https://dinamic.hr
WOO_CONSUMER_KEY=ck_REDACTED_EXAMPLE
WOO_CONSUMER_SECRET=cs_REDACTED_EXAMPLE
```

```php
// ✓ ISPRAVNO
$woocommerce = new Client(
    getenv('WOO_URL'),
    getenv('WOO_CONSUMER_KEY'),
    getenv('WOO_CONSUMER_SECRET'),
    ['verify_ssl' => true]  // ← Uključi verifikaciju!
);
```

**Opcija 2: Config datoteka (sa .gitignore)**
```php
// config.php (dodaj u .gitignore)
return [
    'woo_url' => 'https://dinamic.hr',
    'woo_consumer_key' => 'ck_...',
    'woo_consumer_secret' => 'cs_...',
];
```

### ⚠️ **Uticaj**
- 🔴 KRITIČNO: Bezbednosni rizik
- 🔴 Svako sa pristupom kodu može pristupiti WooCommerce-u
- 🔴 WooCommerce kredencijali mogući javno dostupni

---

## 4️⃣ **Nema validacije JSON datoteke u upload.php - Injection rizik**

### 📍 **Lokacija**: [upload.php](upload.php#L8-L30)

### 🔴 **Problem**
Upload handler ne validira datoteku pre nego što je sprema:

```php
// ❌ NEBEZBEDNO
if (isset($_FILES["file"]["name"])) {
    $name = $_FILES["file"]["name"];
    $tmp_name = $_FILES['file']['tmp_name'];
    
    if (!empty($name)) {
        $location = __DIR__.'/uploads/';
        // Direktno koristi $_FILES bez validacije!
        if (move_uploaded_file($tmp_name, $location . 'datoteka.json')) {
```

**Problemi**:
- 🔴 Nema provere MIME tipa (može biti bilo šta, ne samo JSON)
- 🔴 Nema provere veličine datoteke (DoS rizik - 10GB datoteka)
- 🟡 Nema validacije JSON strukture
- 🟡 Nema provere encoding-a
- 🟡 Moguć path traversal sa drugačitim filename-ima

### ✅ **Rešenje - Dodaj validaciju**

```php
// ✓ ISPRAVNO
$max_size = 50 * 1024 * 1024;  // 50MB limit
$allowed_types = ['application/json'];

// 1. Provera veličine
if ($_FILES['file']['size'] > $max_size) {
    die('Datoteka je prevelika (max 50MB)');
}

// 2. Provera MIME tipa
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $_FILES['file']['tmp_name']);
finfo_close($finfo);

if (!in_array($mime, $allowed_types)) {
    die('Datoteka nije JSON format (' . $mime . ')');
}

// 3. Validacija JSON strukture
$json_content = file_get_contents($_FILES['file']['tmp_name']);
$json_decoded = json_decode($json_content, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    die('JSON nije validan: ' . json_last_error_msg());
}

if (!is_array($json_decoded)) {
    die('JSON mora biti niz zapisa');
}

// 4. Validacija strukture zapisa
$required_fields = ['kodRobe', 'nazivRobe', 'MPC', 'stanje'];
foreach ($json_decoded as $record) {
    foreach ($required_fields as $field) {
        if (!isset($record[$field])) {
            die('Zapis nema obavezno polje: ' . $field);
        }
    }
}

// 5. Čuvaj sa bezbedan filename
$location = __DIR__.'/uploads/datoteka.json';
if (move_uploaded_file($_FILES['file']['tmp_name'], $location)) {
    echo 'Datoteka je uspešno uploadovana i validirana';
}
```

### ⚠️ **Uticaj**
- 🟡 Moguć DoS napad sa ogromnim datotekama
- 🟡 Pogrešna JSON struktura može pokvariti bazu
- 🟡 Sigurnosni rizik ako datoteka sadrži PHP kod

---

## 5️⃣ **SSL verifikacija je disabled - Bezbednosni rizik (Man-in-the-Middle)**

### 📍 **Lokacija**: [import_03.php](import_03.php#L17), [lib/Objekti.php](lib/Objekti.php#L70)

### 🔴 **Problem**
WooCommerce REST API klijent ima onemogućenu SSL verifikaciju:

```php
// ❌ NEBEZBEDNO
$woocommerce = new Client(
    'https://dinamic.hr',
    $consumerKey,
    $consumerSecret,
    [
        'verify_ssl' => false  // ← KRITIČNO!
    ]
);
```

**Problemi**:
- 🔴 Ranljiv na **Man-in-the-Middle (MITM)** napade
- 🔴 Napadač može presresti HTTP(S) zahteve
- 🔴 Kredencijali i podaci su izloženi
- 🟡 Samo za zaobilaženje certifikatskih grešaka (loše rešenje)

### ✅ **Rešenje - Omogući SSL verifikaciju**

```php
// ✓ ISPRAVNO - Produkcija
$woocommerce = new Client(
    'https://dinamic.hr',
    getenv('WOO_CONSUMER_KEY'),
    getenv('WOO_CONSUMER_SECRET'),
    [
        'verify_ssl' => true,  // ← Verifikuj SSL certifikat
        'timeout' => 30
    ]
);

// Ako imaš problem sa certifikatom:
// 1. Preuzmi CA certifikate: https://curl.se/docs/caextract.html
// 2. Dodaj u php.ini ili kod:
// ini_set('curl.cainfo', '/path/to/cacert.pem');
// 3. Napravi zahtev hostatoru da instalira ispravan certifikat
```

### ⚠️ **Uticaj**
- 🔴 KRITIČNO: Bezbednosni rizik
- 🔴 Sve komunikacije sa WooCommerce-om su nesigurne
- 🔴 Napadač može intercept podatke

---

## 📊 **Prioritet rešavanja**

```
🔴 KRITIČNO (Odmah)
├─ Problem #3: Hardkodovani kredencijali (Bezbednost)
├─ Problem #5: SSL verifikacija disabled (Bezbednost)
└─ Problem #4: Nema validacije upload (Bezbednost/DoS)

🟡 VISOKO (Ove nedelje)
├─ Problem #2: Robusna putanja do JSON-a (Portabilnost)
└─ Problem #1: Typo u Objekti.php (Funkcionalnost)
```

---

## ✅ **Checklist rešavanja**

- [ ] Problem #1: Ispraviti typo u Objekti.php (5 min)
- [ ] Problem #2: Standardizovati putanje do JSON-a (15 min)
- [ ] Problem #3: Pomeriti kredencijale u .env (20 min)
- [ ] Problem #4: Dodati validaciju u upload.php (30 min)
- [ ] Problem #5: Omogućiti SSL verifikaciju (5 min)
- [ ] Testirati sve nakon ispravki
- [ ] Kreiraj .env.example sa dummy vrednostima
- [ ] Dodaj u .gitignore: `.env`, `uploads/*.json`

---

## 📝 **Napomene**

- Git history će i dalje sadržavati stare kredencijale - trebalo bi **regenerisati consumer key/secret**
- Razmotriti korišćenje **compose.json** ili **symfony/dotenv** za .env fajlove
- Dodati error logging za sve probleme (currently se koristi upp_log.txt)
- Razmotriti migraciju na **modern PHP** i **dependency injection**
