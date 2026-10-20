# REST API Reference

**Namespace:** `etwa/v1`  
**Base URL:** `/wp-json/etwa/v1/`

## Authentication

| Context | Header | Nonce action | Notes |
|---------|--------|--------------|-------|
| Portal (logged-in users) | `X-WP-Nonce` | `wp_rest` | WordPress cookie session required |
| Login / set-password pages | `X-ETWA-Nonce` | `etwa_auth` | Public; do **not** use `X-WP-Nonce` on auth routes |

Standard response envelope:

```json
{ "success": true, "data": { ... } }
{ "success": false, "message": "...", "code": "..." }
```

---

## Auth (public)

Used by `/login/` and `/set-password/` pages. Requires `X-ETWA-Nonce` header.

### POST `/auth/login`

```json
{
  "email": "user@example.com",
  "password": "secret",
  "remember": true,
  "redirect_to": "https://example.com/portal/"
}
```

**Response:** `{ "redirect": "..." }` — sets WordPress auth cookies via `wp_signon`.

---

### POST `/auth/forgot-password`

```json
{ "email": "user@example.com" }
```

Sends password reset email with link to `/set-password/?key=...&login=...`. Always returns success (no email enumeration).

---

### POST `/auth/reset-password`

```json
{
  "key": "reset-key-from-email",
  "login": "username",
  "password": "newpassword",
  "password_confirm": "newpassword"
}
```

**Response:** `{ "redirect": ".../login/?etwa_auth=password_set" }`

---

## Timesheets

### GET `/timesheets/me`

Employee dashboard data for current or specified work week.

| Param | Type | Description |
|-------|------|-------------|
| date | string | Optional reference date (Y-m-d) |

**Response:** timesheet, entries[], week, work_dates[]

**Capability:** `etwa_view_own_timesheet`

---

### GET `/timesheets`

HR list with filters and pagination.

| Param | Type | Description |
|-------|------|-------------|
| status | string | draft, submitted, approved, rejected |
| date_from | string | Period filter |
| date_to | string | Period filter |
| search | string | Employee name search |
| page | int | Page number (default 1) |

**Capability:** `etwa_view_all_timesheets`

---

### POST `/timesheets/{id}/submit`

Submit timesheet for approval.

**Capability:** `etwa_submit_timesheet`

---

### POST `/timesheets/{id}/save-draft`

Save a draft snapshot of the current week's entries for HR review without submitting. Clears on submit.

**Capability:** `etwa_edit_own_timesheet`

**Response:** Updated timesheet object including `draft_saved_at` and snapshot metadata.

---

### POST `/timesheets/copy-previous`

Copy entries from the work week **immediately before** a target week into that target week. Entry dates are mapped day-for-day (Sun→Sun, Mon→Mon, etc.) using the same calendar grid as the timesheet UI.

**Body (JSON):**

```json
{ "date": "2026-07-12" }
```

| Param | Type | Description |
|-------|------|-------------|
| date | string | Optional reference date (Y-m-d) for the **target** week. Any date within the week works. Defaults to the current work week if omitted. |

**Rules:**

- Target week must have **no existing entries**.
- Source week is the adjacent previous work week (7 days before target `period_start`).
- Source timesheet must exist with at least one copyable entry.
- Target timesheet must be editable (not approved/locked).

**Errors:**

| Code | HTTP | Meaning |
|------|------|---------|
| `no_previous` | 404 | No timesheet for the previous week |
| `not_empty` | 409 | Target week already has entries |
| `nothing_to_copy` | 404 | Previous week has no entries to copy |
| `forbidden` | 403 | Target timesheet is locked or not editable |
| `copy_failed` | 500 | Database insert failed |

**Capability:** `etwa_edit_own_timesheet`

---

## Entries

### POST `/entries`

Create time entry.

```json
{
  "timesheet_id": 1,
  "entry_date": "2026-07-06",
  "start_time": "17:00",
  "end_time": "18:00",
  "client_id": 1,
  "project_id": 5,
  "prospect_id": null,
  "task": "Website",
  "notes": "",
  "entry_type": "regular"
}
```

| Field | Description |
|-------|-------------|
| client_id | Required for Client / Internal / General entries. Omit for standalone Workspace project entries and Prospect entries. |
| project_id | For a Client: omit or null for Social Media work; when set, must belong to `client_id`. For a standalone Workspace project: required, and `client_id` must be omitted. Break and Moderation do not use projects. |
| prospect_id | Optional. When set, `client_id` and `project_id` must be omitted. New assignments require an **Open** prospect. Historical entries on Won/Lost prospects remain editable. |
| entry_type | `regular` (default), `overtime`, or `compensation`. Required for entries at or after 18:00. |

**Capability:** `etwa_edit_own_timesheet`

---

### PUT `/entries/{id}`

Update entry (same body as POST).

---

### DELETE `/entries/{id}`

Delete entry.

---

### GET `/time-slots`

Returns 30-minute slot options for dropdowns.

---

## Approvals

### POST `/approvals/timesheets/{id}`

```json
{ "status": "approved", "notes": "Looks good" }
```

**Capability:** `etwa_approve_timesheets`

---

### POST `/approvals/overtime/{id}`

```json
{ "status": "approved", "approved_hours": 1.0, "notes": "" }
```

**Capability:** `etwa_approve_overtime`

---

### GET `/overtime`

List overtime records.

| Param | Type |
|-------|------|
| status | pending, approved, rejected |
| user_id | int |
| page | int |

---

### POST `/periods/lock`

```json
{ "period_start": "2026-07-06", "period_end": "2026-07-10" }
```

**Capability:** `etwa_lock_periods`

---

## Analytics

### GET `/analytics/dashboard`

HR analytics (KPIs, top clients, employees, trends).

**Capability:** `etwa_view_analytics`

---

### GET `/analytics/advanced`

Owner analytics (all dashboard data + departments + insights).

**Capability:** `etwa_view_advanced_analytics`

---

## Export

### POST `/export/excel` (legacy workforce export)

```json
{ "date_from": "2026-07-01", "date_to": "2026-07-31", "user_id": null }
```

Returns file URL with 5 sheets: Raw Timesheet, Client Analytics, Employee Summary, Charts, Overtime.

**Capability:** `etwa_export_reports`

---

### POST `/export/email`

Same body as Excel export. Sends TO HR, CC Owner (when `email_cc_owner` is enabled). Uses branded HTML wrapper via `MailService` with Excel attachment.

**Capability:** `etwa_email_reports`

---

## Reports (centralized)

Role-based Excel reports and the interactive **All Clients Comparison** dashboard. Uses shared date-range presets via `DateRangeHelper`.

**Base capability:** `etwa_export_reports`

| Report type slug | Who can export | Description |
|------------------|----------------|-------------|
| `employee_timesheet` | HR / Owner | Entry-level detail for one employee |
| `client` | HR / Owner (`etwa_view_analytics`) | Client activity and period totals |
| `prospect` | HR / Owner (`etwa_view_analytics`) | Timesheet hours for one prospect (`prospect_id` on entries) |
| `client_labor_cost` | Labor-cost viewers + analytics | Employee hours and labor cost by client |
| `employee_hours_by_client` | HR / Owner | Hours distribution across clients |
| `all_clients_comparison` | HR / Owner (`etwa_view_analytics`) | Side-by-side client comparison (also available as live UI) |

### GET `/reports/filters`

Dropdown options for report modals: employees, clients (excludes Break), departments, parent projects, prospects (`id`, `name`, `status`, `display_name`), date presets, `can_view_labor_cost`.

**Capability:** `etwa_export_reports`

---

### GET `/reports/all-clients-comparison`

Interactive comparison data for the Reports portal tab.

| Param | Type | Description |
|-------|------|-------------|
| preset | string | `this_week`, `this_month`, `last_month`, `last_3_months`, `last_6_months`, `this_year`, `custom` |
| date_from | string | Y-m-d (required when preset is `custom`) |
| date_to | string | Y-m-d |
| department_id | int | Optional department filter |
| user_id | int | Optional employee filter |

**Response:** Client rows with regular/OT/combined hours, employee counts, labor cost (when permitted), chart-ready aggregates.

**Capability:** `etwa_export_reports` + `etwa_view_analytics`

---

### POST `/reports/export/{type}`

Generate an Excel file for the given report type.

**Body (JSON):**

```json
{
  "preset": "this_month",
  "date_from": "2026-07-01",
  "date_to": "2026-07-31",
  "user_id": 5,
  "client_id": 2,
  "department_id": 1
}
```

| Param | Required for |
|-------|--------------|
| user_id | `employee_timesheet` (required), `employee_hours_by_client`, `all_clients_comparison` (optional) |
| client_id | `client`, `client_labor_cost` (required); optional filter on other types |
| prospect_id | `prospect` (required). Hours come only from `etwa_entries.prospect_id` for the date range; later client hours after conversion are never included. Empty periods still return a valid workbook. |

**Response:**

```json
{
  "success": true,
  "data": {
    "file_path": "/path/to/file.xlsx",
    "file_url": "https://example.com/wp-content/uploads/...",
    "filename": "etwa-report-....xlsx"
  }
}
```

**Capability:** Varies by type (see table above). Requires PhpSpreadsheet via Composer.

#### POST `/reports/export/prospect`

Single-prospect Excel export. Requires `prospect_id` plus a date range (`preset` or `date_from` / `date_to`). Hours are `SUM(regular_hours + overtime_hours)` where `etwa_entries.prospect_id` matches; later client hours after conversion are never included. Open, Won, and Lost prospects are allowed. An empty period still returns a valid workbook with 0 hours.

```json
{
  "prospect_id": 12,
  "preset": "custom",
  "date_from": "2026-07-01",
  "date_to": "2026-07-31"
}
```

**Capability:** `etwa_export_reports` + `etwa_view_analytics`

---

## Prospects

Lightweight pre-client records. Employees pick Open prospects on timesheets via `GET /prospects`. Historical hours stay on `prospect_id` after conversion.

### GET `/prospects`

Open prospects for the timesheet dropdown.

**Capability:** logged in

---

### GET `/prospects/all`

All prospects for management. Optional `status` = `open` \| `won` \| `lost`.

**Capability:** `etwa_manage_settings`

---

### GET `/prospects/{id}`

Prospect detail including `total_hours`, `entry_count`, related entries, and `converted_client` when converted.

**Capability:** `etwa_manage_settings`

---

### POST `/prospects`

```json
{ "name": "Acme Co.", "source": "referrals", "prospect_start_date": "2026-08-23" }
```

Creates an Open prospect. `source` is required and must be one of `referrals`, `social_media`, `direct_contact`, `campaigns`, `events`. `prospect_start_date` is a `Y-m-d` date; if omitted it defaults to today. `created_at` remains the database record timestamp.

Responses include `source` and `prospect_start_date` on create, list, get, and update.

**Capability:** `etwa_manage_settings`

---

### PUT `/prospects/{id}`

Update `name`. May also update `source` and `prospect_start_date` when those keys are sent. Omitted keys are left unchanged (Start Date is never auto-overwritten). Lost prospects may also update `lost_reason`.

**Capability:** `etwa_manage_settings`

---

### POST `/prospects/{id}/won`

Mark an Open prospect as Won. Sets `won_lost_at`.

**Capability:** `etwa_manage_settings`

---

### POST `/prospects/{id}/lost`

```json
{ "lost_reason": "Budget not approved" }
```

Mark an Open prospect as Lost. `lost_reason` is required. Historical entries are kept.

**Capability:** `etwa_manage_settings`

---

### POST `/prospects/{id}/convert`

Convert a Won, not-yet-converted prospect into a Client. Does **not** rewrite historical entries.

```json
{ "name": "Acme Co.", "code": "ACME", "department": "General" }
```

`code` is required (existing Client rule). Duplicate conversion returns `409 already_converted`.

**Capability:** `etwa_manage_settings`

---

### GET `/prospect-analytics`

Read-only dashboard over existing prospect + conversion data. Optional query params:

| Param | Notes |
|-------|-------|
| `preset` | `all_time` (default), `today`, `this_week`, `this_month`, `last_month`, `last_3_months`, `last_6_months`, `this_year`, `custom` |
| `date_from` / `date_to` | Used when `preset=custom` (`Y-m-d`) |
| `interval` | Acquisition grain: `day` \| `week` \| `month` (default `month`) |
| `status` | Table filter: `open` \| `won` \| `lost` |
| `search` | Table filter by prospect name |

New-prospect, acquisition, and source metrics use `prospect_start_date` (not `created_at`). A prospect is **converted** when `converted_client_id` is set (existing Convert to Client flow). Average conversion time is days from `prospect_start_date` to `converted_at`. Conversion rate is omitted (`null`) when there are no new prospects or no conversions in range. Hours on the prospect table still use `etwa_entries.entry_date` where `prospect_id` is set.

**Capability:** `etwa_view_analytics`

---

## Clients

### GET `/clients/all`

List all clients including inactive (owner management).

**Capability:** `etwa_manage_settings`

---

### POST `/clients`

Create a client.

```json
{ "name": "SDM", "code": "SDM", "department": "General" }
```

---

### PUT `/clients/{id}`

Update client fields.

---

### DELETE `/clients/{id}`

Deactivate client (Break cannot be deactivated).

---

### GET `/clients`

| Param | Type |
|-------|------|
| search | string |

Returns active clients for dropdowns.

---

### POST `/clients/merge/preview`

Preview impact of merging secondary clients into a primary client.

```json
{ "primary_id": 1, "merge_ids": [2, 3] }
```

**Capability:** `etwa_manage_settings`

---

### POST `/clients/merge`

Transactionally merge clients; reassigns entries, scopes, and projects; deactivates merged clients.

**Capability:** `etwa_manage_settings`

---

### POST `/clients/{id}/convert-to-project/preview`

Preview converting an active client into a project under either another **Client** or a standalone **Parent Project**. Unlike merge, direct entries receive a `project_id`; child Client Projects become siblings under the selected parent (no nesting).

Send **either** `parent_client_id` **or** `parent_project_id` (not both). Optional `destination_project_id`:

- With `parent_client_id`: must be an active Client Project of that client. When omitted, a new Client Project is created using the source client's name.
- With `parent_project_id`: must be that parent project or one of its standalone sub-projects. When omitted, a new standalone sub-project is created using the source client's name. Direct hours can land on the parent itself by setting `destination_project_id` to the parent.

```json
{ "parent_client_id": 123, "destination_project_id": 456 }
```

```json
{ "parent_project_id": 78, "destination_project_id": 78 }
```

**Response highlights:** source client, `parent_client` and/or `parent_project`, `destination_project` (or `null`), `uses_existing_project`, `new_project_name`, affected entry/scope/allocation counts, child projects, duplicate name conflicts (new-project name check is skipped when using an existing destination), unmigratable relationships (requests, draft snapshots; scopes keep the deactivated source `client_id` when converting under a Parent Project), and `can_convert`.

**Capability:** `etwa_manage_settings`

---

### POST `/clients/{id}/convert-to-project`

Transactionally convert a client into a project under the selected parent; deactivates the source client. Same body as preview: required `parent_client_id` **or** `parent_project_id`, optional `destination_project_id`. When `destination_project_id` is set, direct hours move onto that project and no new project row is created.

```json
{ "parent_client_id": 123, "destination_project_id": 456 }
```

```json
{ "parent_project_id": 78 }
```

**Capability:** `etwa_manage_settings`

---

## Projects (v1.24+)

### GET `/clients/{client_id}/projects`

List projects for a client (active by default; `include_archived=1` for all).

### POST `/clients/{client_id}/projects`

Create a project under a client.

```json
{ "name": "Adformatics", "description": "Optional" }
```

### GET `/projects`

List standalone (clientless) Workspace projects as a parent/child tree. `include_archived=1` includes archived items. Each parent includes `children` (sub-projects).

### POST `/projects`

Create a standalone project. Omit `client_id`. Optional `parent_project_id` creates a one-level sub-project under another standalone parent.

```json
{ "name": "ETWA Development", "description": "Optional", "parent_project_id": null }
```

### GET `/projects/{id}`

Project analytics detail: hours summary, recent entries, related scopes, and employee breakdown. Query: `date_from`, `date_to` (defaults to current month).

For a standalone **Parent Project**, totals include hours on the parent and its sub-projects (`includes_children`, `child_breakdown`, `analytics_project_ids`).

**Capability:** logged in

### PUT `/projects/{id}`

Update project name/description.

### POST `/projects/{id}/archive`

Archive a project.

### POST `/projects/{id}/export/{excel|csv}`

Export project analytics workbook (Summary, Entries, optional Scopes). Body/query: `preset`, `date_from`, `date_to` (same as client analytics export).

**Capability:** `etwa_export_reports`

---

## Employees

HR/Owner employee management. List endpoint requires labor-cost access; create/profile/assignment require `can_manage_employees`.

### GET `/employees`

List employees with role, department, and hourly cost (paginated).

| Param | Type | Description |
|-------|------|-------------|
| page | int | Page number (default 1) |
| per_page | int | Page size (default 25, max 100) |
| search | string | Name or email search |

**Response items:** `id`, `display_name`, `email`, `role`, `role_label`, `department_id`, `department_name`, `hourly_cost_egp`

**Capability:** labor cost view (`etwa_view_all_timesheets`)

---

### POST `/employees`

Create employee and send password setup email.

```json
{
  "display_name": "Jane Doe",
  "email": "jane@example.com",
  "role": "etwa_employee",
  "department_id": 1,
  "job_title": "Designer",
  "join_date": "2026-01-15",
  "birthday": "1990-05-20"
}
```

**Capability:** `can_manage_employees` (HR/Owner)

---

### GET `/employees/me/profile`

Current user's profile.

**Capability:** logged in

---

### PUT `/employees/me/profile`

Employee self-service update.

```json
{
  "display_name": "Jane Doe",
  "email": "jane@example.com",
  "phone": "+20 100 000 0000",
  "birthday": "1990-05-20"
}
```

Employees cannot change role, job title, or join date via this endpoint.

**Capability:** logged in

---

### GET `/employees/{id}/profile`

Employee profile by ID (view permission via `RoleManager::user_can_view_employee`).

---

### PUT `/employees/{id}/profile`

Update profile fields: `display_name`, `email`, `role`, `job_title`, `join_date`, `birthday`.

**Capability:** `can_manage_employees`

---

### PUT `/employees/{id}/assignment`

Update role, department, and/or hourly cost from the employees list edit modal.

```json
{
  "role": "etwa_department_head",
  "department_id": 2,
  "hourly_cost_egp": 150.00
}
```

Pass `department_id: null` to remove department assignment.

**Capability:** `can_manage_employees`

---

### POST `/employees/{id}/send-password-reset`

Send password setup / reset email to employee.

**Capability:** `can_manage_employees`

---

### PUT `/employees/{id}/hourly-cost`

```json
{ "hourly_cost_egp": 150.00 }
```

**Capability:** labor cost view

---

### PUT `/employees/{id}/department`

```json
{ "department_id": 1 }
```

**Capability:** labor cost view

---

## Departments

See `DepartmentController` — `GET/POST /departments`, `PUT/DELETE /departments/{id}`.

---

## Vacations & leave

**Capabilities:**

| Capability | Who |
|------------|-----|
| `etwa_manage_vacation` | HR Manager, Owner — allocations, records, requests review |
| `etwa_view_own_vacation` | Employee — own profile, submit/cancel requests |

**Balance model:** One **annual leave pool** per employee. HR sets entitlement on the **Annual Leave** type only. All approved usage (annual, sick, emergency) deducts from that pool; **unpaid** leave does not. Usage is still stored by leave type for reporting.

Employees must have the **Think Marketing Employee** (`etwa_employee`) role.

---

### GET `/vacations/leave-types`

Active leave types (annual, sick, emergency, unpaid).

**Capability:** `etwa_manage_vacation` or `etwa_view_own_vacation`

---

### GET `/vacations/employees`

Employees eligible for vacation (role `etwa_employee`).

| Param | Type | Description |
|-------|------|-------------|
| page | int | Default 1 |
| per_page | int | Default 100 |
| search | string | Name or email |

**Capability:** `etwa_manage_vacation`

---

### GET `/vacations/my`

Current user's vacation profile: employee info, `balances[]`, `annual_pool` (allocated / used / remaining).

**Capability:** `etwa_view_own_vacation`

---

### GET `/vacations/profile/{user_id}`

HR view of an employee profile (same shape as `/vacations/my`).

**Capability:** `etwa_manage_vacation` or own profile

---

### GET `/vacations/history`

Unified ledger of allocations, usage, and leave records.

| Param | Type | Description |
|-------|------|-------------|
| user_id | int | Employee filter (HR only; employees see own) |
| leave_type_id | int | Leave type filter |
| status | string | pending, approved, rejected |
| month | string | MM |
| year | string | YYYY |
| date_from | string | Y-m-d |
| date_to | string | Y-m-d |
| keyword | string | Search reason/notes |
| transaction_type | string | allocation, usage, adjustment |
| page | int | Default 1 |
| per_page | int | Default 25 |

**Capability:** `etwa_manage_vacation` or `etwa_view_own_vacation`

---

### POST `/vacations/allocations`

Set **annual leave balance** (adds an allocation transaction).

```json
{
  "user_id": 5,
  "leave_type_id": 1,
  "days": 21,
  "effective_date": "2026-01-01",
  "notes": "2026 entitlement"
}
```

- `leave_type_id` must be the **Annual Leave** type (`slug: annual`).
- One allocation per employee + leave type + effective date (409 on duplicate).

**Capability:** `etwa_manage_vacation`

---

### GET `/vacations/allocations/{id}`

**Capability:** `etwa_manage_vacation`

---

### PUT `/vacations/allocations/{id}`

Update allocation days, effective date, or notes.

**Capability:** `etwa_manage_vacation`

---

### DELETE `/vacations/allocations/{id}`

**Capability:** `etwa_lock_periods` (Owner)

---

### POST `/vacations/records`

HR manually records leave.

```json
{
  "user_id": 5,
  "leave_type_id": 2,
  "start_date": "2026-07-10",
  "end_date": "2026-07-12",
  "days": 3,
  "status": "approved",
  "reason": "Sick leave",
  "notes": ""
}
```

Approved non-unpaid records deduct from the employee's annual pool. Returns 409 if insufficient annual balance.

**Capability:** `etwa_manage_vacation`

---

### GET `/vacations/records/{id}`

**Capability:** `etwa_manage_vacation` or own record

---

### PUT `/vacations/records/{id}`

Update leave record fields.

**Capability:** `etwa_manage_vacation`

---

### DELETE `/vacations/records/{id}`

**Capability:** `etwa_lock_periods` (Owner)

---

### GET `/vacations/requests`

List vacation requests.

| Param | Type | Description |
|-------|------|-------------|
| status | string | pending, approved, rejected (empty = all for HR) |
| user_id | int | HR filter by employee |
| page | int | Default 1 |
| per_page | int | Default 25 |

Employees always see only their own requests.

**Capability:** `etwa_manage_vacation` or `etwa_view_own_vacation`

---

### POST `/vacations/requests`

Employee submits a pending request.

```json
{
  "leave_type_id": 1,
  "start_date": "2026-07-10",
  "end_date": "2026-07-12",
  "days": 3,
  "reason": "Family event"
}
```

`reason` is required. Balance is checked on HR approval, not at submit time.

**Capability:** `etwa_view_own_vacation`

---

### PUT `/vacations/requests/{id}/review`

Approve or reject a pending request.

```json
{ "status": "approved", "notes": "" }
```

On **approve**: creates an approved leave record, posts a usage transaction, deducts annual pool (except unpaid), notifies employee.

On **reject**: no balance change; employee notified.

Returns 409 if approval would exceed remaining annual balance.

**Capability:** `etwa_manage_vacation`

---

### DELETE `/vacations/requests/{id}`

Cancel own **pending** request.

**Capability:** `etwa_view_own_vacation`

---

### GET `/vacations/days-between`

| Param | Type | Description |
|-------|------|-------------|
| start_date | string | Y-m-d |
| end_date | string | Y-m-d |

Returns inclusive day count for date pickers.

**Capability:** `etwa_manage_vacation` or `etwa_view_own_vacation`

---

### GET `/vacations/calendar`

| Param | Type | Description |
|-------|------|-------------|
| year | int | Default current year |
| month | int | Default current month |

Approved leave events for calendar view.

**Capability:** `etwa_manage_vacation`

---

### GET `/vacations/analytics`

Dashboard KPIs: totals, pending requests, upcoming leave, remaining annual balance by employee.

**Capability:** `etwa_manage_vacation`

---

### POST `/vacations/export`

Same filter params as `/vacations/history` (JSON body). Returns Excel download URL.

**Capability:** `etwa_export_reports` + `etwa_manage_vacation`

---

### GET `/vacations/notifications`

Unread in-app notifications (approved/rejected requests) for current user.

**Capability:** `etwa_view_own_vacation`

---

### PUT `/vacations/notifications`

Mark all vacation notifications as read (clears stored meta).

**Capability:** `etwa_view_own_vacation`

---

## Portal

### GET `/portal/nav-badges`

Pending request/review counts for portal sidebar badges.

**Capability:** logged in

---

### GET `/portal/session`

Refresh REST and AJAX nonces for long-lived portal sessions (used by `etwa-api.js`).

**Response:**

```json
{
  "success": true,
  "data": {
    "nonce": "...",
    "ajaxNonce": "..."
  }
}
```

**Capability:** logged in

---

## AJAX Actions (admin-ajax.php)

| Action | Description |
|--------|-------------|
| `etwa_save_settings` | Save owner settings |
| `etwa_toggle_dark_mode` | Toggle user dark mode preference (`etwa_dark_mode` user meta); returns `{ dark_mode: boolean }` |
| `etwa_email_preview` | Render branded HTML email preview from posted settings (Email Settings page) |
| `etwa_send_test_email` | Send test branded email to `recipient_email` (Email Settings page) |

---

## Deliverables

Requires `etwa_view_deliverables` (read), `etwa_manage_deliverable_scopes` (HR/Owner configure), or `etwa_allocate_deliverables` (Business allocate).

Alternatively, members of an **active** department with `can_allocate_deliverables = 1` may view and allocate at runtime (checked via `RoleManager::user_can_view_deliverables()` / `user_can_allocate_deliverables()`). Scope create/edit and type catalog management stay HR/Owner only.

| Method | Route | Cap | Notes |
|--------|-------|-----|-------|
| GET/POST | `/deliverable-types` | view / manage | Catalog (used when allocating) |
| PUT | `/deliverable-types/{id}` | manage | Update |
| POST | `/deliverable-types/{id}/deactivate` | manage | Soft deactivate |
| GET | `/deliverables/dashboard` | view | Cross-scope KPI totals (legacy; prefer `/deliverables/dashboard/analytics`) |
| GET | `/deliverables/dashboard/analytics` | view | Executive analytics dashboard payload |
| GET | `/deliverables/files` | view | Client Files — clients with ≥1 scope, rollups; optional `client_id`, `search` |
| GET/POST | `/deliverables/scopes` | view / manage | List with metrics / create |
| GET/PUT | `/deliverables/scopes/{id}` | view / manage | Scope details |
| GET | `/deliverables/scopes/{id}/metrics` | view | Metrics only |
| GET/POST | `/deliverables/allocations` | view / allocate | List / create (returns `allocation` + `metrics`) |
| GET/PUT/DELETE | `/deliverables/allocations/{id}` | view / allocate | Details / update / delete |

### Client Files (`GET /deliverables/files`)

Returns clients that have at least one scope (empty clients omitted).

Query: optional `search` (client/scope name/code), optional `client_id` (single file payload).

List response: `{ items: [ { client, totals, scopes } ], total }`.  
Single file: `{ file: { client, totals, scopes } }`.

`totals` includes `scope_count`, `active_scope_count`, `contract_deliverables`, `planned_qty`, `delivered_qty`, `remaining_qty`, `extra_qty`, `completion_pct`, `effort_score`.

Per-scope convenience fields: `planned_effort` (= `planned_qty` units), `completed_effort` / `effort_score` (weighted allocation effort).

Portal view: `files` (sidebar group Deliverables). Open Scope deep-links to Deliverables detail.

**Deliverables home vs Files:** `Files` is the browse surface (client cards, scope list). **Deliverables** home (no `?scope_id=`) is the analytics dashboard; scope detail + allocate remains at `?scope_id={id}`.

### Dashboard analytics (`GET /deliverables/dashboard/analytics`)

Query (all optional): `client_id`, `scope_id`, `deliverable_type_id`, `status`, `date_from`, `date_to`, `trend` (`week`\|`month`\|`scope`, default `month`), `over_time` (`day`\|`week`\|`month`, default `month`).

Response:

```json
{
  "kpis": {
    "total_scopes": 0,
    "active_scopes": 0,
    "completed_scopes": 0,
    "scopes_with_remaining": 0,
    "overdue_scopes": 0,
    "contract_total": 0,
    "planned_qty": 0,
    "delivered_qty": 0,
    "remaining_qty": 0,
    "extra_qty": 0,
    "completion_pct": 0,
    "effort_score": 0
  },
  "by_type": [],
  "completion_trend": [],
  "deliverables_over_time": [],
  "effort_trend": [],
  "scope_performance": { "top": [], "lowest": [], "overdue": [], "exceeded": [] },
  "client_performance": { "highest_completion": [], "highest_effort": [], "highest_extra": [] },
  "effort": { "planned": 0, "completed": 0, "pct": 0 },
  "recent_activity": [],
  "insights": [],
  "meta": { "trend": "month", "over_time": "month", "scope_count": 0 }
}
```

- **Scopes with Remaining** (`scopes_with_remaining`): active scopes where `remaining_qty > 0`.
- **Overdue scopes**: `status=active`, `ends_on < today`, `remaining_qty > 0`.
- **Effort**: planned = sum of scope `planned_qty` (= `contract_deliverables`); completed = weighted allocation effort (`quantity × type.effort_score`).

### Create / update scope body

```json
{
  "client_id": 1,
  "project_id": 5,
  "name": "August Content Scope",
  "starts_on": "2026-08-01",
  "ends_on": "2026-08-31",
  "contract_deliverables": 20,
  "description": "Notes",
  "status": "active"
}
```

- `code` is auto-generated on create (`SCP-YYYYMMDD-####`) and ignored on update.
- `project_id` optional; must belong to `client_id` when set.
- `contract_deliverables` required on create; must be `>= 0`. HR/Owner sets the **Total Expected Deliverables** directly; Business adds allocation items later.
- Planned = `contract_deliverables` only.

### Allocation body (multi-item)

```json
{
  "scope_id": 1,
  "task_name": "August Social Media Campaign",
  "completed_date": "2026-08-09",
  "notes": "",
  "items": [
    { "deliverable_type_id": 1, "quantity": 2, "scope_impact": "normal" },
    { "deliverable_type_id": 2, "quantity": 2, "scope_impact": "normal" },
    {
      "deliverable_type_id": 3,
      "quantity": 1,
      "scope_impact": "replacement",
      "replaced_type_id": 4,
      "replacement_qty": 1
    }
  ]
}
```

- `task_name` required; at least one `items` row required.
- Per item: `deliverable_type_id`, `quantity` > 0, `scope_impact` (`normal`\|`replacement`\|`extra`); when `replacement` also `replaced_type_id` + `replacement_qty`.
- Duplicate type + impact (+ replaced type for replacements) rows are merged server-side.
- List/detail responses nest `items[]` and include `total_quantity` + `summary` (e.g. `2 Static, 2 Motion`).

---

## Response Format

```json
{
  "success": true,
  "data": { }
}
```

Error:

```json
{
  "success": false,
  "message": "Error description",
  "code": "error_code"
}
```
