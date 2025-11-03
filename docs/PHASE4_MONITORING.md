# Phase 4 – Monitoring & Dashboard Notes

This note captures the final polish we applied to the Phase 4 observability work so ops can wire it into their tooling quickly.

## 1. Admin Dashboard Enhancements

- **Background Job Monitor** now surfaces the most recent start/finish/failure timestamps and the last error message. Consecutive failure counts are highlighted and the entire row turns red when attention is needed.
- **Live Metrics** promotes crawl/classification queue numbers into their own columns (pending, processed, source) so spikes are obvious at a glance.
- **Cron Health Callouts** warn when either job is unscheduled or the last successful run is older than 15 minutes. This helps catch stuck jobs even if the next cron execution is technically queued.

## 2. Monitor Hook Reference

The runner hooks added in Phase 4 feed both the dashboard and any external observability stack. Subscribe to them from a mu-plugin or theme to push data into Grafana/DataDog/etc.:

| Hook | Arguments | When it fires |
|------|-----------|---------------|
| `axs4all_ai_monitor_start` | `( string $context, array $meta = [] )` | Just before a crawl/classification batch begins. `meta` includes `source` (`cron`/`cli`) and `pending_before`. |
| `axs4all_ai_monitor_finish` | `( string $context, array $meta = [] )` | After the batch completes. `meta` includes `processed` and `pending_after`. |
| `axs4all_ai_monitor_failure` | `( string $context, array $meta = [] )` | Whenever a job fails. `meta` contains `job_id`, `message`, and `retry` (`yes`/`no`). |
| `axs4all_ai_monitor_metrics` | `( string $type, array $meta = [] )` | Snapshot metrics (currently `crawl_queue` and `classification_queue`) with keys such as `pending`, `processed`, and `source`. |

### Sample Listener

```php
add_action('axs4all_ai_monitor_finish', static function (string $context, array $meta): void {
    error_log(sprintf(
        '[monitor] %s run processed %d item(s); %d pending.',
        $context,
        isset($meta['processed']) ? (int) $meta['processed'] : 0,
        isset($meta['pending_after']) ? (int) $meta['pending_after'] : -1
    ));
});
```

The complete telemetry snapshot is stored in the `axs4all_ai_monitor_state` option. Read it directly via `Axs4allAi\Infrastructure\Monitor::getState()` when you need to export the latest data for dashboards.

## 3. Metadata Hygiene

`Monitor` now sanitises and truncates metadata payloads before persisting them, preventing large or unsafe values from bloating the stored state. Only scalar fields are kept, with a maximum of twelve keys per context.

---

Next steps (Phase 4 Part 2) will focus on alert channel integrations and broader UX refinements; this document will be updated again when those land.
