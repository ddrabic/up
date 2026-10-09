# Izvješće za naručitelja o implementiranim unapređenjima projekta UPP

**Naručitelj:** Dinamic  
**Projekt:** UPP povezivanje poslovnog sustava i webshopa  
**Datum izvješća:** 9. listopada 2026.

Početna aplikacija za prijenos artikala u webshop proširena je i temeljito obnovljena. Radovi su obuhvatili cijeli postupak: od provjere datoteke iz poslovnog sustava, preko usklađivanja proizvoda, cijena i zaliha, do nadzora obrade i prikaza dostupnosti po poslovnicama.

Naručitelj sada ima izrađeno rješenje s većom kontrolom nad uvozom, detaljnijim podacima o zalihama i jasnijim informacijama o uspješnosti obrade. Uz mogućnosti vidljive korisniku, odrađen je opsežan posao na pouzdanosti povezivanja, zaštiti podataka i pripremi sustava za daljnje održavanje.

## Polazište i opseg obnove

Početni projekt omogućavao je učitavanje datoteke s artiklima, pregled podataka te dodavanje i ažuriranje proizvoda. Već je sadržavao pravila za cijene, popuste, kategorije i proizvođače.

Daljnji rad obuhvatio je obnovu tih funkcija, ispravak postojećih problema i razvoj novih mogućnosti. Posebno su obrađeni složeniji slučajevi: zaliha raspoređena po više lokacija, proizvodi s varijantama, neaktivni artikli, prekidi veze i obrada većeg broja zapisa.

## Obnovljeno povezivanje s webshopom

Obnovljen je način na koji UPP razmjenjuje podatke s WooCommerce trgovinom. Zastarjeli način povezivanja zamijenjen je novijim, a postupci pronalaženja, dodavanja i ažuriranja artikala prilagođeni su novom načinu komunikacije.

Izrađen je i poseban dodatak za webshop koji podržava pronalaženje proizvoda i njihovih varijanti prema šifri te spremanje zalihe po lokacijama. Time je odrađen posao na obje strane povezivanja: u aplikaciji za uvoz i u samoj trgovini.

**Korist za naručitelja:** uvoz može pravilno razlikovati vrste proizvoda i razmjenjivati proširene podatke potrebne za upravljanje ponudom.

## Proširena provjera podataka prije uvoza

Proširene su postojeće provjere datoteke i uvedena provjera svih zapisa prije početka izmjena u webshopu. Provjeravaju se obvezni podaci, šifre i nazivi artikala, ispravnost cijena, raspon popusta, oznaka aktivnosti te količine zalihe.

Dodana je provjera ponavljanja iste šifre artikla unutar datoteke. Neispravna datoteka zaustavlja se prije obrade, uz poruku koja pomaže pronaći problem. Provjera se provodi pri učitavanju datoteke i ponovno prije uvoza.

Izvorna datoteka ostaje nepromijenjena. Rezultati obrade spremaju se zasebno, čime je sačuvan izvorni sadržaj dobiven iz poslovnog sustava.

**Korist za naručitelja:** pogreške se otkrivaju ranije, a izvorni podaci ostaju dostupni za usporedbu i ponovnu obradu.

## Razrađeno usklađivanje artikala i cijena

Obnovljena su i jasnije razrađena pravila prema kojima se novi artikl dodaje, postojeći ažurira ili zapis preskače. Usklađuju se redovna cijena, cijena s popustom, raspoloživa količina i dostupnost.

Zadržano je pravilo da se naziv iz poslovnog sustava preuzima pri dodavanju novog proizvoda. Pri kasnijim uvozima čuva se postojeći naziv uređen u webshopu, uz obradu oznake neaktivnosti. Novi jednostavni proizvodi prema zadanoj postavci nastaju kao skice, što omogućuje pregled prije objave.

Obnovljen je prijenos kategorija, proizvođača i obilježja poput veličine okvira, veličine kotača i spola. Za nepoznate kategorije i proizvođače uvedena su upozorenja i podesiva pravila postupanja.

**Korist za naručitelja:** podaci važni za prodaju mogu se usklađivati uz očuvanje naziva koje je trgovina već uredila i veću kontrolu nad objavom novih artikala.

## Uvedena obrada zaliha po šest lokacija

Uvoz je proširen s jedne ukupne količine na obradu stanja po poslovnicama i skladištu. Obuhvaćene su sljedeće lokacije:

| Lokacija | Adresa |
| --- | --- |
| Čakovec | Bana Jelačića 4 |
| Virovitica | S. Radića 77 |
| Čakovec | Svetojelenska 15 |
| Koprivnica | B. Radića 23 |
| Varaždin | Optujska 50 |
| Skladište | Središnja skladišna lokacija |

Ukupna web-zaliha računa se prema uključenim lokacijama, a pojedinačna stanja čuvaju se zasebno. Pri novom uvozu stanje svake definirane lokacije zamjenjuje se novim podatkom; lokacija koja više nema stanje u dostavljenim podacima postavlja se na nulu.

Obrađene su i decimalne količine. Za ukupnu količinu u webshopu primjenjuje se zaokruživanje naniže, uz upozorenje, dok se detaljno stanje po lokacijama čuva.

**Korist za naručitelja:** webshop dobiva razrađeniju sliku dostupnosti, uz pravila koja sprječavaju zadržavanje starih količina po poslovnicama.

## Izrađen prikaz dostupnosti kupcima

Izrađena je mogućnost prikaza poslovnica u kojima je artikl dostupan, s nazivima i adresama lokacija. Kupcima se prikazuju lokacije s pozitivnom zalihom.

Dodana je postavka kojom naručitelj može uključiti ili isključiti javni prikaz. Spremanje zaliha radi neovisno o toj postavci. Javni prikaz prema zadanoj je postavci isključen.

Kod proizvoda s varijantama prikaz dostupnosti mijenja se nakon odabira pojedine varijante.

**Korist za naručitelja:** nakon uključivanja prikaza kupac može vidjeti u kojoj je poslovnici odabrani artikl dostupan, što može olakšati odluku o kupnji ili dolasku u trgovinu.

## Proširena podrška za varijante proizvoda

Uvedena je zasebna obrada postojećih varijanti proizvoda, primjerice različitih veličina istog modela. Uvoz pronalazi odgovarajuću varijantu prema šifri i ažurira njezinu cijenu i zalihu, uz očuvanje postojećih obilježja varijante.

Omogućeno je i dodavanje nove varijante kada ulazni podaci sadrže pouzdanu vezu s glavnim proizvodom. Nakon izmjena varijanti izračunava se i sprema njihova ukupna zaliha uz glavni proizvod.

**Korist za naručitelja:** pojedine izvedbe artikla mogu imati vlastitu cijenu, količinu i dostupnost po lokacijama, bez pogrešnog ažuriranja glavnog proizvoda.

## Ispravljena obrada neaktivnih artikala

Razrađena je i popravljena primjena postojećih poslovnih pravila za artikle koji više nisu aktivni:

- Postojeći neaktivni artikl bez zalihe označava se oznakom `#0#`, povlači u skicu i skriva iz kataloga. Ako postoji kategorija Brisati, dodaje joj se uz očuvanje postojećih kategorija.

- Neaktivan artikl koji još ima zalihu preskače se uz upozorenje.

- Neaktivan artikl bez zalihe koji još ne postoji u webshopu ne dodaje se.

- Pri ponovnoj aktivnosti uklanja se oznaka neaktivnosti i pripadajuća kategorija Brisati. Ponovna objava ostaje pod kontrolom trgovine.

- Kod varijanti se neaktivnost obrađuje zasebno, bez povlačenja cijelog glavnog proizvoda.

Ponavljanje uvoza ne umnaža oznake i kategorije. Ako postojeći naziv nije moguće pouzdano pročitati, izmjena se zaustavlja uz poruku.

**Korist za naručitelja:** povlačenje artikala iz ponude postaje predvidljivije, a ponovna objava ostaje svjesna odluka trgovine.

## Ubrzana obrada i uvedena kontrola tijeka uvoza

Uvoz je preuređen za rad u paketima od najviše 100 zapisa. Uvedeno je skupno pronalaženje artikala i skupno ažuriranje postojećih jednostavnih proizvoda, čime se smanjuje broj pojedinačnih razmjena s webshopom. Uklonjeno je ranije čekanje koje je blokiralo rad preglednika.

Izrađen je novi prikaz napretka s informacijom o trenutačnoj fazi, artiklu koji se obrađuje, proteklom vremenu i rezultatima pojedinih zapisa. Korisnik može zatražiti zaustavljanje uvoza te pokrenuti obradu od odabranog zapisa, što olakšava nastavak nakon prekida.

Dodana je zaštita od istodobnog pokretanja više obrada iste datoteke. Za određene privremene pogreške veze uvedeni su ponovni pokušaji, a kod nepouzdanog skupnog ažuriranja obrada se može vratiti na pojedinačne izmjene.

**Korist za naručitelja:** manje nepotrebnih poziva prema trgovini, bolji pregled napretka i lakše upravljanje dužim obradama. Stvarno trajanje ovisi o broju artikala i odzivu webshopa.

## Uvedena evidencija rezultata i jasnije poruke

Svaki obrađeni zapis dobiva rezultat: dodan, ažuriran, preskočen ili završen s pogreškom. Uz rezultat se bilježe šifra artikla i pripadajuća poruka. Izrađen je i sažetak s brojem dodanih, ažuriranih i preskočenih artikala, upozorenja i pogrešaka.

Razrađene su poruke za probleme poput istekle prijave, nedostupne trgovine, neispravnog odgovora poslužitelja i nepotpunih podataka. Evidencija obrade odvojena je od ulazne datoteke.

**Korist za naručitelja:** lakše je utvrditi što je preneseno i kojim zapisima treba posvetiti pažnju, što olakšava rješavanje problema i podršku.

## Poboljšana zaštita pristupa i poslovnih podataka

Obnovljena je zaštita prijave i radnje koje mijenjaju podatke dodatno su zaštićene. Lozinka se koristi u zaštićenom obliku, a pristupni podaci za webshop izdvojeni su iz programskih datoteka.

Pregled i preuzimanje podataka vezani su uz prijavu. Dodana su pravila koja na predviđenoj konfiguraciji poslužitelja ograničavaju izravan pristup učitanim datotekama. Uklonjene su stare dijagnostičke stranice, uvedena provjera zaštićene veze s trgovinom i prikrivanje osjetljivih pristupnih podataka u zapisima pogrešaka.

**Korist za naručitelja:** smanjena je izloženost poslovnih i pristupnih podataka te je bolje kontrolirano tko može pokrenuti izmjene u trgovini.

## Provedene provjere i pripremljena dokumentacija

Izrađene su automatizirane provjere najvažnijih pravila: provjere ulaznih podataka, izračuna zalihe, cijena i popusta, dodavanja i ažuriranja proizvoda, varijanti, neaktivnih artikala, skupne obrade, zaustavljanja i obrade pogrešaka.

Pri provjeri za ovo izvješće uspješno su izvršena **53 automatizirana testa s ukupno 204 provjere očekivanog ponašanja**. Zaseban test povezivanja sa stvarnom trgovinom nije izvršen jer za njega nije postavljeno testno okruženje.

Pripremljene su upute za postavljanje aplikacije, sigurnosne postavke, probni rad i instalaciju dodatka za zalihe. Dodatak za webshop pripremljen je i kao instalacijski paket.

**Korist za naručitelja:** ključna pravila imaju ponovljive provjere, a održavanje i buduće izmjene poduprti su dokumentacijom.

## Uvjeti korištenja u stvarnoj trgovini

Opisane mogućnosti implementirane su u aktualnoj verziji projekta. Za njihovo korištenje potrebno je na webshopu imati aktivnu odgovarajuću verziju izrađenog dodatka i pravilno postavljenu vezu s aplikacijom.

Javni prikaz dostupnosti po poslovnicama uključuje se zasebno. Uvoz pokreće korisnik; izrađeni nastavak obrade koristi odabrani početni zapis. Prije potvrde rada u stvarnoj trgovini potrebno je provesti probni uvoz i provjeriti rezultate u webshopu.

Ukupni opseg odrađenog posla obuhvaća obnovu osnovnog uvoza, proširenje upravljanja zalihama, izradu dodatka za trgovinu, obradu varijanti i neaktivnih artikala, nove kontrole uvoza, evidenciju rezultata, zaštitu podataka te provjere i dokumentaciju. Time je postavljena znatno potpunija osnova za svakodnevno usklađivanje poslovnog sustava i webshopa.

