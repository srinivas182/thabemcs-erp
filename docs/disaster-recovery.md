# Backups, restores and disaster recovery

What is backed up, how to get it back, and how long that takes. Targets are proposals until Thabekhulu
agrees them (see "Targets to agree" below).

## What is backed up

| What | How | Kept |
|---|---|---|
| Database | `backup:run` nightly at 01:00 SAST: full dump, gzipped, to the private backup bucket | 30 days |
| Database (point in time) | Managed MySQL automatic backups with binary logs | 7 days, any second |
| Uploaded files and photos | Object storage with versioning and lifecycle rules | Versions 90 days |
| Configuration and code | Git, plus infrastructure defined in code | Forever |
| Secrets | The hosting provider's secret store, not in backups | n/a |

Backups are encrypted at rest. Nobody signed in to the application can read or delete them; only the
operations role in the hosting account can.

## Restore rehearsals

`php artisan backup:verify` takes the latest backup, restores it into a scratch database, counts key
tables and reports how long it took. It runs on the 2nd of each month and must be run:

- before go-live, with the real data volume;
- after any change to the database size or hosting.

**Record each rehearsal here:**

| Date | Backup restored | Time taken | Rows checked | Run by |
|---|---|---|---|---|
| _(to be completed at go-live)_ | | | | |

## Targets to agree

| Target | Proposed | Meaning |
|---|---|---|
| Recovery point (RPO) | 5 minutes | The most data that may be lost, covered by point-in-time recovery |
| Recovery time (RTO) | 4 hours | From declaring a disaster to people working again |
| Backup retention | 30 days | How far back a nightly backup can be restored |

## If the database is lost or corrupted

1. **Stop writes.** Put the application into maintenance mode: `php artisan down --render="errors::503"`.
2. **Decide the restore point.** For corruption caused by a change, use point-in-time recovery to just
   before it. For a lost database, use the latest nightly backup.
3. **Restore** into a new database instance (never over the damaged one — keep it for investigation).
4. **Point the application at it**: update `DB_HOST` in the secret store and restart the application.
5. **Check** with `php artisan backup:verify`-style counts and `/health`, then spot-check a project, a
   recent invoice and the audit trail.
6. **Bring it back up**: `php artisan up`, then watch errors and queue depth for an hour.
7. **Tell people** what was lost, if anything, and write up what happened.

## If an application server is lost

The servers hold no state. The load balancer removes it automatically and the group starts another.
No action beyond checking capacity.

## If Redis is lost

Sessions and queued jobs are lost; data is not. Everyone signs in again. Scheduled work catches up on
its next run; anything mid-flight is retried. Check that queue workers reconnected.

## If a file is deleted by mistake

Object storage keeps versions. Restore the previous version from the bucket; document records in the
database still point at the same path.

## If someone's account is misused

1. Sign them out everywhere (profile page, or revoke their sessions in Redis).
2. Reset their password and require two-factor.
3. Read their activity in the audit trail (Settings → Activity) to see what was changed.
4. Revoke any API tokens they created.

## Contacts

| Role | Who | When |
|---|---|---|
| Platform (MCS) | Mayura Consultancy Services | First call for anything technical |
| Hosting | AWS support plan | Infrastructure faults |
| Information Officer | _(to be confirmed)_ | Anything involving personal information (POPIA) |

A breach involving personal information must be reported to the Information Regulator and to the
people affected, as POPIA section 22 requires. The Information Officer decides and reports.
