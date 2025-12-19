=== AI News Control Center ===
Contributors: ainewsteam
Tags: news, automation, ai, rss, translation, telegram, multilingual
Requires at least: 6.0
Tested up to: 6.4
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Autonomous news platform for content aggregation, AI processing, translation, and multi-channel publishing.

== Description ==

AI News Control Center is a comprehensive WordPress plugin designed for automated news aggregation, processing, and publishing. Perfect for news platforms targeting multilingual audiences.

= Key Features =

* **RSS Aggregation** - Fetch news from 35+ pre-configured sources (German, Ukrainian, International)
* **AI Processing** - Automatic content rewriting and translation using DeepSeek, OpenAI, or Anthropic
* **Multi-Language Support** - DE, UA, RU, EN translations
* **Telegram Publishing** - Automatic posting to Telegram channels
* **Image Integration** - Pexels API for automatic image selection
* **SEO Optimization** - Automatic meta tags, schema markup generation
* **Fact Checking** - Simple cross-reference verification
* **Admin Dashboard** - Modern React-based interface

= Requirements =

* WordPress 6.0 or higher
* PHP 8.0 or higher
* MySQL 5.7 or higher
* API keys for: DeepSeek/OpenAI/Anthropic, Pexels (optional), Telegram Bot (optional)

= Pre-configured Sources =

The plugin comes with 35+ RSS sources:
* Official German sources (BAMF, Bundesregierung, Bayern.de)
* German media (Tagesschau, Spiegel, Zeit, BR24)
* Ukrainian sources (Ukrinform, Pravda, Suspilne)
* Regional (Munich) sources
* Google News aggregated feeds

== Installation ==

1. Upload the `ai-news-control-center-plugin` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to 'AI News Center' in the admin menu
4. Configure your API keys in Settings:
   - DeepSeek API Key (required for AI processing)
   - Pexels API Key (optional, for images)
   - Telegram Bot Token + Channel ID (optional, for Telegram publishing)
5. Review and enable/disable RSS sources as needed
6. The plugin will start fetching news automatically

= Configuration =

**Required Settings:**
- AI Provider: Choose DeepSeek (recommended), OpenAI, or Anthropic
- API Key for your chosen provider

**Optional Settings:**
- Pexels API Key: For automatic image selection
- Telegram: Bot Token and Channel ID
- Auto-publish: Enable for automatic publishing after review delay
- Target Languages: Select which languages to generate

== Frequently Asked Questions ==

= Will this plugin slow down my site? =

No. The plugin uses:
- Lazy loading (components loaded only when needed)
- Admin-only loading (no frontend impact)
- Batch processing with memory limits
- Lock mechanisms to prevent concurrent cron jobs
- REST API only loaded on API requests

= How often does it fetch news? =

By default:
- RSS fetch: Every 5 minutes
- Content processing: Every 2 minutes
- Auto-publish check: Every 5 minutes

These intervals can be adjusted in settings.

= Can I use multiple AI providers? =

Yes, you can switch between DeepSeek, OpenAI, and Anthropic at any time. Just enter the API key and select the provider in settings.

= How does fact-checking work? =

The plugin performs simple fact-checking by:
- Cross-referencing the same story from multiple sources
- Checking source trust scores
- Flagging articles that appear in only one low-trust source

= What happens if an error occurs? =

The plugin has comprehensive error handling:
- Failed operations don't crash the site
- Errors are logged to the plugin's log table
- Failed sources are automatically quarantined after 5 consecutive failures
- All cron jobs have timeout and memory limits

== Changelog ==

= 1.0.0 =
* Initial release
* 35+ pre-configured RSS sources
* DeepSeek, OpenAI, Anthropic AI providers
* Multi-language support (DE, UA, RU, EN)
* Telegram integration
* Pexels image integration
* React admin dashboard
* Comprehensive cron job management
* Error handling and logging

== Upgrade Notice ==

= 1.0.0 =
Initial release. Configure your API keys after activation.

== Screenshots ==

1. Dashboard with draft queue
2. Article editor with AI rewriting
3. Sources management
4. Settings page
5. Analytics overview

== Additional Info ==

= API Costs =

The plugin uses external APIs which may incur costs:
- DeepSeek: ~$0.001 per article
- OpenAI GPT-4o-mini: ~$0.005 per article
- Pexels: Free (with attribution)
- Telegram: Free

= Support =

For support questions, please create an issue on our GitHub repository.

= Privacy =

This plugin:
- Sends article content to AI providers for processing
- Stores news articles in your WordPress database
- Does not collect any personal user data
- Does not use tracking or analytics

== Technical Details ==

= Database Tables Created =

* `{prefix}aincc_sources` - RSS source configuration
* `{prefix}aincc_raw_items` - Fetched articles (raw)
* `{prefix}aincc_events` - News event clustering
* `{prefix}aincc_drafts` - AI-processed drafts
* `{prefix}aincc_fact_checks` - Fact check results
* `{prefix}aincc_publishes` - Published post tracking
* `{prefix}aincc_social_posts` - Social media posts
* `{prefix}aincc_metrics` - Performance metrics
* `{prefix}aincc_ab_tests` - A/B test data
* `{prefix}aincc_trust_history` - Source trust changes
* `{prefix}aincc_comment_moderation` - Comment analysis
* `{prefix}aincc_queue` - Processing queue
* `{prefix}aincc_logs` - Plugin logs

= Cron Jobs =

* `aincc_fetch_sources` - Fetch RSS feeds (every 5 min)
* `aincc_process_queue` - AI processing (every 2 min)
* `aincc_auto_publish` - Auto-publish approved drafts (every 5 min)
* `aincc_cleanup_old_data` - Cleanup old data (daily)

= Safe Mode =

Add to wp-config.php to disable all cron jobs:
`define('AINCC_SAFE_MODE', true);`

= Uninstallation =

The plugin completely removes all data on uninstall unless you set:
`update_option('aincc_keep_data_on_uninstall', true);`
