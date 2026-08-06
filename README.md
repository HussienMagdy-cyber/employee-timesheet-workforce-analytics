# Employee Timesheet & Workforce Analytics

**Think Marketing Workforce** — a production-ready WordPress plugin for date-based time tracking, overtime, compensation, approvals, leave & requests, departments, employee management, analytics, and Excel exports.

**Current version:** `1.1.3` (database schema `1.17.0`)

> **Full system reference:** [docs/SYSTEM-DOCUMENTATION.md](docs/SYSTEM-DOCUMENTATION.md)

---

## What's new in 1.1.x

### After-hours entry types
- Employees logging time **at or after 6:00 PM** must choose **Overtime** or **Compensation**
- Entry type is stored on each row (`entry_type`) and shown on timesheet chips (OT / Comp tags)
- **Overtime** hours flow into Client Analytics **Overtime Hours**
- **Compensation** hours count toward **Regular Hours** and **Combined Hours** (also shown separately on the client detail page)

### Client Analytics
- Per-client **Regular**, **Overtime**, **Combined**, **Compensation**, and **OT %** KPIs
- Employee breakdown table with Regular / OT / Comp / Combined columns
- Overtime cost uses a **1×** multiplier (same hourly rate as regular time)
- Analytics cache refreshes automatically when entries are saved

### Email & branding
- **Settings → Email** — branded HTML wrapper (greeting, body slot, signature footer)
- Configurable appreciation line, closing text, logo, and signature rows
- Live preview and test send in admin
- Applied to workflow notifications (timesheets, leave, requests, reports, reminders)
- Password setup/reset emails remain **plain text** via WordPress core (separate from branded template)

See [docs/SYSTEM-DOCUMENTATION.md — Email system](docs/SYSTEM-DOCUMENTATION.md#email-system) for full details.

### UX & naming
- **All Clients** client renamed to **Moderation** (requires detailed notes on each entry)
- **View Weeks** shortened to **View** in HR dashboards
- Guest users on protected pages are **redirected to login** (no inline “please log in” message)

### Timesheet reliability
- Draft snapshots preserve `entry_type` and hour columns on save/submit
- Database migrations (`1.13.0`–`1.17.0`) repair legacy OT/comp hour splits and prevent double-counting
- Hard refresh after upgrade if timesheet JS looks stale (`employee-dashboard.js?ver=1.1.3`)

### Reports & Exports
- New **Reports & Exports** portal tab and WP admin page for HR/Owner
- Five Excel report types with shared date-range presets (This Week, This Month, Last 3 Months, etc.)
- **All Clients Comparison** — live dashboard with charts, department/employee filters, and Excel export
- Centralized calculations via `ReportingService` (same numbers as exports)
- Shared frontend utilities: `etwa-api.js` (nonce refresh), `etwa-date-range.js` (export modals)

---

## Quick start

### 1. Install

**Windows + Local WP** (regular PowerShell — `php` is not on PATH):

```powershell
cd wp-content/plugins/employee-timesheet-workforce-analytics
powershell -ExecutionPolicy Bypass -File scripts/install-composer-deps.ps1
```

Or double-click `scripts/install-composer-deps.bat`.

**Local Site Shell** (if `composer` is available there):

```bash
cd wp-content/plugins/employee-timesheet-workforce-analytics
composer install --no-dev
```

Activate in **WordPress Admin → Plugins**.

### 2. Create the portal page

Add a WordPress page (e.g. **Workforce Portal**) with:

```
[etwa_portal]
```

Add it to your site menu. This is the main app for all logged-in users.

### 3. Auth pages (auto-created)

On activation the plugin creates:

| URL | Shortcode | Purpose |
|-----|-----------|---------|
| `/login/` | `[etwa_login]` | Branded login |
| `/set-password/` | `[etwa_set_password]` | First-time password setup & forgot password |

There is **no public sign-up**. HR/Owner creates employees; they receive a password-setup email.

### 4. Assign roles

| Role | Who |
|------|-----|
| **Employee** | Staff — timesheet, requests, profile |
| **Department Head** | Team lead — dept dashboard + approvals (must be assigned on a department) |
| **HR Manager** | HR — employees, departments, vacations, HR dashboard |
| **Owner** | Full access — analytics, clients, settings |

WordPress **Administrators** get all capabilities for testing.

---

## Authentication flow

```
HR creates employee  →  email with set-password link
Employee sets password at /set-password/
Employee logs in at /login/  →  redirected to portal
```

- Default `wp-login.php` redirects to custom auth pages
- WordPress admin bar is hidden on the frontend
- Non-admins cannot access `/wp-admin/`

See [Authentication](docs/SYSTEM-DOCUMENTATION.md#authentication) in the system docs.

---

## Roles & access

| Feature | Employee | Dept Head | HR | Owner |
|---------|:--------:|:---------:|:--:|:-----:|
| My Timesheet / History | ✓ | ✓ | — | — |
| Requests (leave, etc.) | ✓ | ✓ | — | — |
| My Profile | ✓ | ✓ | ✓ | ✓ |
| Department Dashboard | — | ✓* | — | — |
| Team / HR Requests | — | ✓ | ✓ | ✓ |
| HR Dashboard | — | — | ✓ | ✓ |
| Employees & Departments | — | — | ✓ | ✓ |
| Vacations (HR) | — | — | ✓ | ✓ |
| Client Analytics | — | — | ✓ | ✓ |
| Owner Analytics | — | — | ✓ | ✓ |
| Clients & Settings | — | — | — | ✓ |
| Export / Reports / Audit logs | — | — | ✓ | ✓ |

\*Requires assignment as department head on a department.

---

## Frontend portal

**Recommended:** one page with `[etwa_portal]` — sidebar navigation shows only tabs the user can access.

### Portal tabs (by role)

| Tab | Employee | Dept Head | HR | Owner |
|-----|:--------:|:---------:|:--:|:-----:|
| My Timesheet | ✓ | ✓ | | |
| History | ✓ | ✓ | | |
| Requests | ✓ | ✓ | | |
| Department Dashboard | | ✓* | | |
| HR Dashboard | | | ✓ | ✓ |
| Reports & Exports | | | ✓ | ✓ |
| Client Analytics | | | ✓ | ✓ |
| Employees | | | ✓ | ✓ |
| Departments | | | ✓ | ✓ |
| Holidays | | | ✓ | ✓ |
| Requests (HR queue) | | ✓ | ✓ | ✓ |
| Vacations | | | ✓ | ✓ |
| Logs | | | ✓ | ✓ |
| Analytics | | | | ✓ |
| Clients | | | | ✓ |
| My Profile | ✓ | ✓ | ✓ | ✓ |

Navigate with `?etwa_view=timesheet&etwa_week=YYYY-MM-DD` (Sunday of the work week).

### Timesheet drafts & HR review

Employees work in **draft** or **rejected** weeks before submitting. HR does not see live edits until the employee clicks **Save Draft**.

| Who | What they see |
|-----|----------------|
| **Employee** | Live timesheet; status labels: *Draft Saved*, *Unsaved changes — not visible to HR yet*, *Not saved for review yet* |
| **HR / Dept Head** | Last **saved draft** snapshot when the employee has saved; otherwise an **empty week** (never blocked from opening the report) |
| **After Submit** | Submitted data for approval |

HR can open any employee week from **HR Dashboard → employee weeks** even when no entries exist yet.

### My Profile

All portal users can view **My Profile**. Who can edit what:

| Field | Employee (self) | HR / Owner |
|-------|:-----------------:|:----------:|
| Full name, email, phone, birthday | ✓ | ✓ |
| Role, job title, join date | — | ✓ |
| Send password reset / deactivate | — | ✓ |

Employees save changes via `PUT /employees/me/profile`. HR edits any employee via the **Employees** tab or full profile endpoint.

### Individual shortcodes

Use standalone shortcodes if not using the unified portal:

| Shortcode | Audience |
|-----------|----------|
| `[etwa_portal]` | All roles — unified app |
| `[etwa_login]` | Guests — login form |
| `[etwa_set_password]` | Guests — password setup / reset |
| `[etwa_timesheet]` | Employee |
| `[etwa_my_vacation]` | Employee (legacy; portal uses Requests) |
| `[etwa_requests]` | Employee |
| `[etwa_requests_hr]` | HR / Dept Head |
| `[etwa_hr]` | HR / Owner |
| `[etwa_reports]` | HR / Owner |
| `[etwa_vacations]` | HR / Owner |
| `[etwa_employees]` | HR / Owner |
| `[etwa_analytics]` | Owner |
| `[etwa_client_analytics]` | HR / Owner |
| `[etwa_clients]` | Owner |
| `[etwa_holidays]` | HR / Owner |

---

## Employees (HR / Owner)

- **Create** — name, email, role, department, job title, join date, birthday; password setup email sent automatically
- **Edit** (list) — role, department, hourly cost (EGP)
- **Profile** (HR) — view/edit all fields, send password reset, deactivate/reactivate (`?employee_id=` on Employees tab)
- **My Profile** (all roles) — employees edit their own name, email, phone, and birthday; HR-only fields remain read-only

Assignable roles: Owner, HR Manager, Department Head, Employee.

---

## Vacation & leave

Single **annual leave pool** per employee. HR sets entitlement; approved leave (except unpaid) deducts from that pool.

| Leave type | Deducts from annual balance? |
|------------|:----------------------------:|
| Annual / Sick / Emergency / Compensatory | Yes |
| Unpaid | No |

**Employee:** Portal → **Requests** → submit leave.  
**HR:** Portal → **Vacations** → allocations, approvals, calendar, analytics.

Dual approval (department + HR) when enabled in settings.

---

## Reports & Exports (HR / Owner)

Portal → **Reports & Exports** or WP Admin → **Reports & Exports**.

| Report | Description |
|--------|-------------|
| Employee Timesheet | Entry-level detail for one employee in a date range |
| Client Report | All activity for a selected client |
| Client Labor Cost | Hours and EGP labor cost by employee for a client |
| Employee Hours by Client | How each employee's hours split across clients |
| All Clients Comparison | Side-by-side client KPIs with live charts and Excel export |

Date presets: This Week, This Month, Last Month, Last 3/6 Months, This Year, Custom Range.

See [REST API — Reports](docs/REST-API.md#reports-centralized) for endpoint details.

---

## Working rules (defaults)

| Setting | Value |
|---------|-------|
| Work week | Sunday → Thursday |
| Working hours | 10:00 – 18:00 |
| Time interval | 30 minutes |
| After-hours entries | At or from 18:00 — **Overtime** or **Compensation** required |
| Overtime cost multiplier | 1× (same rate as regular) |

Configurable by Owner in **Settings** (work day end, interval, OT rules).

### Entry types (after 6:00 PM)

| Type | Stored in | Client Analytics |
|------|-----------|------------------|
| **Regular** | `regular_hours` | Regular Hours |
| **Overtime** | `overtime_hours` | Overtime Hours |
| **Compensation** | `regular_hours` + `duration_minutes` | Regular + Combined (+ Comp KPI on detail) |

Break entries are excluded from client analytics totals.

---

## REST API

```
/wp-json/etwa/v1/
```

| Context | Auth header | Nonce |
|---------|-------------|-------|
| Portal (logged in) | `X-WP-Nonce` | `wp_rest` |
| Login / password pages | `X-ETWA-Nonce` | `etwa_auth` |

See [docs/REST-API.md](docs/REST-API.md) for the full endpoint list.

Notable profile endpoints:

| Method | Path | Who |
|--------|------|-----|
| GET | `/employees/me/profile` | Any logged-in portal user |
| PUT | `/employees/me/profile` | Employee — own name, email, phone, birthday |
| PUT | `/employees/{id}/profile` | HR / Owner — full profile fields |

Notable timesheet endpoints:

| Method | Path | Purpose |
|--------|------|---------|
| POST | `/timesheets/{id}/save-draft` | Employee saves draft snapshot for HR review |
| POST | `/timesheets/{id}/submit` | Employee submits week for approval |
| GET | `/reports/filters` | Report dropdown options and date presets |
| POST | `/reports/export/{type}` | Generate Excel report |
| GET | `/reports/all-clients-comparison` | Live client comparison data |
| GET | `/portal/session` | Refresh REST/AJAX nonces |

---

## Architecture

| Layer | Location |
|-------|----------|
| Bootstrap | `employee-timesheet-workforce-analytics.php` |
| Plugin core | `includes/class-etwa-plugin.php` |
| REST API | `includes/REST/` |
| Services | `includes/Services/` |
| Repositories | `includes/Repositories/` |
| Portal & auth | `includes/Frontend/` |
| Templates | `templates/admin/`, `templates/frontend/` |
| Assets | `assets/css/`, `assets/js/` |

Patterns: OOP, Service Layer, Repository. Storage: custom MySQL tables + user meta for profiles.

---

## Documentation index

| Document | Description |
|----------|-------------|
| [SYSTEM-DOCUMENTATION.md](docs/SYSTEM-DOCUMENTATION.md) | Complete system reference |
| [REST-API.md](docs/REST-API.md) | HTTP API endpoints |
| [DATABASE-SCHEMA.md](docs/DATABASE-SCHEMA.md) | Database tables |
| [MVP-ROADMAP.md](docs/MVP-ROADMAP.md) | Planned features |

---

## Admin pages

| Page | Who |
|------|-----|
| My Timesheet | Employee |
| HR Dashboard | HR, Owner |
| Reports & Exports | HR, Owner |
| Vacations | HR, Owner |
| Client Analytics | HR, Owner |
| Owner Analytics | Owner |
| Email Settings | Owner |
| Settings | Owner |
| Audit Logs | HR, Owner |

The **Timesheets** admin menu opens My Timesheet for employees and HR Dashboard for HR/Owner.

---

## Portal UI

- Light / dark theme toggle (saved per user)
- Scoped CSS for Elementor/theme compatibility
- Toasts & confirm dialogs via `etwa-ui.js`
- **Save Draft** on timesheet — shares the current week with HR without submitting
- **Copy Previous Week** on timesheet — copies from the week before the one you are viewing

---

## Requirements

- WordPress 6.0+
- PHP 8.0+
- MySQL 5.7+ / MariaDB 10.3+
- Composer (PhpSpreadsheet)

---

## Uninstall

To remove all plugin data on uninstall, add to `wp-config.php`:

```php
define( 'ETWA_DELETE_DATA', true );
```

---

## License

GPL-2.0-or-later
