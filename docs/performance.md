# Performance and capacity

What we test, against what targets, and what the runs actually showed. **No figures are filled in yet:
the tooling is built and the runs happen on staging with full-size data.**

## Targets

Agreed with Thabekhulu at the start of Sprint 23 (confirm the concurrency figure, see below):

| Measure | Target |
|---|---|
| Projects | 25,000 |
| Users | 100,000 |
| People online at once | to be confirmed — see "The concurrency question" |
| Page response, 95th percentile | under 500 ms |
| Page response, 99th percentile | under 1.5 s |
| Site app API, 95th percentile | under 800 ms |
| Failed requests | under 1% |
| Queue backlog after a run | clears within 5 minutes |

## The concurrency question

"100,000 concurrent users" can mean two very different things:

- **100,000 people online at the same moment** — roughly 2,000–3,500 requests a second at peak. This needs
  several application servers, a database with a read replica, and Redis. It is achievable and the code is
  built for it, but it is the expensive reading.
- **100,000 registered users**, with a few thousand active at any time — a much smaller, cheaper estate.

Thabekhulu must choose, because it drives the hosting bill they pay. We test to whichever is agreed, and
the load tests take the figure as a parameter either way.

## How it is measured

1. **Build the data**: `php artisan scale:seed --projects=25000 --users=100000`, then `metrics:refresh`
   and let the queues drain.
2. **Server-side timings**: `php artisan perf:measure` reports the time and query count behind the
   dashboard, command centre, the heaviest reports, profitability and the critical path. This finds work
   that grows with data, without needing load.
3. **Load tests**: `load-tests/browsing.js` (people working) and `load-tests/site-app.js` (phones syncing
   in bursts), run with k6 at increasing concurrency.
4. **Watch**: database CPU and connections, Redis memory, queue depth per queue, slow query log.

Automated query-count tests already fail the build if a page's cost grows with the number of projects
(`tests/Feature/Scale`). Load testing checks the rest: connections, memory, queues and the database.

## Results

### Server-side timings (`perf:measure`)

| Date | Data size | Dashboard | Attention list | Cost report | Programme status | Profitability | Notes |
|---|---|---|---|---|---|---|---|
| _(to be run on staging)_ | | | | | | | |

### Load tests

| Date | Script | Concurrency | p95 | p99 | Failures | Queue cleared | Verdict |
|---|---|---|---|---|---|---|---|
| _(to be run on staging)_ | | | | | | | |

### Restore rehearsal at full size

| Date | Backup size | Restore time | Rows checked | Verdict |
|---|---|---|---|---|
| _(to be run on staging)_ | | | | |

## If a target is missed

In order of what usually helps:

1. Read the slow query log; add the missing index.
2. Check whether the work belongs in `project_metrics` instead of the page.
3. Add application servers (they are stateless) before making the database bigger.
4. Send reports and dashboards to the read replica (`DB_READ_HOST`).
5. Raise Redis memory, or split cache and queues onto separate instances.
6. Only then consider a larger database instance.
