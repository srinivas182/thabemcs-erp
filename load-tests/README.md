# Load tests

Run these against **staging with full-size data**, never against production or a small database.

## Preparing

```bash
php artisan scale:seed --projects=25000 --users=100000   # a few hours; run it on the database server
php artisan metrics:refresh                              # then let the queue workers finish
php artisan perf:measure                                 # server-side timings before load
```

## Running

```bash
k6 run -e BASE_URL=https://staging.thabekhulu.co.za -e USERS=2000 load-tests/browsing.js
k6 run -e BASE_URL=https://staging.thabekhulu.co.za -e TOKEN=<site token> -e PROJECT_ID=<ulid> load-tests/site-app.js
```

`USERS` is the number of people online at once, not registered users. Start at 500, then 2,000, then the
agreed figure, recording each run in `docs/performance.md`.

## Targets

| Measure | Target |
|---|---|
| Page response, 95th percentile | under 500 ms |
| Page response, 99th percentile | under 1.5 s |
| Site app API, 95th percentile | under 800 ms |
| Failed requests | under 1% |
| Queue backlog at the end | clears within 5 minutes |

## Watch while it runs

Database CPU and connections, Redis memory, queue depth per queue, application CPU, and the slow query
log. A run that meets its thresholds but leaves the queue hours behind has not passed.
