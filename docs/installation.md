# Installation guide

How to get Thabekhulu Development Software running, step by step. Part A is a developer's machine.
Part B is cPanel shared hosting. Part C is what to do when something does not work.

Follow the parts in order and do not skip the checks — each one catches a problem that is much harder to
diagnose three steps later.

---

## Before you start: is cPanel the right home for this?

**Honest answer: for production, no.** This is an application with background workers, a scheduler, Redis
and a build step, not a PHP script that runs when someone loads a page. On cPanel you will lose:

| What | Why it matters |
|---|---|
| Queue workers running continuously | Notifications, metric refreshes, report emails and integrations all run in the background. On cPanel they run only when cron fires, so "instant" becomes "within a few minutes". |
| Redis | Falls back to the database for cache, sessions and queues. It works; it is slower, and busier on the database. |
| Octane, Horizon | Not available. The application runs fine without them, but you lose the performance headroom and the queue dashboard. |
| Long-running processes | Shared hosts kill them. Large imports, report generation and backups may time out. |
| Real scaling | One box, shared with other people's sites. No read replica, no horizontal scaling. |

**Use cPanel for:** a demonstration site, a small pilot, a staging copy, or a client who insists and
accepts the trade-offs.

**Use a VPS or cloud instance for production.** A modest R600 to R1 500 per month machine (2 vCPU, 4 GB)
runs everything properly. `docs/deployment.md` covers that setup.

Part B below assumes cPanel anyway, and tells you exactly which settings to change so the system works
within those limits.

---

# Part A — A developer's machine

## A1. What you need installed

| Tool | Version | Check with |
|---|---|---|
| PHP | 8.4 | `php -v` |
| Composer | 2.x | `composer -V` |
| Node.js | 22 LTS | `node -v` |
| MySQL | 8.0 | `mysql --version` |
| Redis | 7.x (optional locally) | `redis-cli ping` |
| Git | any recent | `git --version` |

PHP needs these extensions: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`,
`bcmath`, `fileinfo`, `gd`, `zip`, `intl`, and `redis` if you use Redis. Check with:

```bash
php -m | grep -E 'pdo_mysql|mbstring|bcmath|gd|zip|intl|fileinfo'
```

**macOS**

```bash
brew install php@8.4 composer node@22 mysql redis
brew services start mysql && brew services start redis
```

**Ubuntu or Debian**

```bash
sudo add-apt-repository ppa:ondrej/php && sudo apt update
sudo apt install php8.4 php8.4-{mysql,mbstring,xml,bcmath,gd,zip,intl,curl,redis} \
                 mysql-server redis-server git unzip
curl -sS https://getcomposer.org/installer | php && sudo mv composer.phar /usr/local/bin/composer
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash - && sudo apt install nodejs
```

**Windows** — use WSL2 with Ubuntu and follow the Ubuntu steps inside it. Do not run this on Windows
directly; the file watching and permissions will fight you.

## A2. Get the code

```bash
git clone https://github.com/srinivas182/thabemcs-erp.git
cd thabemcs-erp
composer install
npm install
```

`composer install` downloads about 100 packages and takes a few minutes the first time.

## A3. Create the database

```bash
mysql -u root -p
```

```sql
CREATE DATABASE thabekhulu CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'thabekhulu'@'localhost' IDENTIFIED BY 'a-password-you-choose';
GRANT ALL PRIVILEGES ON thabekhulu.* TO 'thabekhulu'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

`utf8mb4` matters: names and addresses in South Africa contain characters that break in older encodings.

## A4. Configure

```bash
cp .env.example .env
php artisan key:generate
```

Open `.env` and set at least:

```ini
APP_NAME="Thabekhulu Development Software"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000
APP_TIMEZONE=Africa/Johannesburg

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=thabekhulu
DB_USERNAME=thabekhulu
DB_PASSWORD=a-password-you-choose

# With Redis installed:
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1

# Without Redis, use these three instead:
# CACHE_STORE=database
# SESSION_DRIVER=database
# QUEUE_CONNECTION=sync

# Where email goes while developing. log writes it to storage/logs.
MAIL_MAILER=log

# The first administrator, created by the seeder.
SUPER_ADMIN_NAME="Your Name"
SUPER_ADMIN_EMAIL=you@example.com
SUPER_ADMIN_PASSWORD=choose-a-long-password

# Two-factor is required of everyone. Turn it off only on a development machine.
REQUIRE_TWO_FACTOR=false
```

## A5. Build the database

```bash
php artisan migrate --seed
```

This creates 110 tables and seeds roles, South African cost codes, public holidays and your super admin.
It should end with a list of migrations and no errors.

**Check:** `php artisan tinker --execute="echo App\Models\User::count();"` should print 1.

## A6. Start it

Four terminals, or use the one-line version below.

```bash
php artisan serve                    # http://localhost:8000
npm run dev                          # the management app
php artisan queue:work --queue=metrics,maintenance,reports,integrations,mail,default
php artisan schedule:work            # only when testing scheduled jobs
```

Or, if the repository's `composer dev` script is present:

```bash
composer dev                         # runs the server, Vite and a queue worker together
```

**Check:** open `http://localhost:8000`. You should see the public website if content has been seeded, or
be redirected to sign in. Sign in at `/login` with the super admin details from `.env`.

## A7. Create a company and give yourself a role

A super admin sees the platform; the day-to-day work happens inside a company.

1. Sign in, go to **Settings → Companies**, create one (name, registration number, VAT number).
2. Go to **Settings → People**, add yourself to that company as **Company Admin**.
3. Switch to that company using the selector at the top.

## A8. Optional: the demo website and full-size data

```bash
# Five pages, menus, two forms and two articles for the public site
php artisan db:seed --class=DemoWebsiteSeeder

# A large database for performance work (never in production)
php artisan scale:seed --projects=500 --users=2000
php artisan metrics:refresh
```

## A9. The site app (the phone app for site teams)

```bash
npm run dev --workspace site-app     # development
npm run build --workspace site-app   # builds into public/site
```

Reach it at `/site`. To use it on a real phone against your machine, serve over HTTPS or the camera and
location will not work.

## A10. Before you push anything

These four are exactly what CI runs:

```bash
vendor/bin/pint                      # formatting, fixes in place
vendor/bin/phpstan analyse           # static analysis, level 8
vendor/bin/pest                      # 225 tests, about 15 seconds
npm run typecheck && npm run build && npm run build --workspace site-app
```

---

# Part B — cPanel shared hosting

Read the warning at the top first. What follows works, within the limits described there.

## B0. What the host must provide

Check these in cPanel before you start. If any is missing, the install will not work.

| Requirement | Where to check | If missing |
|---|---|---|
| PHP 8.2 or newer (8.4 preferred) | MultiPHP Manager | Ask the host to enable it |
| The PHP extensions listed in A1 | Select PHP Version → Extensions | Tick them; ask the host for any that are absent |
| SSH access (Terminal) | Terminal, or SSH Access | Possible without it, but much harder — see B4 |
| MySQL 8.0 or MariaDB 10.6+ | MySQL Databases | No workaround |
| Cron jobs | Cron Jobs | No workaround; the system needs them |
| A dedicated domain or subdomain | Domains | Needed to point at `public/` |

Composer and Node are usually available in Terminal. Check with `composer -V` and `node -v`. If Node is
missing, you will build the front end on your own machine and upload the result (B4).

## B1. Create the database

**cPanel → MySQL Databases**

1. Create a database, e.g. `myacct_thabekhulu`.
2. Create a user, e.g. `myacct_thabe`, with a long generated password. **Write it down now.**
3. Add the user to the database with **All Privileges**.

cPanel prefixes both names with your account name. Use the full prefixed names in `.env`.

## B2. Put the code on the server

**With SSH (much easier):**

```bash
cd ~
git clone https://github.com/srinivas182/thabemcs-erp.git thabekhulu
cd thabekhulu
composer install --no-dev --optimize-autoloader
```

**Without SSH:** on your own machine run `composer install --no-dev --optimize-autoloader` and
`npm run build`, then zip the whole folder **including `vendor/` and `public/build/`**, upload it through
File Manager, and extract it into `~/thabekhulu`.

**Important:** the application must **not** sit in `public_html`. Only its `public/` folder is served.

## B3. Point the domain at public/

**cPanel → Domains** (or Subdomains)

1. Add the domain or subdomain, e.g. `app.thabekhulu.co.za`.
2. Set its **Document Root** to `/home/myacct/thabekhulu/public`.
3. Save, then check that `https://app.thabekhulu.co.za` returns something other than a file listing.

If your cPanel will not let you change the document root, create a symbolic link instead:

```bash
rm -rf ~/public_html && ln -s ~/thabekhulu/public ~/public_html
```

## B4. Build the front end

If Node is available on the server:

```bash
cd ~/thabekhulu
npm ci
npm run build
npm run build --workspace site-app
```

If it is not, build on your own machine and upload `public/build/` and `public/site/` into the same
places on the server. **The site will load unstyled without this step** — that is the usual cause of "it
looks broken".

## B5. Configure

```bash
cd ~/thabekhulu
cp .env.example .env
php artisan key:generate
```

Edit `.env` (File Manager's editor is fine). For shared hosting:

```ini
APP_NAME="Thabekhulu Development Software"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://app.thabekhulu.co.za
APP_TIMEZONE=Africa/Johannesburg

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=myacct_thabekhulu
DB_USERNAME=myacct_thabe
DB_PASSWORD=the-password-from-B1

# No Redis on shared hosting: the database does this work instead.
CACHE_STORE=database
SESSION_DRIVER=database
QUEUE_CONNECTION=database

# Email through the cPanel mail account you created.
MAIL_MAILER=smtp
MAIL_HOST=mail.thabekhulu.co.za
MAIL_PORT=465
MAIL_USERNAME=noreply@thabekhulu.co.za
MAIL_PASSWORD=the-mailbox-password
MAIL_ENCRYPTION=ssl
MAIL_FROM_ADDRESS=noreply@thabekhulu.co.za

SUPER_ADMIN_NAME="Administrator"
SUPER_ADMIN_EMAIL=admin@thabekhulu.co.za
SUPER_ADMIN_PASSWORD=a-long-password-changed-at-first-sign-in

# Keep this on. It is the main thing standing between the internet and their data.
REQUIRE_TWO_FACTOR=true
SESSION_SECURE_COOKIE=true
```

**`APP_DEBUG=false` is not optional.** With it on, any error page shows your database credentials.

## B6. Build the database

```bash
cd ~/thabekhulu
php artisan migrate --force
php artisan db:seed --force
```

`--force` is required because `APP_ENV=production`; it is the confirmation prompt being answered.

If the host's PHP CLI is an older version than the web PHP, call the right binary directly, e.g.
`/opt/cpanel/ea-php84/root/usr/bin/php artisan migrate --force`.

## B7. Permissions and the storage link

```bash
cd ~/thabekhulu
php artisan storage:link
chmod -R 775 storage bootstrap/cache
```

If `storage:link` fails (some hosts block symlinks), copy instead and repeat after each upload:

```bash
cp -r storage/app/public/* public/storage/
```

## B8. Cron: the part people forget

**cPanel → Cron Jobs.** The system needs two entries. Without them, nothing scheduled happens and
background work never runs.

**Every minute — the scheduler:**

```
* * * * * cd /home/myacct/thabekhulu && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```

**Every five minutes — the queue, since no worker can stay running:**

```
*/5 * * * * cd /home/myacct/thabekhulu && /usr/local/bin/php artisan queue:work --stop-when-empty --max-time=280 --tries=3 >> /dev/null 2>&1
```

`--stop-when-empty` makes the worker finish and exit instead of running forever, which shared hosts kill.
`--max-time=280` keeps it under the five-minute gap so two do not overlap.

Confirm the PHP path with `which php`, and use the full path in both entries.

## B9. Make it fast, and lock the cache

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

**Run these again after every change to `.env` or the code.** A cached config ignores `.env`, which is the
single most common cause of "I changed it and nothing happened". To undo: `php artisan optimize:clear`.

## B10. HTTPS

**cPanel → SSL/TLS Status** → select the domain → **Run AutoSSL**. Then force HTTPS in
**Domains → Force HTTPS Redirect**.

The application sets `Strict-Transport-Security` once it sees HTTPS, and secure cookies are already on
from B5. Two-factor codes also depend on the server clock being right; if codes are rejected, check the
server time.

## B11. First sign-in and setup

1. Open `https://app.thabekhulu.co.za/login`.
2. Sign in as the super admin from `.env`, and **change that password immediately**.
3. Set up two-factor when prompted (Google Authenticator, Authy, 1Password — any of them).
4. **Settings → Companies**: create the operating company.
5. **Settings → People**: invite the real users and give them roles.
6. **Settings → Import**: load opening data (suppliers, employees, projects, budgets).
7. Optionally `php artisan db:seed --class=DemoWebsiteSeeder --force` for the starter website, then edit
   it under **Website**.

## B12. Check it actually works

Do all six. Each catches a different failure.

| Check | What it proves |
|---|---|
| The sign-in page loads, styled | The build and the document root are right |
| You can sign in and see the dashboard | Database, sessions and permissions work |
| `https://.../health` returns "ready" | Database, cache, queue and storage are all reachable |
| Upload a photograph under Website → Media | Storage, permissions and the storage link work |
| Trigger a notification, wait five minutes, see it arrive | Cron and the queue are running |
| Visit the site as a stranger; the back office asks for sign-in | The public and private sides are separated |

## B13. Updating later

```bash
cd ~/thabekhulu
php artisan down                      # maintenance page
git pull                              # or upload the new files
composer install --no-dev --optimize-autoloader
php artisan migrate --force
npm run build                         # or upload public/build/
php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan up
```

**Back up the database before every update**, from cPanel → Backup, or:

```bash
mysqldump -u myacct_thabe -p myacct_thabekhulu | gzip > ~/backup-$(date +%F).sql.gz
```

---

# Part C — When it does not work

| What you see | What it usually is | What to do |
|---|---|---|
| Blank white page | A fatal error with debug off | Read `storage/logs/laravel.log`. Check the PHP version and extensions. |
| 500 after deploying | Cached config from a previous `.env` | `php artisan optimize:clear`, then re-cache. |
| "No application encryption key" | `key:generate` was not run, or the cached config predates it | `php artisan key:generate` then `php artisan config:cache`. |
| The page loads unstyled | The front end was never built, or `public/build/` is missing | `npm run build`, or upload the build folder (B4). |
| 403 or a directory listing | The document root is not pointing at `public/` | Fix the document root (B3). |
| "SQLSTATE[HY000] [1045]" | Wrong database user, password, or the user is not attached to the database | Re-check B1; use the cPanel-prefixed names. |
| "SQLSTATE[HY000] [2002]" | Wrong database host | `127.0.0.1` on cPanel, not `localhost`, on most hosts. |
| 419 Page Expired on every form | Sessions are not being stored, or `APP_URL` does not match the address used | Check `SESSION_DRIVER` and the sessions table; make `APP_URL` exact. |
| Emails never arrive | SMTP details or port wrong | Test with `php artisan tinker`: `Mail::raw('test', fn($m) => $m->to('you@example.com')->subject('Test'));` |
| Notifications and reports never happen | Cron is not running | Check both entries in B8, and the PHP path. |
| Two-factor codes always rejected | Server clock is wrong | Check server time; codes are time-based. |
| "Permission denied" writing to storage | Ownership or mode | `chmod -R 775 storage bootstrap/cache`. |
| Uploads fail at a certain size | PHP limits | Raise `upload_max_filesize` and `post_max_size` in MultiPHP INI Editor. |
| Everything 403s after signing in | Your user has no role in the company you are acting as | Settings → People, assign a role. |
| Imports or reports time out | Shared hosting limits | Raise `max_execution_time`, or do the work on a proper server. |

**Where to look first, always:** `storage/logs/laravel.log`. Every line carries a request id, so you can
follow one request end to end.

---

# Part D — The checklist

Print this and tick it off.

**Local**

- [ ] PHP 8.4 with all extensions
- [ ] `composer install` and `npm install` finished without errors
- [ ] Database created with utf8mb4
- [ ] `.env` copied and `key:generate` run
- [ ] `migrate --seed` completed
- [ ] Server, Vite and a queue worker running
- [ ] Signed in, company created, role assigned
- [ ] Pint, PHPStan, Pest and the builds all pass

**cPanel**

- [ ] PHP version and extensions confirmed with the host
- [ ] Database, user and privileges created
- [ ] Code in `~/thabekhulu`, **not** in `public_html`
- [ ] Document root points at `~/thabekhulu/public`
- [ ] Front end built or uploaded
- [ ] `.env` set, with `APP_DEBUG=false` and `APP_ENV=production`
- [ ] `migrate --force` and `db:seed --force` completed
- [ ] `storage:link` done, permissions set
- [ ] **Both cron entries added and verified**
- [ ] Config, route, view and event caches built
- [ ] HTTPS working and forced
- [ ] Super admin password changed, two-factor set up
- [ ] All six checks in B12 pass
- [ ] A database backup taken and **a restore tested**
