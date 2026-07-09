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
  "task": "Website",
  "notes": ""
}
```

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

### POST `/export/excel`

```json
{ "date_from": "2026-07-01", "date_to": "2026-07-31", "user_id": null }
```

Returns file URL with 5 sheets: Raw Timesheet, Client Analytics, Employee Summary, Charts, Overtime.

**Capability:** `etwa_export_reports`

---

### POST `/export/email`

Same body as Excel export. Sends TO HR, CC Owner.

**Capability:** `etwa_email_reports`

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

**Balance model:** One **annual leave pool** per employee. HR sets entitlement on the **Annual Leave** type only. All approved usage (annual, sick, emergency, compensatory) deducts from that pool; **unpaid** leave does not. Usage is still stored by leave type for reporting.

Employees must have the **Think Marketing Employee** (`etwa_employee`) role.

---

### GET `/vacations/leave-types`

Active leave types (annual, sick, emergency, compensatory, unpaid).

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

## AJAX Actions (admin-ajax.php)

| Action | Description |
|--------|-------------|
| `etwa_autosave_entry` | Autosave entry draft |
| `etwa_save_settings` | Save owner settings |
| `etwa_toggle_dark_mode` | Toggle user dark mode preference (`etwa_dark_mode` user meta); returns `{ dark_mode: boolean }` |

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
