# Phase 2 – AI Classification Engine Design

## Objectives
- Transform extracted snippets into deterministic yes/no accessibility classifications.
- Support per-category prompt templates with versioning and overrides.
- Provide an AI client abstraction with retry, logging, and metrics hooks.
- Queue jobs for classification and persist results with audit metadata.

## Core Components
1. **Prompt Templates**
   - Stored in custom table `axs4all_ai_prompts` with fields: `id`, `category`, `version`, `template`, `created_at`, `updated_at`, `active`.
   - Default templates bundled in JSON within the plugin; admin UI enables editing and activation.
   - Template tokens: `{{context}}`, `{{previous_decision}}`, `{{notes}}`.

2. **AI Client Layer**
   - `Axs4allAi\Ai\AiClientInterface` with `classify(PromptContext $context): ClassificationResult`.
   - Default implementation `OpenAiClient` consuming OpenAI-compatible Chat Completions via API key.
   - Features: deterministic temperature (0.0), configurable model, retries with exponential backoff, timeout handling, structured error types.

3. **Classification Workflow**
   - `ClassificationJob` entity describing queue record: snippet id, category, prompt version, status, attempts.
   - `ClassificationRunner` processes jobs from dedicated table `axs4all_ai_classifications_queue`.
   - Output persisted to `axs4all_ai_classifications` table with fields: `id`, `queue_id`, `decision`, `confidence`, `raw_response`, `prompt_version`, `tokens`, `completed_at`.

4. **Result Validation**
   - `ClassificationParser` ensures strict yes/no response; rejects otherwise and leaves job in retry state.
   - Confidence derived from optional model probability or heuristics (fallback `null`).

5. **Admin/CLI Interfaces**
   - Admin submenu “Classification” listing queue + results with manual re-run.
   - CLI command `axs4all-ai classify` to batch process.

## Data Flow
1. Extraction stage enqueues classification jobs (`enqueueClassification(queueId, snippets)`).
2. Classification runner claims N pending jobs, locking by status update.
3. For each job:
   - Fetch active prompt template for category.
   - Build prompt context using extracted snippet, URL metadata, prior decision (if any).
   - Send to AI client; parse response.
   - Persist classification result and mark job `done`; on failure log and increment attempts.
4. Errors beyond attempt threshold mark job `failed` with diagnostic message.

## Logging & Observability
- Reuse debug log for operational events (with structured prefixes).
- Introduce dedicated table `axs4all_ai_logs` in Phase 3; for now, log via `error_log`.
- Collect metrics: attempts, latency (ms), tokens (prompt/completion).

## Security & Compliance
- API key retrieved from settings page or environment—no storage in plaintext logs.
- Responses sanitized before persistence; ensure privacy by stripping PII when possible.
- Respect rate limits using throttled client calls (configurable per minute).

## Immediate Tasks
1. Create DB migrations for prompt templates and classification tables.
2. Implement `AiClientInterface` + `OpenAiClient` (HTTP stubs initially).
3. Build prompt repository with default templates.
4. Scaffold classification queue repository + runner (stub responses for now).
5. Add admin list table shell for monitoring jobs/results.

> Status: Database migrations, prompt management tooling, and category option support are now in place.

### Category Options Bridge
- Categories persist as custom post types (`axs4all_ai_category`) with option lists stored in post meta, mirroring the legacy structure.
- Admin panel offers CRUD for categories plus per-line option editing.
- Classification runner now injects category option metadata into prompt contexts for downstream usage.

### Classification Queue Progress
- Queue repository handles enqueueing, claiming, and completion/failure of classification jobs.
- CLI command processes real queue items or ad-hoc content and records results.
- Results table captures model metadata, raw responses, and timing metrics for future analysis.

### OpenAI Client
- Real OpenAI chat completions integration via Guzzle or `wp_remote_post` with strict yes/no validation.
- Metrics (model, token counts, latency) are surfaced with each classification result.
- Errors propagate back into the queue with sanitised diagnostics for retry control.

### Roadmap Next Steps
1. [done] Wire extraction -> classification queue hand-off with logging.
2. Add cron/WP-CLI runner configuration for automated job processing.
3. Surface classification results in admin UI (list view or detail page).
4. [done] Expand tests for queue lifecycle using mocked OpenAI client.
5. Add operational controls (model/timeout settings, CLI filters, retry toggles).

