<?php
/**
 * Image Handler
 * Manages image sourcing from Pexels, Unsplash, and article sources
 */

if (!defined('ABSPATH')) {
    exit;
}

class AINCC_Image_Handler {

    /**
     * Pexels API key
     */
    private $pexels_key;

    /**
     * Constructor
     */
    public function __construct() {
        $this->pexels_key = AINCC_Settings::get('pexels_api_key', '');
    }

    /**
     * Find image for article
     */
    public function find_image($keywords, $title = '', $category = '') {
        // Build search query
        $query = $this->build_search_query($keywords, $title, $category);

        // Try Pexels first
        $image = $this->search_pexels($query);

        if ($image) {
            return $image;
        }

        // Fallback: try category-based default images
        $default = $this->get_category_default($category);
        if ($default) {
            return $default;
        }

        return null;
    }

    /**
     * Build search query from keywords
     */
    private function build_search_query($keywords, $title = '', $category = '') {
        $query_parts = [];

        // Add category context
        $category_terms = [
            'politik' => 'government politics parliament',
            'wirtschaft' => 'business economy office',
            'migration' => 'people diversity documents',
            'gesellschaft' => 'people community society',
            'verkehr' => 'transport train bus city',
            'lokales' => 'city street urban',
            'kultur' => 'culture art museum',
            'sport' => 'sports fitness',
            'wetter' => 'weather sky',
        ];

        if ($category && isset($category_terms[$category])) {
            $query_parts[] = $category_terms[$category];
        }

        // Add first 2-3 keywords
        if (is_array($keywords)) {
            $query_parts = array_merge($query_parts, array_slice($keywords, 0, 3));
        } elseif (is_string($keywords)) {
            $query_parts[] = $keywords;
        }

        // Build final query
        $query = implode(' ', $query_parts);

        // Translate German keywords to English for better Pexels results
        $translations = [
            'münchen' => 'munich',
            'bayern' => 'bavaria germany',
            'deutschland' => 'germany',
            'regierung' => 'government',
            'verkehr' => 'transport traffic',
            'arbeit' => 'work office',
            'geld' => 'money finance',
            'ukraine' => 'ukraine',
            'schule' => 'school education',
            'wohnung' => 'apartment housing',
        ];

        foreach ($translations as $german => $english) {
            $query = str_ireplace($german, $english, $query);
        }

        return trim($query);
    }

    /**
     * Search Pexels for image
     */
    public function search_pexels($query, $per_page = 5) {
        if (empty($this->pexels_key)) {
            AINCC_Logger::warning('Pexels API key not configured');
            return null;
        }

        $url = add_query_arg([
            'query' => urlencode($query),
            'per_page' => $per_page,
            'orientation' => 'landscape',
        ], 'https://api.pexels.com/v1/search');

        $response = wp_remote_get($url, [
            'timeout' => 15,
            'headers' => [
                'Authorization' => $this->pexels_key,
            ],
        ]);

        if (is_wp_error($response)) {
            AINCC_Logger::error('Pexels API error', [
                'error' => $response->get_error_message(),
            ]);
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (empty($body['photos'])) {
            AINCC_Logger::debug('No Pexels results for query', ['query' => $query]);
            return null;
        }

        // Get first suitable photo
        $photo = $body['photos'][0];

        return [
            'url' => $photo['src']['large2x'] ?? $photo['src']['large'],
            'url_medium' => $photo['src']['medium'],
            'url_small' => $photo['src']['small'],
            'width' => $photo['width'],
            'height' => $photo['height'],
            'author' => $photo['photographer'],
            'author_url' => $photo['photographer_url'],
            'source' => 'Pexels',
            'source_url' => $photo['url'],
            'license' => 'Pexels License',
            'alt' => $photo['alt'] ?? $query,
        ];
    }

    /**
     * Get category default image
     */
    private function get_category_default($category) {
        // Predefined Pexels image IDs for categories
        $defaults = [
            'politik' => [
                'url' => 'https://images.pexels.com/photos/1550337/pexels-photo-1550337.jpeg?auto=compress&cs=tinysrgb&w=1260&h=750',
                'author' => 'Element5 Digital',
                'source' => 'Pexels',
                'license' => 'Pexels License',
                'alt' => 'Government building',
            ],
            'wirtschaft' => [
                'url' => 'https://images.pexels.com/photos/534216/pexels-photo-534216.jpeg?auto=compress&cs=tinysrgb&w=1260&h=750',
                'author' => 'Pixabay',
                'source' => 'Pexels',
                'license' => 'Pexels License',
                'alt' => 'Business and economy',
            ],
            'migration' => [
                'url' => 'https://images.pexels.com/photos/3184418/pexels-photo-3184418.jpeg?auto=compress&cs=tinysrgb&w=1260&h=750',
                'author' => 'fauxels',
                'source' => 'Pexels',
                'license' => 'Pexels License',
                'alt' => 'People and documents',
            ],
            'gesellschaft' => [
                'url' => 'https://images.pexels.com/photos/1595385/pexels-photo-1595385.jpeg?auto=compress&cs=tinysrgb&w=1260&h=750',
                'author' => 'RF._.studio',
                'source' => 'Pexels',
                'license' => 'Pexels License',
                'alt' => 'Community and society',
            ],
            'verkehr' => [
                'url' => 'https://images.pexels.com/photos/1031698/pexels-photo-1031698.jpeg?auto=compress&cs=tinysrgb&w=1260&h=750',
                'author' => 'Pixabay',
                'source' => 'Pexels',
                'license' => 'Pexels License',
                'alt' => 'Public transport',
            ],
            'lokales' => [
                'url' => 'https://images.pexels.com/photos/109629/pexels-photo-109629.jpeg?auto=compress&cs=tinysrgb&w=1260&h=750',
                'author' => 'Pixabay',
                'source' => 'Pexels',
                'license' => 'Pexels License',
                'alt' => 'Munich city',
            ],
            'kultur' => [
                'url' => 'https://images.pexels.com/photos/1839919/pexels-photo-1839919.jpeg?auto=compress&cs=tinysrgb&w=1260&h=750',
                'author' => 'Adrienne Andersen',
                'source' => 'Pexels',
                'license' => 'Pexels License',
                'alt' => 'Culture and art',
            ],
            'wetter' => [
                'url' => 'https://images.pexels.com/photos/1431822/pexels-photo-1431822.jpeg?auto=compress&cs=tinysrgb&w=1260&h=750',
                'author' => 'Johannes Plenio',
                'source' => 'Pexels',
                'license' => 'Pexels License',
                'alt' => 'Weather',
            ],
        ];

        return $defaults[$category] ?? $defaults['lokales'];
    }

    /**
     * Download and save image to WordPress media library
     */
    public function save_to_media_library($image_data, $post_id = 0) {
        if (empty($image_data['url'])) {
            return false;
        }

        // Download image
        $temp_file = download_url($image_data['url']);

        if (is_wp_error($temp_file)) {
            AINCC_Logger::error('Failed to download image', [
                'url' => $image_data['url'],
                'error' => $temp_file->get_error_message(),
            ]);
            return false;
        }

        // Prepare file array
        $file_array = [
            'name' => sanitize_file_name(basename(parse_url($image_data['url'], PHP_URL_PATH))),
            'tmp_name' => $temp_file,
        ];

        // If no file extension, add .jpg
        if (!preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $file_array['name'])) {
            $file_array['name'] .= '.jpg';
        }

        // Upload to media library
        $attachment_id = media_handle_sideload($file_array, $post_id);

        // Clean up temp file
        @unlink($temp_file);

        if (is_wp_error($attachment_id)) {
            AINCC_Logger::error('Failed to save image to media library', [
                'error' => $attachment_id->get_error_message(),
            ]);
            return false;
        }

        // Add metadata
        update_post_meta($attachment_id, '_aincc_image_source', $image_data['source'] ?? 'Unknown');
        update_post_meta($attachment_id, '_aincc_image_author', $image_data['author'] ?? '');
        update_post_meta($attachment_id, '_aincc_image_license', $image_data['license'] ?? '');
        update_post_meta($attachment_id, '_aincc_image_source_url', $image_data['source_url'] ?? '');

        // Set alt text
        if (!empty($image_data['alt'])) {
            update_post_meta($attachment_id, '_wp_attachment_image_alt', $image_data['alt']);
        }

        AINCC_Logger::info('Image saved to media library', [
            'attachment_id' => $attachment_id,
            'source' => $image_data['source'],
        ]);

        return $attachment_id;
    }

    /**
     * Extract image from article HTML
     */
    public function extract_from_html($html, $base_url = '') {
        // Find first image
        if (preg_match('/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $html, $matches)) {
            $image_url = $matches[0];

            // Get src
            if (preg_match('/src=["\']([^"\']+)["\']/i', $image_url, $src_match)) {
                $src = $src_match[1];

                // Make absolute URL
                if (strpos($src, '//') === 0) {
                    $src = 'https:' . $src;
                } elseif (strpos($src, '/') === 0 && $base_url) {
                    $parsed = parse_url($base_url);
                    $src = $parsed['scheme'] . '://' . $parsed['host'] . $src;
                }

                // Get alt text
                $alt = '';
                if (preg_match('/alt=["\']([^"\']+)["\']/i', $image_url, $alt_match)) {
                    $alt = $alt_match[1];
                }

                return [
                    'url' => $src,
                    'alt' => $alt,
                    'source' => 'Article',
                    'license' => 'Source article (editorial use)',
                    'author' => '',
                ];
            }
        }

        return null;
    }

    /**
     * Generate image credit HTML
     */
    public function get_credit_html($image_data) {
        if (empty($image_data)) {
            return '';
        }

        $credit_parts = [];

        if (!empty($image_data['author'])) {
            $author = esc_html($image_data['author']);
            if (!empty($image_data['author_url'])) {
                $author = sprintf(
                    '<a href="%s" target="_blank" rel="noopener">%s</a>',
                    esc_url($image_data['author_url']),
                    $author
                );
            }
            $credit_parts[] = "Foto: {$author}";
        }

        if (!empty($image_data['source'])) {
            $source = esc_html($image_data['source']);
            if (!empty($image_data['source_url'])) {
                $source = sprintf(
                    '<a href="%s" target="_blank" rel="noopener">%s</a>',
                    esc_url($image_data['source_url']),
                    $source
                );
            }
            $credit_parts[] = $source;
        }

        if (!empty($image_data['license'])) {
            $credit_parts[] = esc_html($image_data['license']);
        }

        return implode(' / ', $credit_parts);
    }

    /**
     * Generate alt text using AI
     */
    public function generate_alt_text($image_context, $language = 'de') {
        $ai = AINCC_AI_Provider_Factory::create();

        $lang_names = [
            'de' => 'German',
            'ua' => 'Ukrainian',
            'ru' => 'Russian',
            'en' => 'English',
        ];

        $lang = $lang_names[$language] ?? 'German';

        $prompt = "Generate a descriptive alt text (max 125 characters) in {$lang} for an image used with this article context:\n\n{$image_context}\n\nAlt text:";

        $result = $ai->complete($prompt, 'Generate only the alt text, nothing else.', [
            'max_tokens' => 50,
            'temperature' => 0.3,
        ]);

        if ($result['success']) {
            return substr(trim($result['content']), 0, 125);
        }

        return '';
    }

    /**
     * Assign image to draft
     */
    public function assign_to_draft($draft_id, $image_data = null) {
        $db = new AINCC_Database();
        $draft = $db->get_draft($draft_id);

        if (!$draft) {
            return ['success' => false, 'error' => 'Draft not found'];
        }

        // If no image data provided, find one
        if (!$image_data) {
            $keywords = json_decode($draft['keywords'], true) ?: [];
            $image_data = $this->find_image($keywords, $draft['title'], $draft['category']);
        }

        if (!$image_data) {
            return ['success' => false, 'error' => 'No suitable image found'];
        }

        // Generate alt text if not present
        if (empty($image_data['alt'])) {
            $image_data['alt'] = $this->generate_alt_text($draft['title'], $draft['lang']);
        }

        // Update draft
        $db->update_draft($draft_id, [
            'image_url' => $image_data['url'],
            'image_author' => $image_data['author'] ?? '',
            'image_license' => $image_data['license'] ?? '',
            'image_alt' => $image_data['alt'] ?? '',
        ]);

        return [
            'success' => true,
            'image' => $image_data,
        ];
    }
}
