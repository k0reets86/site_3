<?php
/**
 * Fact Checker
 * Simple fact-checking through cross-reference and source verification
 */

if (!defined('ABSPATH')) {
    exit;
}

class AINCC_Fact_Checker {

    /**
     * Database instance
     */
    private $db;

    /**
     * Constructor
     */
    public function __construct() {
        $this->db = new AINCC_Database();
    }

    /**
     * Check facts for a raw item
     */
    public function check($raw_item_id) {
        global $wpdb;

        // Get item
        $item = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT ri.*, s.trust_score, s.name as source_name
                 FROM {$this->db->table('raw_items')} ri
                 LEFT JOIN {$this->db->table('sources')} s ON ri.source_id = s.id
                 WHERE ri.id = %d",
                $raw_item_id
            ),
            ARRAY_A
        );

        if (!$item) {
            return [
                'success' => false,
                'error' => 'Item not found',
            ];
        }

        $score = 0.5; // Base score
        $checks = [];

        // Check 1: Source trust score
        $source_trust = $item['trust_score'] ?? 0.5;
        $score += $source_trust * 0.3; // 30% weight
        $checks['source_trust'] = [
            'score' => $source_trust,
            'weight' => 0.3,
        ];

        // Check 2: Cross-reference (similar items from other sources)
        $cross_ref = $this->cross_reference($item);
        $score += $cross_ref['score'] * 0.4; // 40% weight
        $checks['cross_reference'] = $cross_ref;

        // Check 3: Content analysis (keywords, entities)
        $content_check = $this->analyze_content($item);
        $score += $content_check['score'] * 0.3; // 30% weight
        $checks['content_analysis'] = $content_check;

        // Normalize score
        $final_score = min(1.0, max(0.0, $score));

        // Save result
        $wpdb->insert(
            $this->db->table('fact_checks'),
            [
                'raw_item_id' => $raw_item_id,
                'claims' => json_encode($checks),
                'score' => $final_score,
                'sources_confirmed' => $cross_ref['confirmations'] ?? 0,
            ]
        );

        // Update raw item
        $wpdb->update(
            $this->db->table('raw_items'),
            ['fact_check_score' => $final_score],
            ['id' => $raw_item_id]
        );

        return [
            'success' => true,
            'score' => round($final_score, 2),
            'checks' => $checks,
        ];
    }

    /**
     * Cross-reference with other sources
     */
    private function cross_reference($item) {
        global $wpdb;

        // Get keywords from title
        $title_words = array_filter(
            explode(' ', strtolower($item['title'])),
            function ($word) {
                return strlen($word) > 4;
            }
        );

        $title_words = array_slice($title_words, 0, 5);

        if (empty($title_words)) {
            return [
                'score' => 0.5,
                'confirmations' => 0,
                'sources' => [],
            ];
        }

        // Build search pattern
        $pattern = implode('|', array_map('preg_quote', $title_words));

        // Find similar items from different sources (last 48 hours)
        $similar = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ri.source_id, ri.title, s.name, s.trust_score
                 FROM {$this->db->table('raw_items')} ri
                 JOIN {$this->db->table('sources')} s ON ri.source_id = s.id
                 WHERE ri.id != %d
                 AND ri.source_id != %s
                 AND ri.fetched_at > DATE_SUB(NOW(), INTERVAL 48 HOUR)
                 AND ri.title REGEXP %s
                 GROUP BY ri.source_id
                 ORDER BY s.trust_score DESC
                 LIMIT 10",
                $item['id'],
                $item['source_id'],
                $pattern
            ),
            ARRAY_A
        );

        $confirmations = count($similar);
        $sources = array_column($similar, 'name');

        // Calculate score based on confirmations and source trust
        $score = 0;
        if ($confirmations > 0) {
            $avg_trust = array_sum(array_column($similar, 'trust_score')) / $confirmations;
            $score = min(1.0, ($confirmations * 0.2) + ($avg_trust * 0.3));
        }

        return [
            'score' => $score,
            'confirmations' => $confirmations,
            'sources' => $sources,
            'weight' => 0.4,
        ];
    }

    /**
     * Analyze content for reliability indicators
     */
    private function analyze_content($item) {
        $score = 0.5;
        $issues = [];

        $content = $item['title'] . ' ' . ($item['summary'] ?? '');
        $content_lower = strtolower($content);

        // Check for sensational language
        $sensational_words = [
            'schockierend', 'unglaublich', 'skandal', 'geheim',
            'shocking', 'unbelievable', 'scandal', 'secret',
            'сенсация', 'шок', 'скандал',
        ];

        $sensational_count = 0;
        foreach ($sensational_words as $word) {
            if (stripos($content_lower, $word) !== false) {
                $sensational_count++;
            }
        }

        if ($sensational_count > 0) {
            $score -= ($sensational_count * 0.1);
            $issues[] = 'sensational_language';
        }

        // Check for specific facts (dates, numbers, names)
        $has_date = preg_match('/\d{1,2}\.\d{1,2}\.\d{2,4}/', $content);
        $has_number = preg_match('/\d+/', $content);

        if ($has_date) {
            $score += 0.1;
        }
        if ($has_number) {
            $score += 0.05;
        }

        // Check content length (too short = suspicious)
        $word_count = str_word_count($content);
        if ($word_count < 20) {
            $score -= 0.1;
            $issues[] = 'very_short_content';
        } elseif ($word_count > 100) {
            $score += 0.1;
        }

        // Normalize
        $score = min(1.0, max(0.0, $score));

        return [
            'score' => $score,
            'issues' => $issues,
            'word_count' => $word_count,
            'weight' => 0.3,
        ];
    }

    /**
     * Get fact check result for item
     */
    public function get_result($raw_item_id) {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->db->table('fact_checks')} WHERE raw_item_id = %d ORDER BY verified_at DESC LIMIT 1",
                $raw_item_id
            ),
            ARRAY_A
        );
    }

    /**
     * Update source trust based on fact check results
     */
    public function update_source_trust($source_id) {
        global $wpdb;

        // Get average fact check score for source's items (last 30 days)
        $avg_score = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT AVG(fc.score)
                 FROM {$this->db->table('fact_checks')} fc
                 JOIN {$this->db->table('raw_items')} ri ON fc.raw_item_id = ri.id
                 WHERE ri.source_id = %s
                 AND fc.verified_at > DATE_SUB(NOW(), INTERVAL 30 DAY)",
                $source_id
            )
        );

        if ($avg_score === null) {
            return;
        }

        // Get current trust
        $current_trust = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT trust_score FROM {$this->db->table('sources')} WHERE id = %s",
                $source_id
            )
        );

        // Calculate new trust (weighted average: 70% current + 30% new)
        $new_trust = ($current_trust * 0.7) + ($avg_score * 0.3);
        $new_trust = round($new_trust, 2);

        // Update if changed
        if ($new_trust !== $current_trust) {
            $wpdb->update(
                $this->db->table('sources'),
                ['trust_score' => $new_trust],
                ['id' => $source_id]
            );

            // Log change
            $wpdb->insert(
                $this->db->table('trust_history'),
                [
                    'source_id' => $source_id,
                    'old_score' => $current_trust,
                    'new_score' => $new_trust,
                    'reason' => 'fact_check_performance',
                ]
            );

            AINCC_Logger::info('Source trust updated', [
                'source_id' => $source_id,
                'old' => $current_trust,
                'new' => $new_trust,
            ]);
        }
    }
}
