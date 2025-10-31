# Client & Category Redesign (Phase 3 Draft)

## Goals
1. Introduce a first-class **Client** entity with attached URLs and categories.
2. Allow category-specific prompt metadata, including real-world phrases for iterative tuning.
3. Support richer decision sets beyond `yes/no` (e.g., `none`, `some`, `full`).
4. Surface the latest classification result per client/category in the admin view.

## Data Model Updates

### Clients
- Entity: `axs4all_ai_client` (custom post type) or dedicated table.
- Fields:
  - `name`
  - `status` (active/inactive)
  - `notes`
- Relationships:
  - **URLs**: separate table `axs4all_ai_client_urls` (id, client_id, url, created/updated).
  - **Categories**: pivot table `axs4all_ai_client_categories` (client_id, category_id, overrides JSON).

### Categories
- Extend existing category CPT/table with metadata:
  - `base_prompt`
  - `keywords` (comma-separated or JSON array)
  - `phrases` array (each phrase: text, language, active flag, created_by, created_at)
  - `decision_set` identifier (e.g., `binary`, `accessibility_scale`)

### Classification Results
- Add columns:
  - `decision_value` (string)
  - `decision_confidence` (nullable float)
  - `decision_scale` (identifier referencing decision set)
- Link results to client/category via queue job (ensure queue records store client_id & category_id).

## Admin UI Reform
1. **Clients**
   - Main listing: client name, URLs count, categories count, latest decision per category.
   - Edit screen:
     - Client info (name, notes).
     - URL repeater (add/edit/delete URLs).
     - Category checklist with overrides section:
       - Optional per-client phrases.
       - Optional per-client prompt tweaks.
2. **Categories**
   - Edit screen gains new meta boxes:
     - Base prompt editor (textarea).
     - Keywords list.
     - Phrases table with “Add phrase” (text, language, active).
     - Decision set selector (predefined list).
3. **Classification Results**
   - Already upgraded; will pull new fields and display decision scales if available.

## Prompt Construction Logic
When running classification:
1. Determine client & category.
2. Assemble prompt:
   - Category base prompt.
   - Category keywords.
   - Category phrases (active ones, limited by token budget).
   - Client-specific overrides (if provided).
3. Include decision instructions according to the selected decision set (`yes/no`, `none/some/full`, etc.).
4. On AI response, map result into the decision set, store value + optional confidence.

## Decision Sets
Define canonical sets in code or database:
- `binary`: `yes`, `no`
- `accessibility`: `none`, `limited`, `full`
- Future: allow per-category custom sets.

Parser will validate AI output against the configured set; invalid outputs trigger a retry or failure with logging.

## Migration Plan
1. Create new tables:
   - `axs4all_ai_clients`
   - `axs4all_ai_client_urls`
   - `axs4all_ai_client_categories`
2. Extend category table to store prompt/phrases metadata (JSON columns if using table).
3. Update classification queue table to store `client_id`, `category_id`, `decision_value`, `decision_scale`.
4. Backfill existing data:
   - Derive clients from existing queue entries (optional or manual).
   - Map current categories into the new structure with default prompts.
5. Update installer upgrades (`Installer::maybeUpgrade`).

## Automation Adjustments
- Crawl runner: after extracting a URL, identify client (via linked URL) and enqueue classification job with client/category IDs.
- Scheduler/runner: fetch client/category metadata, build prompt, retry logic uses configured attempts.
- CLI: add flags `--client`, `--category`, `--decision-set` for targeted reclassification.

## Testing Strategy
- Unit tests for prompt builder (various decision sets, phrases).
- Repository tests for new tables/queries.
- Integration tests simulating client with multiple URLs & categories, verifying results feed back to listing.

## Open Questions
- Should clients be WP posts (for easier admin UI) or custom tables (cleaner schema)?
- How to handle legacy queue items without client IDs (migration strategy)?
- Do we need per-language prompts/phrases (multi-language support)?

---
This document guides the implementation steps that follow: schema changes, admin UI rebuild, classification pipeline updates, and expanded decision handling.
