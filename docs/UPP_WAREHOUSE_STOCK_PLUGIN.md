# UPP Warehouse Stock plugin

Plugin prikazuje ERP zalihu po poslovnicama na WooCommerce stranici proizvoda.
Kompatibilan je s PHP-om 7.4 ili novijim.

## Lokacije

| Šifra | Naziv i adresa |
|---|---|
| 202 | Čakovec, Bana Jelačića 4 |
| 204 | Virovitica, S. Radića 77 |
| 205 | Čakovec, Svetojelenska 15 |
| 208 | Koprivnica, B. Radića 23 |
| 209 | Varaždin, Optujska 50 |
| 701 | Skladište |

Šifra 2018 nije uključena u web-zalihu niti u prikaz jer nije definirana kao
poslovnica/skladište.

## Instalacija

1. U WordPress administraciji otvoriti **Dodaci > Dodaj novi > Prenesi dodatak**.
2. Prenijeti `dist/upp-warehouse-stock.zip`.
3. Aktivirati **UPP Warehouse Stock**.
4. Ponovno pokrenuti UPP import kako bi se stanje upisalo postojećim artiklima.

## Ponašanje importa

Importer u postojećem WooCommerce REST updateu šalje `upp_warehouse_stock`
meta-podatak. Vrijednost uvijek sadrži svih šest definiranih šifri. Plugin prije
spremanja briše prethodnu vrijednost za artikl ili varijaciju te upisuje novi
snapshot, pa se stara stanja ne mogu zadržati kada skladište nestane iz ERP
zapisa.

WooCommerce `stock_quantity` ostaje zbroj navedenih šest lokacija. Na javnoj
stranici artikla prikazuju se samo lokacije s količinom većom od nule.

## Primjer spremljenog snapshot-a

```json
{
  "202": 4,
  "204": 4,
  "205": 5,
  "208": 2,
  "209": 2,
  "701": 73
}
```

Plugin podržava jednostavne proizvode i varijacije. Kod varijabilnog proizvoda
popis lokacija mijenja se nakon odabira varijacije.
