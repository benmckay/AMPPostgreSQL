# RACI and Approval Workflows

## Request types

- new: New access for a user
- additional: Additional rights for an existing user
- reactivation: Reactivate a deactivated account (HR approval required)
- termination: Terminate/deactivate an account (HR may initiate)

## States

- draft → submitted → pending_manager → pending_hr → approved → in_fulfillment → completed
- Rejections may occur at manager or HR review: rejected
- Cancellation: cancelled

## RACI matrix (high-level)

- Requester: Responsible (submit, track, comment)
- COS: Responsible (submit physician requests with COS fields)
- Manager: Accountable for approve/reject of staff requests
- HR: Accountable for reactivation/termination approval; may initiate termination
- ICT Admin: Responsible for fulfillment and completion
- Auditor: Consulted/Informed via immutable audit logs and reports

## Approvals

- New/Additional: Manager approval; HR step if policy requires
- Reactivation: HR approval mandatory
- Termination: HR can initiate; Manager is informed; ICT Admin executes

## SLAs and escalation (suggested)

- Manager review: 48h → escalate to alternate approver
- HR review: 48h → escalate to HR lead
- Fulfillment: 24–72h depending on system

## Events to audit

- submit, approve_manager, approve_hr, reject, comment, fulfill (in_fulfillment), complete

## Endpoints mapping

- Submit: POST /api/requests
- Comment: POST /api/requests/:id/comment
- Manager approve: POST /api/requests/:id/approve/manager
- HR approve: POST /api/requests/:id/approve/hr
- Reject: POST /api/requests/:id/reject
- Fulfillment: POST /api/requests/:id/fulfillment (status=in_fulfillment|completed)
