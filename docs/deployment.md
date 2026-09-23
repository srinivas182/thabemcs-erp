# Deployment and scaling

How the platform is deployed, and what to switch on for full scale. Sized for the target of
25,000 projects and 100,000 concurrent users; the actual capacity is measured in Sprint 23.

## Recommended hosting

**AWS Cape Town (af-south-1)**, so that personal information stays in South Africa (POPIA) and
latency for users on site is low. Azure South Africa North is an equivalent alternative.

| Part | Service | Notes |
|---|---|---|
| Application servers | ECS/EC2 behind an Application Load Balancer | Start with 3; scale on CPU. Stateless, so they scale out freely |
| Queue workers | Same image, different command | Separate services per queue group (see below) |
| Scheduler | One task running `schedule:work` | Exactly one, never more |
| Database | RDS MySQL 8 (Multi-AZ) + one read replica | `DB_READ_HOST` points at the replica |
| Cache, sessions, queues | ElastiCache Redis | Separate databases: cache 1, sessions 2, queues 3 |
| Files | S3 (private bucket, versioned, encrypted) | `DOCUMENTS_DISK=documents_s3` |
| Assets | CloudFront | `ASSET_URL` set to the distribution |
| Search | Meilisearch (container or managed) | Once Scout is installed |
| Mail | SES or the client's SMTP provider | Needed for RFQs, reports and password resets |

## Packages to install at deployment

These four need a machine with package-registry access. The code and configuration already expect
them; nothing else changes.

```bash
composer require laravel/octane spiral/roadrunner-cli   # or swoole
composer require laravel/horizon                        # queue dashboard and supervision
composer require laravel/scout meilisearch/meilisearch-php  # search
composer require league/flysystem-aws-s3-v3             # S3 file storage

php artisan octane:install
php artisan horizon:install
php artisan vendor:publish --provider="Laravel\Scout\ScoutServiceProvider"
```

Then set `DOCUMENTS_DISK=documents_s3`, `SCOUT_DRIVER=meilisearch`, and serve with
`php artisan octane:start --workers=auto --task-workers=auto`.

Until Octane is installed the application runs under PHP-FPM with OPcache preloading, which is already
configured (`preload.php`, `docker/php/opcache.ini`).

## Queues

| Queue | Work | Timeout |
|---|---|---|
| `default`, `mail` | Notifications and everyday jobs | 120s |
| `metrics` | Project metrics and earned-value snapshots | 300s |
| `maintenance` | Nightly per-company work, POPIA clean-up | 900s |
| `reports` | Scheduled report emails | 900s |
| `integrations` | Sage and SimplePay | 900s |

Scheduled work is queued per company, so it runs in parallel and one large company never holds up the
others. Run at least one worker per group; scale `metrics` first as the portfolio grows.

## Deploying

`./deploy.sh` installs dependencies, migrates, caches config/routes/views/events, restarts workers and
checks `/health`. Blue-green deployment and the full runbook come in Sprint 22.

## Health checks

| Path | Purpose |
|---|---|
| `/up` | Liveness: the process is running |
| `/health` | Readiness: database, cache, queue and file storage all reachable. Point the load balancer here |

## Environment settings that matter at scale

```
DB_READ_HOST=replica.internal        # reads go to the replica, writes to the primary
REDIS_SESSION_DB=2
REDIS_QUEUE_DB=3
PERMISSION_CACHE_STORE=redis
DOCUMENTS_DISK=documents_s3
ASSET_URL=https://cdn.example.co.za
REPORT_ROW_LIMIT=5000
```

## Operating notes

- **One scheduler only.** Two schedulers means duplicate emails.
- **Sessions and queues are separate Redis databases** so clearing the cache cannot sign everyone out
  or drop queued jobs.
- **`opcache.validate_timestamps=0`** means PHP does not re-read changed files: deploys must restart
  PHP-FPM (or reload Octane), which `deploy.sh` does.
- **Uploads** are limited to 30 MB at the web server and in PHP; raise both together if needed.
