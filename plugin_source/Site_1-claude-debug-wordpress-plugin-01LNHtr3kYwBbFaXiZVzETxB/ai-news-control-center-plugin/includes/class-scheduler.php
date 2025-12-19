<?php
/**
 * Scheduler
 * Manages cron jobs and scheduled tasks with memory/time protection
 *
 * KEY SAFETY FEATURES:
 * 1. Memory limit checks before processing
 * 2. Time limit enforcement
 * 3. Batch processing to prevent overload
 * 4. Lock mechanism to prevent concurrent runs
 * 5. Error isolation - one failure doesn't stop others
 */

if (!defined('ABSPATH')) {
    exit;
}

class AINCC_Scheduler {

    /**
     * Memory limit for batch operations (in bytes)
     */
    private $memory_limit;

    /**
     * Max execution time for cron jobs
     */
    private $max_execution_time = 120;

    /**
     * Lock transient prefix
     */
    private const LOCK_PREFIX = 'aincc_cron_lock_';

    /**
     * Lock timeout in seconds
     */
    private const LOCK_TIMEOUT = 300;

    /**
     * Constructor
     */
    public function __construct() {
        // Calculate memory limit (use 80% of available, max 256MB)
        $configured = AINCC_Settings::get('max_memory_mb', 256);
        $this->memory_limit = min($configured, 256) * 1024 * 1024;
    }

    /**
     * Check if memory is sufficient to continue
     */
    private function has_memory_available(): bool {
        $used = memory_get_usage(true);
        $available = $this->memory_limit - $used;

        // Need at least 32MB free
        return $available > (32 * 1024 * 1024);
    }

    /**
     * Acquire a lock for a cron job
     */
    private function acquire_lock(string $job): bool {
        $lock_key = self::LOCK_PREFIX . $job;
        $existing = get_transient($lock_key);

        if ($existing !== false) {
            // Lock exists, check if it's stale
            AINCC_Logger::warning("Lock exists for {$job}, skipping");
            return false;
        }

        // Set lock
        set_transient($lock_key, time(), self::LOCK_TIMEOUT);
        return true;
    }

    /**
     * Release a lock
     */
    private function release_lock(string $job): void {
        delete_transient(self::LOCK_PREFIX . $job);
    }

    /**
     * Fetch sources from RSS feeds
     */
    public function fetch_sources(): array {
        $job = 'fetch_sources';
        $result = ['success' => false, 'fetched' => 0, 'errors' => [], 'sources_processed' => 0];

        if (!$this->acquire_lock($job)) {
            $result['error'] = 'Job already running';
            return $result;
        }

        try {
            $start_time = time();
            $batch_size = (int) AINCC_Settings::get('batch_size', 5);

            // Get RSS parser - ensure it's loaded
            $rss_parser = aincc_get('rss_parser');
            $db = aincc_get('database');

            if (!$rss_parser) {
                // Try to load directly
                if (!class_exists('AINCC_RSS_Parser')) {
                    require_once AINCC_PLUGIN_DIR . 'includes/class-rss-parser.php';
                }
                $rss_parser = new AINCC_RSS_Parser();
            }

            if (!$db) {
                if (!class_exists('AINCC_Database')) {
                    require_once AINCC_PLUGIN_DIR . 'includes/class-database.php';
                }
                $db = new AINCC_Database();
            }

            // Get sources to fetch
            $sources = $db->get_sources_to_fetch($batch_size);

            if (empty($sources)) {
                $result['success'] = true;
                $result['message'] = 'No sources to fetch';
                AINCC_Logger::info('No sources ready to fetch');
                $this->release_lock($job);
                return $result;
            }

            $fetched = 0;
            $sources_processed = 0;

            foreach ($sources as $source) {
                // Check time limit
                if ((time() - $start_time) > $this->max_execution_time) {
                    AINCC_Logger::warning('Fetch timeout reached', [
                        'processed' => $fetched
                    ]);
                    break;
                }

                // Check memory
                if (!$this->has_memory_available()) {
                    AINCC_Logger::warning('Memory limit approaching', [
                        'processed' => $fetched
                    ]);
                    break;
                }

                try {
                    // fetch_source returns ['new_count' => X, 'total_count' => Y, 'duration' => Z]
                    $fetch_result = $rss_parser->fetch_source($source);

                    // Extract new articles count from result
                    if (is_array($fetch_result) && isset($fetch_result['new_count'])) {
                        $fetched += (int) $fetch_result['new_count'];
                    } elseif (is_array($fetch_result)) {
                        // Fallback: count array items if it's direct articles array
                        $fetched += count($fetch_result);
                    }

                    $sources_processed++;

                    // Update last fetch time
                    $db->update_source_fetch_time($source['id']);

                    AINCC_Logger::debug("Fetched from source", [
                        'source' => $source['name'],
                        'new_articles' => $fetch_result['new_count'] ?? 0,
                        'total_found' => $fetch_result['total_count'] ?? 0
                    ]);

                } catch (Throwable $e) {
                    $result['errors'][] = [
                        'source' => $source['name'] ?? 'unknown',
                        'error' => $e->getMessage()
                    ];
                    AINCC_Logger::error("Failed to fetch source", [
                        'source' => $source['name'] ?? 'unknown',
                        'error' => $e->getMessage()
                    ]);

                    // Mark source error in DB
                    $db->update_source_fetched($source['id'], $e->getMessage());
                }
            }

            $result['success'] = true;
            $result['fetched'] = $fetched;
            $result['sources_processed'] = $sources_processed;

            AINCC_Logger::info('Fetch completed', $result);

        } catch (Throwable $e) {
            $result['error'] = $e->getMessage();
            AINCC_Logger::error('Fetch failed', [
                'error' => $e->getMessage()
            ]);
        } finally {
            $this->release_lock($job);
        }

        return $result;
    }

    /**
     * Process content queue (AI processing)
     */
    public function process_queue(): array {
        $job = 'process_queue';
        $result = ['success' => false, 'processed' => 0, 'errors' => []];

        if (!$this->acquire_lock($job)) {
            $result['error'] = 'Job already running';
            return $result;
        }

        try {
            $start_time = time();
            $batch_size = (int) AINCC_Settings::get('batch_size', 5);

            // Get content processor
            $processor = aincc_get('content_processor');
            $db = aincc_get('database');

            if (!$processor || !$db) {
                throw new Exception('Required components not available');
            }

            // Get pending articles from queue
            $queue_items = $db->get_queue_items('pending', $batch_size);

            if (empty($queue_items)) {
                $result['success'] = true;
                $result['message'] = 'Queue is empty';
                return $result;
            }

            $processed = 0;

            foreach ($queue_items as $item) {
                // Check time limit (AI processing can be slow)
                if ((time() - $start_time) > ($this->max_execution_time - 30)) {
                    AINCC_Logger::warning('Process timeout approaching', [
                        'processed' => $processed
                    ]);
                    break;
                }

                // Check memory
                if (!$this->has_memory_available()) {
                    AINCC_Logger::warning('Memory limit approaching', [
                        'processed' => $processed
                    ]);
                    break;
                }

                try {
                    // Mark as processing
                    $db->update_queue_status($item->id, 'processing');

                    // Process the article
                    $process_result = $processor->process_article($item->article_id);

                    if ($process_result) {
                        $db->update_queue_status($item->id, 'completed');
                        $processed++;
                    } else {
                        $db->update_queue_status($item->id, 'failed');
                        $result['errors'][] = [
                            'article_id' => $item->article_id,
                            'error' => 'Processing returned false'
                        ];
                    }

                } catch (Throwable $e) {
                    $db->update_queue_status($item->id, 'failed', $e->getMessage());
                    $result['errors'][] = [
                        'article_id' => $item->article_id,
                        'error' => $e->getMessage()
                    ];
                    AINCC_Logger::error("Failed to process article", [
                        'article_id' => $item->article_id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $result['success'] = true;
            $result['processed'] = $processed;

            AINCC_Logger::info('Queue processing completed', $result);

        } catch (Throwable $e) {
            $result['error'] = $e->getMessage();
            AINCC_Logger::error('Queue processing failed', [
                'error' => $e->getMessage()
            ]);
        } finally {
            $this->release_lock($job);
        }

        return $result;
    }

    /**
     * Auto publish approved articles
     */
    public function auto_publish(): array {
        $job = 'auto_publish';
        $result = ['success' => false, 'published' => 0, 'errors' => []];

        // Check if auto-publish is enabled
        if (!AINCC_Settings::get('auto_publish_enabled', false)) {
            $result['success'] = true;
            $result['message'] = 'Auto-publish disabled';
            return $result;
        }

        if (!$this->acquire_lock($job)) {
            $result['error'] = 'Job already running';
            return $result;
        }

        try {
            $publisher = aincc_get('publisher');
            $db = aincc_get('database');

            if (!$publisher || !$db) {
                throw new Exception('Required components not available');
            }

            $delay_minutes = (int) AINCC_Settings::get('auto_publish_delay', 10);
            $max_per_run = 5;

            // Get approved drafts ready for publishing
            $drafts = $db->get_drafts_for_auto_publish($delay_minutes, $max_per_run);

            if (empty($drafts)) {
                $result['success'] = true;
                $result['message'] = 'No drafts ready for auto-publish';
                return $result;
            }

            $published = 0;

            foreach ($drafts as $draft) {
                try {
                    $pub_result = $publisher->publish($draft->id);

                    if ($pub_result['success']) {
                        $published++;
                    } else {
                        $result['errors'][] = [
                            'draft_id' => $draft->id,
                            'error' => $pub_result['error'] ?? 'Unknown error'
                        ];
                    }

                } catch (Throwable $e) {
                    $result['errors'][] = [
                        'draft_id' => $draft->id,
                        'error' => $e->getMessage()
                    ];
                    AINCC_Logger::error("Auto-publish failed for draft", [
                        'draft_id' => $draft->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $result['success'] = true;
            $result['published'] = $published;

            if ($published > 0) {
                AINCC_Logger::info('Auto-publish completed', $result);
            }

        } catch (Throwable $e) {
            $result['error'] = $e->getMessage();
            AINCC_Logger::error('Auto-publish failed', [
                'error' => $e->getMessage()
            ]);
        } finally {
            $this->release_lock($job);
        }

        return $result;
    }

    /**
     * Process scheduled posts
     */
    public function process_scheduled(): array {
        $job = 'process_scheduled';
        $result = ['success' => false, 'processed' => 0];

        if (!$this->acquire_lock($job)) {
            $result['error'] = 'Job already running';
            return $result;
        }

        try {
            $publisher = aincc_get('publisher');

            if (!$publisher) {
                throw new Exception('Publisher not available');
            }

            $processed = $publisher->process_scheduled();
            $result['success'] = true;
            $result['processed'] = $processed;

        } catch (Throwable $e) {
            $result['error'] = $e->getMessage();
            AINCC_Logger::error('Scheduled processing failed', [
                'error' => $e->getMessage()
            ]);
        } finally {
            $this->release_lock($job);
        }

        return $result;
    }

    /**
     * Cleanup old data
     */
    public function cleanup(): array {
        $job = 'cleanup';
        $result = ['success' => false];

        if (!$this->acquire_lock($job)) {
            $result['error'] = 'Job already running';
            return $result;
        }

        try {
            $db = aincc_get('database');

            if (!$db) {
                throw new Exception('Database not available');
            }

            $retention_days = (int) AINCC_Settings::get('log_retention_days', 30);

            // Cleanup logs
            $logs_deleted = $db->cleanup_old_logs($retention_days);

            // Cleanup old raw articles (keep 7 days)
            $articles_deleted = $db->cleanup_old_raw_articles(7);

            // Cleanup failed queue items (keep 3 days)
            $queue_deleted = $db->cleanup_failed_queue_items(3);

            // Clear expired transients
            $this->cleanup_transients();

            $result = [
                'success' => true,
                'logs_deleted' => $logs_deleted,
                'articles_deleted' => $articles_deleted,
                'queue_deleted' => $queue_deleted
            ];

            AINCC_Logger::info('Cleanup completed', $result);

        } catch (Throwable $e) {
            $result['error'] = $e->getMessage();
            AINCC_Logger::error('Cleanup failed', [
                'error' => $e->getMessage()
            ]);
        } finally {
            $this->release_lock($job);
        }

        return $result;
    }

    /**
     * Cleanup expired transients
     */
    private function cleanup_transients(): void {
        global $wpdb;

        $wpdb->query(
            "DELETE a, b FROM {$wpdb->options} a, {$wpdb->options} b
            WHERE a.option_name LIKE '_transient_aincc_%'
            AND a.option_name NOT LIKE '_transient_timeout_aincc_%'
            AND b.option_name = CONCAT('_transient_timeout_', SUBSTRING(a.option_name, 12))
            AND b.option_value < UNIX_TIMESTAMP()"
        );
    }

    /**
     * Get cron status for all jobs
     */
    public function get_cron_status(): array {
        $crons = [
            'aincc_fetch_sources' => [
                'name' => 'Fetch RSS Sources',
                'interval' => 'every_5_minutes',
            ],
            'aincc_process_queue' => [
                'name' => 'Process Content Queue',
                'interval' => 'every_2_minutes',
            ],
            'aincc_auto_publish' => [
                'name' => 'Auto Publish',
                'interval' => 'every_5_minutes',
            ],
            'aincc_process_scheduled' => [
                'name' => 'Process Scheduled Posts',
                'interval' => 'every_5_minutes',
            ],
            'aincc_cleanup_old_data' => [
                'name' => 'Cleanup Old Data',
                'interval' => 'daily',
            ],
        ];

        $status = [];

        foreach ($crons as $hook => $info) {
            $next = wp_next_scheduled($hook);
            $lock_key = self::LOCK_PREFIX . str_replace('aincc_', '', $hook);
            $is_running = get_transient($lock_key) !== false;

            $status[$hook] = [
                'name' => $info['name'],
                'interval' => $info['interval'],
                'next_run' => $next ? date('Y-m-d H:i:s', $next) : 'Not scheduled',
                'scheduled' => (bool) $next,
                'is_running' => $is_running,
            ];
        }

        return $status;
    }

    /**
     * Manually trigger a cron job (with validation)
     */
    public function trigger_cron(string $hook): array {
        $allowed_hooks = [
            'aincc_fetch_sources' => 'fetch_sources',
            'aincc_process_queue' => 'process_queue',
            'aincc_auto_publish' => 'auto_publish',
            'aincc_process_scheduled' => 'process_scheduled',
            'aincc_cleanup_old_data' => 'cleanup',
        ];

        if (!isset($allowed_hooks[$hook])) {
            return ['success' => false, 'error' => 'Invalid cron hook'];
        }

        // Call the method directly instead of do_action for better control
        $method = $allowed_hooks[$hook];
        return $this->$method();
    }

    /**
     * Reschedule all cron jobs
     */
    public function reschedule_all(): array {
        // Clear existing
        wp_clear_scheduled_hook('aincc_fetch_sources');
        wp_clear_scheduled_hook('aincc_process_queue');
        wp_clear_scheduled_hook('aincc_auto_publish');
        wp_clear_scheduled_hook('aincc_process_scheduled');
        wp_clear_scheduled_hook('aincc_cleanup_old_data');

        // Clear all locks
        delete_transient(self::LOCK_PREFIX . 'fetch_sources');
        delete_transient(self::LOCK_PREFIX . 'process_queue');
        delete_transient(self::LOCK_PREFIX . 'auto_publish');
        delete_transient(self::LOCK_PREFIX . 'process_scheduled');
        delete_transient(self::LOCK_PREFIX . 'cleanup');

        // Reschedule with staggered times to prevent overlap
        wp_schedule_event(time() + 30, 'every_5_minutes', 'aincc_fetch_sources');
        wp_schedule_event(time() + 90, 'every_2_minutes', 'aincc_process_queue');
        wp_schedule_event(time() + 150, 'every_5_minutes', 'aincc_auto_publish');
        wp_schedule_event(time() + 210, 'every_5_minutes', 'aincc_process_scheduled');

        // Daily cleanup at 3 AM
        $tomorrow_3am = strtotime('tomorrow 3:00am');
        wp_schedule_event($tomorrow_3am, 'daily', 'aincc_cleanup_old_data');

        AINCC_Logger::info('All cron jobs rescheduled');

        return ['success' => true, 'status' => $this->get_cron_status()];
    }

    /**
     * Get current system stats
     */
    public function get_system_stats(): array {
        return [
            'memory_used' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'memory_limit' => $this->memory_limit,
            'memory_available' => $this->has_memory_available(),
            'php_version' => PHP_VERSION,
            'wp_version' => get_bloginfo('version'),
            'cron_status' => $this->get_cron_status(),
        ];
    }
}
