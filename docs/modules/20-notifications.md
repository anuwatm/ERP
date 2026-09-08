# Module: Operational Notifications

| Meta | Value |
| --- | --- |
| Module code | `notifications` |
| Phase | 8 foundation, 21 operational outbox |
| Runtime source | `NotificationService`, `NotificationOutboxService`, `routes/console.php` |

## Scope

- In-app and email notifications remain compatible with existing event call sites.
- LINE Messaging API, Slack webhook and Telegram bot are external organization channels.
- Delivery is durable: a domain event creates a deduplicated `notification_events` row and per-channel `notification_outbox` records.
- External delivery is disabled by default. It requires both an enabled organization channel and a user opt-in preference.

## Delivery Flow

```text
Domain event
  -> NotificationService dedupe (`notification_events`)
  -> notification_outbox per permitted channel
  -> notifications:dispatch (every minute)
  -> in_app | email | LINE | Slack | Telegram
  -> notification_dispatches audit
  -> sent | retrying (exponential backoff) | failed (after 5 attempts)
```

## Tables

| Table | Responsibility |
| --- | --- |
| `in_app_notifications` | User-facing bell feed and read state |
| `notification_events` | Per-user domain-event dedupe key |
| `notification_preferences` | Per-event email/in-app choices from Phase 8 |
| `notification_channels` | Organization external configuration; `config` is encrypted |
| `user_channel_preferences` | Per-user external opt-in, quiet hours and urgent-only rule |
| `notification_outbox` | Durable delivery work, idempotency, status and retry schedule |
| `notification_dispatches` | Immutable attempt result history |

## Security and Privacy

- Channel config is encrypted at rest and never returned through Inertia props or audit payloads.
- External configuration and test ping require `settings.organization.update`, password confirmation and throttling.
- User preference changes are available to every authenticated user; channel administration remains admin-only.
- Failure records are generic and do not retain provider response bodies, webhook URLs, tokens or secrets.
- Daily digest contains only operational counts for due and overdue invoices. It never includes raw cash balance.

## Operations

- `php artisan notifications:dispatch --limit=100` sends due outbox items. Scheduler runs it every minute with `withoutOverlapping()`.
- `php artisan notifications:daily-digest` queues the safe executive digest at 08:45.
- Failed records remain in `notification_outbox` with status `failed` after five attempts for operational visibility and manual remediation.
