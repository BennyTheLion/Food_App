# Kitchen Log

Mobile-first, Hebrew RTL food safety logging app for an institutional kitchen. Covers all 13 regulatory forms (disinfectant checks, chiller logs, receiving, station serving, training, defrost tracking, etc.) plus a manager dashboard.

## Requirements

- PHP 8.1+ with the **`pdo_sqlite`** extension enabled
- A writable `data/` directory (the app creates and seeds `data/kitchen_log.sqlite` automatically on first request)
- No database server, no Node, no build step — it's plain PHP served by Apache/nginx

## Local development (XAMPP)

Drop this folder anywhere under `htdocs`, make sure Apache is running, and visit `index.php` in that folder. The app detects its own base URL at runtime (`inc/layout.php::kl_base_path()`), so it works unchanged from any subfolder or from a domain root — no config file to edit.

## Deploying to Hostinger

1. In hPanel, go to your website → **Advanced → Git**.
2. Repository URL: `https://github.com/BennyTheLion/Food_App.git`
3. Branch: `main`
4. Deployment path: wherever you want the app served from (domain root or a subfolder — both work, see above).
5. Deploy. Hostinger pulls the repo in.
6. Load the site once — this seeds the SQLite database automatically. If you get a database error, check that `pdo_sqlite` is enabled for your PHP version in hPanel → **Advanced → PHP Configuration**, and that the `data/` folder is writable.
7. To ship an update later: push to `main` on GitHub, then re-deploy from the same Git screen (or enable Hostinger's auto-deploy-on-push if available on your plan).

## Data model

- `forms` / `form_fields` — form templates, seeded from `data/forms-data.json` on every request (`inc/db.php::kl_seed_forms()`). Edit that JSON to change a form; don't hand-edit the database, it gets overwritten.
- `submissions` / `submission_values` — one row per filled-out form, one row per field value.

## Structure

```
index.php          Home: station picker + forms grouped by category
form.php            Dynamic form renderer + submit handler (?id=<formId>)
success.php         Post-submit confirmation
dashboard.php        Manager view: stats + recent submissions
submission.php       Single submission detail (?id=<submissionId>)
inc/db.php            SQLite bootstrap + form seeding
inc/gauge.php          Safe-zone ranges for temperature/ppm slider fields
inc/layout.php          Shared header/footer, kl_url() base-path helper
assets/css/style.css      Design system
assets/js/app.js           Station persistence, gauge readout, dynamic rows
data/forms-data.json        Source of truth for all 13 forms and their fields
```
