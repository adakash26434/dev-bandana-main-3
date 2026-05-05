# 📂 आकाश सहकारी — Code Map / Project Structure

> **For:** Future developers, future-you, र manual maintenance
> **Updated:** v5.0 — सबै file कहाँ छ, के गर्छ, कहिले छुने भन्ने guide

---

## 🗺️ Quick Navigation

```
public_html/
├── 📁 admin/      → Admin panel (60+ pages)
├── 📁 member/     → Member portal
├── 📁 includes/   → Shared backend (engine room)
├── 📁 database/   → SQL migrations
├── 📁 scripts/    → Cron / CLI tools
├── 📁 assets/     → CSS, JS, images
├── 📁 uploads/    → User uploads (KYC docs etc.)
└── *.php          → Public website pages (~25 files)
```

---

## 🏗️ Architecture (3 Layers)

```
┌─────────────────────────────────────────┐
│   PUBLIC WEBSITE (no login)              │  ← *.php in root
│   - Anyone can view                      │
│   - Forms submit to admin queue          │
└─────────────────────────────────────────┘
                  ↕
┌─────────────────────────────────────────┐
│   MEMBER PORTAL (/member/)               │  ← Logged-in members
│   - View own applications                │
│   - EMI calc, profile, notifications     │
└─────────────────────────────────────────┘
                  ↕
┌─────────────────────────────────────────┐
│   ADMIN PANEL (/admin/)                  │  ← Staff
│   - Manage everything                    │
│   - Approve/reject applications          │
│   - Edit content, settings, templates    │
└─────────────────────────────────────────┘
                  ↕
┌─────────────────────────────────────────┐
│   includes/  (shared engine)             │
│   - DB connection, auth, notifications   │
│   - Used by ALL 3 layers above           │
└─────────────────────────────────────────┘
                  ↕
┌─────────────────────────────────────────┐
│   MySQL Database                         │
│   - 30+ tables (members, loans, etc.)    │
└─────────────────────────────────────────┘
```

---

## 📁 Folder-by-folder Reference

### `/admin/` — Admin Panel (60+ files)

| File | Purpose |
|------|---------|
| `index.php` | Dashboard (KPIs, recent activity) |
| `login.php` | Admin login page |
| `members.php` | Member list/add/edit |
| `loan-applications.php` | ऋण आवेदन management |
| `kyc-applications.php` | KYC review/approve |
| `account-applications.php` | खाता खोल्ने आवेदन |
| `appointments.php` | भेटघाट booking |
| `grievances.php` | गुनासो handling |
| `digital-services.php` | Digital service requests |
| `job-applications.php` | Career applications |
| `contact-messages.php` | Contact form submissions |
| `notices.php / news.php / gallery.php` | Content management |
| `loan-products.php / interest-rates.php` | Product management |
| `committees.php` | Office bearers / board |
| `notification-templates.php` | **v4** — Email/SMS template editor |
| `notification-settings.php` | Email/SMS gateway config |
| `notification-logs.php` | Sent notification history |
| `backup-restore.php` | Manual backup/restore |
| `run-migration.php` | Database version upgrade |
| `audit-log.php` | Action history (planned) |
| `settings.php` | Site-wide settings |
| `admin-users.php` | Manage admin accounts |
| `help-guide.php` | **v5** — User manual (in-panel) |

### `/member/` — Member Portal

| File | Purpose |
|------|---------|
| `login.php` | Member login (with Forgot password) |
| `register.php` | New member signup |
| `verify.php` | Email verification |
| `forgot-password.php` | Password reset request |
| `reset-password.php` | Password reset form |
| `index.php` | Dashboard (recent apps, quick actions) |
| `profile.php` | View/edit profile, KYC upload |
| `tracker.php` | All applications timeline |
| `finance.php` | **v4** — EMI calc + saving + transactions |
| `notifications.php` | In-app notifications |
| `_bootstrap.php` | Common setup (session, security headers) |

### `/includes/` — Engine Room (Backend Logic)

| File | What it does |
|------|--------------|
| `config.php` | DB connection, session, autoload helpers |
| `database.php` | PDO wrapper, getDB(), helper queries |
| `member-auth.php` | Member login/register/session/password |
| `notifications.php` | **Email + SMS sender** (~800 lines, complex) |
| `notification-templates.php` | **v4** — Load admin-edited templates from DB |
| `member-widgets.php` | **v4** — EMI calc, loan/saving/txn helpers |
| `audit.php` | **v3** — auditLog(), softDelete(), softRestore() |
| `compatibility.php` | PHP version compat shims |
| `ensure-tables.php` | Auto-create tables if missing |
| `header.php / footer.php` | Public site shared layout |
| `superadmin-config.php` | Superadmin-only feature flags |
| `nrb-forex-fetch.php` | NRB exchange rate scraper |
| `satisfaction-widget.php` | Public satisfaction poll widget |

### `/database/` — SQL Migrations

| File | Run when? |
|------|-----------|
| `database/install.sql` | Fresh install (schema + indexes) |
| `install.sql` | Upgrading to v3 (audit + soft-delete) |
| `install.sql` | Upgrading to v4 (notification templates) |

> **Tip:** Admin → Run Migration page बाट सजिलै run गर्न मिल्छ। Idempotent छन् — दोहोर्‍याए पनि safe।

### `/scripts/` — CLI / Cron

| File | Purpose | When to run |
|------|---------|-------------|
| `cron-backup.php` | **v4** — Daily DB+files backup | cPanel cron, राति 2 बजे |

### `/assets/` — Frontend resources

```
assets/
├── css/         → Theme CSS files
├── js/          → JavaScript
├── images/      → Logo, icons, banners
├── fonts/       → Custom fonts
└── uploads/     → (sometimes) user uploads
```

### Public root files (`/*.php`)

| File | URL | Purpose |
|------|-----|---------|
| `index.php` | `/` | Homepage |
| `about.php` | `/about.php` | संस्थाको बारेमा |
| `contact.php` | `/contact.php` | Contact form |
| `loan-apply.php` | `/loan-apply.php` | ऋण आवेदन form |
| `online-account.php` | `/online-account.php` | खाता खोल्ने form |
| `kyc.php` | `/kyc.php` | KYC online form |
| `appointment.php` | `/appointment.php` | भेटघाट book |
| `grievance.php` | `/grievance.php` | गुनासो form |
| `digital-services.php` | `/digital-services.php` | Service request form |
| `career.php / career-detail.php` | `/career.php` | Job listings + apply |
| `emi-calculator.php` | `/emi-calculator.php` | Public EMI calc |
| `application-tracker.php` | `/application-tracker.php` | Track by ID |
| `notices.php / news.php / gallery.php` | — | Public listings |
| `interest-rates.php / loan-products.php` | — | Public rate listings |
| `404.php` | — | Not found page |

---

## 🔑 Key Workflows (Data Flow)

### 1. Public form submission → Admin queue

```
Visitor fills form (loan-apply.php)
        ↓
Form POSTs to same file
        ↓
PHP validates + saves to DB (loan_applications table)
        ↓
sendAdminNotification() in includes/notifications.php
        ↓ (parallel)
   ├─→ Email to admin
   ├─→ SMS to admin
   └─→ Audit log entry
        ↓
Admin sees in /admin/loan-applications.php
```

### 2. Admin approves application → Member gets notified

```
Admin opens application detail page
        ↓
Updates status (approved/rejected) + comment
        ↓
sendMemberStatusUpdate() in includes/notifications.php
        ↓ (parallel)
   ├─→ Email to member (DB template)
   ├─→ SMS to member (DB template)
   ├─→ In-app notification (member portal)
   └─→ Audit log entry
        ↓
Member sees in /member/notifications.php + /member/tracker.php
```

### 3. Member registration → Email verify

```
member/register.php (POST)
        ↓
includes/member-auth.php → registerMember()
        ↓
Save to members table (status: pending_verification)
        ↓
Send verification email with token link
        ↓
Member clicks link → /member/verify.php?token=...
        ↓
Token validated → status = active
        ↓
Member can now login
```

---

## 🧠 Common Tables (DB)

| Table | Purpose |
|-------|---------|
| `members` | Registered members |
| `admin_users` | Admin accounts |
| `loan_applications` | ऋण आवेदन |
| `account_applications` | खाता खोल्ने आवेदन |
| `kyc_applications` | KYC submissions |
| `appointments` | भेटघाट bookings |
| `grievances` | गुनासो |
| `digital_service_requests` | Service requests |
| `job_applications` | Career applications |
| `contact_messages` | Contact form |
| `notices / news / gallery` | Content tables |
| `loan_products / interest_rates` | Product data |
| `committees / committee_members` | Board info |
| `settings` | key-value site config |
| `notification_templates` | **v4** — Editable email/SMS templates |
| `notification_logs` | Sent notification history |
| `notification_in_app` | Member in-app notifications |
| `audit_log` | **v3** — Admin action history |
| `member_sessions` | Member login sessions |

> **Soft-delete:** सबै main tables मा `deleted_at` column छ। `WHERE deleted_at IS NULL` query गर्दा only active rows आउँछन्।

---

## 🛠️ Common Edit Tasks

| Task | What to edit | Where |
|------|--------------|-------|
| Change site name/logo | — | Admin → Settings (no code) |
| Edit email/SMS message | — | Admin → Notification Templates (no code) |
| Add notice | — | Admin → Notices → Add (no code) |
| Change theme color | `assets/css/admin.css` or `assets/css/style.css` | CSS variables |
| Add new menu item in admin | `admin/includes/admin-header.php` | sidebar nav |
| Add new public page | Create `newpage.php` in root | Copy `about.php` as template |
| Add new admin page | Create file in `admin/` | Copy any existing `.php` as template |
| New DB table | Create migration SQL in `database/` | Run via `admin/run-migration.php` |

---

## ⚠️ Things to Never Touch (without backup)

- `.htaccess` — URL rewriting + security
- `includes/config.php` — DB credentials line
- `database/` SQL files (already-run migrations)
- Anything in `/backups/`

---

## 📞 Help

In-app manual: **Admin Panel → Help & Guide** (`/admin/help-guide.php`)

This document complements that — open both side-by-side when learning the codebase.
