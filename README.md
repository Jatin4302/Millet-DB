# Millet Genomic Resource

Complete deployment guide for the Millet Genomic Resource website. The application is a static HTML/CSS/JavaScript frontend with PHP search and CSV endpoints backed by MySQL or MariaDB.

## Requirements

- Apache web server
- PHP 7.4 or newer with the `mysqli` extension enabled
- MySQL 5.7+ or MariaDB 10.4+
- A modern browser with JavaScript enabled

XAMPP includes Apache, PHP, MySQL/MariaDB, and phpMyAdmin, so it is the easiest local option on Windows.

## Project files

- `home.html` - landing page
- `search.html` - database search page
- `resources.html` - genomics tools workspace with vertical tool toolbar
- `about.html` - project and data model information
- `contact.html` - contact form interface; it does not send email yet
- `css/style.css` - shared responsive styles
- `js/script.js` - search requests, filters, pagination, and result rendering
- `php/db_connect.php` - database connection settings
- `php/schema.sql` - database, tables, indexes, and sample records
- `php/search.php` - filtered, paginated JSON search endpoint
- `php/export.php` - filtered CSV export endpoint
- `php/health.php` - JSON health check for PHP and MySQL
- `php/admin.php` - protected image manager and CSV import workspace
- `php/site_config.php` - public read-only endpoint for managed homepage images
- `tests/run_tests.php` - integration tests for health, filters, pagination, and CSV export
- `assets/logo.png` - site logo

## Local deployment with XAMPP on Windows

### 1. Install XAMPP

1. Download XAMPP from [apachefriends.org](https://www.apachefriends.org/).
2. Install it, normally in `C:\xampp`.
3. Open the XAMPP Control Panel.
4. Start **Apache** and **MySQL**.

If Apache will not start, another program may be using port 80 or 443. Stop that program or change Apache's port in the XAMPP configuration.

### 2. Copy the website into Apache

Copy the entire project folder into:

```text
C:\xampp\htdocs\MilletDB
```

Using a folder name without spaces is recommended. The final structure should include:

```text
C:\xampp\htdocs\MilletDB\home.html
C:\xampp\htdocs\MilletDB\search.html
C:\xampp\htdocs\MilletDB\css\style.css
C:\xampp\htdocs\MilletDB\js\script.js
C:\xampp\htdocs\MilletDB\php\db_connect.php
C:\xampp\htdocs\MilletDB\php\schema.sql
```

### 3. Create the database

1. Open [http://localhost/phpmyadmin](http://localhost/phpmyadmin).
2. Select **Import**.
3. Choose `C:\xampp\htdocs\MilletDB\php\schema.sql`.
4. Click **Go**.
5. Confirm that `ssr_transcriptomics_db` was created.
6. Confirm that it contains `ssr_markers` and `transcriptomics` tables.
7. Open the admin page. The application automatically creates the `site_images` and `site_settings` tables and inserts their default settings when it connects.

The schema inserts sample records for testing. Do not repeatedly import it into a production database without checking first, because the sample `INSERT` statements can create duplicates.

### 4. Configure the database connection

Open `C:\xampp\htdocs\MilletDB\php\db_connect.php` and set values matching MySQL:

```php
$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "ssr_transcriptomics_db";
```

For a default XAMPP installation, the root password is often empty. If you configured a root password, set `$db_pass` to it.

For production, do not use MySQL `root`. Create a separate user with access only to this database and a strong password.

### 5. Open the website

Open:

```text
http://localhost/MilletDB/home.html
```

The search page is:

```text
http://localhost/MilletDB/search.html
```

The Tools page is:

```text
http://localhost/MilletDB/resources.html
```

It provides shortcuts for NCBI BLAST, Pfam, Phytozome, ChopChop, and ePCR. Each tool has an inline preview where the provider permits framing, plus an `Open tool` fallback for providers that block embedded views.

The administration page is:

```text
http://localhost/MilletDB/php/admin.php
```

The development login password is configured in `php/admin.php` or through `MGR_ADMIN_PASSWORD`. Before sharing the site, set the environment variable for Apache/PHP to a strong password. The admin page accepts JPG, PNG, WEBP, and GIF images up to 5 MB, including separate navbar and page background images, and imports CSV files using the headers shown on the page. Uploaded images are stored in `assets/uploads/`, so that directory must be writable by Apache.

Do not open the HTML files directly with a `file:///` URL. Apache must serve the site so the browser can call the PHP endpoints.

### 6. Test the deployment

On `search.html`:

1. Click the `Oryza sativa` sample search.
2. Confirm marker records appear.
3. Search for gene ID `OsGene001`.
4. Switch to **Transcriptomics**.
5. Try tissue `leaf` or condition `drought stress`.
6. Try SSR filters such as chromosome `Chr1` and motif `AT`.
7. Click **Download CSV** and confirm a CSV downloads.
8. If there is an error, inspect the browser developer console and Apache/PHP logs.

### Health check

Once Apache and MySQL are running, open this URL:

```text
http://localhost/MilletDB/php/health.php
```

A healthy installation returns HTTP `200` and JSON containing `"status":"healthy"`, `"php":"ok"`, and `"database":"ok"`. A database failure returns HTTP `503` without exposing database credentials.

### Run the automated endpoint tests

The test runner requires PHP CLI with the cURL extension. Keep Apache and MySQL running, then run this command from the project root:

```bash
php tests/run_tests.php http://localhost/MilletDB
```

The tests verify:

- PHP and MySQL health
- SSR species, chromosome, and motif filters
- Transcriptomics tissue and condition filters
- Pagination metadata
- CSV response status, headers, and sample data

The command exits with status `0` when all tests pass and status `1` when any test fails.

## Deploying to a live Apache server

### 1. Prepare the server

Install or enable Apache 2.4+, PHP 7.4+ with `mysqli`, and MySQL/MariaDB. Verify PHP from the server shell:

```bash
php -v
php -m | grep mysqli
```

### 2. Upload the website

Copy the project into the Apache document root, for example:

```text
/var/www/html/milletdb/
```

The public URL will usually be:

```text
https://your-domain.example/milletdb/home.html
```

Keep the folder name simple and without spaces. Preserve the directory structure and filenames exactly.

### 3. Create the production database

Import `php/schema.sql` using phpMyAdmin or the command line:

```bash
mysql -u root -p < php/schema.sql
```

Create a restricted application user instead of using `root`:

```sql
CREATE USER 'millet_app'@'localhost' IDENTIFIED BY 'REPLACE_WITH_A_LONG_RANDOM_PASSWORD';
GRANT SELECT ON ssr_transcriptomics_db.* TO 'millet_app'@'localhost';
FLUSH PRIVILEGES;
```

The search and export endpoints only read data, so `SELECT` is sufficient for normal operation. Update `php/db_connect.php` with the production credentials. Never commit real passwords to a public repository.

### 4. Set permissions

Apache/PHP needs to read the website files; it does not need write access to HTML, JavaScript, CSS, PHP, or asset files.

Example Linux permissions:

```bash
find /var/www/html/milletdb -type d -exec chmod 755 {} \;
find /var/www/html/milletdb -type f -exec chmod 644 {} \;
```

Adjust ownership and permissions for your server distribution and hosting provider.

### 5. Enable HTTPS

Use a valid TLS certificate, such as one from Let's Encrypt, and redirect HTTP traffic to HTTPS. Do not expose phpMyAdmin publicly without authentication and network restrictions.

## Replacing sample data

The schema contains sample records for `Oryza sativa` and `Zea mays`. For real data:

1. Back up the database.
2. Import records into `ssr_markers` and/or `transcriptomics`.
3. Keep `species` and `gene_id` for the existing search fields.
4. Keep SSR fields `chromosome` and `motif` for those filters.
5. Keep transcriptomics fields `tissue` and `condition_name` for those filters.
6. If columns are added or renamed, update both `php/search.php` and `php/export.php`.
7. Test searches and CSV exports after importing data.

## Troubleshooting

### Search says “Search unavailable”

- Confirm Apache is running.
- Confirm the site was opened through `http://localhost/...`, not as a local file.
- Open `http://localhost/MilletDB/php/search.php?dataset=ssr` directly and inspect the response.
- Open `http://localhost/MilletDB/php/health.php` to distinguish a PHP/Apache problem from a database connection problem.
- Check credentials in `php/db_connect.php`.
- Check Apache/PHP error logs.

### “Database connection failed” appears

- Confirm MySQL is running.
- Confirm the database is named `ssr_transcriptomics_db`.
- Confirm the username and password.
- Confirm the database user is allowed to connect from the configured host.

### CSV download is empty

- Run the same filters on the search page first.
- Check spelling of species, gene, tissue, or condition values.
- Confirm the selected dataset matches the filter fields.

### Apache shows PHP source code

PHP is not being processed by Apache. Install PHP, enable the Apache PHP module or PHP-FPM integration, and restart Apache.

### Contact form does not send messages

The current `contact.html` form is only a frontend interface and has no server-side mail handler. Add a PHP endpoint, validation, spam protection, and an authenticated mail service before using it in production.

## Backups and maintenance

Back up the database regularly:

```bash
mysqldump -u millet_app -p ssr_transcriptomics_db > millet_backup.sql
```

Also back up the website files, especially `php/db_connect.php` and any local data files. Keep Apache, PHP, MySQL/MariaDB, and the operating system patched.
