# Personas and Role Templates

## Requester (Staff)

- Responsibilities: Submit New/Additional/Reactivate/Terminate access requests; track status; add comments.
- Dashboards: `/api/dashboard/requester` (KPIs, recent requests).
- Permissions: Create/list own requests; view own details; receive notifications on status changes.
- Request Types: new, additional, reactivation, termination.

## COS (Chief of Staff / Physicians Desk)

- Responsibilities: Submit physician-specific access requests with COS fields; track status; attach documents.
- Dashboards: `/api/dashboard/cos` (recent COS requests).
- Permissions: Create COS payload requests (provider group/type, specialty, service, admitting Y/N, ordering Y/N, sign/co‑sign).
- Request Types: new, additional; attachments as required by policy.

## Manager / Supervisor

- Responsibilities: Review and approve/reject staff requests; provide comments; monitor team submissions.
- Dashboards: `/api/dashboard/manager` (pending approvals).
- Permissions: Approve/Reject endpoints for team requests; add comments; view request history.
- Endpoints: `POST /api/requests/:id/approve/manager`, `POST /api/requests/:id/reject`.

## HR

- Responsibilities: Approve Reactivation/Termination; initiate deactivation; ensure compliance.
- Dashboards: `/api/dashboard/hr` (HR queue).
- Permissions: Approve HR-gated steps; initiate termination; add comments; audit readiness.
- Endpoints: `POST /api/requests/:id/approve/hr`, `POST /api/requests/:id/reject`.

## ICT Admin

- Responsibilities: Fulfill approved requests; mark fulfillment and completion; manage user accounts.
- Dashboards: `/api/dashboard/ict` (fulfillment queue).
- Permissions: Update fulfillment status; CRUD users (as per policy); view audit logs.
- Endpoints: `POST /api/requests/:id/fulfillment` (status=`in_fulfillment|completed`).

## Auditor

- Responsibilities: Read-only review of immutable audit logs and compliance reports.
- Dashboards: Admin analytics or dedicated audit views (future enhancement).
- Permissions: Read-only access to audit and reports; no write operations.

## Admin Analytics (Analytics Role/Template)

- Responsibilities: Review KPIs, volumes by type/department/status, processing times.
- Dashboards: `/api/dashboard/admin` (by_status, by_type summaries).
- Permissions: Read-only analytics queries.

## Access Matrix (High-level)

- Requester: Create/list own requests; comment; receive notifications.
- COS: Create COS requests; attach documents; track status.
- Manager: Approve/Reject; comment; view team requests.
- HR: Approve reactivation/termination; initiate termination; comment.
- ICT Admin: Update fulfillment; complete requests; manage users.
- Auditor: Read audit; export reports.
- Admin Analytics: Read dashboards/metrics.

## Notifications (current)

- Email/SMS stubs invoked on status changes and auth flows; provider integration to be configured.

## Notes

- RBAC enforcement will be tightened with team/role relationships and template assignment in the DB.
- COS forms include physician‑specific fields per PRD; validation rules should be applied at API layer.
