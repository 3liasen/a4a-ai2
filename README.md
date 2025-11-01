# axs4all AI Accessibility Plugin

WordPress plugin for axs4all that automates accessibility data collection and AI-powered classification.

## Key Features
- Automated crawl → extract → classify pipeline with per-category prompts and snippet limits.
- Queue & classifier dashboards with manual requeue controls and history snapshots.
- Alerting system (email, Slack, PagerDuty or generic webhook) with severity thresholds and a 5‑minute health monitor.
- Secure storage for AI credentials plus billing/usage reporting (token totals, USD→DKK auto-rates).

## Development
Install dependencies:

```bash
composer install
```

Run tests and linting:

```bash
composer test
composer lint
```

## Operations
Operational notes for Phase 3 (alerting, monitoring, cron health) live in [`docs/PHASE3_OPERATIONS.md`](docs/PHASE3_OPERATIONS.md).
