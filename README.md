# Employee Timesheet & Workforce Analytics

**Think Marketing Workforce** — a production-ready WordPress plugin for date-based time tracking, overtime, approvals, leave & requests, departments, employee management, analytics, and Excel exports.

> **Full system reference:** [docs/SYSTEM-DOCUMENTATION.md](docs/SYSTEM-DOCUMENTATION.md)

---

## Quick start

### 1. Install

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
| Export / Audit logs | — | — | ✓ | ✓ |

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
- **Profile** — view/edit details, send password reset (`?employee_id=` on Employees tab)

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

## Working rules (defaults)

| Setting | Value |
|---------|-------|
| Work week | Sunday → Thursday |
| Working hours | 10:00 – 18:00 |
| Time interval | 30 minutes |
| Overtime | After 18:00, min 30 min, rounded to 30 min |

Configurable by Owner in **Settings**.

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
| Vacations | HR, Owner |
| Owner Analytics | Owner |
| Settings | Owner |
| Audit Logs | HR, Owner |

The **Timesheets** admin menu opens My Timesheet for employees and HR Dashboard for HR/Owner.

---

## Portal UI

- Light / dark theme toggle (saved per user)
- Scoped CSS for Elementor/theme compatibility
- Toasts & confirm dialogs via `etwa-ui.js`
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
