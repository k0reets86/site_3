<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * When populating this file, consider the following flow
 * of control:
 *
 * - This method should be static
 * - Check if the $_REQUEST content actually is the plugin name
 * - Run an admin referrer check to make sure it goes through authentication
 * - Verify the output of $_GET makes sense
 * - Repeat with other user roles. Best if the current user is logged in.
 *
 * @link       https://your-site.de
 * @since      1.0.0
 *
 * @package    AI_News_Control_Center
 */

// If uninstall not called from WordPress, then exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Remove plugin data on uninstall
 *
 * IMPORTANT: This completely removes all plugin data!
 * Options, database tables, and cached data will be deleted.
 */

global $wpdb;

// Check if user wants to keep data (option can be set before uninstall)
$keep_data = get_option('aincc_keep_data_on_uninstall', false);

if ($keep_data) {
    // User wants to keep data, just exit
    return;
}

// ============================================
// 1. Remove all plugin options
// ============================================
$options_to_delete = [
    'aincc_ai_provider',
    'aincc_deepseek_api_key',
    'aincc_deepseek_model',
    'aincc_openai_api_key',
    'aincc_openai_model',
    'aincc_anthropic_api_key',
    'aincc_anthropic_model',
    'aincc_pexels_api_key',
    'aincc_telegram_bot_token',
    'aincc_telegram_channel_id',
    'aincc_default_language',
    'aincc_target_languages',
    'aincc_auto_publish_enabled',
    'aincc_auto_publish_delay',
    'aincc_fact_check_threshold',
    'aincc_source_trust_threshold',
    'aincc_fetch_interval',
    'aincc_max_articles_per_day',
    'aincc_categories_require_approval',
    'aincc_db_version',
    'aincc_batch_size',
    'aincc_api_timeout',
    'aincc_max_memory_mb',
    'aincc_keep_data_on_uninstall',
];

foreach ($options_to_delete as $option) {
    delete_option($option);
}

// Also delete any options that might have been added dynamically
$wpdb->query(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'aincc_%'"
);

// ============================================
// 2. Remove all custom database tables
// ============================================
$tables_to_drop = [
    $wpdb->prefix . 'aincc_sources',
    $wpdb->prefix . 'aincc_articles_raw',
    $wpdb->prefix . 'aincc_drafts',
    $wpdb->prefix . 'aincc_published',
    $wpdb->prefix . 'aincc_images',
    $wpdb->prefix . 'aincc_entities',
    $wpdb->prefix . 'aincc_fact_checks',
    $wpdb->prefix . 'aincc_telegram_posts',
    $wpdb->prefix . 'aincc_analytics',
    $wpdb->prefix . 'aincc_ab_tests',
    $wpdb->prefix . 'aincc_logs',
    $wpdb->prefix . 'aincc_queue',
    $wpdb->prefix . 'aincc_cache',
];

foreach ($tables_to_drop as $table) {
    $wpdb->query("DROP TABLE IF EXISTS {$table}");
}

// ============================================
// 3. Clear scheduled cron jobs
// ============================================
$cron_hooks = [
    'aincc_fetch_sources',
    'aincc_process_queue',
    'aincc_auto_publish',
    'aincc_cleanup_old_data',
];

foreach ($cron_hooks as $hook) {
    wp_clear_scheduled_hook($hook);
}

// ============================================
// 4. Remove any transients
// ============================================
$wpdb->query(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_aincc_%'"
);
$wpdb->query(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_aincc_%'"
);

// ============================================
// 5. Remove plugin upload directory (if exists)
// ============================================
$upload_dir = wp_upload_dir();
$plugin_upload_path = $upload_dir['basedir'] . '/ai-news-control-center';

if (is_dir($plugin_upload_path)) {
    // Recursively delete directory
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($plugin_upload_path, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($files as $fileinfo) {
        $action = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
        @$action($fileinfo->getRealPath());
    }

    @rmdir($plugin_upload_path);
}

// ============================================
// 6. Clean up post meta related to plugin
// ============================================
$wpdb->query(
    "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_aincc_%'"
);

// ============================================
// 7. Remove user meta related to plugin
// ============================================
$wpdb->query(
    "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'aincc_%'"
);

// ============================================
// 8. Flush rewrite rules
// ============================================
flush_rewrite_rules();

// Log uninstall (to PHP error log, since our logger is gone)
error_log('AI News Control Center plugin uninstalled successfully');
