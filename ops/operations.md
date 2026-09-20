# Deployment and recovery

Serve only Laravel's `public` directory. Configure HTTPS, `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` to the staff HTTPS origin, `CITIZEN_DOMAIN` to the citizen hostname, and `SESSION_SECURE_COOKIE=true`. Keep session cookies scoped to each host. Use a dedicated MySQL account and keep `.env`, private storage, logs and backups out of the web root.

Deployment sequence: save a backup; install locked PHP dependencies; validate assets with `npm run build`; run regression tests against isolated SQLite; run `php artisan migrate --force`; rebuild configuration/routes/views with `php artisan optimize`; and check staff login, each role's routes, citizen lookup, settlement verification, report download and private-asset denial. Do not use demo seeders in production. Do not use `migrate:fresh` or restore over a live database as routine deployment steps.

Run `php artisan poso:backup` to write a private MySQL dump. Set `POSO_MYSQLDUMP` to the correct executable. The command uses a temporary private option file for credentials, cleans it up, removes incomplete output on failure, and never displays the password. The Laravel scheduler registers a daily 23:00 backup; the host must invoke `php artisan schedule:run` every minute. Linux cron or Windows Task Scheduler can provide that invocation. No Windows scheduled task is installed by the code itself.

Restrict filesystem access to the service account and authorized operators. Keep an encrypted copy of backups on a different device or managed backup service. Decide retention and monitoring with the office; this application does not automatically delete backup files or claim off-device backup coverage. Check failed jobs/logs and available disk capacity.

Test recovery in an independently named, empty database. Use a dump created without `--databases` so it has no database-switch command. Confirm the selected target is not the live database, import the dump there, point only the isolated test process at it, and compare counts, identifiers, original values and settlement history. Test migrations on this restored copy before applying them to the live database. Record the date, dump identifier and outcome of the drill.

A local recovery drill for this change restored the pre-change dump into `poso_restore_arch_20260911`, migrated that isolated copy, and confirmed hashes of every original column in users, profiles, violations, offense types, payments, receipts, payment events and audit logs. This demonstrates that dump's recovery, not continuous operational coverage.

Incident correction/dismissal authority, ID custody and release, ordinance-specific deadlines, valid Treasury reference series, and any Treasury integration remain office policy decisions. Admin fine reconciliation is limited to an unknown amount and requires ordinance evidence and a reason. It is not general incident editing.
