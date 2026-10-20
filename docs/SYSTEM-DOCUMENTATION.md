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
11. [Email system](#email-system)
12. [Architecture & code layout](#architecture--code-layout)
13. [Scheduled jobs & notifications](#scheduled-jobs--notifications)
14. [Troubleshooting](#troubleshooting)

---

## Overview

The plugin replaces weekly Excel timesheets with a web-based workforce platform for **Think Marketing**. It provides:

- **Date-based timesheets** (Sun–Thu work week by default)
- **Overtime** tracking and approval
- **Leave & requests** (annual pool, dual approval where configured)
- **Departments** and department-head dashboards
- **Employee management** (HR/Owner creates accounts, assigns roles & departments)
- **Analytics & exports** (client analytics, owner analytics, centralized Reports & Exports, legacy Excel/email)
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
6. Configure **Settings** (Owner): work week, hours, HR/owner emails
7. Configure **Email Settings** (Owner): branded HTML template, signature, test send

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
| Export / Reports / Audit logs | — | — | ✓ | ✓ |

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

Employee / Dept Head groups: **My work**, **Requests**, **Team**, **Deliverables**, **Account**.

HR / Owner groups: **HR**, **Time & Leave**, **Work Management**, **Analytics**, **Administration**, **Account**, **Logs**.

| Tab ID | Label | Roles |
|--------|-------|-------|
| `timesheet` | My Timesheet | Employee, Dept Head |
| `history` | History | Employee, Dept Head |
| `requests` | Requests | Employee, Dept Head |
| `dept-dashboard` | Department Dashboard | Dept Head (assigned) |
| `requests-hr` | Team Requests / Requests | Dept Head, HR, Owner |
| `hr` | HR Dashboard | HR, Owner (group: HR) |
| `holidays` | Holidays | HR, Owner (group: Time & Leave) |
| `vacations` | Vacations | HR, Owner (group: Time & Leave) |
| `clients` | Clients & Workspace | HR, Owner (group: Work Management) |
| `prospects` | Prospects | HR, Owner (group: Work Management) |
| `files` | Files | View deliverables (HR/Owner/Dept Head; dept-flagged employees) |
| `deliverables` | Deliverables | Analytics dashboard home; scope detail / allocate via `?scope_id=` |
| `analytics` | Overview | HR, Owner (group: Analytics) |
| `client-analytics` | Client Analytics | HR, Owner (group: Analytics) |
| `prospect-analytics` | Prospect Analytics | HR, Owner (group: Analytics) |
| `reports` | Reports & Exports | HR, Owner (group: Analytics) |
| `employees` | Employees | HR, Owner (group: Administration) |
| `departments` | Departments | HR, Owner (group: Administration) |
| `logs` | Logs | HR, Owner (top-level) |
| `my-profile` | My Profile | All logged-in portal users (group: Account) |

### UI theming

- Design tokens: `assets/css/tokens.css`
- Dark mode per user (`etwa_dark_mode` user meta), toggled via header control
- Portal styles isolated under `.etwa-portal-layout` for Elementor/theme compatibility
- Shared toasts/confirm dialogs: `assets/js/etwa-ui.js`
- Shared REST client with nonce refresh: `assets/js/etwa-api.js`
- Shared date-range presets and export modals: `assets/js/etwa-date-range.js`

### Asset loading

`includes/Assets/AssetRegistry.php` enqueues scripts per shortcode/portal view. Portal shell may load via `wp_footer` fallback when shortcodes render after `wp_enqueue_scripts`.

---

## Feature modules

### Timesheets

- **Work week:** Default Sun–Thu (`SettingsRepository`)
- **Day cards:** Entries with client, optional **project**, task, start/end time, notes, **entry type** (regular / overtime / compensation after 18:00)
- **Save Draft** — snapshot visible to HR before submit (`draft_snapshot` on timesheet)
- **Submit / approve workflow** via `TimesheetService`, `ApprovalService`
- **Copy previous week** — copies day-aligned entries from the prior work week into an empty week
- **Overtime** after configured end time (`OvertimeService`); after-hours entries require explicit OT or Compensation type
- **Moderation client** — detailed notes required on every entry

**Key paths:** `TimesheetController`, `EntryController`, `TimesheetDraftHelper`, `EntryTypeHelper`, `assets/js/employee-dashboard.js`, `templates/partials/week-timesheet.php`

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

- **Single annual pool** per employee; sick/emergency deduct from it; unpaid does not
- HR sets allocation, reviews requests, manual records, calendar, analytics
- See README vacation section for workflow details

**Key paths:** `VacationService`, `VacationHelper`, `VacationController`, `assets/js/vacation-manager.js`

### Analytics

| Module | Audience | Description |
|--------|----------|-------------|
| Client Analytics | HR, Owner | Per-client hours plus **Workspace Projects** (standalone parent analytics, including sub-project hour rollup) |
| Owner Analytics | Owner | Workforce KPIs, departments, insights |
| Reports & Exports | HR, Owner | Centralized Excel reports and live All Clients Comparison dashboard |

**Reports & Exports** (`ReportingService`, `ReportExportService`, `ReportsController`):

| Report | Export | Live UI |
|--------|:------:|:-------:|
| Employee Timesheet | ✓ | — |
| Client Report | ✓ | — |
| Client Labor Cost | ✓* | — |
| Employee Hours by Client | ✓ | — |
| All Clients Comparison | ✓ | ✓ (charts + sortable table) |

Entry-level exports and aggregated report workbooks include a **Project** column (entries without a project export as “Social Media”). Client/project analytics exports include project breakdown sheets.

\*Requires labor-cost permission (`EmployeeCostHelper::can_view_labor_cost()`).

**Key paths:** `includes/Services/ReportingService.php`, `includes/Services/ReportExportService.php`, `includes/REST/ReportsController.php`, `assets/js/reports-exports.js`, `templates/admin/reports-exports.php`

### Deliverables & Client Files

| Module | Audience | Description |
|--------|----------|-------------|
| Files | View deliverables | Client-first cards of clients that have scopes; drill into a Client File |
| Deliverables | View / manage / allocate | **Home:** analytics dashboard. **Detail:** scope create/edit with client, optional project, and **Total Expected Deliverables**; multi-item allocate with edit/delete (`?scope_id=`). **Deliverable Types:** always available from page actions and scope detail; create, edit, delete (soft deactivate), reactivate. |

- **Files** (`DeliverableFilesService`, `GET /deliverables/files`, portal view `files`): lists only clients with ≥1 scope; Open Scope deep-links to Deliverables detail.
- Access: `RoleManager::user_can_view_deliverables()` (role cap or department `can_allocate_deliverables`).
- UI: `templates/admin/files.php`, `assets/js/files.js`, glass cards in `assets/css/analytics.css`.

### Clients & holidays

- **Clients:** billable entities for timesheet entries (+ system **Break** and **Moderation** clients)
- **Workspace Projects:** standalone Parent Projects may nest Sub-projects (`etwa_projects.parent_project_id`). Client Projects stay on `etwa_projects.client_id`.
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
| Reports & Exports | `etwa_export_reports` | HR/Owner |
| Vacations | `etwa_manage_vacation` | Leave management |
| Client Analytics | `etwa_view_analytics` | HR/Owner |
| Owner Analytics | `etwa_view_advanced_analytics` | Owner only |
| Email Settings | `etwa_manage_settings` | Branded HTML template, signature, live preview, test send |
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
| `ProjectController` | Projects (CRUD, analytics detail, Excel/CSV export) |
| `HolidayController` | Holidays |
| `ExportController` | Legacy Excel export & email |
| `ReportsController` | Centralized reports, filters, comparison |
| `DeliverablesController` | Types, scopes, files, dashboard analytics, allocations |
| `PortalController` | Nav badge counts, session nonce refresh |

Standard response envelope:

```json
{ "success": true, "data": { ... } }
{ "success": false, "message": "...", "code": "..." }
```

---

## Database

Schema version tracked in option `etwa_db_version` (current: **1.28.0**). Migrations in `Schema::maybe_upgrade()`.

**Reference:** [docs/DATABASE-SCHEMA.md](DATABASE-SCHEMA.md)

### Table groups

| Group | Tables |
|-------|--------|
| Timesheets | `etwa_timesheets`, `etwa_entries`, `etwa_overtime`, `etwa_clients` |
| Workforce | `etwa_departments`, user meta for dept assignment & hourly cost |
| Vacation | `etwa_leave_types`, `etwa_vacation_transactions`, `etwa_vacation_records`, `etwa_vacation_requests` |
| Requests | `etwa_requests` (unified request workflow) |
| System | `etwa_settings`, `etwa_audit_logs`, `etwa_holidays` |

Employee profile fields (job title, join date, birthday, phone) stored in **user meta** via `EmployeeProfileHelper`.

Email branding stored in WordPress option `etwa_email_settings` via `EmailSettingsRepository` and rendered by `MailService`.

---

## Configuration & settings

Stored in `etwa_settings` table (`SettingsRepository`).

| Setting | Default | Description |
|---------|---------|-------------|
| `work_day_start` / `work_day_end` | 10:00 / 18:00 | Standard hours |
| `time_interval_minutes` | 30 | Entry slot size |
| `available_hours_week` | 40 | Expected weekly hours |
| `overtime_cost_multiplier` | 1.0 | Cost calculation (1× = same rate as regular) |
| `hr_email` / `owner_email` | admin_email | Report recipients |
| `vacation_dual_approval` | 1 | Dept + HR approval |
| `enable_audit_logs` | 1 | Audit trail |

Owner configures via **Settings** admin page or Owner portal settings view.

---

## Email system

ETWA uses two email paths: **branded HTML notifications** (workforce events) and **plain-text password reset** (WordPress core).

### Architecture

| Layer | File | Role |
|-------|------|------|
| Delivery & HTML wrapper | `includes/Services/MailService.php` | Builds branded HTML, replaces `{placeholders}`, sends via `wp_mail()` |
| Event notifications | `includes/Services/NotificationService.php` | Listens to workflow hooks, supplies subject + message per event |
| Report emails | `includes/Services/EmailService.php` | Generates Excel export, emails HR (optional CC Owner) |
| Template storage | `includes/Repositories/EmailSettingsRepository.php` | Defaults + `wp_options` key `etwa_email_settings` |
| Admin UI | `includes/Admin/EmailSettingsManager.php`, `templates/admin/email-settings.php` | Settings form, live preview, test send |
| Scheduled mail | `includes/Cron/CronJobs.php` | Weekly report + daily timesheet reminders |

There are **no separate `.html` email template files**. Per-event code passes plain (or limited HTML) **message content**; `MailService` wraps it in one shared layout.

### Branded HTML layout

All ETWA notifications use `Content-Type: text/html; charset=UTF-8`.

```
┌─────────────────────────────────────────┐
│  Outer page (#f3f4f6 bg, 24px padding)  │
│  ┌───────────────────────────────────┐  │
│  │ Card (640px max, white, bordered) │  │
│  │  • Greeting (optional)            │  │
│  │  • Main body ({email_content})    │  │
│  │  ─── divider ───                  │  │
│  │  • Signature / footer block       │  │
│  └───────────────────────────────────┘  │
└─────────────────────────────────────────┘
```

**Main content typography:** Arial/Helvetica, 15px, `#1f2937`, line-height 1.6.

Default admin template parts:

| Setting | Default |
|---------|---------|
| `greeting` | `Hello {employee_name},` |
| `email_body` | `{email_content}` |
| `appreciation` | `With Appreciation,` |
| `closing_text` | Assistance / clarifications line |
| `signature_dedication` | `Dedicated to your success,` |

### Signature / footer block

Everything **below the horizontal rule** is the footer. Order:

1. **Appreciation line** — 13px muted gray (`#6b7280`)
2. **Closing / assistance text** — same muted style
3. **Logo** (optional) — media attachment from Email Settings; default 180×60 px
4. **Signature rows** — configurable styled lines (size, weight, color, spacing)

Default signature rows:

| Line | Content | Style |
|------|---------|-------|
| Dedication | From `signature_dedication` setting | 15px normal |
| Sender name | Reviewer or default sender | 16px bold, dark |
| Sender role | Job title or default role | 14px muted |
| Contact | `M: {sender_phone} \| E: {sender_email}` | 14px; email as `mailto:` link |
| Company | `{company_name} \| {company_tagline}` | 14px bold, dark |
| Office phone | `T: {office_phone}` | 14px |
| Office address | `A: {office_address}` | 14px; multiline supported |

**Not included:** unsubscribe link, social icons, legal disclaimer, or copyright line.

### Placeholders

Simple `{key}` string replace in greeting, body, and signature. Available keys:

`employee_name`, `employee_email`, `sender_name`, `sender_role`, `sender_email`, `sender_phone`, `company_name`, `company_tagline`, `office_phone`, `office_address`, `current_date`, `request_type`, `request_status`, `department`, `approval_link`, `system_name`, `email_content`

When `sender_user_id` / `recipient_user_id` are passed to `MailService::send()`, sender and recipient fields are filled from the WordPress user and employee profile.

### Notification emails (`NotificationService`)

Hook-driven emails for workflow events. Subjects and message text are defined in PHP; only the **wrapper** is admin-configurable.

| Hook | Recipient | Purpose |
|------|-----------|---------|
| `etwa_timesheet_submitted` | HR | Employee submitted timesheet |
| `etwa_timesheet_reviewed` | Employee | Timesheet approved / rejected |
| `etwa_overtime_reviewed` | Employee | Overtime approved / rejected |
| `etwa_vacation_requested` | Dept head or HR | New vacation request |
| `etwa_vacation_dept_approved` | HR | Dept approved → HR step |
| `etwa_vacation_approved` | Employee | Final leave approval |
| `etwa_vacation_rejected` | Employee | Leave rejected (with reason) |
| `etwa_request_submitted` | Dept head or HR | Generic request submitted |
| `etwa_request_dept_approved` | HR | Dept approved → HR step |
| `etwa_request_approved` | Employee | Request approved |
| `etwa_request_rejected` | Employee | Request rejected (with reason) |

**Routing:** If the employee has a department head, dept head is notified first; otherwise HR (`hr_email` from settings). After dept approval, HR is notified. Final approve/reject goes to the employee.

### Report & reminder emails

| Source | Recipient | Content |
|--------|-----------|---------|
| `EmailService::send_report()` | HR (+ CC Owner if enabled) | Plain message + Excel attachment (manual from HR dashboard) |
| Cron `etwa_daily_reminder` | Employees missing submit/approve | Timesheet reminder for current week |
| Cron `etwa_deliverable_expiration_check` | Daily (08:30) | Scope expiration alerts to HR/Owner (7/3/1 day thresholds) |

### Deliverable notifications (v1.24+)

| Event | Trigger | Recipients |
|-------|---------|------------|
| Content delivery required | Scope created or re-activated with contract qty | Users who can allocate deliverables |
| Scope expiration | Daily cron when `ends_on` within configured threshold | HR + Owner |
| Scope completed | Allocation save when `remaining_qty` reaches 0 | HR + Owner |

Dedup state stored in `etwa_notification_log`. UI labels display deliverable **Hours** (internal field remains `effort_score`).

Manual report email: `POST /export/email` (`ExportController`).

### Password reset emails (separate — plain text)

Password setup and reset **do not** use `MailService` or the branded HTML wrapper. They use WordPress `retrieve_password()` with filters in `includes/Frontend/Auth.php`:

- Custom subject via `AuthHelper::password_reset_email_subject()`
- Custom plain-text body with link to `/set-password/?key=...&login=...`

Triggered when HR creates an employee, sends reset from profile, or user uses forgot-password on the login page.

### Email Settings admin

**Timesheets → Email Settings** (`etwa_manage_settings`):

- General: From Name, From Email, Reply-To
- Branding: logo upload, dimensions
- Template: greeting, main body (must include `{email_content}` slot)
- Signature: appreciation, closing, dedication, default sender fields, signature row builder
- Company info: name, tagline, phone, address
- Live iframe preview (AJAX) and send test email

### Key files

| File | Purpose |
|------|---------|
| `includes/Services/MailService.php` | HTML render, placeholders, `wp_mail()` |
| `includes/Services/NotificationService.php` | Workflow notification handlers |
| `includes/Services/EmailService.php` | Report export email |
| `includes/Repositories/EmailSettingsRepository.php` | Option storage & defaults |
| `includes/Admin/EmailSettingsManager.php` | Settings API, preview/test AJAX |
| `assets/js/email-settings.js` | Preview + signature builder UI |

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
│   └── js/                                      # Feature modules + etwa-ui.js, etwa-api.js, etwa-date-range.js
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
| `etwa_daily_reminder` | Daily (09:00) | Timesheet reminders to employees who have not submitted |
| `etwa_deliverable_expiration_check` | Daily (08:30) | Scope expiration alerts (HR/Owner) |

**Email notifications** for workflow events are sent by `NotificationService`; deliverable alerts use `DeliverableNotificationService` (see [Email system](#email-system)).

**In-app vacation notifications** (portal badge / list) are stored in user meta `etwa_vacation_notifications` by `VacationService` — separate from email delivery.

---

## Troubleshooting

### “Cookie check failed” on login or long portal sessions

Caused by expired REST nonces. Auth JS must use **`X-ETWA-Nonce`** on login routes. Portal modules use `etwa-api.js`, which refreshes nonces via `GET /portal/session` every 10 minutes and retries failed requests.

### Login page layout broken

Auth pages use a plugin-only template (`auth-page.php`). If the theme still wraps content, flush permalinks and confirm `template_include` filter in `Auth.php` is active.

### Password email not received

Password reset uses WordPress core plain-text mail (not the branded HTML template). Local environments (Local WP) need Mailhog or SMTP. Check spam; verify `wp_mail()` works.

### Branded notification email not received

Workflow emails (timesheet, leave, requests) use `MailService` HTML. Test from **Email Settings → Send Test Email**. Confirm `hr_email` is set under **Settings** for HR-bound notifications.

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

*Last updated: July 2026 — plugin version 1.1.3*
