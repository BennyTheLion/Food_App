# Kitchen Log

Mobile-first, Hebrew RTL food safety logging app for an institutional kitchen. Covers all 13 regulatory forms (disinfectant checks, chiller logs, receiving, station serving, training, defrost tracking, etc.), multi-site/multi-kitchen access control, and a manager dashboard.

## Requirements

- PHP 8.1+ with the **`pdo_sqlite`** extension enabled
- A writable `data/` directory (the app creates and seeds `data/kitchen_log.sqlite` automatically on first request)
- `mail()` working on the host, if you want the "send to admin" email feature to actually deliver (works out of the box on Hostinger; on other hosts you may need SMTP configured at the server level)
- No database server, no Node, no build step — it's plain PHP served by Apache/nginx

## First login

On a fresh database the app seeds exactly one account:

- **Email:** `admin@kitchenlog.local`
- **Password:** `ChangeMe123!`

Log in, go to the admin panel, and:
1. Create your real sites and kitchens (Advanced → nothing needed, it's all in-app: `/admin/sites.php`, `/admin/kitchens.php`)
2. Create real user accounts and change the default admin's password (or just create a new admin and delete the seeded one)

These defaults live in `inc/db.php` (`KL_DEFAULT_ADMIN_EMAIL` / `KL_DEFAULT_ADMIN_PASSWORD`) if you want to change them before first deploy instead.

## Roles & access model

- **Site → Kitchen → Diary.** A site is a physical location (e.g. a campus); each site has one or more kitchens. The 13 forms are filled per-kitchen.
- **Users** are assigned to exactly one site (regular users) or no site at all (admins, who can access every site).
- **Admins** can reach `/admin/*` — manage sites, kitchens, users, and date-opening requests. Regular users get a 403 on those URLs.
- **Only admins can create users** — there's no self-service signup.
- **Entry dates are locked to today** for regular users on every form's primary date field (anything except `expiry_date`, which is inherently a future date). To backdate a form, a user submits a request from the form page itself; an admin approves or denies it in `/admin/date-requests.php`. Once approved, that kitchen can use that specific date.

## Google sign-in

The login page has a "Sign in with Google" button, currently disabled. To activate it:
1. Create an OAuth 2.0 Client ID in [Google Cloud Console](https://console.cloud.google.com/apis/credentials)
2. Set `KL_GOOGLE_CLIENT_ID` / `KL_GOOGLE_CLIENT_SECRET` in `inc/auth.php` (or better, load them from environment variables — don't commit real credentials)
3. The button enables itself automatically once both constants are non-empty (`kl_google_login_enabled()` in `inc/auth.php`)

## Uptime watcher

`healthcheck.php` is a lightweight public endpoint that checks PHP + database connectivity and returns JSON. `cron/watcher.php` polls it, and emails every admin only when the status actually *changes* (not on every check) — down, and again when it recovers.

**This needs to be set up in hPanel — I can't do this part myself, it requires your login:**

1. In hPanel, go to **Advanced → Cron Jobs**
2. Create a job that runs every 5–15 minutes
3. If your plan supports running a command: `php /home/<your-account-path>/cron/watcher.php`
   If it only supports fetching a URL: `https://<your-domain>/cron/watcher.php?secret=<KL_WATCHER_SECRET from inc/db.php>`
4. Check **Admin → זמינות האתר** (Site Health) any time to see current status without waiting for an email

**Real limitation worth knowing:** this cron job runs on the same Hostinger account it's checking. If the entire server or hosting account goes down, cron stops running too — so this catches application-level failures (a broken page, a database error, a bug) but not a total server outage. For that, point a free external service like [UptimeRobot](https://uptimerobot.com) at `healthcheck.php` too — that's a five-minute signup on their end, not something I can set up for you from inside this codebase.

Change `KL_WATCHER_SECRET` in `inc/db.php` if you want to invalidate the current one (e.g. if it's ever been shared).

## Local development (XAMPP)

Drop this folder anywhere under `htdocs`, make sure Apache is running, and visit `login.php` in that folder. The app detects its own base URL at runtime (`inc/layout.php::kl_base_path()`), so it works unchanged from any subfolder or from a domain root — no config file to edit.

## Deploying to Hostinger

1. In hPanel, go to your website → **Advanced → Git**.
2. Repository URL: `https://github.com/BennyTheLion/Food_App.git`
3. Branch: `main`
4. Deployment path: wherever you want the app served from (domain root or a subfolder — both work, see above).
5. Deploy. Hostinger pulls the repo in.
6. Load the site once — this seeds the SQLite database automatically, including the default admin login above. If you get a database error, check that `pdo_sqlite` is enabled for your PHP version in hPanel → **Advanced → PHP Configuration**, and that the `data/` folder is writable.
7. To ship an update later: push to `main` on GitHub, then re-deploy from the same Git screen (or enable Hostinger's auto-deploy-on-push if available on your plan).

## Data model

- `forms` / `form_fields` — form templates, seeded from `data/forms-data.json` on every request (`inc/db.php::kl_seed_forms()`). Edit that JSON to change a form; don't hand-edit the database, it gets overwritten.
- `sites` / `kitchens` — the location hierarchy, managed entirely through the admin panel.
- `users` — accounts with `role` (`admin`/`user`) and `site_id` (null for admins).
- `date_open_requests` — backdating requests and their approve/deny state.
- `submissions` / `submission_values` — one row per filled-out form, one row per field value. Linked to `kitchen_id` and `filled_by` (a user), not free-text names.

## Structure

```
login.php / logout.php     Auth
select-site.php              Post-login site picker (auto-skips if user has only one)
select-kitchen.php            Kitchen picker within a site (auto-skips if only one)
index.php                       Diary: forms grouped by category for the current kitchen
form.php                         Dynamic form renderer + submit handler (?id=<formId>)
success.php                       Post-submit confirmation
dashboard.php                      Kitchen-scoped stats + recent submissions
submission.php                      Submission detail, CSV export, "send to admin" email
admin/                                Sites, kitchens, users, date-request CRUD (admin-only)
inc/db.php                              SQLite bootstrap, form seeding, default admin seed
inc/auth.php                              Sessions, login, role/site access checks
inc/dates.php                              Date-restriction + request logic
inc/mailer.php                              "Send to admin" email
inc/export.php                              CSV export
inc/gauge.php                                Safe-zone ranges for temperature/ppm slider fields
inc/layout.php                                Shared header/footer, kl_url() base-path helper
assets/css/style.css                            Design system
assets/js/app.js                                 Gauge readout, dynamic rows
data/forms-data.json                              Source of truth for all 13 forms and their fields
healthcheck.php                                     Public status endpoint (PHP + DB check), JSON response
cron/watcher.php                                     Uptime watcher -- see "Uptime watcher" section above
```
