# सहकारी Website Theme

**PHP + MySQL — नेपाली सहकारी संस्थाको लागि तयार website template**

---

## ✨ के-के छ?

| Portal | विवरण |
|--------|-------|
| 🌐 **Public Website** | Homepage, Services, News, Gallery, Contact, Forms |
| 🔐 **Admin Panel** | Dashboard, KYC, Members, Settings, Reports |
| 👤 **Member Portal** | Dashboard, ID Card, Tracker, Profile, Notifications |
| ✅ **Verify Portal** | Public ID card verification page |

### Admin Panel बाट सबै control:
- Logo, रंग, नाम, contact — code बिना
- Members approve/reject, ID card generate
- News, Notices, Gallery manage
- Online KYC review + approve
- Loan, Account, Appointment applications
- Staff management + roles
- Notification templates
- Developer credit (Powered by)

---

## 🚀 Install — 5 मिनेट

```
1. Files upload → public_html/
2. Browser मा खोल्नुहोस्: https://yourdomain.com/install.php
3. Wizard follow गर्नुहोस् (DB details, cooperative name, admin password)
4. Done! install.php delete गर्नुहोस्।
```

Detail: `INSTALL.md` हेर्नुहोस्।

---

## 🖥️ Hosting

- **PHP 8.0+** (8.2 सिफारिस)
- **MySQL 5.7+** वा **MariaDB 10.3+**
- **PDO MySQL** extension
- cPanel shared hosting मा perfect (Hostinger, Namecheap, etc.)

---

## 📁 फाइल संरचना

```
/
├── install.php          ← First-time install wizard
├── index.php            ← Public homepage
├── admin/               ← Admin panel
├── member/              ← Member portal
├── assets/              ← CSS, JS, images, uploads
├── includes/            ← Config, helpers, auth
│   ├── config.php
│   └── database.local.php   ← Wizard ले auto-बनाउँछ (gitignored)
├── database/
│   └── install.sql      ← Full DB schema
└── INSTALL.md           ← Install instructions
```

---

## 🔐 Default Admin

Install wizard मा आफ्नै username/password set गर्नुहोस्।

Default fallback (wizard नगरेको भए): `admin` / `password`
→ **पहिलो login पछि तुरुन्त बदल्नुहोस्!**

---

## 📖 Documentation

- `INSTALL.md` — Installation guide
- `UPGRADE.md` — Update instructions
- `CODE_MAP.md` — Developer reference
- `CHANGELOG.md` — Version history

---

*प्रत्येक सहकारीको आफ्नै installation — same ZIP, different settings.*
