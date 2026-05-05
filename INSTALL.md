# 🚀 Install Guide — Cooperative Website v3.2

**Total time: ~5 minutes. कुनै code edit गर्नुपर्दैन।**

---

## ✅ Method 1: Install Wizard (सिफारिस — सबभन्दा सजिलो)

### Step 1: Files Upload
1. यो ZIP extract गर्नुहोस्
2. **सबै files** cPanel File Manager वा FileZilla बाट `public_html/` मा upload गर्नुहोस्
3. `.htaccess` file पनि upload हुनु पर्छ (hidden file — "Show Hidden Files" enable गर्नुहोस्)

### Step 2: Browser मा Wizard खोल्नुहोस्
```
https://yourdomain.com/install.php
```

Wizard ले गर्छ:
- ✅ System requirements जाँच
- ✅ Database connection test
- ✅ सबै tables auto-create (`database/install.sql` बाट)
- ✅ Cooperative नाम, रंग, contact details save
- ✅ Admin username + password set
- ✅ Config file auto-लेखिन्छ (`includes/database.local.php`)
- ✅ Install lock — wizard दोस्रो पटक नखुल्ने

### Step 3: Install सकेपछि
1. **Admin Panel:** `https://yourdomain.com/admin/` → आफ्नो username/password
2. **Security:** `install.php` file delete गर्नुहोस् (cPanel File Manager बाट)
3. **Logo Upload:** Admin → Settings → Logo मा सहकारीको logo राख्नुहोस्

---

## 🔧 Method 2: Manual Install (Advanced users)

### Step 1: Files Upload
माथि जस्तै।

### Step 2: Database config
`includes/database.local.php.example` copy गरी `includes/database.local.php` बनाउनुहोस्:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_dbname');
define('DB_USER', 'your_dbuser');
define('DB_PASS', 'your_password');
define('SITE_URL', 'https://yourdomain.com/');
define('CRED_MASTER_KEY', 'your-32-char-random-key');
```

### Step 3: Database tables
phpMyAdmin → आफ्नो DB → SQL tab → `database/install.sql` को content paste → Go

### Step 4: Encryption key (Manual method मा)
```bash
php -r "echo bin2hex(random_bytes(16));"
```
आएको 32-char string `CRED_MASTER_KEY` मा राख्नुहोस्।

---

## 📋 Hosting Requirements

| आवश्यकता | न्यूनतम |
|----------|---------|
| PHP | 8.0+ (8.2 सिफारिस) |
| MySQL / MariaDB | 5.7+ / 10.3+ |
| PDO + PDO MySQL | enabled |
| Disk space | 50MB+ |
| RAM | 128MB+ |

> **cPanel shared hosting** (Namecheap, Hostinger, Bluehost, etc.) मा यी सबै default मा available हुन्छन्।

---

## 🩹 Troubleshooting

| समस्या | समाधान |
|--------|--------|
| install.php खुल्दैन | Files राम्ररी upload भए? .htaccess छ? |
| DB connection fail | DB name, user, password cPanel मा जाँच्नुहोस् |
| `includes/` writable छैन | cPanel → File Manager → includes/ → Permissions → 755 |
| Logo देखिँदैन | Admin → Settings → Logo upload गर्नुहोस् |
| Color बदलिँदैन | Admin → Settings → Primary Color → Save; फिर browser cache clear (Ctrl+Shift+R) |
| 500 Error | cPanel → Error Logs हेर्नुहोस् |

---

## 🔄 Update (नयाँ version आए)

1. **Backup:** cPanel → JetBackup वा phpMyAdmin → Export
2. नयाँ files upload (overwrite)
3. phpMyAdmin → SQL → `database/install.sql` पुनः run (safe — `IF NOT EXISTS` छ)
4. Admin Panel → Dashboard → System Info जाँच्नुहोस्

---

## 🎉 पहिलो login पछि गर्ने काम

1. **Logo upload** — Admin → Settings → Logo
2. **Site name बदल्ने** — Admin → Settings → Site Name
3. **Primary color** — Admin → Settings → Primary Color (सहकारीको brand color)
4. **Contact details** — phone, email, address
5. **Homepage text** — Hero title, slogan, About text
6. **Admin को नाम बदल्ने** — Admin → Manage Staff → Edit

---

*Wizard method मा कुनै code edit गर्नुपर्दैन — browser बाटै सबै हुन्छ।*
