# अपडेट / Upgrade

1. **Backup** — phpMyAdmin मा DB export + `public_html` को zip।
2. **कोड** — नयाँ फाइलहरू upload (credentials भएको `includes/database.local.php` नमेट्नुहोस्)।
3. **Database** — Admin → **Run Migration** वा phpMyAdmin बाट **`database/install.sql`** चलाउनुहोस् (idempotent; धेरै changes पहिले नै भइसकेका भए skip हुन्छ)।

नयाँ सेटअपको लागि: `README.md` र `database/DEPLOY.md`।
