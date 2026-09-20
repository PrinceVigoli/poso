# Audit remediation — 11 September 2026

All ten implementation findings in the logic audit have been addressed. The two-role workflow remains: Enforcers submit violations; Admins verify Treasury receipts and monitor records.

| Finding | Result |
| --- | --- |
| Same-name merging | All matching profiles require explicit selection; a separate person with the same name is allowed. Candidate choices show addresses and profile references. |
| Historical data overwritten | Each violation captures its own person details. Recording another violation does not rewrite the profile or older incidents, and a blank plate stays blank. Staff violation searches use incident details. |
| Public enumeration | Public lookup now requires a full name and private random access code via POST. It grants access to one violation for 15 minutes and reveals only payment status and relevant dates. Numeric URLs, name searches, and wildcards cannot disclose other records. |
| Receipt reuse | Receipt references are normalized and claimed under a database lock. Shared coverage requires explicit acknowledgement, the same person, a consistent receipt total, and enough remaining value. |
| Unpaid statistics | Both pending and overdue payments count as unpaid. |
| Historical offense ranking | The dashboard includes retired offense versions instead of dropping their historical counts. |
| Dismissed records | Dismissed violations do not become overdue, count as unpaid, or accept new payment verification. Public and report views show Dismissed. |
| Traffic-only categorization | Admin forms support Traffic Violation, Peace and Order, Public Safety, and Other Local Ordinance, and preserve the selected category during editing. These labels do not establish the applicable ordinance catalog. |
| Incorrect payment verification | Admins can reverse a verification with a reason and confirmation, then verify the correct receipt. Version checks reject stale reversals. Original receipt details, actor, timestamp, and correction reason remain in payment history and report events. |
| Concurrent reference collisions | Internal record references use UUIDs rather than counting today's rows. Submission tokens have a unique database constraint. |

**Using the updated workflows**

After enforcer confirmation, the saved-record page displays a private citizen access code once. Give it only to the named person after checking their identity. Admins, and the enforcer who recorded the violation, can generate or replace a code from that record. Replacing it revokes both the old code and existing access granted with it. Existing records receive codes only when staff generate them; the migration does not publish new credentials. POSO is not issuing a Treasury receipt through this feature.

For payment verification, enter the official Treasury receipt number and its total amount. If it also covers other violations for the same person, review those records and explicitly confirm shared coverage. The server checks that allocated fines do not exceed the receipt total. Unknown fine amounts are blocked from verification until reconciled; the system does not invent amounts. To correct an erroneous verification, use the correction section on the violation record, provide the reason, reverse it, and verify the correct receipt.

**Historical data and migration**

A SQL backup was saved under `tmp/backups/poso-portal-before-audit-fixes-2026-09-11.sql` before migration. That directory is excluded from Git. The migration preserved all existing users, profiles, violations, payment statuses, dates, and recording-user IDs. Available profile details were copied into legacy incident snapshots. It cannot recover details overwritten before the fix; the staff page identifies legacy snapshots accordingly.

Existing receipt references are retained and linked to receipt records. Their original total is not guessed. Old conflicting references are marked for review; future allocation requires reconciliation. Existing paid records do not need to be re-entered just because of the migration.

Before and after local migration: 5 users (1 Admin, 4 Enforcers), 6 violator profiles, 1 violation, and 1 payment record. The authorship checksum was unchanged.

**Validation**

45 automated tests passed with 457 assertions, using isolated in-memory SQLite databases. The suite covers same-name people, immutable incident details, blank plates, record-specific public access, code expiry and revocation, role permissions, shared-receipt limits, stale corrections, audit retention, dismissed outcomes, offense history, and migration preservation. The test base now rejects non-isolated database configurations before tests can run database resets.

Seven local MySQL-backed page checks returned HTTP 200: dashboard, filtered violations, violation details, reports, offense management, public lookup, and the issuing enforcer's saved record. New receipt allocation uses row locks and unique reference keys; concurrent MySQL load testing was not performed.

**Business rules retained**

The existing 15-day deadline remains unchanged pending confirmation of ordinance-specific due dates. POSO settlement dates remain verification dates, not asserted Treasury payment dates. Enforcer record correction and confiscated-ID return tracking remain separate future workflow decisions; this update does not grant general editing or deletion access.