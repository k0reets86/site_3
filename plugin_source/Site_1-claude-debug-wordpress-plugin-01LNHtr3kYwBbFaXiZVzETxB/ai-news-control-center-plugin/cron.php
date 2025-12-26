<?php
/**
 * AI News Control Center - Server Cron Endpoint
 *
 * This file allows TRUE scheduled execution independent of site visits.
 *
 * SETUP INSTRUCTIONS (on your server):
 * ====================================
 *
 * 1. Add this line to your server's crontab (crontab -e):
 *
 *    */5 * * * * curl -s "https://your-site.com/wp-content/plugins/ai-news-control-center-plugin/cron.php?secret=YOUR_SECRET_KEY" > /dev/null 2>&1
 *
 *    OR using wget:
 *
 *    */5 * * * * wget -q -O /dev/null "https://your-site.com/wp-content/plugins/ai-news-control-center-plugin/cron.php?secret=YOUR_SECRET_KEY"
 *
 *    OR using PHP CLI (more reliable):
 *
 *    */5 * * * * cd /path/to/wordpress && /usr/bin/php wp-content/plugins/ai-news-control-center-plugin/cron.php secret=YOUR_SECRET_KEY
 *
 * 2. Replace YOUR_SECRET_KEY with the key shown in plugin settings
 *
 * 3. Adjust timing:
 *    */5  = every 5 minutes (recommended)
 *    */10 = every 10 minutes
 *    */15 = every 15 minutes
 *    */30 = every 30 minutes
 *    0 *  = every hour
 *
 * 4. Disable WordPress pseudo-cron in wp-config.php:
 *    define('DISABLE_WP_CRON', true);
 *
 * @package AINCC
 */

// Get secret from URL or CLI
$secret = '';
if (php_sapi_name() === 'cli') {
    // CLI mode: parse arguments
    foreach ($argv as $arg) {
        if (strpos($arg, 'secret=') === 0) {
            $secret = substr($arg, 7);
            break;
        }
    }
} else {
    // HTTP mode
    $secret = $_GET['secret'] ?? '';
}

// Find WordPress installation
$wp_load_paths = [
    dirname(__FILE__) . '/../../../wp-load.php',
    dirname(__FILE__) . '/../../../../wp-load.php',
    dirname(__FILE__) . '/../../../../../wp-load.php',
];

$wp_load = null;
foreach ($wp_load_paths as $path) {
    if (file_exists($path)) {
        $wp_load = $path;
        break;
    }
}

if (!$wp_load) {
    http_response_code(500);
    die(json_encode(['error' => 'WordPress not found']));
}

// Load WordPress in minimal mode
define('DOING_CRON', true);
define('WP_USE_THEMES', false);

require_once $wp_load;

// Set headers for JSON response
if (php_sapi_name() !== 'cli') {
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, no-store, must-revalidate');
}

/**
 * Output result
 */
function aincc_cron_output($data) {
    if (php_sapi_name() === 'cli') {
        echo date('[Y-m-d H:i:s] ') . json_encode($data, JSON_PRETTY_PRINT) . "\n";
    } else {
        echo json_encode($data, JSON_PRETTY_PRINT);
    }
}

// Verify secret key
$stored_secret = get_option('aincc_cron_secret', '');

// Auto-generate secret if not exists
if (empty($stored_secret)) {
    $stored_secret = wp_generate_password(32, false);
    update_option('aincc_cron_secret', $stored_secret, 'no');
}

if (empty($secret) || !hash_equals($stored_secret, $secret)) {
    http_response_code(403);
    aincc_cron_output([
        'error' => 'Invalid or missing secret key',
        'help' => 'Get the secret key from AI News Control Center > Settings > Cron Setup',
    ]);
    exit;
}

// Check if plugin is active
if (!function_exists('aincc_get')) {
    http_response_code(500);
    aincc_cron_output(['error' => 'AI News Control Center plugin not active']);
    exit;
}

// Get action parameter
$action = isset($_GET['action']) ? sanitize_key($_GET['action']) : 'all';
if (php_sapi_name() === 'cli') {
    foreach ($argv as $arg) {
        if (strpos($arg, 'action=') === 0) {
            $action = sanitize_key(substr($arg, 7));
            break;
        }
    }
}

// Set time limit for cron execution
if (function_exists('set_time_limit')) {
    @set_time_limit(300);
}

// Set memory limit
ini_set('memory_limit', '256M');

$results = [
    'status' => 'ok',
    'timestamp' => current_time('mysql'),
    'actions' => [],
];

try {
    // Load required components
    $scheduler = aincc_get('scheduler');

    if (!$scheduler) {
        throw new Exception('Scheduler component not available');
    }

    switch ($action) {
        case 'fetch':
            // Only fetch RSS sources
            $result = $scheduler->fetch_sources();
            $results['actions']['fetch'] = $result;
            break;

        case 'process':
            // Only process queue
            $result = $scheduler->process_queue();
            $results['actions']['process'] = $result;
            break;

        case 'publish':
            // Only auto-publish
            $result = $scheduler->auto_publish();
            $results['actions']['publish'] = $result;
            break;

        case 'cleanup':
            // Only cleanup
            $result = $scheduler->cleanup();
            $results['actions']['cleanup'] = $result;
            break;

        case 'all':
        default:
            // Run full cycle: fetch → process → publish

            // 1. Fetch new articles from RSS
            $fetch_result = $scheduler->fetch_sources();
            $results['actions']['fetch'] = [
                'success' => $fetch_result['success'] ?? false,
                'fetched' => $fetch_result['fetched'] ?? 0,
                'errors' => count($fetch_result['errors'] ?? []),
            ];

            // Small delay between operations
            usleep(500000); // 0.5 seconds

            // 2. Process queue (create drafts with AI)
            // This is now automatically called after fetch, but we call it again
            // in case there are pending items from previous runs
            $process_result = $scheduler->process_queue();
            $results['actions']['process'] = [
                'success' => $process_result['success'] ?? false,
                'processed' => $process_result['processed'] ?? 0,
            ];

            // 3. Auto-publish if enabled
            $publish_result = $scheduler->auto_publish();
            $results['actions']['publish'] = [
                'success' => $publish_result['success'] ?? false,
                'published' => $publish_result['published'] ?? 0,
            ];

            break;
    }

    // Summary
    $results['summary'] = [
        'articles_fetched' => $results['actions']['fetch']['fetched'] ?? 0,
        'drafts_created' => $results['actions']['process']['processed'] ?? 0,
        'posts_published' => $results['actions']['publish']['published'] ?? 0,
    ];

} catch (Throwable $e) {
    $results['status'] = 'error';
    $results['error'] = $e->getMessage();

    // Log error
    if (class_exists('AINCC_Logger')) {
        AINCC_Logger::error('Server cron error', [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);
    }
}

// Output results
aincc_cron_output($results);
