# Deploying the OrcQuest Upgrade Tracker

This guide puts the tracker on the internet so you can use it from any phone, tablet or computer.

- [Choosing a host](#choosing-a-host)
- [Option A: cPanel shared hosting (SparkedHost web hosting and similar)](#option-a-cpanel-shared-hosting)
- [Option B: your own server (VPS)](#option-b-your-own-server-vps)
- [After the first deploy](#after-the-first-deploy)
- [Updating to a new version](#updating-to-a-new-version)
- [Backups](#backups)
- [Troubleshooting](#troubleshooting)

## Choosing a host

The tracker is a PHP (Laravel) app with a SQLite database file, so it needs:

| Need | Why |
|---|---|
| **PHP 8.3 or newer** | Laravel 13 requires it. |
| PHP extensions `pdo_sqlite`, `sqlite3`, `mbstring`, `openssl`, `fileinfo`, `xml`, `ctype`, `tokenizer`, `dom` | Standard on almost every host; switch them on if needed. |
| A way to point a domain/subdomain at the app's `public/` folder | Only `public/` should be reachable from the web. |
| About 60 MB of disk | The app with its packages. Campaign data is tiny. |
| Ideally **a terminal** (SSH or cPanel *Terminal*) | To run a few one-off setup commands. Without one, cPanel *Cron Jobs* can stand in (explained below). |

**Bandwidth is not a concern.** Measured on this app: the first visit transfers about 260 KB (compressed), later visits about 50 KB, and each auto-save about 13 KB. A long game night with a few hundred saves comes to roughly **5 MB**, so a 20 GB/month plan covers thousands of sessions.

### Recommendations

| Situation | Recommendation |
|---|---|
| You have (or are buying) **cPanel web hosting**, e.g. SparkedHost's web hosting plans | Use it: follow [Option A](#option-a-cpanel-shared-hosting). Before buying, check the plan offers **PHP 8.3+** and ideally **SSH or the cPanel Terminal**; ask support if the plan page does not say. |
| You want **free** and don't mind some Linux setup | Oracle Cloud's *Always Free* tier includes a small VPS that runs this easily: follow [Option B](#option-b-your-own-server-vps). |
| You want **zero server work** and don't mind paying | A managed Laravel host (Laravel Cloud, Forge + a cheap VPS) handles it with Git push-to-deploy. |
| Your account only has **game servers** (Minecraft, etc.) or a "bot hosting" panel | These panels generally do not run PHP websites. You need a web hosting plan or a VPS. |

## Option A: cPanel shared hosting

These steps use cPanel menu names; your host's theme may arrange them slightly differently.

### 1. Set the PHP version and extensions

1. In cPanel open **Select PHP Version** (CloudLinux) or **MultiPHP Manager**.
2. Choose **PHP 8.3** (or newer) for the domain you'll use.
3. Under **Extensions**, make sure `pdo_sqlite`, `sqlite3`, `mbstring`, `fileinfo`, `openssl`, `xml`, `dom`, `ctype` and `tokenizer` are ticked.

### 2. Create the address

A **subdomain** (for example `tracker.yourdomain.com`) is the cleanest, because you can point it straight at the app's `public/` folder:

1. cPanel → **Domains** (or **Subdomains**) → **Create a new domain**.
2. Enter `tracker.yourdomain.com`.
3. Untick **Share document root** and set the **Document Root** to `orcquest/public`.

> Want it on your main domain instead? Upload the app as below, then in the Terminal run
> `mv ~/public_html ~/public_html.old && ln -s ~/orcquest/public ~/public_html`.

### 3. Build the upload package (on your computer)

In the project folder, in PowerShell:

```powershell
powershell -ExecutionPolicy Bypass -File scripts\package-release.ps1
```

This creates `dist\orcquest-tracker-<date>.zip` containing the app, its production packages and the built styles and scripts. It never includes your `.env`, your database or anything in `storage/`.

> Not on Windows? Run `npm run build` and `composer install --no-dev --optimize-autoloader`, zip the project folder **without** `node_modules`, `.env`, `database/database.sqlite` and `storage/logs`, then run `composer install` again to restore your development packages.

### 4. Upload and unpack

1. cPanel → **File Manager** → your home folder (the one that contains `public_html`).
2. Create a folder named **`orcquest`** and open it.
3. **Upload** the zip, then right-click it → **Extract**. Delete the zip afterwards.

### 5. Configure `.env`

1. In `orcquest`, copy **`.env.example`** to **`.env`** (File Manager → right-click → Copy; enable *Show Hidden Files* in Settings if you can't see it).
2. Edit `.env` and set:

```dotenv
APP_NAME="OrcQuest Tracker"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tracker.yourdomain.com
APP_KEY=            # filled in by the next step

DB_CONNECTION=sqlite

LOG_LEVEL=warning
SESSION_SECURE_COOKIE=true
ALLOW_REGISTRATION=true   # set to false after you've created your account
```

3. Create an empty database file: in `orcquest/database`, **+ File** → `database.sqlite`.

> Prefer MySQL? Create a database and user with cPanel's **MySQL Database Wizard**, then use `DB_CONNECTION=mysql`, `DB_HOST=localhost`, `DB_DATABASE=...`, `DB_USERNAME=...`, `DB_PASSWORD=...` instead.

### 6. Run the setup commands

**With a terminal** (cPanel → **Terminal**, or SSH):

```bash
cd ~/orcquest
php artisan key:generate --force
php artisan migrate --force
php artisan optimize
```

If `php -v` shows an older version than you selected, use the full path to PHP 8.3, typically `/opt/cpanel/ea-php83/root/usr/bin/php` (or `/usr/local/bin/ea-php83`).

**Without a terminal**:

1. On your computer, run `php artisan key:generate --show` and paste the printed `base64:...` value into `APP_KEY=` in the server's `.env`.
2. cPanel → **Cron Jobs** → add a job set to *Once Per Minute* with the command:
   `cd ~/orcquest && php artisan migrate --force && php artisan optimize`
3. Wait two minutes, then **delete the cron job**.

### 7. Permissions and HTTPS

1. Make sure these folders are writable by the site (in File Manager, *Change Permissions* to `755`, or `775` if `755` isn't enough): `orcquest/storage` (and everything inside), `orcquest/bootstrap/cache`, and `orcquest/database` (SQLite writes a small journal file next to the database).
2. cPanel → **SSL/TLS Status** → run **AutoSSL** for the new subdomain so it gets a free HTTPS certificate.

### 8. Open it

Visit `https://tracker.yourdomain.com`. You'll be asked to **claim the tracker** by creating the first account. Then continue with [After the first deploy](#after-the-first-deploy).

## Option B: your own server (VPS)

On a fresh Ubuntu 24.04 server (an Oracle Cloud *Always Free* instance works):

```bash
sudo apt update
sudo apt install -y nginx php8.3-fpm php8.3-sqlite3 php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip unzip git composer nodejs npm

sudo git clone https://github.com/lotius/OrcQuestTracker.git /var/www/orcquest
cd /var/www/orcquest
sudo chown -R $USER:www-data .
composer install --no-dev --optimize-autoloader
npm ci && npm run build
cp .env.example .env            # then edit it as in step 5 of Option A
touch database/database.sqlite
php artisan key:generate --force
php artisan migrate --force
php artisan optimize
sudo chgrp -R www-data storage bootstrap/cache database && sudo chmod -R g+w storage bootstrap/cache database
```

`/etc/nginx/sites-available/orcquest`:

```nginx
server {
    listen 80;
    server_name tracker.yourdomain.com;
    root /var/www/orcquest/public;
    index index.php;
    client_max_body_size 12m;

    gzip on;
    gzip_types text/css application/javascript application/json image/svg+xml;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    location ~ /\.(?!well-known) {
        deny all;
    }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/orcquest /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
sudo apt install -y certbot python3-certbot-nginx && sudo certbot --nginx -d tracker.yourdomain.com
```

(On Oracle Cloud, also open ports 80 and 443 in the instance's security list and in the server firewall.)

## After the first deploy

1. **Claim the tracker** by creating your account.
2. **Close registration** so strangers can't sign up: set `ALLOW_REGISTRATION=false` in `.env`, then run `php artisan optimize` again (settings are cached, so `.env` edits only take effect after this; without a terminal, use the cron trick from step 6).
3. **Bring your existing campaigns** across: on your own computer, open each campaign → `⋮` → **Export to file**; on the live site, **Campaigns** → **Import**. The original pre-login save file (`orcquest-tracker-state.json`) can be imported the same way.

## Updating to a new version

**cPanel:** build a new zip with `scripts\package-release.ps1`, upload it to `~/orcquest` and **Extract**, letting it overwrite existing files. Your `.env`, `database/database.sqlite` and `storage/` are not in the zip, so they stay as they are. Then run:

```bash
cd ~/orcquest && php artisan migrate --force && php artisan optimize
```

**VPS:**

```bash
cd /var/www/orcquest
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan optimize
```

## Backups

Everything lives in **`database/database.sqlite`** (or your MySQL database). Download that file now and then (File Manager → right-click → Download), or export individual campaigns from the app. Many cPanel hosts also offer automatic backups under **Backup** or **JetBackup**.

## Troubleshooting

| Symptom | Fix |
|---|---|
| **500 Server Error** | Check `storage/logs/laravel.log`. Common causes: `storage/` or `bootstrap/cache/` not writable; empty `APP_KEY`; PHP older than 8.3 for that domain. |
| **"could not find driver"** or a database error | Enable the `pdo_sqlite` and `sqlite3` extensions (or `pdo_mysql` for MySQL). Check `database/database.sqlite` exists and the `database/` folder is writable. |
| **Page loads without styles** | `public/build` is missing: the zip wasn't built with `npm run build`, or the document root does not point at `orcquest/public`. |
| **You see a file listing or the raw `index.php`** | The document root points at `orcquest` instead of `orcquest/public`. |
| **419 Page Expired** on sign-in | Sessions can't be stored: make `storage/framework/sessions` writable. If you use HTTPS, keep `SESSION_SECURE_COOKIE=true` and make sure `APP_URL` starts with `https://`. |
| **`.env` changes don't take effect** | Settings are cached: run `php artisan optimize` (or `php artisan config:clear`). |
| **"Not saved: changed elsewhere"** banner | The campaign was saved from another tab or device after you opened it. Reload the page; nothing was overwritten. |
