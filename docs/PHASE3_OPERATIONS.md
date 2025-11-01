<details>
<summary><strong>Phase 3 – Operations & Alerting Overview</strong></summary>

This guide summarises the operational tooling added in Phase&nbsp;3 so the production team knows how to configure and monitor the crawler/classifier stack.

### 1. Alert Destinations & Severity

Alerts now flow through three optional channels, each with its own minimum severity threshold:

| Channel | Settings fields | Typical use |
|---------|-----------------|-------------|
| **Email** | `Alert Email`, `Email min. severity` | Individual notifications for moderate/critical issues. |
| **Slack** | `Slack Webhook`, `Slack min. severity` | Team channel pings when thresholds are breached. |
| **Ticketing / On-call** | Select provider (`None`, `Generic Webhook`, `PagerDuty`), plus webhook URL or routing key and `Ticket/on-call min. severity` | Opens tickets or notifies on-call engineers for the most critical events. |

Severity levels are `info`, `warning`, and `critical`. The alert manager only dispatches if the event severity is **greater than or equal** to the channel’s minimum severity.

### 2. Queue & Cron Health Monitor

A new WordPress cron (`axs4all_ai_health_check`) runs every 5&nbsp;minutes and:

1. Verifies the crawler (`axs4all_ai_process_queue`) and classifier (`axs4all_ai_run_classifications`) events are scheduled. Missing events raise a **critical** alert.
2. Captures current crawl and classification queue sizes. When counts exceed the configured “Queue Threshold” the alert severity escalates (`warning` above threshold, `critical` at ≥2× threshold).
3. Stores metrics in `axs4all_ai_alert_<queue>` options so the admin UI can surface the latest state.

### 3. Admin UI Enhancements

* **Queue / Classification pages**: cards now show next/last cron run, pending counts, and allow one-click requeue.
* **Debug page**: new *Alert Activity* table summarises the last 50 alerts by severity alongside crawler snapshots/events.
* **Categories page**: snippet limit inputs are logged through the alert manager so correctness can be audited.

### 4. PagerDuty Integration

When “PagerDuty Events API” is selected, the plugin posts to `https://events.pagerduty.com/v2/enqueue` using the supplied routing key. Severity mapping follows PagerDuty’s vocabulary (`warning`, `critical`, `info`).

### 5. Verification Checklist

1. Navigate to **Settings → axs4all AI** and configure alert destinations (email/Slack) and thresholds.
2. If PagerDuty is used, supply the routing key; otherwise, use a generic webhook for ticketing/on-call.
3. On the same screen, set the queue threshold (defaults to `0`, i.e. disabled).
4. Check **axs4all AI → Debug Log** to confirm alerts appear under *Alert Activity* when thresholds are crossed.
5. Ensure cron is running by verifying the next run timestamps on the queue/classification screens.

</details>
