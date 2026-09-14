=== UPP Warehouse Stock ===
Contributors: upp
Requires at least: 6.4
Requires PHP: 7.4
WC requires at least: 8.0
Stable tag: 1.2.0
License: GPLv2 or later

Sprema ERP snapshot zalihe po poslovnicama uz opcionalni prikaz dostupnih lokacija na stranici proizvoda.

== Installation ==

1. Prenesite ZIP kroz WordPress: Dodaci > Dodaj novi > Prenesi dodatak.
2. Aktivirajte UPP Warehouse Stock.
3. Pokrenite UPP import. Svaki update zamjenjuje prethodno stanje svih skladišta za artikl.
4. Ako želite javni prikaz, uključite ga pod WooCommerce > UPP zalihe.

== Behavior ==

Podržane šifre su 202, 204, 205, 208, 209 i 701. Lokacije s količinom 0 ne prikazuju se kupcu.
Ukupnom WooCommerce zalihom i dalje upravlja UPP importer kao zbrojem ovih skladišta.
Javni prikaz je zadano isključen. Ta postavka ne utječe na REST rutu ni spremanje podataka tijekom importa.

== Changelog ==

= 1.2.0 =
* Dodano skupno razrješavanje do 100 SKU-ova po REST zahtjevu.
* Resolver vraća naziv i kategorije kako bi importer izbjegao redundantno REST dohvaćanje prije ažuriranja.

= 1.1.0 =
* Dodana postavka za uključivanje i isključivanje javnog prikaza zaliha.
* Javni prikaz je zadano isključen, dok REST ruta i import uvijek ostaju aktivni.
