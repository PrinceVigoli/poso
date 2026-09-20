# Local POSO sites

Both domains use this Laravel application's `public` directory. Start Apache and MySQL in Laragon.

| URL | Page |
| --- | --- |
| http://poso-portal.test | POSO landing page |
| http://poso-portal.test/login | Staff login |
| http://portal.poso.test | Check Settlement Status |

The main Laragon virtual host already points at `C:/laragon/www/poso-portal/public`. Copy `portal.poso.test.conf` into `C:/laragon/etc/apache2/sites-enabled/` and add `127.0.0.1 portal.poso.test` to the Windows hosts file (`C:/Windows/System32/drivers/etc/hosts`) with administrator access. Reload Apache after configuration changes.

No separate Laravel server is needed for either domain when Apache is running. CSS, Bootstrap, icons and fonts are served locally. Run `npm run build` to validate asset references after changes; no Vite server is needed.

For the alternative `php artisan serve` workflow, `http://127.0.0.1:8000/` serves the landing page and `/search` serves citizen search. The named citizen domain uses Apache on port 80.

For hosting, point both domains to the same Laravel `public` directory, set `APP_URL` to the main site's HTTPS URL and `CITIZEN_DOMAIN` to the citizen hostname (without scheme or path), then rebuild Laravel's configuration and route caches. Configure DNS and TLS on the hosting provider. Keep session cookies scoped to each host.
