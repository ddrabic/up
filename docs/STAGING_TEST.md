# Ručni integracijski test prema staging trgovini

Test nikad ne koristi produkcijske vrijednosti iz repozitorija. Pokreće se samo
uz zaseban WooCommerce Read/Write ključ staging trgovine:

```bash
UPP_STAGING_WOO_URL=https://staging.example.com \
UPP_STAGING_WOO_KEY=ck_xxx \
UPP_STAGING_WOO_SECRET=cs_xxx \
vendor/bin/phpunit tests/Integration/StagingConnectionTest.php
```

URL je osnovni URL bez `/wp-json/wc/v3`. SSL provjera ostaje uključena. Ako
varijable nisu postavljene, integracijski test označava se preskočenim i redovni
test suite ostaje potpuno offline.
