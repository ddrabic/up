# Poslovna oznaka #0#

Import preuzima ERP naziv samo pri kreiranju. Za postojeći proizvod čita aktualni
naziv iz WooCommercea i mijenja isključivo oznaku `#0#`.

- Postojeći proizvod s `aktivan = 0` i ukupnom uključenom zalihom `0` dobiva
  oznaku ` #0#` ako je već nema, status `draft` i vidljivost `hidden`.
  Kategorija sa slugom `brisati` dodaje se uz postojeće kategorije. Ako ne postoji,
  rezultat sadrži upozorenje, a oznaka i skica svejedno se upisuju.
- Nepoznata ERP kategorija ili brand ne smiju spriječiti povlačenje proizvoda.
- Kad je proizvod ponovno aktivan, uklanjaju se oznaka i kategorija `brisati`.
  Import ga ne objavljuje i ne vraća automatski vidljivost; to se uređuje u trgovini.
- Neaktivan proizvod s pozitivnom zalihom preskače se. Neaktivan proizvod bez
  zalihe koji još ne postoji ne kreira se.
- Ponavljanje importa ne umnaža oznaku ni kategoriju. Ako nije moguće pročitati
  naziv ili nakon uklanjanja oznake ne ostaje naziv, zapis završava greškom bez
  ažuriranja. Naziv se tada mora ispraviti u WooCommerceu.
- Varijacije koriste status `draft` i metapodatak `upp_inactive_marker = #0#`,
  jer REST endpoint nema zaseban naziv varijacije koji se može uređivati.
  Pri ponovnoj aktivnosti metapodatak se prazni bez automatske objave.
  Naziv, kategorije i status roditelja ne mijenjaju se zbog jedne varijacije.

Ovo ostvaruje namjeru početnog `lib/Objekti.php`: oznaku neaktivnosti te
povlačenje u skicu/kategoriju Brisati, uz ispravak nedostupne grane i rada
s neučitanim nazivom. Ne zahtijeva izmjenu WordPress dodatka. Import nije
migracija već oštećenih naziva; njih ne rekonstruira iz ERP-a.
