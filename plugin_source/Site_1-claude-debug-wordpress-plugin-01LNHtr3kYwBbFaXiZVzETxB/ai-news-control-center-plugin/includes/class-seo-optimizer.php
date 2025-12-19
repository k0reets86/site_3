<?php
/**
 * SEO Optimizer
 * Handles SEO generation and optimization
 */

if (!defined('ABSPATH')) {
    exit;
}

class AINCC_SEO_Optimizer {

    /**
     * AI Provider
     */
    private $ai;

    /**
     * Constructor
     */
    public function __construct() {
        $this->ai = AINCC_AI_Provider_Factory::create();
    }

    /**
     * Generate SEO for content
     */
    public function generate($content, $language = 'de') {
        return $this->ai->generate_seo($content, $language);
    }

    /**
     * Generate schema markup (JSON-LD)
     */
    public function generate_schema($draft, $post_url = '') {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'NewsArticle',
            'headline' => $draft['seo_title'] ?: $draft['title'],
            'alternativeHeadline' => $draft['title'],
            'description' => $draft['meta_description'] ?: $draft['lead'],
            'datePublished' => $draft['published_at'] ?: current_time('c'),
            'dateModified' => $draft['updated_at'] ?: current_time('c'),
            'author' => [
                '@type' => 'Organization',
                'name' => get_bloginfo('name'),
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => get_bloginfo('name'),
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => get_site_icon_url(),
                ],
            ],
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id' => $post_url ?: home_url('/'),
            ],
            'articleSection' => $draft['category'] ?? 'News',
            'inLanguage' => $this->get_locale($draft['lang']),
        ];

        // Add image if available
        if (!empty($draft['image_url'])) {
            $schema['image'] = [
                '@type' => 'ImageObject',
                'url' => $draft['image_url'],
                'width' => 1200,
                'height' => 630,
            ];
        }

        // Add keywords
        $keywords = json_decode($draft['keywords'], true);
        if (!empty($keywords) && is_array($keywords)) {
            $schema['keywords'] = implode(', ', $keywords);
        }

        return json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    /**
     * Generate Open Graph tags
     */
    public function generate_og_tags($draft, $post_url = '') {
        return [
            'og:type' => 'article',
            'og:title' => $draft['seo_title'] ?: $draft['title'],
            'og:description' => $draft['meta_description'] ?: $draft['lead'],
            'og:url' => $post_url,
            'og:site_name' => get_bloginfo('name'),
            'og:locale' => $this->get_locale($draft['lang']),
            'og:image' => $draft['image_url'] ?? '',
            'article:published_time' => $draft['published_at'] ?? current_time('c'),
            'article:modified_time' => $draft['updated_at'] ?? current_time('c'),
            'article:section' => $draft['category'] ?? 'News',
        ];
    }

    /**
     * Generate Twitter Card tags
     */
    public function generate_twitter_tags($draft) {
        return [
            'twitter:card' => 'summary_large_image',
            'twitter:title' => $draft['seo_title'] ?: $draft['title'],
            'twitter:description' => $draft['meta_description'] ?: $draft['lead'],
            'twitter:image' => $draft['image_url'] ?? '',
        ];
    }

    /**
     * Get locale code
     */
    private function get_locale($lang) {
        $locales = [
            'de' => 'de_DE',
            'ua' => 'uk_UA',
            'ru' => 'ru_RU',
            'en' => 'en_GB',
        ];

        return $locales[$lang] ?? 'de_DE';
    }

    /**
     * Generate slug from title
     */
    public function generate_slug($title, $language = 'de') {
        // Remove special characters
        $slug = $title;

        // Transliterate based on language
        if ($language === 'ua' || $language === 'ru') {
            $slug = $this->transliterate_cyrillic($slug);
        }

        // Convert to lowercase and replace spaces
        $slug = strtolower($slug);
        $slug = preg_replace('/[^a-z0-9\-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');

        // Limit length
        $slug = substr($slug, 0, 60);

        return $slug;
    }

    /**
     * Transliterate Cyrillic to Latin
     */
    private function transliterate_cyrillic($text) {
        $map = [
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'h', 'ґ' => 'g',
            'д' => 'd', 'е' => 'e', 'є' => 'ye', 'ж' => 'zh', 'з' => 'z',
            'и' => 'y', 'і' => 'i', 'ї' => 'yi', 'й' => 'y', 'к' => 'k',
            'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p',
            'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f',
            'х' => 'kh', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shch',
            'ь' => '', 'ю' => 'yu', 'я' => 'ya', 'ы' => 'y', 'э' => 'e',
            'ё' => 'yo', 'ъ' => '',
            'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'H', 'Ґ' => 'G',
            'Д' => 'D', 'Е' => 'E', 'Є' => 'Ye', 'Ж' => 'Zh', 'З' => 'Z',
            'И' => 'Y', 'І' => 'I', 'Ї' => 'Yi', 'Й' => 'Y', 'К' => 'K',
            'Л' => 'L', 'М' => 'M', 'Н' => 'N', 'О' => 'O', 'П' => 'P',
            'Р' => 'R', 'С' => 'S', 'Т' => 'T', 'У' => 'U', 'Ф' => 'F',
            'Х' => 'Kh', 'Ц' => 'Ts', 'Ч' => 'Ch', 'Ш' => 'Sh', 'Щ' => 'Shch',
            'Ь' => '', 'Ю' => 'Yu', 'Я' => 'Ya', 'Ы' => 'Y', 'Э' => 'E',
            'Ё' => 'Yo', 'Ъ' => '',
            // German
            'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
            'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue',
        ];

        return strtr($text, $map);
    }

    /**
     * Analyze SEO quality
     */
    public function analyze($draft) {
        $score = 0;
        $issues = [];
        $suggestions = [];

        // Check title length
        $title_len = mb_strlen($draft['seo_title'] ?: $draft['title']);
        if ($title_len < 30) {
            $issues[] = 'title_too_short';
            $suggestions[] = 'Заголовок слишком короткий. Рекомендуется 50-60 символов.';
        } elseif ($title_len > 60) {
            $issues[] = 'title_too_long';
            $suggestions[] = 'Заголовок слишком длинный. Максимум 60 символов.';
        } else {
            $score += 20;
        }

        // Check meta description
        $desc_len = mb_strlen($draft['meta_description'] ?: $draft['lead']);
        if ($desc_len < 70) {
            $issues[] = 'meta_desc_too_short';
            $suggestions[] = 'Meta description слишком короткий. Рекомендуется 120-155 символов.';
        } elseif ($desc_len > 155) {
            $issues[] = 'meta_desc_too_long';
            $suggestions[] = 'Meta description слишком длинный. Максимум 155 символов.';
        } else {
            $score += 20;
        }

        // Check slug
        $slug = $draft['slug'] ?? '';
        if (empty($slug)) {
            $issues[] = 'no_slug';
            $suggestions[] = 'Отсутствует URL slug.';
        } elseif (strlen($slug) > 60) {
            $issues[] = 'slug_too_long';
        } else {
            $score += 15;
        }

        // Check keywords
        $keywords = json_decode($draft['keywords'], true);
        if (empty($keywords)) {
            $issues[] = 'no_keywords';
            $suggestions[] = 'Отсутствуют ключевые слова.';
        } else {
            $score += 15;

            // Check if keywords appear in title
            $title_lower = strtolower($draft['title']);
            $keyword_in_title = false;
            foreach ($keywords as $kw) {
                if (stripos($title_lower, strtolower($kw)) !== false) {
                    $keyword_in_title = true;
                    break;
                }
            }

            if ($keyword_in_title) {
                $score += 10;
            } else {
                $suggestions[] = 'Рекомендуется включить ключевое слово в заголовок.';
            }
        }

        // Check image
        if (!empty($draft['image_url'])) {
            $score += 10;

            // Check alt text
            if (!empty($draft['image_alt'])) {
                $score += 10;
            } else {
                $issues[] = 'no_image_alt';
                $suggestions[] = 'Добавьте alt-текст для изображения.';
            }
        } else {
            $issues[] = 'no_image';
            $suggestions[] = 'Рекомендуется добавить изображение.';
        }

        return [
            'score' => $score,
            'max_score' => 100,
            'percentage' => $score,
            'issues' => $issues,
            'suggestions' => $suggestions,
        ];
    }
}
