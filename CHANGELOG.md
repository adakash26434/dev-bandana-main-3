# CHANGELOG

## v6.5 — 2026-04-21 (Credential Vault polish + DB consolidation + Member chrome)
- **Office Credential Vault hardening**:
  - `CRED_MASTER_KEY` auto-generated on first run into `includes/.cred-master.key` (0600, blocked by `includes/.htaccess`). No manual config edit needed.
  - `admin/credentials.php` now logs reveals via `?ajax=log` endpoint (action: `view`).
  - Password is `required` on create; optional on edit (preserves existing).
  - `cred_log_action()` accepts `'create'` action.
- **Database consolidation** — single `database/install.sql` replaces:
  - `database.sql`, `v2-performance-migration.sql`, `v3-advanced-migration.sql`, `v4-templates-and-widgets.sql`, `v6-roles-and-credentials.sql`, `member-portal-v2-migration.sql` (all deleted).
  - Idempotent (`IF NOT EXISTS` everywhere) — safe to re-run, no data loss.
  - All admin pages (`db-setup`, `run-migration`, `site-health`, `site-setup`, `error-log`, `institutional-profile`, `update-checklist`, `help-guide`) updated to point at the single file.
- **Member panel unified chrome**: new `member/includes/chrome.php` + `chrome-foot.php` (shared topbar, live notification bell, auto-active nav). `profile.php`, `tracker.php`, `notifications.php` refactored. `id-card.php` kept standalone (print layout).
- **Dead code removed**: `includes/safe-query.php` (unreferenced).

## v6.4 — 2026-04-21 (Tabbed Admin Dashboard)
- Restructured `admin/dashboard.php` into a two-tab layout:
  - **Tab 1 — Office Dashboard** (default): grid of top 12 saved sites with logos/favicons, username preview, category, "Open" link, plus an "+ Add" card → `credentials.php`.
  - **Tab 2 — सदस्य अनुरोध (Member Requests)**: existing KPI cards, charts, quick actions, recent activity feed.
- Tab state persists in `localStorage` (`dashActiveTab`). Chart.js redraws on tab switch.
- Removed v6.3 purple promo callout (superseded by Office tab).
- Sort uses `sort_order ASC, id DESC` with `is_active = 1` filter (matches actual schema).

## v6.3 — 2026-04-21 (RBAC enforcement + Office Dashboard promo)
- Added `require_role('admin')` POST guards to 8 member-action pages: `account-applications`, `loan-applications`, `kyc-applications`, `members`, `grievances`, `job-applications`, `digital-service-requests`, `auction-bids`. Staff can view; only admin+ can mutate.
- Added Office Dashboard promo callout + quick-action pill on dashboard (later replaced by v6.4 tab).

## v6.2 — 2026-04-21 (Header polish)
- Added matching FontAwesome icons to Services, Team, More dropdowns (desktop + mobile nav) in `includes/header.php`.

## v6.1 — 2026-04-21 (Sidebar gradient fix)
- Removed circular `--primary-color: var(--primary-color)` self-reference in `admin/assets/admin.css` that broke the sidebar gradient after design-tokens wiring.

## v6.0 — 2026-04-21 (Deep Cleanup + Uniformity Pass)

### 🧹 Removed (dead / duplicate / dev-only files)
- `assets/css/_public-fixed/` — duplicate, unreferenced
- `assets/css/public/` — duplicate, unreferenced
- `admin/admin-tokens-fix.css` — duplicate of `assets/css/admin-tokens.css`
- `_apply-patches.php` — one-shot patcher (changes now applied)
- `verify-security.php` + `archive/verify-security.php` — dev-only diagnostic
- `debug.php`, `setup.php`, `setup-config.php` — dev/install only
- `_install/` — install bundle (already deployed)
- `member/_bootstrap-error-style.txt` — stale snippet
- `logs/*` — old log files cleared (`.htaccess` retained)
- Old upgrade notes consolidated: `UPGRADE_v2.md`, `UPGRADE_v2.1_phase1.md`,
  `UPGRADE_v3_final.md`, `UPGRADE_v4_final.md`, `UPGRADE_v5_phase2.md`

### 🎨 Uniformity (Public + Member + Admin एकै font/color/spacing)
- `includes/header.php` — `design-tokens.css` + `_color-vars.php` अब load हुन्छन्
- `admin/includes/admin-header.php` — `design-tokens.css` + `admin-tokens.css` +
  `_color-vars.php` load + Noto Sans Devanagari font थपियो (पहिले Mukta मात्र)
- `member/_bootstrap.php` — नयाँ `memberHeadAssets()` helper:
  सबै member पृष्ठ मा एकै font + tokens + admin-overridable colors
- सबै member पृष्ठ (`index`, `profile`, `tracker`, `notifications`,
  `id-card`, `password-reset-request`, `login`) ले अब tokens प्रयोग गर्छन्
- `panel-uniform.php` अब member panel मा पनि auto-load हुन्छ
  (पहिले public + admin मात्र)

### ✨ Member Login (`member/login.php`) Refresh
- सबै hard-coded greens → design tokens (admin Settings बाट color बदल्दा यहाँ पनि लागू)
- Eye-catching: animated gradient, floating orbs, dot-pattern overlay
- "Verified Member Portal" eyebrow badge + warmer hero
- Form panel: lift-in animation + "Member Login" pill badge
- Improved focus rings, hover lifts, tracker callout

### 📦 Result
- File count: **209 → 185** (~12% lighter)
- Zero references broken (all removed files were unreferenced or dev-only)

---

## v3.1.0 — Earlier 2026-04-21 (Auto-Integration)
- `_apply-patches.php` introduced (now retired in v6 after applying)
- `database/install.sql` consolidated migration
- Removed `database/v6-roles-and-credentials.sql`

## v3.0.0 — Clean Bundle
- Removed unused `_install/`, `_security/` folders
- Renamed `_public-fixed/` → `assets/css/public/`
- Centralized `admin-tokens.css` to `assets/css/`
- Added root `.htaccess`, `README.md`, `CHANGELOG.md`

## v2.0 — Phase 2
- 1,200+ hardcoded colors → CSS variables
- SQL injection fixes
- Mukta font unified

## v1.0 — Phase 1
- Initial design tokens, RBAC, AES-256 credential vault
