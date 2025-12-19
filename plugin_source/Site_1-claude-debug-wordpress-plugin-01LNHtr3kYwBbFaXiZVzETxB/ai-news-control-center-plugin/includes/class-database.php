<?php
/**
 * Database Handler
 * Creates and manages custom database tables
 */

if (!defined('ABSPATH')) {
    exit;
}

class AINCC_Database {

    /**
     * WordPress database object
     */
    private $wpdb;

    /**
     * Table prefix
     */
    private $prefix;

    /**
     * Constructor
     */
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->prefix = $wpdb->prefix . 'aincc_';
    }

    /**
     * Get table name
     */
    public function table($name) {
        return $this->prefix . $name;
    }

    /**
     * Create all tables
     */
    public function create_tables() {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $this->wpdb->get_charset_collate();

        // Sources table - RSS/API sources
        $sql_sources = "CREATE TABLE {$this->table('sources')} (
            id VARCHAR(100) NOT NULL,
            name VARCHAR(255) NOT NULL,
            method VARCHAR(20) NOT NULL DEFAULT 'rss',
            url TEXT NOT NULL,
            lang VARCHAR(5) NOT NULL DEFAULT 'de',
            geo VARCHAR(100) DEFAULT 'Deutschland',
            category VARCHAR(50) DEFAULT 'media',
            trust_score DECIMAL(3,2) DEFAULT 0.50,
            fetch_interval INT DEFAULT 15,
            enabled TINYINT(1) DEFAULT 1,
            last_fetched_at DATETIME DEFAULT NULL,
            last_error TEXT DEFAULT NULL,
            error_count INT DEFAULT 0,
            quarantine_until DATETIME DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_enabled (enabled),
            KEY idx_category (category),
            KEY idx_last_fetched (last_fetched_at)
        ) $charset_collate;";

        // Raw items - fetched articles before processing
        $sql_raw_items = "CREATE TABLE {$this->table('raw_items')} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            source_id VARCHAR(100) NOT NULL,
            url TEXT NOT NULL,
            url_hash VARCHAR(32) NOT NULL,
            title TEXT,
            summary TEXT,
            body_plain LONGTEXT,
            body_html LONGTEXT,
            author VARCHAR(255),
            published_at DATETIME,
            fetched_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            lang VARCHAR(5) DEFAULT 'de',
            entities JSON,
            keywords JSON,
            fingerprint BIGINT UNSIGNED,
            event_id VARCHAR(100),
            fact_check_score DECIMAL(3,2),
            status VARCHAR(20) DEFAULT 'new',
            priority VARCHAR(10) DEFAULT 'normal',
            PRIMARY KEY (id),
            UNIQUE KEY idx_url_hash (url_hash),
            KEY idx_source_id (source_id),
            KEY idx_event_id (event_id),
            KEY idx_status (status),
            KEY idx_fetched_at (fetched_at),
            KEY idx_fingerprint (fingerprint)
        ) $charset_collate;";

        // Events - clustered news events
        $sql_events = "CREATE TABLE {$this->table('events')} (
            id VARCHAR(100) NOT NULL,
            first_seen_at DATETIME NOT NULL,
            last_updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            geo JSON,
            topics JSON,
            confidence DECIMAL(3,2) DEFAULT 0.50,
            item_count INT DEFAULT 1,
            status VARCHAR(20) DEFAULT 'new',
            is_breaking TINYINT(1) DEFAULT 0,
            PRIMARY KEY (id),
            KEY idx_status (status),
            KEY idx_first_seen (first_seen_at)
        ) $charset_collate;";

        // Drafts - AI-generated content awaiting approval/publishing
        $sql_drafts = "CREATE TABLE {$this->table('drafts')} (
            id VARCHAR(100) NOT NULL,
            event_id VARCHAR(100),
            raw_item_id BIGINT UNSIGNED,
            lang VARCHAR(5) NOT NULL,
            title TEXT NOT NULL,
            lead TEXT,
            body_html LONGTEXT,
            sources JSON,
            structured_data JSON,
            sentiment DECIMAL(4,2),
            category VARCHAR(50),
            subcategory VARCHAR(50),
            tags JSON,
            geo_tags JSON,
            risk_flags JSON,
            seo_title VARCHAR(70),
            meta_description VARCHAR(160),
            slug VARCHAR(200),
            canonical_url TEXT,
            keywords JSON,
            schema_markup TEXT,
            og_data JSON,
            image_url TEXT,
            image_author VARCHAR(255),
            image_license VARCHAR(100),
            image_alt TEXT,
            image_local_id BIGINT UNSIGNED,
            audio_url TEXT,
            audio_duration INT,
            predicted_ctr DECIMAL(4,2),
            translation_quality DECIMAL(3,2),
            status VARCHAR(30) DEFAULT 'ai_draft',
            gate_reason VARCHAR(100),
            scheduled_at DATETIME,
            published_at DATETIME,
            wp_post_id BIGINT UNSIGNED,
            created_by BIGINT UNSIGNED,
            edited_by BIGINT UNSIGNED,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_event_id (event_id),
            KEY idx_status (status),
            KEY idx_lang (lang),
            KEY idx_wp_post_id (wp_post_id),
            KEY idx_scheduled_at (scheduled_at)
        ) $charset_collate;";

        // Fact checks
        $sql_fact_checks = "CREATE TABLE {$this->table('fact_checks')} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            raw_item_id BIGINT UNSIGNED NOT NULL,
            claims JSON,
            score DECIMAL(3,2),
            sources_confirmed INT DEFAULT 0,
            external_checks JSON,
            verified_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_raw_item_id (raw_item_id)
        ) $charset_collate;";

        // Published posts tracking
        $sql_publishes = "CREATE TABLE {$this->table('publishes')} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            draft_id VARCHAR(100) NOT NULL,
            wp_post_id BIGINT UNSIGNED NOT NULL,
            lang VARCHAR(5),
            url TEXT,
            published_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_draft_id (draft_id),
            KEY idx_wp_post_id (wp_post_id)
        ) $charset_collate;";

        // Social media posts
        $sql_social_posts = "CREATE TABLE {$this->table('social_posts')} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            draft_id VARCHAR(100) NOT NULL,
            platform VARCHAR(20) NOT NULL,
            platform_post_id VARCHAR(100),
            url TEXT,
            lang VARCHAR(5),
            status VARCHAR(20) DEFAULT 'pending',
            error_message TEXT,
            posted_at DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_draft_id (draft_id),
            KEY idx_platform (platform),
            KEY idx_status (status)
        ) $charset_collate;";

        // Metrics tracking
        $sql_metrics = "CREATE TABLE {$this->table('metrics')} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id BIGINT UNSIGNED NOT NULL,
            metric_type VARCHAR(50) NOT NULL,
            metric_value DECIMAL(12,2),
            period VARCHAR(20) DEFAULT 'day',
            recorded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_post_id (post_id),
            KEY idx_metric_type (metric_type),
            KEY idx_recorded_at (recorded_at)
        ) $charset_collate;";

        // A/B Tests
        $sql_tests = "CREATE TABLE {$this->table('ab_tests')} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            draft_id VARCHAR(100) NOT NULL,
            test_type VARCHAR(30) NOT NULL,
            variant_a TEXT,
            variant_b TEXT,
            impressions_a INT DEFAULT 0,
            impressions_b INT DEFAULT 0,
            clicks_a INT DEFAULT 0,
            clicks_b INT DEFAULT 0,
            winner VARCHAR(1),
            status VARCHAR(20) DEFAULT 'active',
            started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            ended_at DATETIME,
            PRIMARY KEY (id),
            KEY idx_draft_id (draft_id),
            KEY idx_status (status)
        ) $charset_collate;";

        // Source trust history
        $sql_trust_history = "CREATE TABLE {$this->table('trust_history')} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            source_id VARCHAR(100) NOT NULL,
            old_score DECIMAL(3,2),
            new_score DECIMAL(3,2),
            reason TEXT,
            changed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_source_id (source_id)
        ) $charset_collate;";

        // Comments moderation
        $sql_moderation = "CREATE TABLE {$this->table('comment_moderation')} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            comment_id BIGINT UNSIGNED NOT NULL,
            toxicity_score DECIMAL(3,2),
            spam_score DECIMAL(3,2),
            action_taken VARCHAR(20),
            reason VARCHAR(100),
            analyzed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_comment_id (comment_id)
        ) $charset_collate;";

        // Processing queue
        $sql_queue = "CREATE TABLE {$this->table('queue')} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            job_type VARCHAR(50) NOT NULL,
            payload JSON NOT NULL,
            priority INT DEFAULT 10,
            status VARCHAR(20) DEFAULT 'pending',
            attempts INT DEFAULT 0,
            max_attempts INT DEFAULT 3,
            error_message TEXT,
            scheduled_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            started_at DATETIME,
            completed_at DATETIME,
            PRIMARY KEY (id),
            KEY idx_status (status),
            KEY idx_job_type (job_type),
            KEY idx_scheduled_at (scheduled_at),
            KEY idx_priority (priority)
        ) $charset_collate;";

        // Logs
        $sql_logs = "CREATE TABLE {$this->table('logs')} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            level VARCHAR(20) NOT NULL,
            message TEXT NOT NULL,
            context JSON,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_level (level),
            KEY idx_created_at (created_at)
        ) $charset_collate;";

        // Execute all table creations
        dbDelta($sql_sources);
        dbDelta($sql_raw_items);
        dbDelta($sql_events);
        dbDelta($sql_drafts);
        dbDelta($sql_fact_checks);
        dbDelta($sql_publishes);
        dbDelta($sql_social_posts);
        dbDelta($sql_metrics);
        dbDelta($sql_tests);
        dbDelta($sql_trust_history);
        dbDelta($sql_moderation);
        dbDelta($sql_queue);
        dbDelta($sql_logs);

        // Insert default sources
        $this->insert_default_sources();

        // Update DB version
        update_option('aincc_db_version', AINCC_DB_VERSION);
    }

    /**
     * Insert default RSS sources
     */
    private function insert_default_sources() {
        $this->load_default_sources();
    }

    /**
     * Load default sources (public method for reinit)
     */
    public function load_default_sources() {
        $sources = $this->get_default_sources();
        $inserted = 0;

        foreach ($sources as $source) {
            $exists = $this->wpdb->get_var(
                $this->wpdb->prepare(
                    "SELECT id FROM {$this->table('sources')} WHERE id = %s",
                    $source['id']
                )
            );

            if (!$exists) {
                $result = $this->wpdb->insert(
                    $this->table('sources'),
                    $source,
                    ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%d', '%d']
                );
                if ($result) {
                    $inserted++;
                }
            }
        }

        return $inserted;
    }

    /**
     * Get default sources list
     */
    private function get_default_sources() {
        return [
            // Official German sources
            [
                'id' => 'bamf_press',
                'name' => 'BAMF Pressemitteilungen',
                'method' => 'rss',
                'url' => 'https://www.bamf.de/SiteGlobals/Functions/RSSFeed/DE/RSSNewsfeed_Meldungen.xml',
                'lang' => 'de',
                'geo' => 'Deutschland',
                'category' => 'official',
                'trust_score' => 0.95,
                'fetch_interval' => 10,
                'enabled' => 1
            ],
            [
                'id' => 'bundesregierung',
                'name' => 'Bundesregierung',
                'method' => 'rss',
                'url' => 'https://www.bundesregierung.de/breg-de/service/rss/970410/feed.xml',
                'lang' => 'de',
                'geo' => 'Deutschland',
                'category' => 'official',
                'trust_score' => 0.98,
                'fetch_interval' => 10,
                'enabled' => 1
            ],
            [
                'id' => 'muenchen_stadt',
                'name' => 'Stadt München',
                'method' => 'rss',
                'url' => 'https://www.muenchen.de/aktuell/rss.xml',
                'lang' => 'de',
                'geo' => 'München',
                'category' => 'official',
                'trust_score' => 0.95,
                'fetch_interval' => 10,
                'enabled' => 1
            ],
            [
                'id' => 'bayern_de',
                'name' => 'Bayern.de',
                'method' => 'rss',
                'url' => 'https://www.bayern.de/feed/',
                'lang' => 'de',
                'geo' => 'Bayern',
                'category' => 'official',
                'trust_score' => 0.95,
                'fetch_interval' => 15,
                'enabled' => 1
            ],

            // German Media
            [
                'id' => 'tagesschau',
                'name' => 'Tagesschau',
                'method' => 'rss',
                'url' => 'https://www.tagesschau.de/index~rss2.xml',
                'lang' => 'de',
                'geo' => 'Deutschland',
                'category' => 'media',
                'trust_score' => 0.90,
                'fetch_interval' => 5,
                'enabled' => 1
            ],
            [
                'id' => 'spiegel',
                'name' => 'Der Spiegel',
                'method' => 'rss',
                'url' => 'https://www.spiegel.de/schlagzeilen/index.rss',
                'lang' => 'de',
                'geo' => 'Deutschland',
                'category' => 'media',
                'trust_score' => 0.85,
                'fetch_interval' => 10,
                'enabled' => 1
            ],
            [
                'id' => 'zeit',
                'name' => 'Die Zeit',
                'method' => 'rss',
                'url' => 'https://newsfeed.zeit.de/index',
                'lang' => 'de',
                'geo' => 'Deutschland',
                'category' => 'media',
                'trust_score' => 0.88,
                'fetch_interval' => 10,
                'enabled' => 1
            ],
            [
                'id' => 'sueddeutsche',
                'name' => 'Süddeutsche Zeitung',
                'method' => 'rss',
                'url' => 'https://rss.sueddeutsche.de/rss/Topthemen',
                'lang' => 'de',
                'geo' => 'München',
                'category' => 'media',
                'trust_score' => 0.87,
                'fetch_interval' => 10,
                'enabled' => 1
            ],
            [
                'id' => 'br24',
                'name' => 'BR24',
                'method' => 'rss',
                'url' => 'https://www.br.de/nachrichten/feed/rss',
                'lang' => 'de',
                'geo' => 'Bayern',
                'category' => 'media',
                'trust_score' => 0.88,
                'fetch_interval' => 5,
                'enabled' => 1
            ],
            [
                'id' => 'merkur',
                'name' => 'Münchner Merkur',
                'method' => 'rss',
                'url' => 'https://www.merkur.de/welt/rssfeed.rdf',
                'lang' => 'de',
                'geo' => 'München',
                'category' => 'media',
                'trust_score' => 0.80,
                'fetch_interval' => 10,
                'enabled' => 1
            ],
            [
                'id' => 'tz_muenchen',
                'name' => 'tz München',
                'method' => 'rss',
                'url' => 'https://www.tz.de/rssfeed.rdf',
                'lang' => 'de',
                'geo' => 'München',
                'category' => 'media',
                'trust_score' => 0.78,
                'fetch_interval' => 10,
                'enabled' => 1
            ],
            [
                'id' => 'abendzeitung',
                'name' => 'Abendzeitung München',
                'method' => 'rss',
                'url' => 'https://www.abendzeitung-muenchen.de/rss/muenchen.xml',
                'lang' => 'de',
                'geo' => 'München',
                'category' => 'media',
                'trust_score' => 0.75,
                'fetch_interval' => 15,
                'enabled' => 1
            ],

            // International
            [
                'id' => 'dw_deutsch',
                'name' => 'Deutsche Welle',
                'method' => 'rss',
                'url' => 'https://rss.dw.com/xml/rss-de-all',
                'lang' => 'de',
                'geo' => 'International',
                'category' => 'international',
                'trust_score' => 0.90,
                'fetch_interval' => 10,
                'enabled' => 1
            ],
            [
                'id' => 'dw_ukrainian',
                'name' => 'Deutsche Welle Українська',
                'method' => 'rss',
                'url' => 'https://rss.dw.com/xml/rss-uk-all',
                'lang' => 'uk',
                'geo' => 'International',
                'category' => 'international',
                'trust_score' => 0.90,
                'fetch_interval' => 10,
                'enabled' => 1
            ],
            [
                'id' => 'euronews_de',
                'name' => 'Euronews Deutsch',
                'method' => 'rss',
                'url' => 'https://de.euronews.com/rss',
                'lang' => 'de',
                'geo' => 'Europa',
                'category' => 'international',
                'trust_score' => 0.82,
                'fetch_interval' => 15,
                'enabled' => 1
            ],

            // Ukrainian sources
            [
                'id' => 'ukrinform_de',
                'name' => 'Ukrinform Deutsch',
                'method' => 'rss',
                'url' => 'https://www.ukrinform.de/rss/block-lastnews',
                'lang' => 'de',
                'geo' => 'Ukraine',
                'category' => 'ukraine',
                'trust_score' => 0.85,
                'fetch_interval' => 10,
                'enabled' => 1
            ],
            [
                'id' => 'ukrinform_ua',
                'name' => 'Ukrinform Українська',
                'method' => 'rss',
                'url' => 'https://www.ukrinform.ua/rss/block-lastnews',
                'lang' => 'uk',
                'geo' => 'Ukraine',
                'category' => 'ukraine',
                'trust_score' => 0.85,
                'fetch_interval' => 10,
                'enabled' => 1
            ],
            [
                'id' => 'pravda_ua',
                'name' => 'Українська правда',
                'method' => 'rss',
                'url' => 'https://www.pravda.com.ua/rss/',
                'lang' => 'uk',
                'geo' => 'Ukraine',
                'category' => 'ukraine',
                'trust_score' => 0.82,
                'fetch_interval' => 10,
                'enabled' => 1
            ],
            [
                'id' => 'suspilne',
                'name' => 'Суспільне Новини',
                'method' => 'rss',
                'url' => 'https://suspilne.media/rss/ukr.rss',
                'lang' => 'uk',
                'geo' => 'Ukraine',
                'category' => 'ukraine',
                'trust_score' => 0.88,
                'fetch_interval' => 10,
                'enabled' => 1
            ],
            [
                'id' => 'unian_ua',
                'name' => 'УНІАН',
                'method' => 'rss',
                'url' => 'https://rss.unian.net/site/news_ukr.rss',
                'lang' => 'uk',
                'geo' => 'Ukraine',
                'category' => 'ukraine',
                'trust_score' => 0.80,
                'fetch_interval' => 5,
                'enabled' => 1
            ],
            [
                'id' => 'unian_ru',
                'name' => 'УНИ|АН Русский',
                'method' => 'rss',
                'url' => 'https://rss.unian.net/site/news_rus.rss',
                'lang' => 'ru',
                'geo' => 'Ukraine',
                'category' => 'ukraine',
                'trust_score' => 0.80,
                'fetch_interval' => 5,
                'enabled' => 1
            ],
            [
                'id' => 'unian_de',
                'name' => 'UNIAN Deutsch',
                'method' => 'rss',
                'url' => 'https://rss.unian.net/site/news_deu.rss',
                'lang' => 'de',
                'geo' => 'Ukraine',
                'category' => 'ukraine',
                'trust_score' => 0.80,
                'fetch_interval' => 10,
                'enabled' => 1
            ],
            [
                'id' => 'liga_ua',
                'name' => 'Ліга.net',
                'method' => 'rss',
                'url' => 'https://news.liga.net/all/rss.xml',
                'lang' => 'uk',
                'geo' => 'Ukraine',
                'category' => 'ukraine',
                'trust_score' => 0.78,
                'fetch_interval' => 10,
                'enabled' => 1
            ],
            [
                'id' => 'nv_ua',
                'name' => 'НВ (Новое Время)',
                'method' => 'rss',
                'url' => 'https://nv.ua/rss/all.xml',
                'lang' => 'uk',
                'geo' => 'Ukraine',
                'category' => 'ukraine',
                'trust_score' => 0.80,
                'fetch_interval' => 10,
                'enabled' => 1
            ],
            [
                'id' => 'eurointegration',
                'name' => 'Європейська правда',
                'method' => 'rss',
                'url' => 'https://www.eurointegration.com.ua/rss/',
                'lang' => 'uk',
                'geo' => 'Ukraine',
                'category' => 'ukraine',
                'trust_score' => 0.85,
                'fetch_interval' => 15,
                'enabled' => 1
            ],
            [
                'id' => 'hromadske',
                'name' => 'Громадське',
                'method' => 'rss',
                'url' => 'https://hromadske.ua/rss',
                'lang' => 'uk',
                'geo' => 'Ukraine',
                'category' => 'ukraine',
                'trust_score' => 0.85,
                'fetch_interval' => 10,
                'enabled' => 1
            ],
            [
                'id' => 'zn_ua',
                'name' => 'Дзеркало тижня',
                'method' => 'rss',
                'url' => 'https://zn.ua/rss/full.rss',
                'lang' => 'uk',
                'geo' => 'Ukraine',
                'category' => 'ukraine',
                'trust_score' => 0.85,
                'fetch_interval' => 15,
                'enabled' => 1
            ],

            // Migration & Integration topics
            [
                'id' => 'integrationsbeauftragte',
                'name' => 'Integrationsbeauftragte',
                'method' => 'rss',
                'url' => 'https://www.integrationsbeauftragte.de/ib-de/service/rss/1865218/feed.xml',
                'lang' => 'de',
                'geo' => 'Deutschland',
                'category' => 'official',
                'trust_score' => 0.95,
                'fetch_interval' => 30,
                'enabled' => 1
            ],

            // Transport
            [
                'id' => 'mvg_ticker',
                'name' => 'MVG Störungsticker',
                'method' => 'rss',
                'url' => 'https://www.mvg.de/dienste/rss-feed.xml',
                'lang' => 'de',
                'geo' => 'München',
                'category' => 'transport',
                'trust_score' => 0.95,
                'fetch_interval' => 5,
                'enabled' => 1
            ],
            [
                'id' => 'db_verkehrsmeldungen',
                'name' => 'Deutsche Bahn',
                'method' => 'rss',
                'url' => 'https://www.deutschebahn.com/de/presse/newsfeed-22640892',
                'lang' => 'de',
                'geo' => 'Deutschland',
                'category' => 'transport',
                'trust_score' => 0.90,
                'fetch_interval' => 10,
                'enabled' => 1
            ],

            // Economy & Jobs
            [
                'id' => 'arbeitsagentur',
                'name' => 'Bundesagentur für Arbeit',
                'method' => 'rss',
                'url' => 'https://www.arbeitsagentur.de/rss/presse',
                'lang' => 'de',
                'geo' => 'Deutschland',
                'category' => 'official',
                'trust_score' => 0.95,
                'fetch_interval' => 30,
                'enabled' => 1
            ],

            // Google News searches (for specific topics)
            [
                'id' => 'google_ukraine_de',
                'name' => 'Google News: Ukraine Deutschland',
                'method' => 'rss',
                'url' => 'https://news.google.com/rss/search?q=Ukraine+Deutschland+Flüchtlinge&hl=de&gl=DE&ceid=DE:de',
                'lang' => 'de',
                'geo' => 'Deutschland',
                'category' => 'aggregator',
                'trust_score' => 0.70,
                'fetch_interval' => 15,
                'enabled' => 1
            ],
            [
                'id' => 'google_muenchen',
                'name' => 'Google News: München',
                'method' => 'rss',
                'url' => 'https://news.google.com/rss/search?q=München&hl=de&gl=DE&ceid=DE:de',
                'lang' => 'de',
                'geo' => 'München',
                'category' => 'aggregator',
                'trust_score' => 0.70,
                'fetch_interval' => 15,
                'enabled' => 1
            ],
            [
                'id' => 'google_aufenthaltstitel',
                'name' => 'Google News: Aufenthaltstitel',
                'method' => 'rss',
                'url' => 'https://news.google.com/rss/search?q=Aufenthaltstitel+OR+Aufenthaltserlaubnis&hl=de&gl=DE&ceid=DE:de',
                'lang' => 'de',
                'geo' => 'Deutschland',
                'category' => 'aggregator',
                'trust_score' => 0.70,
                'fetch_interval' => 20,
                'enabled' => 1
            ],
            [
                'id' => 'google_buergergeld',
                'name' => 'Google News: Bürgergeld',
                'method' => 'rss',
                'url' => 'https://news.google.com/rss/search?q=Bürgergeld+2024&hl=de&gl=DE&ceid=DE:de',
                'lang' => 'de',
                'geo' => 'Deutschland',
                'category' => 'aggregator',
                'trust_score' => 0.70,
                'fetch_interval' => 30,
                'enabled' => 1
            ],

            // Weather & Emergency
            [
                'id' => 'dwd_warnungen',
                'name' => 'DWD Wetterwarnungen',
                'method' => 'rss',
                'url' => 'https://www.dwd.de/DE/wetter/warnungen_gemeinden/warnWetter_node.rss',
                'lang' => 'de',
                'geo' => 'Deutschland',
                'category' => 'emergency',
                'trust_score' => 0.98,
                'fetch_interval' => 5,
                'enabled' => 1
            ],

            // More news sources
            [
                'id' => 'faz',
                'name' => 'FAZ',
                'method' => 'rss',
                'url' => 'https://www.faz.net/rss/aktuell/',
                'lang' => 'de',
                'geo' => 'Deutschland',
                'category' => 'media',
                'trust_score' => 0.88,
                'fetch_interval' => 10,
                'enabled' => 1
            ],
            [
                'id' => 'welt',
                'name' => 'Die Welt',
                'method' => 'rss',
                'url' => 'https://www.welt.de/feeds/latest.rss',
                'lang' => 'de',
                'geo' => 'Deutschland',
                'category' => 'media',
                'trust_score' => 0.82,
                'fetch_interval' => 10,
                'enabled' => 1
            ],
            [
                'id' => 'focus',
                'name' => 'Focus',
                'method' => 'rss',
                'url' => 'https://rss.focus.de/fol/XML/rss_folnews.xml',
                'lang' => 'de',
                'geo' => 'Deutschland',
                'category' => 'media',
                'trust_score' => 0.75,
                'fetch_interval' => 15,
                'enabled' => 1
            ],
            [
                'id' => 'stern',
                'name' => 'Stern',
                'method' => 'rss',
                'url' => 'https://www.stern.de/feed/standard/all/',
                'lang' => 'de',
                'geo' => 'Deutschland',
                'category' => 'media',
                'trust_score' => 0.78,
                'fetch_interval' => 15,
                'enabled' => 1
            ],
            [
                'id' => 'ntv',
                'name' => 'n-tv',
                'method' => 'rss',
                'url' => 'https://www.n-tv.de/rss',
                'lang' => 'de',
                'geo' => 'Deutschland',
                'category' => 'media',
                'trust_score' => 0.80,
                'fetch_interval' => 10,
                'enabled' => 1
            ],
            [
                'id' => 'zdf',
                'name' => 'ZDF heute',
                'method' => 'rss',
                'url' => 'https://www.zdf.de/rss/zdf/nachrichten',
                'lang' => 'de',
                'geo' => 'Deutschland',
                'category' => 'media',
                'trust_score' => 0.90,
                'fetch_interval' => 10,
                'enabled' => 1
            ],

            // Tech & Economy
            [
                'id' => 'handelsblatt',
                'name' => 'Handelsblatt',
                'method' => 'rss',
                'url' => 'https://www.handelsblatt.com/contentexport/feed/schlagzeilen',
                'lang' => 'de',
                'geo' => 'Deutschland',
                'category' => 'economy',
                'trust_score' => 0.88,
                'fetch_interval' => 15,
                'enabled' => 1
            ],

            // Radio Free Europe - Ukrainian
            [
                'id' => 'radiosvoboda',
                'name' => 'Радіо Свобода',
                'method' => 'rss',
                'url' => 'https://www.radiosvoboda.org/api/z-pqpiev-qpp',
                'lang' => 'uk',
                'geo' => 'Ukraine',
                'category' => 'ukraine',
                'trust_score' => 0.85,
                'fetch_interval' => 10,
                'enabled' => 1
            ],

            // Local Bavaria
            [
                'id' => 'augsburger',
                'name' => 'Augsburger Allgemeine',
                'method' => 'rss',
                'url' => 'https://www.augsburger-allgemeine.de/rss/feed.xml',
                'lang' => 'de',
                'geo' => 'Bayern',
                'category' => 'media',
                'trust_score' => 0.80,
                'fetch_interval' => 15,
                'enabled' => 1
            ],
            [
                'id' => 'nordbayern',
                'name' => 'Nordbayern',
                'method' => 'rss',
                'url' => 'https://www.nordbayern.de/rss',
                'lang' => 'de',
                'geo' => 'Bayern',
                'category' => 'media',
                'trust_score' => 0.78,
                'fetch_interval' => 15,
                'enabled' => 1
            ],
        ];
    }

    /**
     * Get source by ID
     */
    public function get_source($id) {
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table('sources')} WHERE id = %s",
                $id
            ),
            ARRAY_A
        );
    }

    /**
     * Get all active sources
     */
    public function get_active_sources() {
        return $this->wpdb->get_results(
            "SELECT * FROM {$this->table('sources')}
             WHERE enabled = 1
             AND (quarantine_until IS NULL OR quarantine_until < NOW())
             ORDER BY fetch_interval ASC, trust_score DESC",
            ARRAY_A
        );
    }

    /**
     * Get sources due for fetching
     */
    public function get_sources_to_fetch($limit = 10) {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table('sources')}
                 WHERE enabled = 1
                 AND (quarantine_until IS NULL OR quarantine_until < NOW())
                 AND (last_fetched_at IS NULL
                      OR TIMESTAMPDIFF(MINUTE, last_fetched_at, NOW()) >= fetch_interval)
                 ORDER BY last_fetched_at ASC
                 LIMIT %d",
                $limit
            ),
            ARRAY_A
        );
    }

    /**
     * Update source fetch time
     */
    public function update_source_fetch_time($source_id) {
        return $this->wpdb->update(
            $this->table('sources'),
            [
                'last_fetched_at' => current_time('mysql'),
                'error_count' => 0,
                'last_error' => null
            ],
            ['id' => $source_id]
        );
    }

    /**
     * Update source last fetched time
     */
    public function update_source_fetched($source_id, $error = null) {
        $data = ['last_fetched_at' => current_time('mysql')];

        if ($error) {
            $data['last_error'] = $error;
            $data['error_count'] = $this->wpdb->get_var(
                $this->wpdb->prepare(
                    "SELECT error_count + 1 FROM {$this->table('sources')} WHERE id = %s",
                    $source_id
                )
            );

            // Quarantine after 5 consecutive errors
            if ($data['error_count'] >= 5) {
                $data['quarantine_until'] = date('Y-m-d H:i:s', strtotime('+30 minutes'));
            }
        } else {
            $data['error_count'] = 0;
            $data['last_error'] = null;
        }

        $this->wpdb->update(
            $this->table('sources'),
            $data,
            ['id' => $source_id]
        );
    }

    /**
     * Insert raw item
     */
    public function insert_raw_item($data) {
        $data['url_hash'] = md5($data['url']);

        // Check for duplicate
        $exists = $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT id FROM {$this->table('raw_items')} WHERE url_hash = %s",
                $data['url_hash']
            )
        );

        if ($exists) {
            return false;
        }

        $this->wpdb->insert($this->table('raw_items'), $data);
        return $this->wpdb->insert_id;
    }

    /**
     * Get raw items by status
     */
    public function get_raw_items_by_status($status, $limit = 10) {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT ri.*, s.name as source_name, s.trust_score
                 FROM {$this->table('raw_items')} ri
                 LEFT JOIN {$this->table('sources')} s ON ri.source_id = s.id
                 WHERE ri.status = %s
                 ORDER BY ri.fetched_at DESC
                 LIMIT %d",
                $status,
                $limit
            ),
            ARRAY_A
        );
    }

    /**
     * Insert draft
     */
    public function insert_draft($data) {
        $this->wpdb->insert($this->table('drafts'), $data);
        return $this->wpdb->insert_id ? $data['id'] : false;
    }

    /**
     * Update draft
     */
    public function update_draft($id, $data) {
        return $this->wpdb->update(
            $this->table('drafts'),
            $data,
            ['id' => $id]
        );
    }

    /**
     * Get draft by ID
     */
    public function get_draft($id) {
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table('drafts')} WHERE id = %s",
                $id
            ),
            ARRAY_A
        );
    }

    /**
     * Get drafts by status
     */
    public function get_drafts_by_status($statuses, $lang = null, $limit = 20, $offset = 0) {
        $status_placeholders = implode(',', array_fill(0, count($statuses), '%s'));

        $query = "SELECT d.*,
                         ri.source_id,
                         s.name as source_name,
                         s.trust_score as source_trust
                  FROM {$this->table('drafts')} d
                  LEFT JOIN {$this->table('raw_items')} ri ON d.raw_item_id = ri.id
                  LEFT JOIN {$this->table('sources')} s ON ri.source_id = s.id
                  WHERE d.status IN ($status_placeholders)";

        $params = $statuses;

        if ($lang) {
            $query .= " AND d.lang = %s";
            $params[] = $lang;
        }

        $query .= " ORDER BY d.created_at DESC LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;

        return $this->wpdb->get_results(
            $this->wpdb->prepare($query, ...$params),
            ARRAY_A
        );
    }

    /**
     * Count drafts by status
     */
    public function count_drafts_by_status($statuses) {
        $status_placeholders = implode(',', array_fill(0, count($statuses), '%s'));

        return $this->wpdb->get_var(
            $this->wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table('drafts')} WHERE status IN ($status_placeholders)",
                ...$statuses
            )
        );
    }

    /**
     * Add to queue
     */
    public function add_to_queue($job_type, $payload, $priority = 10) {
        return $this->wpdb->insert(
            $this->table('queue'),
            [
                'job_type' => $job_type,
                'payload' => json_encode($payload),
                'priority' => $priority,
                'status' => 'pending',
            ]
        );
    }

    /**
     * Get next queue job
     */
    public function get_next_queue_job($job_type = null) {
        $query = "SELECT * FROM {$this->table('queue')}
                  WHERE status = 'pending'
                  AND attempts < max_attempts
                  AND scheduled_at <= NOW()";

        if ($job_type) {
            $query .= $this->wpdb->prepare(" AND job_type = %s", $job_type);
        }

        $query .= " ORDER BY priority ASC, scheduled_at ASC LIMIT 1";

        return $this->wpdb->get_row($query, ARRAY_A);
    }

    /**
     * Update queue job
     */
    public function update_queue_job($id, $data) {
        return $this->wpdb->update(
            $this->table('queue'),
            $data,
            ['id' => $id]
        );
    }

    /**
     * Insert log
     */
    public function insert_log($level, $message, $context = []) {
        return $this->wpdb->insert(
            $this->table('logs'),
            [
                'level' => $level,
                'message' => $message,
                'context' => json_encode($context),
            ]
        );
    }

    /**
     * Get logs
     */
    public function get_logs($level = null, $limit = 100) {
        $query = "SELECT * FROM {$this->table('logs')}";

        if ($level) {
            $query .= $this->wpdb->prepare(" WHERE level = %s", $level);
        }

        $query .= " ORDER BY created_at DESC LIMIT %d";

        return $this->wpdb->get_results(
            $this->wpdb->prepare($query, $limit),
            ARRAY_A
        );
    }

    /**
     * Cleanup old data
     */
    public function cleanup_old_data($days = 30) {
        // Delete old logs
        $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->table('logs')} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            )
        );

        // Delete completed queue jobs
        $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->table('queue')}
                 WHERE status = 'completed' AND completed_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                7
            )
        );

        // Delete old raw items that were processed
        $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->table('raw_items')}
                 WHERE status IN ('processed', 'rejected', 'duplicate')
                 AND fetched_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            )
        );
    }

    /**
     * Get queue items by status
     */
    public function get_queue_items($status, $limit = 10) {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT q.*,
                        JSON_UNQUOTE(JSON_EXTRACT(q.payload, '$.article_id')) as article_id
                 FROM {$this->table('queue')} q
                 WHERE q.status = %s
                 AND q.attempts < q.max_attempts
                 AND q.scheduled_at <= NOW()
                 ORDER BY q.priority ASC, q.scheduled_at ASC
                 LIMIT %d",
                $status,
                $limit
            )
        );
    }

    /**
     * Update queue item status
     */
    public function update_queue_status($id, $status, $error_message = null) {
        $data = [
            'status' => $status,
            'attempts' => $this->wpdb->get_var(
                $this->wpdb->prepare(
                    "SELECT attempts + 1 FROM {$this->table('queue')} WHERE id = %d",
                    $id
                )
            )
        ];

        if ($status === 'processing') {
            $data['started_at'] = current_time('mysql');
        } elseif ($status === 'completed') {
            $data['completed_at'] = current_time('mysql');
        } elseif ($status === 'failed' && $error_message) {
            $data['error_message'] = $error_message;
        }

        return $this->wpdb->update(
            $this->table('queue'),
            $data,
            ['id' => $id]
        );
    }

    /**
     * Get drafts ready for auto-publish
     */
    public function get_drafts_for_auto_publish($delay_minutes = 10, $limit = 5) {
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table('drafts')}
                 WHERE status = 'approved'
                 AND TIMESTAMPDIFF(MINUTE, updated_at, NOW()) >= %d
                 ORDER BY updated_at ASC
                 LIMIT %d",
                $delay_minutes,
                $limit
            )
        );
    }

    /**
     * Cleanup old logs
     */
    public function cleanup_old_logs($days = 30) {
        return $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->table('logs')}
                 WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            )
        );
    }

    /**
     * Cleanup old raw articles
     */
    public function cleanup_old_raw_articles($days = 7) {
        return $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->table('raw_items')}
                 WHERE status IN ('processed', 'rejected', 'duplicate', 'skipped')
                 AND fetched_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            )
        );
    }

    /**
     * Cleanup failed queue items
     */
    public function cleanup_failed_queue_items($days = 3) {
        return $this->wpdb->query(
            $this->wpdb->prepare(
                "DELETE FROM {$this->table('queue')}
                 WHERE status = 'failed'
                 AND scheduled_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                $days
            )
        );
    }

    /**
     * Get statistics for dashboard
     */
    public function get_stats() {
        $stats = [];

        // Count drafts by status
        $draft_counts = $this->wpdb->get_results(
            "SELECT status, COUNT(*) as count
             FROM {$this->table('drafts')}
             GROUP BY status",
            OBJECT_K
        );

        $stats['drafts'] = [
            'pending' => $draft_counts['ai_draft']->count ?? 0,
            'approved' => $draft_counts['approved']->count ?? 0,
            'published' => $draft_counts['published']->count ?? 0,
            'rejected' => $draft_counts['rejected']->count ?? 0,
        ];

        // Count sources
        $stats['sources'] = [
            'total' => $this->wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->table('sources')}"
            ),
            'active' => $this->wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->table('sources')} WHERE enabled = 1"
            ),
        ];

        // Today's articles
        $stats['today'] = [
            'fetched' => $this->wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->table('raw_items')}
                 WHERE DATE(fetched_at) = CURDATE()"
            ),
            'processed' => $this->wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->table('drafts')}
                 WHERE DATE(created_at) = CURDATE()"
            ),
            'published' => $this->wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->table('drafts')}
                 WHERE status = 'published' AND DATE(published_at) = CURDATE()"
            ),
        ];

        // Queue status
        $stats['queue'] = [
            'pending' => $this->wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->table('queue')} WHERE status = 'pending'"
            ),
            'processing' => $this->wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->table('queue')} WHERE status = 'processing'"
            ),
            'failed' => $this->wpdb->get_var(
                "SELECT COUNT(*) FROM {$this->table('queue')} WHERE status = 'failed'"
            ),
        ];

        return $stats;
    }

    /**
     * Get all sources (for admin)
     */
    public function get_all_sources() {
        return $this->wpdb->get_results(
            "SELECT * FROM {$this->table('sources')} ORDER BY name ASC",
            ARRAY_A
        );
    }

    /**
     * Update source
     */
    public function update_source($id, $data) {
        return $this->wpdb->update(
            $this->table('sources'),
            $data,
            ['id' => $id]
        );
    }

    /**
     * Delete source
     */
    public function delete_source($id) {
        return $this->wpdb->delete(
            $this->table('sources'),
            ['id' => $id]
        );
    }

    /**
     * Insert new source
     */
    public function insert_source($data) {
        return $this->wpdb->insert(
            $this->table('sources'),
            $data
        );
    }
}
