# MVP Roadmap — Phased Implementation

## Phase 1 — Foundation (Complete in this repo)

**Goal:** Core infrastructure and employee workflow

- [x] Plugin bootstrap, autoloader, activation/deactivation
- [x] Custom database tables (6 tables)
- [x] Role & capability system (Employee, HR Manager, Owner) — HR/Owner have no own-timesheet caps; employees only fill/submit timesheets
- [x] Time calculation engine (regular + overtime rules)
- [x] Date-based work week (Sun–Thu)
- [x] Employee dashboard (entries, draft, submit)
- [x] REST API for timesheets & entries
- [x] Audit logging
- [x] Autosave via AJAX

**Deploy:** Activate plugin, assign roles, add clients, test employee flow.

---

## Phase 2 — HR Operations (Complete in this repo)

**Goal:** Approval workflows and reporting

- [x] HR dashboard (pending/approved/overtime tabs)
- [x] Timesheet approve/reject workflow
- [x] Separate overtime approve/reject
- [x] Lock approved periods
- [x] Excel export (5 sheets via PhpSpreadsheet)
- [x] Email reports (TO HR, CC Owner)
- [x] Filters, search, pagination (REST-driven)
- [x] Copy previous week (into the **viewed** week; previous week = adjacent week before target)

**Deploy:** Run `composer install`, configure HR/Owner emails in Settings.

---

## Phase 2b — Portal UI polish (Complete in this repo)

**Goal:** Unified frontend portal usable with Elementor themes

- [x] Unified portal shell (`[etwa_portal]`) with role-based sidebar and header actions
- [x] Theme toggle (light / OLED dark) on all portal tabs, persisted per user
- [x] Portal asset loading fix (`portal-shell.js` + footer fallback)
- [x] Elementor/theme isolation for tabs, buttons, links, and confirmation dialogs
- [x] HR Dashboard pill tabs (`.etwa-tab`) — consistent inactive/hover/active states
- [x] Client Analytics date preset tabs (same pill style)
- [x] Timesheet week switcher controls and Add entry button hover states
- [x] Copy Previous Week respects `?etwa_week=` / viewed week start (REST `date` param)

**Deploy:** Hard-refresh portal page after plugin updates; verify Theme toggle and Copy Previous Week on a navigated empty week.

---

## Phase 2c — Reports & Exports (Complete in this repo)

**Goal:** Centralized reporting with shared date ranges and Excel exports

- [x] `ReportingService` — unified query/calculation layer for all report types
- [x] `ReportExportService` — PhpSpreadsheet Excel generation per report
- [x] Reports REST API (`/reports/filters`, `/reports/export/{type}`, `/reports/all-clients-comparison`)
- [x] Portal tab + WP admin page **Reports & Exports**
- [x] Shared JS utilities (`etwa-api.js`, `etwa-date-range.js`, `reports-exports.js`)
- [x] Five report types: Employee Timesheet, Client, Client Labor Cost, Employee Hours by Client, All Clients Comparison
- [x] Live All Clients Comparison dashboard with Chart.js, department/employee filters, sortable table
- [x] Role-gated labor cost and analytics visibility

**Deploy:** Run `composer install`, open Portal → Reports & Exports or WP Admin → Reports & Exports.

---

## Phase 2d — Timesheet entry types & draft workflow (Complete in this repo)

**Goal:** After-hours OT/compensation tracking and HR draft visibility

- [x] `entry_type` column (`regular`, `overtime`, `compensation`) with migrations through schema 1.17.0
- [x] After-hours entry type picker in employee dashboard
- [x] Save Draft snapshot (`draft_saved_at`, `draft_snapshot`) for HR review before submit
- [x] Moderation client rename with required detailed notes
- [x] Client Analytics Regular / OT / Combined / Compensation KPIs
- [x] Overtime cost multiplier default 1×

**Deploy:** Activate or re-save plugin to run migrations; hard-refresh timesheet JS after upgrade.

---

## Phase 2e — Branded email notifications (Complete in this repo)

**Goal:** Consistent HTML email wrapper for all workforce notifications

- [x] `MailService` — centralized HTML layout, placeholder replacement, `wp_mail()` delivery
- [x] `EmailSettingsRepository` — template defaults in `etwa_email_settings` option
- [x] Email Settings admin page — greeting, body slot, signature rows, logo, live preview, test send
- [x] `NotificationService` — hook-driven emails for timesheets, overtime, leave, requests
- [x] Branded report and reminder emails via `EmailService` and `CronJobs`
- [x] Plain-text password reset kept separate (WordPress `retrieve_password()` + `AuthHelper` filters)

**Deploy:** Configure **Timesheets → Email Settings**; set `hr_email` under **Settings** for HR notifications.

See [SYSTEM-DOCUMENTATION.md — Email system](SYSTEM-DOCUMENTATION.md#email-system).

---

## Phase 3 — Owner Analytics (Complete in this repo)

**Goal:** Executive visibility

- [x] KPI cards (Total, Regular, OT, Utilization %)
- [x] Top clients & client consumption %
- [x] Employee utilization %
- [x] Monthly & overtime trends (Chart.js)
- [x] Department performance
- [x] Insights (overloaded, underutilized, client OT ratio)
- [x] Advanced analytics REST endpoint

**Deploy:** Verify Chart.js CDN access; review date range filters.

---

## Phase 4 — Production Hardening (Recommended next)

**Goal:** Enterprise readiness

- [ ] Unit tests (PHPUnit) for TimeCalculator, OvertimeService
- [ ] Integration tests for REST endpoints
- [ ] Rate limiting on export/email endpoints
- [ ] Database indexes review under load (10k+ entries)
- [ ] Object cache for settings & analytics queries
- [ ] i18n: load `.pot` / translation files
- [ ] Capability checks on every admin template render
- [ ] Nonce validation audit

**Estimate:** 1–2 sprints

---

## Phase 5 — UX Enhancements

**Goal:** Excel replacement parity+

- [ ] Inline grid editing (spreadsheet-style)
- [ ] Keyboard navigation between 30-min cells
- [ ] Bulk entry import from legacy Excel
- [ ] Mobile-optimized entry form
- [ ] In-app notification center (not just email)
- [x] Previous reports browser with date picker (Reports & Exports tab)
- [ ] Department/team filters on HR dashboard
- [ ] Additional theme palettes beyond light / OLED dark

**Estimate:** 2–3 sprints

---

## Phase 6 — Scale & Integrations

**Goal:** Multi-site and external systems

- [ ] Multisite network support
- [ ] SSO / LDAP role mapping
- [ ] Webhook events (timesheet.submitted, overtime.approved)
- [ ] Slack/Teams notifications
- [ ] Payroll system export adapters
- [ ] Read replica support for analytics queries
- [ ] Background export queue (Action Scheduler)

**Estimate:** 3+ sprints

---

## Implementation Phases — Paste Order

When deploying incrementally, activate in this order:

1. **Phase 1 files:** Main plugin file, `includes/Database`, `includes/Models`, `includes/Repositories`, `includes/Helpers`, `includes/Services/TimesheetService.php`, `includes/REST/TimesheetController.php`, `includes/REST/EntryController.php`, employee dashboard
2. **Phase 2 files:** Add `OvertimeService`, approval controllers, HR dashboard, export/email services, cron
3. **Phase 3 files:** `AnalyticsService`, owner dashboard, Chart.js assets

Each phase is independently testable via REST before UI polish.

---

## Success Metrics

| Metric | Target |
|--------|--------|
| Employee submission time | < 5 min/week |
| HR approval cycle | < 24 hours |
| Export generation | < 10 seconds for 50 employees |
| Page load (dashboard) | < 2 seconds |
| Audit coverage | 100% of state changes |
