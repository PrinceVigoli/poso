# Architecture and design remediation — 11 September 2026

Implementation and local deployment are complete. **The migration is applied to the main local database.** Original record values were preserved, and all 12 local MySQL-backed page checks returned HTTP 200.

## Implemented changes

- Password, role and activation changes increment a credential version and invalidate persistent login tokens. Existing sessions with the previous version must sign in again. New and reset passwords require 15–128 characters. Existing passwords are unchanged.
- Admins can reconcile an unknown fine with an ordinance reference, reason, confirmation and version check. The action records before/after history and cannot change an already configured fine or grant general incident editing.
- Treasury receipt date is recorded separately from POSO verification date. Shared allocations must agree on receipt date and total. Unknown legacy dates remain empty.
- The database migration enforces one payment row per violation. It rejects duplicate rows before changing the schema rather than removing history.
- Citizen and staff pages use settlement wording. Admin record pages show the incident address. The verification dialog shows the offense, required fine and an allocation review with remaining receipt value.
- Enforcers have a read-only My submissions page limited to their own records. Opening the entry page preserves previews. Prepared previews have separate tokens, a 30-minute lifetime and a maximum of ten open drafts. Clearing drafts is explicit.
- Mobile violation lists show cards with status and review actions. Search and date filters have labels. Citizen access codes can be copied from the saved record.
- Reports paginate each section and offer a complete streamed CSV export. Formula-like text is escaped. Queries use date ranges and additional indexes.
- Name suggestions use indexed normalized prefixes and score at most 200 candidates, showing up to 20. They remain suggestions requiring explicit identity selection, not an exhaustive identity search.
- Overdue status is derived consistently from due date and verification state. Dashboard and list reads no longer update payment rows.
- New offense versions share a stable identity for filtering and ranking. The migration does not guess the lineage of older unrelated version rows.
- Bootstrap, icons and fonts are served locally with license files. The unused Vite entry points were removed; npm run build validates the assets actually used by the layouts.
- Setup and operation documents were rewritten, CI verification was added, and a local Git repository was initialized. No commit or remote publication was performed.
- A private MySQL backup command and a daily 23:00 Laravel schedule were added. The command was exercised against the restored database. A host scheduler still needs to invoke Laravel's scheduler; no Windows scheduled task was installed.

## Verification completed

- **57 application tests passed with 557 assertions**, using isolated SQLite databases.
- An additional synthetic rendering test passed and produced representative pages.
- **17 pages were checked at desktop and phone widths**, producing 38 screenshots. The browser summary reported no issues. Local asset validation passed.
- A pre-change backup was saved as `tmp/backups/poso-before-architecture-fixes-2026-09-11.sql` (26,174 bytes).
- The backup was restored to the separate MySQL database `poso_restore_arch_20260911`.
- Migration `2026_09_11_000002_strengthen_settlement_architecture` succeeded on that restored copy. Hashes of every original column across the eight reviewed tables were unchanged.
- Original counts: 5 users, 6 profiles, 1 violation, 22 offense rows, 1 payment row, 0 Treasury receipt rows, 0 payment events and 4 audit logs.
- The backup command succeeded against the restored copy. Laravel schedule:list showed the daily backup task.

Evidence remains under `tmp/architecture-audit/`, which is excluded from version control. These checks do not establish production concurrency performance, full accessibility compliance or ongoing off-device backup coverage.

## Local deployment completed

Workspace approval execution became available again. The main database still matched the verified baseline, and a fresh private backup was saved before migration. Migration `2026_09_11_000002_strengthen_settlement_architecture` then ran successfully on the main local database in batch 6.

Completed checks:

1. Hash comparisons confirmed that all original values in the eight reviewed tables were unchanged before and after migration.
2. Dashboard, filtered violations, violation details, period report, offense management, public lookup and the issuing enforcer's saved record returned HTTP 200.
3. Amount-review and overdue queues, user management, My submissions and enforcer entry also returned HTTP 200. The 12 total page checks used the local MySQL database.
4. Local CSS, Bootstrap, icon and font references passed `npm run build` validation. Migration status showed no pending migrations.
5. A pre-migration backup was saved as `storage/app/private/backups/poso-20260911-220648-5e484af0.sql`.
6. A post-migration backup was saved as `storage/app/private/backups/poso-20260911-220708-d6bc0f07.sql`.
7. Laravel lists the daily 23:00 backup task. The host must still invoke `schedule:run` every minute; no Windows scheduled task was installed.

The earlier credit-related approval rejection is resolved for this deployment. The application suite remains at 57 passing tests and 557 assertions. The migration additionally passed the restored-copy and main-database checks; no claim is made that this constitutes concurrent production load testing.

The existing 15-day deadline, incident-date policy, authority for dismissal or incident corrections, ID custody/return procedure and Treasury integration remain office policy decisions. The implementation does not add those authorities.
