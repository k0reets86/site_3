<?php
/**
 * Content Processor
 * Handles AI-powered content generation, rewriting, translation, and SEO
 */

if (!defined('ABSPATH')) {
    exit;
}

class AINCC_Content_Processor {

    /**
     * Database instance
     */
    private $db;

    /**
     * AI Provider instance
     */
    private $ai;

    /**
     * Constructor
     */
    public function __construct() {
        $this->db = new AINCC_Database();
        $this->ai = AINCC_AI_Provider_Factory::create();

        // Register cron hook
        add_action('aincc_process_queue', [$this, 'process_queue']);
    }

    /**
     * Process items in queue
     */
    public function process_queue() {
        AINCC_Logger::info('Starting content processing queue');

        $max_items = 5; // Process max 5 items per cycle
        $processed = 0;

        while ($processed < $max_items) {
            $job = $this->db->get_next_queue_job('process_item');

            if (!$job) {
                break;
            }

            // Mark as processing
            $this->db->update_queue_job($job['id'], [
                'status' => 'processing',
                'started_at' => current_time('mysql'),
                'attempts' => $job['attempts'] + 1,
            ]);

            try {
                $payload = json_decode($job['payload'], true);
                $result = $this->process_item($payload['raw_item_id']);

                $this->db->update_queue_job($job['id'], [
                    'status' => 'completed',
                    'completed_at' => current_time('mysql'),
                ]);

                AINCC_Logger::info("Processed item {$payload['raw_item_id']}", $result);

            } catch (Exception $e) {
                AINCC_Logger::error("Processing failed for item", [
                    'job_id' => $job['id'],
                    'error' => $e->getMessage(),
                ]);

                $this->db->update_queue_job($job['id'], [
                    'status' => $job['attempts'] >= $job['max_attempts'] ? 'failed' : 'pending',
                    'error_message' => $e->getMessage(),
                ]);
            }

            $processed++;
        }

        AINCC_Logger::info("Queue processing complete", ['processed' => $processed]);
    }

    /**
     * Process a single raw item
     */
    public function process_item($raw_item_id) {
        global $wpdb;

        // Get raw item with source info
        $item = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT ri.*, s.name as source_name, s.trust_score, s.category as source_category
                 FROM {$this->db->table('raw_items')} ri
                 LEFT JOIN {$this->db->table('sources')} s ON ri.source_id = s.id
                 WHERE ri.id = %d",
                $raw_item_id
            ),
            ARRAY_A
        );

        if (!$item) {
            throw new Exception("Raw item not found: {$raw_item_id}");
        }

        // Update status to processing
        $wpdb->update(
            $this->db->table('raw_items'),
            ['status' => 'processing'],
            ['id' => $raw_item_id]
        );

        // Step 1: Extract entities and classify
        $analysis = $this->analyze_content($item);

        // Update raw item with analysis
        $wpdb->update(
            $this->db->table('raw_items'),
            [
                'entities' => json_encode($analysis['entities']),
                'keywords' => json_encode($analysis['keywords']),
            ],
            ['id' => $raw_item_id]
        );

        // Step 2: Check for duplicates (content fingerprint)
        if ($this->is_duplicate($item, $analysis)) {
            $wpdb->update(
                $this->db->table('raw_items'),
                ['status' => 'duplicate'],
                ['id' => $raw_item_id]
            );
            return ['status' => 'duplicate'];
        }

        // Step 3: Simple fact check (cross-reference)
        $fact_check = $this->simple_fact_check($item, $analysis);

        // Step 4: Generate content for each target language
        $target_languages = AINCC_Settings::get('target_languages', ['de', 'ua', 'ru', 'en']);
        $source_lang = $item['lang'] ?: 'de';

        $drafts = [];
        foreach ($target_languages as $lang) {
            $draft = $this->generate_draft($item, $analysis, $source_lang, $lang, $fact_check);
            if ($draft) {
                $drafts[$lang] = $draft;
            }
        }

        // Step 5: Update raw item status
        $wpdb->update(
            $this->db->table('raw_items'),
            [
                'status' => 'processed',
                'fact_check_score' => $fact_check['score'],
            ],
            ['id' => $raw_item_id]
        );

        return [
            'status' => 'success',
            'drafts' => count($drafts),
            'languages' => array_keys($drafts),
            'fact_check_score' => $fact_check['score'],
        ];
    }

    /**
     * Analyze content (entities, keywords, classification)
     */
    private function analyze_content($item) {
        $content = $item['title'] . "\n\n" . ($item['body_html'] ?: $item['summary']);

        // Use AI to extract entities
        $result = $this->ai->extract_entities($content);

        if (!$result['success']) {
            AINCC_Logger::warning("Entity extraction failed, using fallback", [
                'item_id' => $item['id'],
            ]);

            // Fallback: basic keyword extraction
            return [
                'entities' => [
                    'persons' => [],
                    'organizations' => [],
                    'locations' => [],
                    'dates' => [],
                ],
                'keywords' => $this->extract_keywords_simple($content),
                'category' => $this->guess_category($content),
                'sentiment' => 0,
                'geo' => [],
            ];
        }

        return $result;
    }

    /**
     * Simple keyword extraction fallback
     */
    private function extract_keywords_simple($content) {
        // Remove HTML
        $text = wp_strip_all_tags($content);

        // Get words
        $words = str_word_count(strtolower($text), 1, 'äöüßÄÖÜ');

        // Filter stop words (basic German stop words)
        $stop_words = ['der', 'die', 'das', 'und', 'ist', 'in', 'von', 'mit', 'für',
            'auf', 'den', 'des', 'dem', 'ein', 'eine', 'einer', 'als', 'auch',
            'es', 'an', 'werden', 'aus', 'er', 'hat', 'dass', 'sie', 'nach',
            'wird', 'bei', 'einer', 'um', 'am', 'sind', 'noch', 'wie', 'einem',
            'über', 'einen', 'so', 'zum', 'kann', 'nur', 'sein', 'ich', 'nicht',
            'the', 'and', 'is', 'in', 'to', 'of', 'for', 'a', 'on', 'with'];

        $filtered = array_filter($words, function ($word) use ($stop_words) {
            return strlen($word) > 3 && !in_array($word, $stop_words);
        });

        // Count frequency
        $freq = array_count_values($filtered);
        arsort($freq);

        // Return top 10
        return array_slice(array_keys($freq), 0, 10);
    }

    /**
     * Guess category from content
     */
    private function guess_category($content) {
        $content_lower = strtolower($content);

        $category_keywords = [
            'politik' => ['bundestag', 'regierung', 'minister', 'partei', 'wahl', 'gesetz', 'politik'],
            'wirtschaft' => ['wirtschaft', 'unternehmen', 'aktie', 'börse', 'euro', 'inflation', 'arbeitsmarkt'],
            'migration' => ['aufenthaltstitel', 'bamf', 'flüchtling', 'asyl', 'integration', 'migration', 'ukrainer'],
            'gesellschaft' => ['bildung', 'schule', 'universität', 'kultur', 'sozial', 'gesellschaft'],
            'verkehr' => ['mvg', 'bahn', 'verkehr', 'stau', 'bus', 'u-bahn', 's-bahn', 'fahrplan'],
            'lokales' => ['münchen', 'bayern', 'rathaus', 'stadt', 'bezirk', 'gemeinde'],
            'wetter' => ['wetter', 'unwetter', 'warnung', 'sturm', 'regen', 'temperatur'],
        ];

        $scores = [];
        foreach ($category_keywords as $category => $keywords) {
            $score = 0;
            foreach ($keywords as $keyword) {
                $score += substr_count($content_lower, $keyword);
            }
            $scores[$category] = $score;
        }

        arsort($scores);
        $top = array_keys($scores)[0];

        return $scores[$top] > 0 ? $top : 'nachrichten';
    }

    /**
     * Check for duplicate content
     */
    private function is_duplicate($item, $analysis) {
        global $wpdb;

        // Check by title similarity in recent items (last 72 hours)
        $similar = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->db->table('raw_items')}
                 WHERE id != %d
                 AND fetched_at > DATE_SUB(NOW(), INTERVAL 72 HOUR)
                 AND (
                     title = %s
                     OR url = %s
                 )",
                $item['id'],
                $item['title'],
                $item['url']
            )
        );

        return $similar > 0;
    }

    /**
     * Simple fact check (cross-reference sources)
     */
    private function simple_fact_check($item, $analysis) {
        global $wpdb;

        $score = 0.5; // Base score
        $confirmations = 0;
        $sources_checked = 0;

        // Check if similar content exists from other sources
        $keywords = $analysis['keywords'] ?? [];
        $title_words = array_slice(explode(' ', $item['title']), 0, 5);

        if (!empty($keywords)) {
            // Search for similar items from different sources
            $keyword_pattern = implode('|', array_slice($keywords, 0, 3));

            $similar_items = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT ri.source_id, s.trust_score
                     FROM {$this->db->table('raw_items')} ri
                     JOIN {$this->db->table('sources')} s ON ri.source_id = s.id
                     WHERE ri.id != %d
                     AND ri.source_id != %s
                     AND ri.fetched_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                     AND (ri.title REGEXP %s OR ri.summary REGEXP %s)
                     GROUP BY ri.source_id",
                    $item['id'],
                    $item['source_id'],
                    $keyword_pattern,
                    $keyword_pattern
                ),
                ARRAY_A
            );

            $sources_checked = count($similar_items);
            foreach ($similar_items as $similar) {
                $confirmations++;
                // Add weighted score based on source trust
                $score += ($similar['trust_score'] * 0.1);
            }
        }

        // Factor in source trust
        $source_trust = $item['trust_score'] ?? 0.5;
        $score = ($score * 0.6) + ($source_trust * 0.4);

        // Normalize score
        $score = min(1.0, max(0.0, $score));

        // Store fact check result
        $wpdb->insert(
            $this->db->table('fact_checks'),
            [
                'raw_item_id' => $item['id'],
                'claims' => json_encode([]),
                'score' => $score,
                'sources_confirmed' => $confirmations,
            ]
        );

        return [
            'score' => round($score, 2),
            'confirmations' => $confirmations,
            'sources_checked' => $sources_checked,
        ];
    }

    /**
     * Generate draft for a specific language
     */
    private function generate_draft($item, $analysis, $source_lang, $target_lang, $fact_check) {
        $draft_id = 'draft_' . uniqid() . '_' . $target_lang;

        // Prepare source info
        $sources = [
            [
                'name' => $item['source_name'],
                'url' => $item['url'],
                'date' => date('d.m.Y', strtotime($item['published_at'])),
                'title' => $item['title'],
                'summary' => substr($item['summary'], 0, 500),
            ],
        ];

        // Step 1: Rewrite content (or translate if not source language)
        $content = $item['body_html'] ?: $item['summary'];

        // Style prompts for each language
        $style_prompts = [
            'de' => 'Professioneller Nachrichtenartikel. Fakten, Klarheit, keine Emotionen. Stil wie Deutsche Welle. Für ukrainische Zielgruppe in Deutschland. Erkläre deutsche Begriffe (BAMF, Jobcenter) kurz.',
            'ua' => 'Професійна новинна стаття. Факти, ясність, без емоцій. Стиль Deutsche Welle. Для української аудиторії в Німеччині. Зберігай німецькі терміни (BAMF, Jobcenter) з поясненнями.',
            'ru' => 'Профессиональная новостная статья. Факты, ясность, без эмоций. Стиль Deutsche Welle. Для украинской аудитории в Германии. Сохраняй немецкие термины (BAMF, Jobcenter) с пояснениями.',
            'en' => 'Professional news article. Facts, clarity, no emotions. Deutsche Welle style. For Ukrainian audience in Germany. Keep German terms (BAMF, Jobcenter) with brief explanations.',
        ];

        $style = $style_prompts[$target_lang] ?? $style_prompts['de'];

        if ($target_lang === $source_lang) {
            // Rewrite in same language
            $rewritten = $this->ai->rewrite($content, $style, $target_lang);
        } else {
            // First rewrite in source language, then translate
            $source_style = $style_prompts[$source_lang] ?? $style_prompts['de'];
            $rewritten = $this->ai->rewrite($content, $source_style, $source_lang);

            if ($rewritten['success']) {
                $rewritten = $this->ai->translate($rewritten['content'], $source_lang, $target_lang);
            }
        }

        if (!$rewritten['success']) {
            AINCC_Logger::warning("Content generation failed", [
                'item_id' => $item['id'],
                'language' => $target_lang,
                'error' => $rewritten['error'] ?? 'Unknown',
            ]);
            return null;
        }

        // Parse generated content
        $parsed = $this->parse_generated_content($rewritten['content'], $target_lang);

        // Step 2: Generate SEO
        $seo = $this->ai->generate_seo($rewritten['content'], $target_lang);

        // Step 3: Determine status (auto or needs approval)
        $status = $this->determine_draft_status($item, $analysis, $fact_check);

        // Step 4: Save draft
        $draft_data = [
            'id' => $draft_id,
            'event_id' => $item['event_id'],
            'raw_item_id' => $item['id'],
            'lang' => $target_lang,
            'title' => $parsed['title'] ?: $item['title'],
            'lead' => $parsed['lead'],
            'body_html' => $parsed['body'],
            'sources' => json_encode($sources),
            'structured_data' => json_encode([
                'what' => $parsed['sections']['what'] ?? '',
                'why' => $parsed['sections']['why'] ?? '',
                'action' => $parsed['sections']['action'] ?? '',
            ]),
            'sentiment' => $analysis['sentiment'] ?? 0,
            'category' => $analysis['category'] ?? 'nachrichten',
            'tags' => json_encode($analysis['keywords'] ?? []),
            'geo_tags' => json_encode($analysis['geo'] ?? []),
            'risk_flags' => json_encode($this->get_risk_flags($item, $analysis)),
            'seo_title' => $seo['success'] ? $seo['title'] : $parsed['title'],
            'meta_description' => $seo['success'] ? $seo['description'] : $parsed['lead'],
            'slug' => $seo['success'] ? $seo['slug'] : sanitize_title($parsed['title']),
            'keywords' => $seo['success'] ? json_encode($seo['keywords']) : json_encode($analysis['keywords']),
            'status' => $status,
            'gate_reason' => $status === 'pending_ok' ? $this->get_gate_reason($item, $analysis, $fact_check) : null,
        ];

        $this->db->insert_draft($draft_data);

        return $draft_data;
    }

    /**
     * Parse AI-generated content
     * Handles both HTML format (<title>/<lead>/<body>) and JSON format
     */
    private function parse_generated_content($content, $language) {
        $result = [
            'title' => '',
            'lead' => '',
            'body' => '',
            'sections' => [],
        ];

        // First, check if content is JSON format
        $trimmed = trim($content);

        // Try to detect and parse JSON response
        if ((strpos($trimmed, '{') === 0 || strpos($trimmed, '```json') !== false || strpos($trimmed, '```') !== false)) {
            // Extract JSON from markdown code blocks if present
            if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $trimmed, $jsonMatches)) {
                $jsonStr = trim($jsonMatches[1]);
            } else {
                // Try to find JSON object
                if (preg_match('/\{[\s\S]*\}/', $trimmed, $jsonMatches)) {
                    $jsonStr = $jsonMatches[0];
                } else {
                    $jsonStr = $trimmed;
                }
            }

            $parsed = json_decode($jsonStr, true);

            if ($parsed && is_array($parsed)) {
                AINCC_Logger::debug('Parsed AI response as JSON', ['keys' => array_keys($parsed)]);

                // Extract title - try various possible keys
                $titleKeys = ['title', 'headline', 'заголовок', 'заголовок_статті', 'Заголовок'];
                foreach ($titleKeys as $key) {
                    if (!empty($parsed[$key])) {
                        $result['title'] = $this->clean_text($parsed[$key]);
                        break;
                    }
                }

                // Extract lead/excerpt - try various possible keys
                $leadKeys = ['lead', 'dek', 'excerpt', 'summary', 'description', 'отрывок', 'лід', 'вступ', 'введение', 'Лид', 'Отрывок'];
                foreach ($leadKeys as $key) {
                    if (!empty($parsed[$key])) {
                        $result['lead'] = $this->clean_text($parsed[$key]);
                        break;
                    }
                }

                // Extract body - try various possible keys
                $bodyKeys = ['body', 'body_html', 'content', 'text', 'article', 'тело', 'текст', 'содержание', 'зміст', 'Текст'];
                foreach ($bodyKeys as $key) {
                    if (!empty($parsed[$key])) {
                        $result['body'] = $this->clean_html($parsed[$key]);
                        break;
                    }
                }

                // If body is still empty but we have other content, build it from available data
                if (empty($result['body'])) {
                    // Try to build body from paragraphs array
                    if (!empty($parsed['paragraphs']) && is_array($parsed['paragraphs'])) {
                        $result['body'] = '<p>' . implode('</p><p>', array_map([$this, 'clean_text'], $parsed['paragraphs'])) . '</p>';
                    }
                    // Or from sections
                    elseif (!empty($parsed['sections']) && is_array($parsed['sections'])) {
                        $bodyParts = [];
                        foreach ($parsed['sections'] as $section) {
                            if (is_string($section)) {
                                $bodyParts[] = '<p>' . $this->clean_text($section) . '</p>';
                            } elseif (is_array($section)) {
                                if (!empty($section['title'])) {
                                    $bodyParts[] = '<h2>' . $this->clean_text($section['title']) . '</h2>';
                                }
                                if (!empty($section['content'])) {
                                    $bodyParts[] = '<p>' . $this->clean_text($section['content']) . '</p>';
                                }
                            }
                        }
                        $result['body'] = implode("\n", $bodyParts);
                    }
                }

                // Extract sections if available
                if (!empty($parsed['what'])) {
                    $result['sections']['what'] = $this->clean_text($parsed['what']);
                }
                if (!empty($parsed['why'])) {
                    $result['sections']['why'] = $this->clean_text($parsed['why']);
                }
                if (!empty($parsed['action'])) {
                    $result['sections']['action'] = $this->clean_text($parsed['action']);
                }

                // If we successfully parsed JSON, return the result
                if (!empty($result['title']) || !empty($result['body'])) {
                    AINCC_Logger::debug('Successfully parsed JSON content', [
                        'has_title' => !empty($result['title']),
                        'has_lead' => !empty($result['lead']),
                        'has_body' => !empty($result['body']),
                    ]);
                    return $result;
                }
            }
        }

        // Fall back to HTML/XML tag parsing
        $workingContent = $content;

        // Try to extract title
        if (preg_match('/<title>(.*?)<\/title>/is', $workingContent, $matches)) {
            $result['title'] = $this->clean_text($matches[1]);
            $workingContent = str_replace($matches[0], '', $workingContent);
        }

        // Try to extract lead
        if (preg_match('/<lead>(.*?)<\/lead>/is', $workingContent, $matches)) {
            $result['lead'] = $this->clean_text($matches[1]);
            $workingContent = str_replace($matches[0], '', $workingContent);
        }

        // Try to extract body tag
        if (preg_match('/<body>(.*?)<\/body>/is', $workingContent, $matches)) {
            $result['body'] = $this->clean_html($matches[1]);
            $workingContent = str_replace($matches[0], '', $workingContent);
        }

        // Extract sections
        if (preg_match('/<section[^>]*id="what"[^>]*>(.*?)<\/section>/is', $workingContent, $matches)) {
            $result['sections']['what'] = trim($matches[1]);
        }
        if (preg_match('/<section[^>]*id="why"[^>]*>(.*?)<\/section>/is', $workingContent, $matches)) {
            $result['sections']['why'] = trim($matches[1]);
        }
        if (preg_match('/<section[^>]*id="action"[^>]*>(.*?)<\/section>/is', $workingContent, $matches)) {
            $result['sections']['action'] = trim($matches[1]);
        }

        // If we still don't have body, use the remaining content
        if (empty($result['body'])) {
            $result['body'] = $this->clean_html($workingContent);
        }

        // If no title was extracted, try to get first line or heading
        if (empty($result['title'])) {
            // Try h1
            if (preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $content, $matches)) {
                $result['title'] = $this->clean_text($matches[1]);
            } else {
                // Get first non-empty line
                $lines = explode("\n", strip_tags($content));
                foreach ($lines as $line) {
                    $line = trim($line);
                    // Skip JSON-like lines
                    if (!empty($line) && strlen($line) < 150 && strpos($line, '{') !== 0 && strpos($line, '"') !== 0) {
                        $result['title'] = $line;
                        break;
                    }
                }
            }
        }

        // If no lead, get first paragraph
        if (empty($result['lead'])) {
            if (preg_match('/<p[^>]*>(.*?)<\/p>/is', $result['body'], $matches)) {
                $result['lead'] = $this->clean_text($matches[1]);
            }
        }

        return $result;
    }

    /**
     * Clean text content - remove HTML, extra whitespace, quotes
     */
    private function clean_text($text) {
        if (!is_string($text)) {
            return '';
        }
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }

    /**
     * Clean HTML content - allow safe tags, normalize whitespace
     */
    private function clean_html($html) {
        if (!is_string($html)) {
            return '';
        }

        // Decode entities first
        $html = html_entity_decode($html, ENT_QUOTES, 'UTF-8');

        // Convert escaped newlines to actual newlines
        $html = str_replace(['\\n', '\n'], "\n", $html);

        // If there are no HTML tags, wrap paragraphs
        if (strip_tags($html) === $html) {
            // Split by double newlines for paragraphs
            $paragraphs = preg_split('/\n\s*\n/', $html);
            $paragraphs = array_filter(array_map('trim', $paragraphs));
            if (count($paragraphs) > 1) {
                $html = '<p>' . implode('</p><p>', $paragraphs) . '</p>';
            } elseif (count($paragraphs) === 1) {
                // Single paragraph
                $lines = explode("\n", trim($paragraphs[0]));
                if (count($lines) > 1) {
                    $html = '<p>' . implode('</p><p>', array_filter(array_map('trim', $lines))) . '</p>';
                } else {
                    $html = '<p>' . trim($paragraphs[0]) . '</p>';
                }
            }
        }

        // Allow safe HTML tags
        $html = wp_kses($html, [
            'p' => [],
            'br' => [],
            'strong' => [],
            'b' => [],
            'em' => [],
            'i' => [],
            'u' => [],
            'h1' => [],
            'h2' => [],
            'h3' => [],
            'h4' => [],
            'ul' => [],
            'ol' => [],
            'li' => [],
            'a' => ['href' => [], 'target' => [], 'rel' => []],
            'blockquote' => [],
        ]);

        return trim($html);
    }

    /**
     * Determine draft status
     */
    private function determine_draft_status($item, $analysis, $fact_check) {
        // Categories requiring approval
        $approval_categories = AINCC_Settings::get('categories_require_approval', ['politik', 'wirtschaft']);

        // Check if category requires approval
        if (in_array($analysis['category'], $approval_categories)) {
            return 'pending_ok';
        }

        // Low fact check score
        if ($fact_check['score'] < AINCC_Settings::get('fact_check_threshold', 0.6)) {
            return 'pending_ok';
        }

        // Low source trust
        if (($item['trust_score'] ?? 0.5) < AINCC_Settings::get('source_trust_threshold', 0.7)) {
            return 'pending_ok';
        }

        // Check for sensitive keywords
        $sensitive_keywords = ['krieg', 'konflikt', 'tod', 'unfall', 'krise', 'skandal'];
        $content_lower = strtolower($item['title'] . ' ' . $item['summary']);
        foreach ($sensitive_keywords as $keyword) {
            if (strpos($content_lower, $keyword) !== false) {
                return 'pending_ok';
            }
        }

        // Auto-publish enabled?
        if (!AINCC_Settings::get('auto_publish_enabled', false)) {
            return 'pending_ok';
        }

        return 'auto_ready';
    }

    /**
     * Get risk flags for content
     */
    private function get_risk_flags($item, $analysis) {
        $flags = [];

        // Category-based flags
        if (in_array($analysis['category'], ['politik', 'wirtschaft'])) {
            $flags[] = $analysis['category'];
            $flags[] = 'sensitive';
        }

        // Low trust source
        if (($item['trust_score'] ?? 0.5) < 0.7) {
            $flags[] = 'low_trust_source';
        }

        // Sensitive content keywords
        $sensitive_keywords = ['krieg', 'konflikt', 'tod', 'unfall', 'krise', 'skandal', 'korruption'];
        $content_lower = strtolower($item['title'] . ' ' . $item['summary']);
        foreach ($sensitive_keywords as $keyword) {
            if (strpos($content_lower, $keyword) !== false) {
                $flags[] = 'sensitive_content';
                break;
            }
        }

        return array_unique($flags);
    }

    /**
     * Get reason for requiring approval
     */
    private function get_gate_reason($item, $analysis, $fact_check) {
        $reasons = [];

        if (in_array($analysis['category'], ['politik', 'wirtschaft'])) {
            $reasons[] = 'category_requires_review';
        }

        if ($fact_check['score'] < 0.6) {
            $reasons[] = 'low_fact_check_score';
        }

        if (($item['trust_score'] ?? 0.5) < 0.7) {
            $reasons[] = 'low_source_trust';
        }

        return implode(', ', $reasons) ?: 'manual_review';
    }

    /**
     * Process manual article submission
     */
    public function process_manual_article($data) {
        $draft_id_base = 'manual_' . uniqid();
        $source_lang = $data['source_lang'] ?? 'de';
        $target_langs = $data['target_langs'] ?? ['de', 'ua', 'ru', 'en'];

        $drafts = [];

        // First create draft in source language
        $source_draft_id = $draft_id_base . '_' . $source_lang;

        // Generate SEO for source language
        $content = $data['title'] . "\n\n" . $data['lead'] . "\n\n" . $data['body'];
        $seo = $this->ai->generate_seo($content, $source_lang);

        // Extract entities/keywords
        $analysis = $this->ai->extract_entities($content);

        $source_draft = [
            'id' => $source_draft_id,
            'event_id' => null,
            'raw_item_id' => null,
            'lang' => $source_lang,
            'title' => $data['title'],
            'lead' => $data['lead'],
            'body_html' => $data['body'],
            'sources' => json_encode($data['sources'] ?? []),
            'category' => $data['category'] ?? 'nachrichten',
            'tags' => json_encode($data['tags'] ?? []),
            'geo_tags' => json_encode($data['geo'] ?? []),
            'seo_title' => $seo['success'] ? $seo['title'] : substr($data['title'], 0, 60),
            'meta_description' => $seo['success'] ? $seo['description'] : substr($data['lead'], 0, 155),
            'slug' => $seo['success'] ? $seo['slug'] : sanitize_title($data['title']),
            'keywords' => $seo['success'] ? json_encode($seo['keywords']) : json_encode($analysis['keywords'] ?? []),
            'status' => 'pending_ok',
            'gate_reason' => 'manual_submission',
            'created_by' => get_current_user_id(),
        ];

        $this->db->insert_draft($source_draft);
        $drafts[$source_lang] = $source_draft_id;

        // Auto-assign image to source language draft
        $image_result = null;
        try {
            $image_handler = new AINCC_Image_Handler();
            $keywords = $seo['success'] && !empty($seo['keywords']) ? $seo['keywords'] : ($analysis['keywords'] ?? []);
            // If no keywords from SEO, extract from title
            if (empty($keywords)) {
                $title_words = explode(' ', $data['title']);
                $keywords = array_filter($title_words, function($w) {
                    return strlen($w) > 4;
                });
                $keywords = array_slice(array_values($keywords), 0, 5);
            }
            $image_result = $image_handler->assign_to_draft($source_draft_id);
            if ($image_result && $image_result['success']) {
                AINCC_Logger::info('Image assigned to draft', ['draft_id' => $source_draft_id, 'image' => $image_result['image']['url'] ?? 'unknown']);
            }
        } catch (Exception $e) {
            AINCC_Logger::warning('Auto-assign image failed', ['error' => $e->getMessage()]);
        }

        // Translate to other languages
        foreach ($target_langs as $target_lang) {
            if ($target_lang === $source_lang) {
                continue;
            }

            $translated_title = $this->ai->translate($data['title'], $source_lang, $target_lang);
            $translated_lead = $this->ai->translate($data['lead'], $source_lang, $target_lang);
            $translated_body = $this->ai->translate($data['body'], $source_lang, $target_lang);

            if (!$translated_title['success'] || !$translated_body['success']) {
                AINCC_Logger::warning("Translation failed for {$target_lang}");
                continue;
            }

            $target_draft_id = $draft_id_base . '_' . $target_lang;

            // Generate SEO for target language
            $target_content = $translated_title['content'] . "\n\n" . ($translated_lead['content'] ?? '') . "\n\n" . $translated_body['content'];
            $target_seo = $this->ai->generate_seo($target_content, $target_lang);

            // Get source draft to copy image data
            $source_draft_for_image = $this->db->get_draft($source_draft_id);

            $target_draft = [
                'id' => $target_draft_id,
                'event_id' => null,
                'raw_item_id' => null,
                'lang' => $target_lang,
                'title' => trim($translated_title['content']),
                'lead' => trim($translated_lead['content'] ?? ''),
                'body_html' => $translated_body['content'],
                'sources' => json_encode($data['sources'] ?? []),
                'category' => $data['category'] ?? 'nachrichten',
                'tags' => json_encode($data['tags'] ?? []),
                'geo_tags' => json_encode($data['geo'] ?? []),
                'seo_title' => $target_seo['success'] ? $target_seo['title'] : substr($translated_title['content'], 0, 60),
                'meta_description' => $target_seo['success'] ? $target_seo['description'] : substr($translated_lead['content'] ?? '', 0, 155),
                'slug' => $target_seo['success'] ? $target_seo['slug'] : sanitize_title($translated_title['content']),
                'keywords' => $target_seo['success'] ? json_encode($target_seo['keywords']) : json_encode([]),
                'status' => 'pending_ok',
                'gate_reason' => 'manual_submission',
                'created_by' => get_current_user_id(),
                // Copy image from source draft
                'image_url' => $source_draft_for_image['image_url'] ?? null,
                'image_alt' => $source_draft_for_image['image_alt'] ?? null,
                'image_author' => $source_draft_for_image['image_author'] ?? null,
                'image_license' => $source_draft_for_image['image_license'] ?? null,
                'image_local_id' => $source_draft_for_image['image_local_id'] ?? null,
            ];

            $this->db->insert_draft($target_draft);
            $drafts[$target_lang] = $target_draft_id;
        }

        return [
            'success' => true,
            'draft_id' => $draft_id_base,
            'drafts' => $drafts,
        ];
    }

    /**
     * Process article from URL
     */
    public function process_article_from_url($url, $source_lang = 'de', $target_langs = ['de', 'ua', 'ru', 'en'], $category = 'nachrichten') {
        // Fetch content from URL using improved method
        $response = wp_remote_get($url, [
            'timeout' => 30,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'de-DE,de;q=0.9,en;q=0.8',
            ],
        ]);

        if (is_wp_error($response)) {
            AINCC_Logger::error('URL fetch failed', ['url' => $url, 'error' => $response->get_error_message()]);
            return [
                'success' => false,
                'error' => 'Не удалось загрузить страницу: ' . $response->get_error_message(),
            ];
        }

        $html = wp_remote_retrieve_body($response);

        if (empty($html)) {
            return [
                'success' => false,
                'error' => 'Пустой ответ от сервера',
            ];
        }

        // Extract title using multiple methods
        $title = '';

        // Try og:title first (most reliable)
        if (preg_match('/property=["\']og:title["\'][^>]*content=["\']([^"\']+)/i', $html, $m)) {
            $title = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
        }
        // Try meta title
        if (empty($title) && preg_match('/name=["\']title["\'][^>]*content=["\']([^"\']+)/i', $html, $m)) {
            $title = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
        }
        // Try <h1>
        if (empty($title) && preg_match('/<h1[^>]*>([^<]+)<\/h1>/i', $html, $m)) {
            $title = html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8');
        }
        // Try <title>
        if (empty($title) && preg_match('/<title[^>]*>([^<]+)<\/title>/i', $html, $m)) {
            $title = html_entity_decode(trim($m[1]), ENT_QUOTES, 'UTF-8');
            // Remove site name from title
            $title = preg_replace('/\s*[\|\-–—]\s*[^|\-–—]+$/', '', $title);
        }

        // Get description/lead
        $lead = '';
        if (preg_match('/property=["\']og:description["\'][^>]*content=["\']([^"\']+)/i', $html, $m)) {
            $lead = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
        } elseif (preg_match('/name=["\']description["\'][^>]*content=["\']([^"\']+)/i', $html, $m)) {
            $lead = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
        }

        // Extract main content
        $body = '';
        $patterns = [
            '/<article[^>]*>(.*?)<\/article>/is',
            '/<div[^>]*class="[^"]*article[-_]?body[^"]*"[^>]*>(.*?)<\/div>/is',
            '/<div[^>]*class="[^"]*entry[-_]?content[^"]*"[^>]*>(.*?)<\/div>/is',
            '/<div[^>]*class="[^"]*post[-_]?content[^"]*"[^>]*>(.*?)<\/div>/is',
            '/<main[^>]*>(.*?)<\/main>/is',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $m)) {
                $body = $m[1];
                break;
            }
        }

        // Clean body
        if ($body) {
            $body = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $body);
            $body = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $body);
            $body = preg_replace('/<nav[^>]*>.*?<\/nav>/is', '', $body);
            $body = preg_replace('/<aside[^>]*>.*?<\/aside>/is', '', $body);
            $body = preg_replace('/<footer[^>]*>.*?<\/footer>/is', '', $body);
            $body = preg_replace('/<div[^>]*class="[^"]*(?:share|social|comment|related|sidebar)[^"]*"[^>]*>.*?<\/div>/is', '', $body);
        }

        $body_text = wp_strip_all_tags($body ?: $html);
        $body_text = preg_replace('/\s+/', ' ', $body_text);
        $body_text = trim($body_text);

        // If no title found, try to generate one from URL or content
        if (empty($title)) {
            // Try to get from URL path
            $path = parse_url($url, PHP_URL_PATH);
            if ($path) {
                $slug = basename($path);
                $slug = preg_replace('/\.(html?|php|aspx?)$/i', '', $slug);
                $slug = str_replace(['-', '_'], ' ', $slug);
                $title = ucfirst($slug);
            }

            // If still empty, use first sentence of content
            if (empty($title) && $body_text) {
                $sentences = preg_split('/[.!?]+/', $body_text, 2);
                if (!empty($sentences[0])) {
                    $title = trim(substr($sentences[0], 0, 100));
                }
            }
        }

        // Final fallback - generate title from URL domain
        if (empty($title)) {
            $host = parse_url($url, PHP_URL_HOST);
            $title = "Новость с " . $host;
        }

        // Ensure we have some body content
        if (empty($body_text) || strlen($body_text) < 100) {
            return [
                'success' => false,
                'error' => 'Не удалось извлечь содержимое статьи. Страница может быть защищена или иметь нестандартную структуру.',
            ];
        }

        // Get lead from body if not found
        if (empty($lead)) {
            $lead = substr($body_text, 0, 200);
            if (strlen($body_text) > 200) {
                $lead .= '...';
            }
        }

        // Use AI to rewrite/improve the article
        $full_content = $title . "\n\n" . $lead . "\n\n" . ($body ?: $body_text);

        // Style prompts for each language
        $style_prompts = [
            'de' => 'Professioneller Nachrichtenartikel. Fakten, Klarheit, keine Emotionen. Stil wie Deutsche Welle. Für ukrainische Zielgruppe in Deutschland.',
            'ua' => 'Професійна новинна стаття. Факти, ясність, без емоцій. Стиль Deutsche Welle. Для української аудиторії в Німеччині.',
            'ru' => 'Профессиональная новостная статья. Факты, ясность, без эмоций. Стиль Deutsche Welle. Для украинской аудитории в Германии.',
            'en' => 'Professional news article. Facts, clarity, no emotions. Deutsche Welle style. For Ukrainian audience in Germany.',
        ];

        $style = $style_prompts[$source_lang] ?? $style_prompts['de'];

        try {
            $rewritten = $this->ai->rewrite(
                $full_content,
                $style,
                $source_lang
            );

            if ($rewritten['success']) {
                $parsed = $this->parse_generated_content($rewritten['content'], $source_lang);
                if (!empty($parsed['title'])) {
                    $title = $parsed['title'];
                }
                if (!empty($parsed['lead'])) {
                    $lead = $parsed['lead'];
                }
                if (!empty($parsed['body'])) {
                    $body = $parsed['body'];
                }
            }
        } catch (Exception $e) {
            AINCC_Logger::warning('AI rewrite failed, using extracted content', ['error' => $e->getMessage()]);
        }

        // Search for additional sources on the same topic
        $additional_sources = $this->find_related_sources($title, $lead);

        // Build sources array - original source first, then additional
        $sources = [
            [
                'name' => parse_url($url, PHP_URL_HOST),
                'url' => $url,
                'date' => date('d.m.Y'),
                'is_primary' => true,
            ]
        ];

        // Add additional sources (max 3)
        foreach (array_slice($additional_sources, 0, 3) as $add_source) {
            $sources[] = $add_source;
        }

        // Create article data for processing
        $article_data = [
            'title' => $title,
            'lead' => $lead,
            'body' => $body ?: $body_text,
            'source_lang' => $source_lang,
            'target_langs' => $target_langs,
            'category' => $category,
            'sources' => $sources,
        ];

        // Process the article
        $result = $this->process_manual_article($article_data);

        if ($result['success']) {
            $result['title'] = $title;
        }

        return $result;
    }

    /**
     * Extract title from HTML
     */
    private function extract_title_from_html($html) {
        // Try <h1>
        if (preg_match('/<h1[^>]*>(.*?)<\/h1>/is', $html, $matches)) {
            return strip_tags($matches[1]);
        }
        // Try <title>
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $matches)) {
            return strip_tags($matches[1]);
        }
        // Try og:title
        if (preg_match('/property=["\']og:title["\'][^>]*content=["\']([^"\']+)/is', $html, $matches)) {
            return $matches[1];
        }
        return '';
    }

    /**
     * Regenerate content for a draft
     */
    public function regenerate_draft($draft_id, $what = 'all', $instructions = '') {
        $draft = $this->db->get_draft($draft_id);

        if (!$draft) {
            return ['success' => false, 'error' => 'Draft not found'];
        }

        $content = $draft['title'] . "\n\n" . $draft['lead'] . "\n\n" . $draft['body_html'];

        switch ($what) {
            case 'title':
                $prompt = "Generate a new, compelling title (max 60 chars) for this article:\n\n{$content}";
                if ($instructions) {
                    $prompt .= "\n\nAdditional instructions: {$instructions}";
                }
                $result = $this->ai->complete($prompt, "Generate only the title, nothing else.");

                if ($result['success']) {
                    $this->db->update_draft($draft_id, ['title' => trim($result['content'])]);
                }
                break;

            case 'seo':
                $seo = $this->ai->generate_seo($content, $draft['lang']);
                if ($seo['success']) {
                    $this->db->update_draft($draft_id, [
                        'seo_title' => $seo['title'],
                        'meta_description' => $seo['description'],
                        'slug' => $seo['slug'],
                        'keywords' => json_encode($seo['keywords']),
                    ]);
                }
                break;

            case 'translate':
                // Re-translate from original
                if ($draft['raw_item_id']) {
                    global $wpdb;
                    $raw_item = $wpdb->get_row(
                        $wpdb->prepare(
                            "SELECT * FROM {$this->db->table('raw_items')} WHERE id = %d",
                            $draft['raw_item_id']
                        ),
                        ARRAY_A
                    );

                    if ($raw_item) {
                        $translated = $this->ai->translate(
                            $raw_item['body_html'] ?: $raw_item['summary'],
                            $raw_item['lang'],
                            $draft['lang']
                        );

                        if ($translated['success']) {
                            $parsed = $this->parse_generated_content($translated['content'], $draft['lang']);
                            $this->db->update_draft($draft_id, [
                                'title' => $parsed['title'] ?: $draft['title'],
                                'lead' => $parsed['lead'],
                                'body_html' => $parsed['body'],
                            ]);
                        }
                    }
                }
                break;

            case 'all':
            default:
                // Full rewrite
                $style = "News article for Ukrainian audience in Germany. {$instructions}";
                $rewritten = $this->ai->rewrite($content, $style, $draft['lang']);

                if ($rewritten['success']) {
                    $parsed = $this->parse_generated_content($rewritten['content'], $draft['lang']);
                    $seo = $this->ai->generate_seo($rewritten['content'], $draft['lang']);

                    $this->db->update_draft($draft_id, [
                        'title' => $parsed['title'] ?: $draft['title'],
                        'lead' => $parsed['lead'],
                        'body_html' => $parsed['body'],
                        'seo_title' => $seo['success'] ? $seo['title'] : $parsed['title'],
                        'meta_description' => $seo['success'] ? $seo['description'] : $parsed['lead'],
                        'slug' => $seo['success'] ? $seo['slug'] : sanitize_title($parsed['title']),
                        'keywords' => $seo['success'] ? json_encode($seo['keywords']) : $draft['keywords'],
                    ]);
                }
                break;
        }

        return ['success' => true];
    }

    /**
     * Find related sources for a topic using Google News RSS
     */
    private function find_related_sources($title, $lead = '') {
        $sources = [];

        try {
            // Extract key terms from title for search
            $search_terms = $this->extract_search_terms($title);

            if (empty($search_terms)) {
                return $sources;
            }

            // Build Google News RSS URL
            $query = urlencode(implode(' ', $search_terms));
            $google_news_url = "https://news.google.com/rss/search?q={$query}&hl=de&gl=DE&ceid=DE:de";

            $response = wp_remote_get($google_news_url, [
                'timeout' => 15,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                ],
            ]);

            if (is_wp_error($response)) {
                AINCC_Logger::debug('Google News search failed', ['error' => $response->get_error_message()]);
                return $sources;
            }

            $xml_content = wp_remote_retrieve_body($response);

            if (empty($xml_content)) {
                return $sources;
            }

            // Parse RSS feed
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($xml_content);

            if ($xml === false) {
                return $sources;
            }

            // Get items from channel
            $count = 0;
            foreach ($xml->channel->item as $item) {
                if ($count >= 5) break; // Max 5 sources

                $item_title = (string) $item->title;
                $item_link = (string) $item->link;
                $pub_date = (string) $item->pubDate;

                // Extract actual source name from title (Google News format: "Title - Source Name")
                $source_name = 'Unknown';
                if (preg_match('/ - ([^-]+)$/', $item_title, $matches)) {
                    $source_name = trim($matches[1]);
                    $item_title = trim(preg_replace('/ - [^-]+$/', '', $item_title));
                }

                // Skip duplicates or too similar titles
                $is_duplicate = false;
                foreach ($sources as $existing) {
                    if (similar_text(strtolower($existing['name']), strtolower($source_name)) > 80) {
                        $is_duplicate = true;
                        break;
                    }
                }

                if (!$is_duplicate) {
                    $sources[] = [
                        'name' => $source_name,
                        'url' => $item_link,
                        'title' => $item_title,
                        'date' => $pub_date ? date('d.m.Y', strtotime($pub_date)) : date('d.m.Y'),
                        'is_primary' => false,
                    ];
                    $count++;
                }
            }

            AINCC_Logger::debug('Found related sources', ['count' => count($sources)]);

        } catch (Exception $e) {
            AINCC_Logger::warning('Related sources search failed', ['error' => $e->getMessage()]);
        }

        return $sources;
    }

    /**
     * Extract search terms from title
     */
    private function extract_search_terms($title) {
        // Remove common stop words
        $stop_words = ['der', 'die', 'das', 'und', 'ist', 'in', 'von', 'mit', 'für', 'auf', 'den',
                       'des', 'dem', 'ein', 'eine', 'einer', 'als', 'auch', 'es', 'an', 'werden',
                       'the', 'a', 'an', 'and', 'or', 'is', 'in', 'on', 'at', 'to', 'for'];

        // Split title into words
        $words = preg_split('/\s+/', strtolower($title));

        // Filter and keep meaningful words
        $terms = [];
        foreach ($words as $word) {
            $word = preg_replace('/[^\p{L}\p{N}]/u', '', $word); // Keep only letters and numbers
            if (strlen($word) > 3 && !in_array($word, $stop_words)) {
                $terms[] = $word;
            }
        }

        // Return max 5 terms for more focused search
        return array_slice($terms, 0, 5);
    }
}
