# HIMS Access and Visibility Rules

This document is the application-level policy for recognition, performance reviews, succession planning, notifications, and Global Search. Controller checks and route middleware remain the enforcement points; hiding a link or field in a view is not authorization.

## Recognition

| Role | Read | Create or interact | Moderate |
| --- | --- | --- | --- |
| Employee | Public wall; public and private recognition they sent or received | Named public or private recognition; comments and reactions on posts they may read | No |
| Supervisor | Same recognition access as any employee | Same recognition access as any employee | No |
| HR/Admin | Public wall and all private recognition | Same named posting access when linked to an employee | Badges, posts, comments, and audit history |

Recognition is always named. Public posts appear on the wall. Private posts are limited to the sender, recipient, and HR/admin moderators. Comments and reactions inherit the post audience. Anonymous recognition is not supported.

## Reporting Lines and People Managers

**People Manager controls who may appear in Reports To. Account role controls what they may do inside HIMS.** These are separate controls: marking an employee as a People Manager never changes a Staff account to Supervisor or grants any other permission.

For a new reporting assignment, the selected employee must be marked as a People Manager, have `employment_status = active`, and have a linked account whose role is `supervisor`, `hr_manager`, or `admin`. Admin and HR accounts do not appear merely because of their access role; their employee record must also be explicitly marked as a People Manager. The server repeats every check and rejects self-reporting and direct or indirect loops.

An unchanged legacy manager remains visible on the employee edit form even when inactive or missing suitable HIMS access, with a setup warning. That person cannot be chosen for a new assignment. People Manager status cannot be removed while direct reports remain. Employment status may still change to reflect leave, suspension, resignation, or termination, but active direct reports are named for reassignment. HR/Admin can review these mismatches at `GET /employees/manager-setup`.

## Performance Reviews

| Role | Read | Edit |
| --- | --- | --- |
| Employee | Their own reviews, reviewer identity, status, scores, comments, goals, response, signatures, and timestamps | Their response or appeal; one final acknowledgement after the cycle closes; never ratings or reviewer comments |
| Supervisor | Reviews they authored and reviews for their direct reports | Scores and reviewer comments only when named as the reviewer and while the cycle is open |
| HR/Admin | Reviews they authored, their own reviews, and direct-report reviews | Review cycles; documented exception reviews only when the reporting chain cannot act |

Self-reviews and peer reviews are prohibited. A formal review has one identifiable reviewer. A recorded manager can write it only through a linked Supervisor, HR Manager, or Admin account. If the recorded manager has no account or only Staff access, HR/Admin may use the documented `supervisor_account_unavailable` exception with a typed reason and audit entry. Finished reviews carry the reviewer's signed timestamp. Completed reviews are locked when the cycle end date passes. The employee may submit or replace a response after the reviewer has finished, but may acknowledge only after the cycle closes; acknowledgement locks that response. Review creation, review-cycle creation and edits, employee responses, acknowledgements, and reviewer score/comment/status changes record the acting user and employee in `audit_trails`. Accounts without a linked employee profile cannot create a review or review cycle under another employee's identity.

Any future 360-degree feedback process must keep contributors identifiable to HR while limiting the report to the employee and authorized HR users. It must not reuse the formal review score-writing path.

## Succession Planning

| Role | Read | Edit |
| --- | --- | --- |
| Employee/Staff | No succession records | No succession records |
| Supervisor | Candidate identity, target role, milestones and supporting evidence for direct-report candidates only; confidential ratings, readiness, nomination status, mentor, and vacancy-risk fields are redacted | Direct-report development milestones only |
| HR/Admin | Critical roles, vacancy risk, named candidates, performance and potential ratings, 9-box placement, readiness, mentors, evidence, and audit history | Critical roles, nominations, ratings, readiness, mentors, and all milestones |

Candidate ratings, readiness, nomination status, mentor, and vacancy risk are confidential HR data. Supervisor queries are scoped by `employees.supervisor_id`; department membership alone grants no access. HR/Admin can record the position's quarterly review timestamp, reviewer and notes. Every succession mutation must record the acting user and employee in `audit_trails`.

The organisation dashboard alerts HR/admin when a high or critical vacancy-risk role has no `ready_now` successor.

## Notifications

Notifications are scoped to the recipient employee. The topbar feed shows a short recent history of both unread and read rows; the unread count, blue emphasis, and unread marker are presentation state, not a second permission layer. A user may mark one of their own notifications read or mark all of their own notifications read. Neither endpoint can update another employee's rows.

Notification links never bypass module authorization. A destination may focus a related record when the recipient can see that record; an escalation recipient outside the record's reporting-line scope is sent to the accessible module landing page instead. The link is a convenience, not a grant of access.

## Global Search

Global Search is an authenticated JSON endpoint backed by an explicit source allow-list. It is not a database-wide scan and it never treats search-result visibility as permission to open a record. Each source applies the same boundary as its owning module:

| Role | Search boundary |
| --- | --- |
| Employee/Staff | Own employee, review, goal, learning, competency, credential, CPD, and renewal records; public pages and public recognition; own AI conversations |
| Supervisor | Own records plus direct-report records where the module permits reporting-line access; direct-report succession positions/candidates with confidential ratings redacted; own AI conversations |
| HR/Admin | Organisation-wide records permitted by their role, including private recognition moderation, succession confidential fields, departments, and (Admin only) user accounts; own AI conversations |

Private recognition is searchable only by its sender, recipient, or HR/Admin moderators. AI session titles and messages are searchable only by the owning account. Selecting a result redirects to the real module route and may include a focus anchor; the destination controller still enforces access.

## Audit Access

Audit history is restricted to HR and administrators. UI visibility is backed by route middleware; staff and supervisors cannot retrieve the audit endpoint directly. The audit trail is attributable but selective: reads are not comprehensively logged and ordinary edits outside the covered workflows may not create an audit row.
