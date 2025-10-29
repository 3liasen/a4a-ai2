# Phase 1 – Data Acquisition Layer Design

## Objectives
- Discover and manage target URLs for accessibility data extraction.
- Retrieve HTML safely, respecting site policies and rate limits.
- Extract relevant content snippets for downstream AI classification.
- Provide observability and tooling for operators to manage the crawl pipeline.

## Data Flow Overview
1. **Seed Input** – Admin uploads CSV or enters URLs manually into the queue.
2. **Queue Management** – URLs persist in `axs4all_ai_queue` with status workflow (`pending → fetching → fetched → extracting → done/failed`).
3. **Crawler Execution** – WP-Cron/CLI job claims pending records, enforces per-domain throttling, and requests content via Guzzle.
4. **Snapshot Storage** – Raw HTML persisted in `axs4all_ai_snapshots` (compressed) for auditing.
5. **Extraction** – DomCrawler applies configured selectors to produce normalized text blocks stored in `axs4all_ai_extractions`.
6. **Classification Queue** – Successful extractions enqueue records for Phase 2 processing.

## Database Schema (Initial Draft)

### `axs4all_ai_queue`
| Column             | Type            | Notes                                                                    |
|--------------------|-----------------|--------------------------------------------------------------------------|
| `id`               | BIGINT unsigned | Primary key.                                                             |
| `source_url`       | TEXT            | Canonical URL to fetch. Unique constraint per normalized host/path.     |
| `status`           | VARCHAR(32)     | Enum-like: `pending`, `fetching`, `fetched`, `extracting`, `done`, `failed`. |
| `category`         | VARCHAR(64)     | Business type (restaurant, hotel, etc.) for selector/prompt mapping.    |
| `priority`         | TINYINT         | Lower value = higher priority. Default 5.                               |
| `attempts`         | TINYINT         | Number of fetch attempts. Max attempts triggers failure.                |
| `last_error`       | TEXT nullable   | Serialized error/context.                                               |
| `last_attempted_at`| DATETIME        | Timestamp of last fetch attempt.                                        |
| `created_at`       | DATETIME        | Creation timestamp.                                                     |
| `updated_at`       | DATETIME        | Update timestamp.                                                       |

Indexes:
- Unique composite on (`source_url(191)`).
- Index on `status`.
- Index on `category`.

### `axs4all_ai_snapshots`
| Column          | Type            | Notes                                         |
|-----------------|-----------------|-----------------------------------------------|
| `id`            | BIGINT unsigned | Primary key.                                  |
| `queue_id`      | BIGINT unsigned | Foreign key to `axs4all_ai_queue`.            |
| `content`       | LONGTEXT        | Gzipped HTML stored via `base64_encode`.      |
| `content_hash`  | CHAR(64)        | SHA-256 hash to detect changes.               |
| `fetched_at`    | DATETIME        | Timestamp of successful fetch.                |

Index on `queue_id`.

### `axs4all_ai_extractions`
| Column         | Type            | Notes                                                     |
|----------------|-----------------|-----------------------------------------------------------|
| `id`           | BIGINT unsigned | Primary key.                                              |
| `queue_id`     | BIGINT unsigned | Foreign key to `axs4all_ai_queue`.                        |
| `extracted_text`| LONGTEXT       | Normalized text for classification.                       |
| `language`     | VARCHAR(12)     | ISO language code (if detected).                          |
| `extracted_at` | DATETIME        | Timestamp of extraction.                                  |

Index on `queue_id`.

## WordPress Integration
- **Table Creation**: Run on plugin activation via dbDelta; maintain schema migrations via versioned upgrades.
- **Scheduling**: Register WP-Cron event `axs4all_ai_process_queue` running every 5 minutes; also provide WP-CLI command `axs4all-ai crawl` for manual execution.
- **Settings**: Extend admin configuration for crawling limits (requests per domain per hour, user-agent string).
- **Admin UI**: Add submenu “Crawl Queue” with list table for statuses, bulk actions (retry, deactivate), CSV import/export.

## Crawler Execution Logic
1. Select up to `batch_size` pending records ordered by priority and created_at.
2. Transition each to `fetching` status with optimistic locking (update where status=pending).
3. Respect robots.txt (use cached results; fallback to enabling/disabling fetch if disallowed).
4. Fetch HTML using Guzzle with:
   - Custom user-agent.
   - Timeout 15s.
   - Retry with exponential backoff (max 3).
   - Optional proxy support via environment config.
5. Persist snapshot; detect unchanged content to skip extraction.
6. Move status to `extracting`; run extraction service (Phase 1 stub) to produce text.
7. On success, enqueue classification job record (Phase 2 hand-off) and mark status `done`.
8. On error, increment attempts, log via central logger, and mark `failed` after max attempts.

## Dependencies & Helpers
- **HTTP**: Guzzle client, reuse existing service container once available.
- **HTML Parsing**: Symfony DomCrawler + CSS Selector.
- **Logging**: WordPress `error_log` fallback; plan for custom table in Phase 3.
- **Rate Limiting**: Store per-host timestamps in transient cache; fallback to in-memory static map during single run.

## Next Implementation Steps
1. Scaffold activation hook to create tables.
2. Implement queue repository + models (`src/Data/QueueRepository.php`, etc.).
3. Build admin list table & import handlers.
4. Implement crawler runner class hooking into WP-Cron/WP-CLI.
5. Add scraper/extractor service stubs returning placeholder data until selectors defined.
