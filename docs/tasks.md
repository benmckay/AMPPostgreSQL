# AMP Tasks Tracker

Status legend: pending | in_progress | completed | blocked | cancelled

- Personas and role templates: completed
  - Summary: Roles, responsibilities, dashboards, permissions defined. See `docs/personas_and_templates.md`.

- RACI and approval workflows: completed
  - Summary: States, RACI, SLAs, endpoint mapping. See `docs/raci_workflows.md`.

- Authentication design (login, MFA, policy): completed
  - Summary: bcrypt, JWT, OTP, password policy, rate limits, audit. See `docs/auth_mfa_policy.md`.
  - Files: `php/api/controllers/AuthController.php`, `php/api/lib/JWT.php`, `php/api/lib/Notifications.php`, `php/api/lib/Audit.php`
  - Endpoints: `POST /api/auth/login`, `POST /api/auth/verify-otp`, `POST /api/auth/reset-password`, `PUT /api/auth/update-password`

- Auth service implementation: in_progress
  - Summary: Login→OTP→JWT, reset/update, login auditing, last_login_at; added OTP rate limiting and account lockouts; add further hardening.
  - Files: `php/api/controllers/AuthController.php`, `php/public/login.html`, `php/public/otp.html`, `php/public/reset.html`

- OTP adapters and rate limiting: in_progress
  - Summary: Rate limits added; providers remain stubs; integrate Email/SMS providers next.
  - Files: `php/api/lib/Notifications.php`

- User management backend (CRUD, departments, login history): in_progress
  - Summary: CRUD present; added login history endpoint and last_login_at updates; departments/roles workflows pending.
  - Files: `php/api/controllers/UsersController.php`, `db/migrations/001_init.sql`
  - Endpoints: `GET /api/users`, `GET /api/users/:id`, `POST /api/users`, `PUT /api/users/:id`, `DELETE /api/users/:id`, `GET /api/users/:id/logins`

- User management UI: in_progress
  - Summary: `users.html` lists/creates users, adds filter and deactivate/reactivate toggle; further edits pending.
  - Files: `php/public/users.html`

- Access request model (Requester): in_progress
  - Summary: Create/list/get/comment implemented; add more validations and UI forms.
  - Files: `php/api/controllers/RequestsController.php`, `db/migrations/001_init.sql`
  - Endpoints: `GET /api/requests`, `GET /api/requests/:id`, `POST /api/requests`, `POST /api/requests/:id/comment`, attachments download

- COS access request (physician fields): in_progress
  - Summary: Server-side validations added; attachments supported; UI and admin review rules pending.
  - Files: `php/api/controllers/RequestsController.php`

- Request lifecycle, tracking, comments: completed
  - Summary: Transitions/events implemented; notifications on status change.
  - Files: `php/api/controllers/RequestsController.php`, `db/migrations/001_init.sql`
  - Endpoints: approvals, fulfillment, comments

- Approval endpoints (Manager, HR): completed
  - Summary: Manager approve/reject, HR approve, fulfillment updates.
  - Files: `php/api/controllers/RequestsController.php`, `php/public/index.php`
  - Endpoints: `POST /api/requests/:id/approve/manager`, `POST /api/requests/:id/approve/hr`, `POST /api/requests/:id/reject`, `POST /api/requests/:id/fulfillment`

- Dashboards (all roles): completed
  - Summary: KPIs/queues for requester/manager/cos/hr/ict; admin summaries.
  - Files: `php/api/controllers/DashboardController.php`, `php/public/dashboard.html`
  - Endpoints: `GET /api/dashboard/requester|manager|cos|hr|ict|admin`

- Immutable audit logging: completed
  - Summary: Hash-chain writes, verification endpoint, and CSV export implemented.
  - Files: `php/api/lib/Audit.php`, `php/api/controllers/AuditController.php`, `db/migrations/001_init.sql`
  - Endpoints: `GET /api/audit/verify`, `GET /api/audit/export.csv`

- Reports & analytics: completed
  - Summary: Summary endpoint with filters (type, status, date range) and CSV export of requests.
  - Files: `php/api/controllers/ReportsController.php`
  - Endpoints: `GET /api/reports/requests/summary`, `GET /api/reports/requests.csv`

- Database schema and migrations: in_progress
  - Summary: Core tables done; added lockout fields via `002_lockout.sql`; runner applies all migrations.
  - Files: `db/migrations/*.sql`, `php/scripts/migrate.php`

- Encryption (TLS, pgcrypto): pending
  - Summary: Enforce TLS at deploy; evaluate encrypting sensitive columns with `pgcrypto`; key management plan.

- Notifications service and templates: in_progress
  - Summary: Stub adapters; integrate providers and message templates.

- Observability: in_progress
  - Summary: X-Request-Id header and structured request logs with duration added; extend to metrics/tracing.
  - Files: `php/public/index.php`

- Security & compliance controls: in_progress
  - Summary: Added security headers/CSP baseline, password policy (basic), OTP rate limits, account lockout; continue DPIA/retention.
  - Files: `php/public/index.php`, `php/api/controllers/AuthController.php`

- CI/CD and deploy: pending
  - Summary: Add GitHub Actions; Docker Compose present; document AWS EC2/RDS deployment.

- Performance SLOs and load tests: pending
  - Summary: Define SLOs and add load tests/regression gates.

- Data seeding: pending
  - Summary: Seed templates/systems and non-PII sample data. See `db/seed.sql`.

- UAT and go-live: pending
  - Summary: UAT cases mapped to PRD; go-live checklist; hypercare.
