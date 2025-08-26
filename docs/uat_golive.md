# UAT and Go-Live

## UAT Scope

- Auth flows: login, OTP, reset/update password
- Requests: create (all types), approve/reject, fulfillment, comments
- Dashboards: requester/manager/cos/hr/ict/admin
- Reports: summary and CSV export
- Audit: verify chain, export CSV

## UAT Roles

- Requester, Manager, HR, ICT Admin, Auditor

## Acceptance Criteria

- All endpoints return expected responses and errors are user-friendly
- Audit logs present for key actions
- Notifications triggered for status changes (stub OK in UAT)

## Go-Live Checklist

- [ ] Backups configured for PostgreSQL
- [ ] Secrets set (JWT, DB creds) in environment/secret store
- [ ] TLS termination configured (proxy/load balancer)
- [ ] Monitoring and alerts enabled
- [ ] Admin accounts created; roles assigned
- [ ] Rollback plan documented
- [ ] Hypercare on-call rota for first 2 weeks
