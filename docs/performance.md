# Performance SLOs and Load Test Plan

## SLOs (initial)

- Auth login API p95: <= 300ms (excluding OTP wait)
- OTP verify API p95: <= 200ms
- Requests list (own) p95: <= 500ms for 1000 rows
- Dashboard requester p95: <= 400ms
- Admin summary p95: <= 600ms

## Test Data Volume (baseline)

- Users: 5k
- Access requests: 100k (mixed types/statuses)
- Request events: 300k

## Load Scenarios

- Login burst: 50 RPS for 1 min, steady 10 RPS for 10 min
- Requests creation: 20 RPS for 10 min
- Dashboard reads: 30 RPS for 10 min

## Tooling

- k6 or JMeter scripts (to be added under `load/`)
- CI gate: fail if any p95 exceeds SLO + 20% buffer

## Tuning Notes

- Ensure proper indexes (status, type, requester_id, created_at)
- Use pagination for lists
- Avoid N+1 queries; prefer single aggregate queries for dashboards
