## axs4all AI Plugin – Developer Setup

### Requirements
- PHP 8.4 with curl, json, mbstring extensions.
- Composer 2.6+.
- WordPress instance (6.0+) with access to wp-content/plugins directory.

### Initial Setup
1. Clone repository and change into plugin directory:
   ```bash
   git clone <repo-url>
   cd axs4all-ai
   ```
2. Install dependencies:
   ```bash
   composer install
   ```
3. Copy environment template:
   ```bash
   cp .env.example .env
   ```
   Fill in real `OPENAI_API_KEY` when available.
4. Symlink or copy plugin folder into WordPress `wp-content/plugins/`.
5. Activate plugin via WordPress admin.

### Development Tasks
- Run unit tests: `composer test`
- Run coding standards: `composer lint`
- Use WP-CLI for development cron/testing when available.

### Notes
- If `.env` contains `OPENAI_API_KEY`, it overrides any key saved in plugin settings.
- Keys saved through admin UI are stored in `wp_options` table (`axs4all_ai_settings`).
