# Think Marketing Workforce — System Documentation

Complete reference for the **Employee Timesheet & Workforce Analytics** WordPress plugin (namespace `ETWA`).

---

## Table of contents

1. [Overview](#overview)
2. [Installation & setup](#installation--setup)
3. [Authentication](#authentication)
4. [Roles & permissions](#roles--permissions)
5. [Portal application](#portal-application)
6. [Feature modules](#feature-modules)
7. [Admin backend](#admin-backend)
8. [REST API](#rest-api)
9. [Database](#database)
10. [Configuration & settings](#configuration--settings)
11. [Architecture & code layout](#architecture--code-layout)
12. [Scheduled jobs & notifications](#scheduled-jobs--notifications)
13. [Troubleshooting](#troubleshooting)

---

## Overview

The plugin replaces weekly Excel timesheets with a web-based workforce platform for **Think Marketing**. It provides:

- **Date-based timesheets** (Sun–Thu work week by default)
- **Overtime** tracking and approval
- **Leave & requests** (annual pool, dual approval where configured)
- **Departments** and department-head dashboards
- **Employee management** (HR/Owner creates accounts, assigns roles & departments)
- **Analytics & exports** (client analytics, owner analytics, Excel reports)
- **Unified frontend portal** with role-based navigation

**Tech stack:** WordPress 6+, PHP 8+, custom MySQL tables, REST API (`/wp-json/etwa/v1/`), jQuery frontend modules, PhpSpreadsheet for exports.

**Branding:** User-facing product name is **Think Marketing** (`BrandHelper::name()`). WordPress role display names are prefixed (e.g. “Think Marketing Employee”); portal UI shows plain labels (Owner, HR Manager, etc.).

---

## Installation & setup

### Requirements

| Requirement | Version |
|-------------|---------|
| WordPress | 6.0+ |
| PHP | 8.0+ |
| MySQL / MariaDB | 5.7+ / 10.3+ |
| Composer | For PhpSpreadsheet |

### Steps

1. Copy the plugin folder to `wp-content/plugins/employee-timesheet-workforce-analytics/`
2. Run `composer install --no-dev` inside the plugin directory
3. Activate the plugin in **Plugins**
4. On activation, the plugin:
   - Creates database tables (`Schema::create_tables()`)
   - Registers ETWA roles and capabilities
   - Creates auth pages (`/login/`, `/set-password/`) if missing
   - Seeds default settings and system clients (e.g. SDM, Break)

### Recommended WordPress pages

| Page slug | Shortcode | Purpose |
|-----------|-----------|---------|
| `login` | `[etwa_login]` | Branded login (auto-created) |
| `set-password` | `[etwa_set_password]` | Password setup / forgot password (auto-created) |
| `portal` (or any slug) | `[etwa_portal]` | Main workforce app — **create this manually** |

Point your site menu or homepage redirect to the portal page for logged-in users and `/login/` for guests.

### First-time HR setup

1. Assign a user **Owner** or **HR Manager** role (or use WordPress Administrator for testing)
2. Log in at `/login/`
3. Open **Employees** → create staff (no password required; setup email is sent)
4. Open **Departments** → create departments and assign department heads
5. Open **Vacations** → set annual leave allocations
6. Configure **Settings** (Owner): work week, hours, emails, etc.

---

## Authentication

There is **no public sign-up**. Employees are invited by HR/Owner only.

### Flow

```
HR creates employee → retrieve_password() email sent
        ↓
Employee opens /set-password/?key=...&login=...
        ↓
Sets password → redirected to /login/
        ↓
Logs in → redirected to portal ([etwa_portal] page)
```

### Auth pages

| Page | Shortcode | Template |
|------|-----------|----------|
| Log In | `[etwa_login]` | Minimal full-screen plugin template (no theme header/footer) |
| Set Password | `[etwa_set_password]` | Same; shows reset form when `key` + `login` query args present |

### Security behaviour

- `wp-login.php` redirects to custom auth pages (except logout)
- WordPress admin bar is **hidden** on the frontend for all users
- Non-administrators are **blocked from `/wp-admin/`** and stay in the portal
- Auth REST endpoints use `X-ETWA-Nonce` (not `X-WP-Nonce`) to avoid WordPress cookie-check conflicts
- Portal REST endpoints use logged-in cookie auth + `X-WP-Nonce` (`wp_rest`)

### Key files

| File | Purpose |
|------|---------|
| `includes/Frontend/Auth.php` | Shortcodes, hooks, template override |
| `includes/Services/AuthService.php` | Login, reset, validation |
| `includes/REST/AuthController.php` | `/auth/login`, `/auth/forgot-password`, `/auth/reset-password` |
| `includes/Helpers/AuthHelper.php` | Page URLs, email body, page auto-creation |
| `templates/frontend/auth-*.php` | Login / set-password UI |
| `assets/js/auth.js` | Form submission |

---

## Roles & permissions

### WordPress roles

| Slug | Portal label | Summary |
|------|--------------|---------|
| `etwa_employee` | Employee | Own timesheet, history, requests, profile |
| `etwa_department_head` | Department Head | Employee features + dept dashboard + team request approvals (when assigned as head) |
| `etwa_hr_manager` | HR Manager | HR dashboard, employees, departments, vacations, requests, analytics (no own timesheet) |
| `etwa_owner` | Owner | HR features + advanced analytics, clients, settings, period locks |

WordPress **Administrators** receive all capabilities for testing (including own timesheet).

### Capability matrix (summary)

| Feature | Employee | Dept Head | HR | Owner |
|---------|:--------:|:---------:|:--:|:-----:|
| My Timesheet / History | ✓ | ✓ | — | — |
| Requests (leave, etc.) | ✓ | ✓ | — | — |
| Department Dashboard | — | ✓* | — | — |
| Team / HR Requests | — | ✓ | ✓ | ✓ |
| HR Dashboard | — | — | ✓ | ✓ |
| Employees & Departments | — | — | ✓ | ✓ |
| Vacations (HR) | — | — | ✓ | ✓ |
| Client Analytics | — | — | ✓ | ✓ |
| Owner Analytics | — | — | ✓ | ✓ |
| Clients & Settings | — | — | — | ✓ |
| Export / Audit logs | — | — | ✓ | ✓ |

\*Dept Head features require the user to be assigned as **department head** on a department.

### Employee management roles

HR/Owner can assign any of the four ETWA roles when creating or editing employees. Managed users are identified by `EmployeeProfileHelper::is_managed_user()`.

---

## Portal application

### Entry point

Shortcode: **`[etwa_portal]`**

Renders `templates/frontend/portal-shell.php` — sidebar navigation, header (theme toggle, logout), and dynamic content area.

### URL routing

Views are selected via query argument:

```
?etwa_view=timesheet&etwa_week=2026-07-06
?etwa_view=employees&employee_id=42
?etwa_view=hr&review_user=5&etwa_week=2026-07-06
```

Navigation URLs are built by `PortalNav::view_url()`.

### Portal tabs by role

| Tab ID | Label | Roles |
|--------|-------|-------|
| `timesheet` | My Timesheet | Employee, Dept Head |
| `history` | History | Employee, Dept Head |
| `requests` | Requests | Employee, Dept Head |
| `dept-dashboard` | Department Dashboard | Dept Head (assigned) |
| `requests-hr` | Team Requests / Requests | Dept Head, HR, Owner |
| `hr` | HR Dashboard | HR, Owner |
| `client-analytics` | Client Analytics | HR, Owner |
| `employees` | Employees | HR, Owner |
| `departments` | Departments | HR, Owner |
| `holidays` | Holidays | HR, Owner |
| `vacations` | Vacations | HR, Owner |
| `logs` | Logs | HR, Owner |
| `analytics` | Analytics | Owner |
| `clients` | Clients | Owner |
| `my-profile` | My Profile | All logged-in portal users |

### UI theming

- Design tokens: `assets/css/tokens.css`
- Dark mode per user (`etwa_dark_mode` user meta), toggled via header control
- Portal styles isolated under `.etwa-portal-layout` for Elementor/theme compatibility
- Shared toasts/confirm dialogs: `assets/js/etwa-ui.js`

### Asset loading

`includes/Assets/AssetRegistry.php` enqueues scripts per shortcode/portal view. Portal shell may load via `wp_footer` fallback when shortcodes render after `wp_enqueue_scripts`.

---

## Feature modules

### Timesheets

- **Work week:** Default Sun–Thu (`SettingsRepository`)
- **Day cards:** Entries with client, task, start/end time, notes
- **Submit / approve workflow** via `TimesheetService`, `ApprovalService`
- **Copy previous week** — copies day-aligned entries from the prior work week into an empty week
- **Overtime** after configured end time (`OvertimeService`)

**Key paths:** `TimesheetController`, `EntryController`, `assets/js/employee-dashboard.js`, `templates/partials/week-timesheet.php`

### HR Dashboard

- Review submitted timesheets, filter by employee/status/date
- Week drill-down per employee (`?review_user=&etwa_week=`)
- Export and email reports

**Key paths:** `HrTimesheetController`, `assets/js/hr-dashboard.js`, `templates/admin/hr-dashboard.php`

### Employees

HR/Owner only.

- **List:** search, pagination, role & department columns
- **Create:** name, email, role, department, job title, join date, birthday — password setup email sent automatically
- **Edit (list):** role, department, hourly cost (EGP)
- **Profile:** view/edit details, send password reset; embedded via `?employee_id=` on Employees tab

**Key paths:** `EmployeeService`, `EmployeeController`, `EmployeeProfileHelper`, `assets/js/employees-manager.js`, `assets/js/employee-profile.js`

### Departments

- CRUD departments, assign department head, assign employees
- Department dashboard for heads: team calendar, timesheet review

**Key paths:** `DepartmentService`, `DepartmentController`, `assets/js/departments-manager.js`

### Requests center

Unified leave and permission requests (replaces standalone “My Vacation” tab for employees).

- Employees submit leave/permission requests
- Dual approval (department + HR) when enabled in settings
- HR/Dept Head review queues with badges (`ReviewerCountsService`)

**Key paths:** `RequestService`, `RequestController`, `assets/js/employee-requests.js`, `assets/js/requests-manager.js`

### Vacations & leave

- **Single annual pool** per employee; sick/emergency/compensatory deduct from it; unpaid does not
- HR sets allocation, reviews requests, manual records, calendar, analytics
- See README vacation section for workflow details

**Key paths:** `VacationService`, `VacationHelper`, `VacationController`, `assets/js/vacation-manager.js`

### Analytics

| Module | Audience | Description |
|--------|----------|-------------|
| Client Analytics | HR, Owner | Client-level hours/cost trends |
| Owner Analytics | Owner | Workforce KPIs, departments, insights |

### Clients & holidays

- **Clients:** billable entities for timesheet entries (+ system “Break” client)
- **Holidays:** company calendar affecting leave calculations

### Audit & debug logs

- **Audit logs:** HR/Owner — user actions on timesheets, employees, departments
- **Debug log viewer:** reads WP debug log when enabled

---

## Admin backend

WordPress admin menu **Timesheets** (slug varies by role):

| Page | Capability | Notes |
|------|------------|-------|
| My Timesheet | `etwa_view_own_timesheet` | Employee landing |
| HR Dashboard | `etwa_view_all_timesheets` | HR/Owner |
| Vacations | `etwa_manage_vacation` | Leave management |
| Owner Analytics | `etwa_view_advanced_analytics` | Owner only |
| Settings | `etwa_manage_settings` | Work rules, emails |
| Audit Logs | `etwa_view_all_timesheets` | HR/Owner |

Frontend portal is the primary UX; admin pages mirror many features for back-office access.

---

## REST API

**Base URL:** `/wp-json/etwa/v1/`

| Auth context | Header | Nonce action |
|--------------|--------|--------------|
| Logged-in portal | `X-WP-Nonce` | `wp_rest` |
| Login / password pages | `X-ETWA-Nonce` | `etwa_auth` |

Full endpoint reference: **[docs/REST-API.md](REST-API.md)**

### Controller map

| Controller | Domain |
|------------|--------|
| `AuthController` | Login, forgot/reset password |
| `TimesheetController` | Employee timesheets, submit, copy week |
| `EntryController` | Time entries CRUD |
| `HrTimesheetController` | HR timesheet review/edit |
| `EmployeeController` | Employees, profiles, assignment, hourly cost |
| `DepartmentController` | Departments |
| `VacationController` | Leave types, balances, requests |
| `RequestController` | Unified requests workflow |
| `ApprovalController` | Timesheet/overtime approvals |
| `AnalyticsController` | Dashboard analytics |
| `ClientController` / `ClientAnalyticsController` | Clients |
| `HolidayController` | Holidays |
| `ExportController` | Excel export & email |
| `PortalController` | Nav badge counts |

Standard response envelope:

```json
{ "success": true, "data": { ... } }
{ "success": false, "message": "...", "code": "..." }
```

---

## Database

Schema version tracked in option `etwa_db_version`. Migrations in `Schema::maybe_upgrade()`.

**Reference:** [docs/DATABASE-SCHEMA.md](DATABASE-SCHEMA.md)

### Table groups

| Group | Tables |
|-------|--------|
| Timesheets | `etwa_timesheets`, `etwa_entries`, `etwa_overtime`, `etwa_clients` |
| Workforce | `etwa_departments`, user meta for dept assignment & hourly cost |
| Vacation | `etwa_leave_types`, `etwa_vacation_transactions`, `etwa_vacation_records`, `etwa_vacation_requests` |
| Requests | `etwa_requests` (unified request workflow) |
| System | `etwa_settings`, `etwa_audit_logs`, `etwa_holidays` |

Employee profile fields (job title, join date, birthday) stored in **user meta** via `EmployeeProfileHelper`.

---

## Configuration & settings

Stored in `etwa_settings` table (`SettingsRepository`).

| Setting | Default | Description |
|---------|---------|-------------|
| `work_week_start` / `work_week_end` | sunday / thursday | Work week boundaries |
| `work_day_start` / `work_day_end` | 10:00 / 18:00 | Standard hours |
| `time_interval_minutes` | 30 | Entry slot size |
| `min_overtime_minutes` | 30 | OT threshold |
| `overtime_round_minutes` | 30 | OT rounding |
| `available_hours_week` | 40 | Expected weekly hours |
| `overtime_cost_multiplier` | 1.5 | Cost calculation |
| `hr_email` / `owner_email` | admin_email | Report recipients |
| `vacation_dual_approval` | 1 | Dept + HR approval |
| `enable_audit_logs` | 1 | Audit trail |

Owner configures via **Settings** admin page or Owner portal settings view.

---

## Architecture & code layout

```
employee-timesheet-workforce-analytics/
├── employee-timesheet-workforce-analytics.php   # Bootstrap
├── includes/
│   ├── class-etwa-plugin.php                    # Hooks, REST registration
│   ├── Admin/                                   # WP admin pages, AJAX
│   ├── Assets/AssetRegistry.php                 # CSS/JS enqueue
│   ├── Database/Schema.php                      # Tables & migrations
│   ├── Frontend/
│   │   ├── Auth.php                             # Login / set-password
│   │   ├── PortalNav.php                        # Portal routing & nav
│   │   └── Shortcodes.php                       # All shortcodes
│   ├── Helpers/                                 # Domain helpers
│   ├── Models/                                  # Data objects
│   ├── Repositories/                            # DB access
│   ├── REST/                                    # API controllers
│   └── Services/                                # Business logic
├── templates/
│   ├── admin/                                   # Admin & portal partials
│   └── frontend/                                # Portal shell, auth, views
├── assets/
│   ├── css/                                     # tokens, components, portal, auth
│   └── js/                                      # Feature modules + etwa-ui.js
└── docs/                                        # This file, REST API, schema, roadmap
```

### Patterns

- **Service layer** — business rules (`EmployeeService`, `TimesheetService`, …)
- **Repositories** — SQL / `$wpdb` access
- **REST controllers** — thin HTTP adapters extending `RestController`
- **Helpers** — shared domain logic (dates, roles, vacation pool, departments)

---

## Scheduled jobs & notifications

| Cron hook | Schedule | Purpose |
|-----------|----------|---------|
| `etwa_weekly_report_email` | Weekly (Friday 08:00) | Email workforce export |
| `etwa_daily_reminder` | Daily (09:00) | Timesheet reminders |

In-app notifications for approved/rejected requests via `NotificationService` (user meta).

---

## Troubleshooting

### “Cookie check failed” on login

Caused by sending auth nonce in `X-WP-Nonce`. Auth JS must use **`X-ETWA-Nonce`**. Hard-refresh the login page after plugin updates.

### Login page layout broken

Auth pages use a plugin-only template (`auth-page.php`). If the theme still wraps content, flush permalinks and confirm `template_include` filter in `Auth.php` is active.

### Password email not received

Local environments (Local WP) need Mailhog or SMTP. Check spam; verify `wp_mail()` works.

### Employee cannot log in

- Confirm user has an ETWA role (or is Administrator)
- Resend password reset from employee profile (HR)
- Ensure `/set-password/` page exists with `[etwa_set_password]`

### Department head cannot see dashboard

User must be assigned as **department head** on a department in **Departments**, not only hold the `etwa_department_head` role.

### Vacation balance wrong

Only **Annual Leave** allocation sets the pool. Approved usage deducts from `VacationHelper::annual_pool()`. Role must include vacation access (`etwa_view_own_vacation` or managed employee).

### Uninstall data removal

Add to `wp-config.php` before uninstall:

```php
define( 'ETWA_DELETE_DATA', true );
```

---

## Related documents

| Document | Description |
|----------|-------------|
| [README.md](../README.md) | Quick start & feature summary |
| [REST-API.md](REST-API.md) | HTTP endpoint reference |
| [DATABASE-SCHEMA.md](DATABASE-SCHEMA.md) | Table definitions |
| [MVP-ROADMAP.md](MVP-ROADMAP.md) | Planned enhancements |

---

*Last updated: July 2026 — plugin version 1.0.0*
