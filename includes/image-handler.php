<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIMatic_Image_Handler {
    
    /**
     * Fetch images from Lexica.art (Search existing AI images)
     */
    public static function fetch_lexica_images($query, $count = 5) {
        // Lexica Search API: https://lexica.art/api/v1/search?q={query}
        $url = 'https://lexica.art/api/v1/search?q=' . urlencode($query);

        $response = wp_remote_get($url);
        if (is_wp_error($response)) {
            error_log('AIMatic: Lexica API error: ' . $response->get_error_message());
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (empty($data) || empty($data['images'])) {
            return false;
        }

        $images = array();
        $results = array_slice($data['images'], 0, $count);
        
        foreach ($results as $img) {
            $images[] = array(
                'url' => $img['src'],
                'thumb' => $img['srcSmall'],
                'alt' => $query, // Lexica doesn't return titles, using query
                'source' => 'Lexica.art'
            );
        }

        return $images;
    }

    /**
     * Generate images using DeepAI (Text to Image)
     */
    public static function fetch_deepai_images($query, $count = 5) {
        $api_key = get_option('aimatic_writer_deepai_key');
        if (empty($api_key)) {
            return false;
        }

        $images = array();
        
        // DeepAI generates one image per request. We'll loop.
        // Limit to 2 parallel requests or sequential to avoid hitting rate limits too hard if free tier
        // Let's do sequential for safety.
        for ($i = 0; $i < $count; $i++) {
            $response = wp_remote_post('https://api.deepai.org/api/text2img', array(
                'headers' => array(
                    'api-key' => $api_key
                ),
                'body' => array(
                    'text' => $query
                ),
                'timeout' => 30
            ));

            if (is_wp_error($response)) {
                error_log('AIMatic: DeepAI error: ' . $response->get_error_message());
                continue;
            }

            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if (isset($data['output_url'])) {
                $images[] = array(
                    'url' => $data['output_url'],
                    'thumb' => $data['output_url'],
                    'alt' => $query . ' ' . ($i + 1),
                    'source' => 'DeepAI'
                );
            }
        }

        return !empty($images) ? $images : false;
    }


    /**
     * Generate images using Pollinations.ai (Supports 'flux', 'turbo', etc)
     */
    public static function fetch_pollinations_images($query, $count = 5, $width = null, $height = null, $model = 'flux') {
        $width = $width ? $width : get_option('aimatic_writer_pollinations_width', 1200);
        $height = $height ? $height : get_option('aimatic_writer_pollinations_height', 632);
        
        $images = array();
        
        for ($i = 0; $i < $count; $i++) {
            // Add random seed to ensure uniqueness for same query
            $seed = rand(10000, 99999);
            
            // Enhance prompt for quality if Flux
            $encoded_query = urlencode($query . ', photorealistic, 8k');
            if ($model === 'flux') {
                 $encoded_query = urlencode($query . ', photorealistic, 8k, cinematic lighting, highly detailed, professional photography');
            }
            
            // Construct URL
            $image_url = sprintf(
                'https://image.pollinations.ai/prompt/%s?width=%d&height=%d&seed=%d&nologo=true&model=%s',
                $encoded_query,
                $width,
                $height,
                $seed,
                $model
            );
            
            $images[] = array(
                'url' => $image_url,
                'thumb' => $image_url,
                'alt' => $query . ' ' . ($i + 1),
                'source' => 'Pollinations (' . ucfirst($model) . ')'
            );
        }

        return $images;
    }

    /**
     * Fetch images with fallback
     */
    public static function fetch_images($query, $count = 5, $width = null, $height = null) {
        $preferred_source = get_option('aimatic_writer_image_source', 'pollinations');
        
        $images = false;
        
        // Route to preferred source
        if ($preferred_source === 'pollinations') {
            $images = self::fetch_pollinations_images($query, $count, $width, $height, 'turbo'); 
        } elseif ($preferred_source === 'flux') {
            $images = self::fetch_pollinations_images($query, $count, $width, $height, 'flux');  
        } elseif ($preferred_source === 'lexica') {
            $images = self::fetch_lexica_images($query, $count);
        } elseif ($preferred_source === 'pexels') {
            $images = self::fetch_pexels_images($query, $count);
        } elseif ($preferred_source === 'pixabay') {
            $images = self::fetch_pixabay_images($query, $count);
        } elseif ($preferred_source === 'unsplash') {
            $images = self::fetch_unsplash_images($query, $count);
        } elseif ($preferred_source === 'deepai') {
            $images = self::fetch_deepai_images($query, $count);
        }
        
        if ($images && !empty($images)) {
             return $images;
        }
        
        // If preferred source fails (e.g. key missing), fallback order:
        // Pexels -> Pixabay -> Unsplash -> Pollinations (Always works free)
        
        $images = self::fetch_pexels_images($query, $count);
        if ($images) return $images;
        
        $images = self::fetch_pixabay_images($query, $count);
        if ($images) return $images;

        $images = self::fetch_unsplash_images($query, $count);
        if ($images) return $images;
        
        return self::fetch_pollinations_images($query, $count, null, null, 'flux');
    }

    /**
     * Unsplash API (Stock Photos)
     */
    public static function fetch_unsplash_images($query, $count = 5) {
        $api_key = get_option('aimatic_writer_unsplash_key');
        if (empty($api_key)) return false;
        
        // Get dimensions
        $width = get_option('aimatic_writer_pollinations_width', 1200);
        $height = get_option('aimatic_writer_pollinations_height', 632);

        // Clean query
        $query = strip_tags($query);
        $query = wp_trim_words($query, 3, ''); // Keep it short for unsplash
        
        $url = 'https://api.unsplash.com/search/photos?page=1&query=' . urlencode($query) . '&per_page=' . $count;
        
        $response = wp_remote_get($url, array(
            'headers' => array('Authorization' => 'Client-ID ' . $api_key),
            'timeout' => 15, 'sslverify' => false
        ));
        
        if (is_wp_error($response)) {
             error_log('AIMatic: Unsplash API error: ' . $response->get_error_message());
             return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        $images = array();
        if (isset($data['results']) && is_array($data['results'])) {
            foreach ($data['results'] as $result) {
                // Use 'raw' URL and append dimensions for Imgix dynamic resizing
                $base_url = isset($result['urls']['raw']) ? $result['urls']['raw'] : (isset($result['urls']['regular']) ? $result['urls']['regular'] : '');
                
                if (!empty($base_url)) {
                    // Append params: w, h, fit=crop
                    $image_url = add_query_arg(array(
                        'w' => $width,
                        'h' => $height,
                        'fit' => 'crop',
                        'crop' => 'faces,edges' // Smart crop
                    ), $base_url);

                    $images[] = array(
                        'url' => $image_url,
                        'thumb' => isset($result['urls']['small']) ? $result['urls']['small'] : $image_url,
                        'alt' => isset($result['alt_description']) ? $result['alt_description'] : $query,
                        'source' => 'Unsplash'
                    );
                }
            }
        }
        
        return !empty($images) ? $images : false;
    }
    
    /**
     * Pexels API (Stock Photos)
     */
    public static function fetch_pexels_images($query, $count = 5) {
        $api_key = get_option('aimatic_writer_pexels_key');
        if (empty($api_key)) return false;

        // Get dimensions
        $width = get_option('aimatic_writer_pollinations_width', 1200);
        $height = get_option('aimatic_writer_pollinations_height', 632);
        
        // Clean query for search
        $query = strip_tags($query);
        $query = wp_trim_words($query, 5, '');
        
        $url = 'https://api.pexels.com/v1/search?query=' . urlencode($query) . '&per_page=' . $count;
        
        $response = wp_remote_get($url, array(
            'headers' => array('Authorization' => $api_key),
            'timeout' => 15, 'sslverify' => false
        ));
        
        if (is_wp_error($response)) {
            error_log('AIMatic: Pexels API error: ' . $response->get_error_message());
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        $images = array();
        if (isset($data['photos']) && is_array($data['photos'])) {
            foreach ($data['photos'] as $photo) {
                // Use 'original' and resize
                $base_url = isset($photo['src']['original']) ? $photo['src']['original'] : '';

                if (!empty($base_url)) {
                    // Pexels supports ?auto=compress&cs=tinysrgb&w=...&h=...&fit=crop
                     $image_url = add_query_arg(array(
                        'auto' => 'compress',
                        'cs' => 'tinysrgb',
                        'w' => $width,
                        'h' => $height,
                        'fit' => 'crop'
                    ), $base_url);

                    $images[] = array(
                        'url' => $image_url,
                        'thumb' => isset($photo['src']['medium']) ? $photo['src']['medium'] : $image_url,
                        'alt' => isset($photo['alt']) ? $photo['alt'] : $query,
                        'source' => 'Pexels'
                    );
                }
            }
        }
        
        return !empty($images) ? $images : false;
    }

    /**
     * Pixabay API (Stock Photos)
     */
    public static function fetch_pixabay_images($query, $count = 5) {
        $api_key = get_option('aimatic_writer_pixabay_key');
        if (empty($api_key)) return false;
        
        // Clean query
        $query = strip_tags($query);
        $query = wp_trim_words($query, 5, ''); // Max 5 words
        
        $url = 'https://pixabay.com/api/?key=' . $api_key . '&q=' . urlencode($query) . '&image_type=photo&per_page=' . $count; 
        
        $response = wp_remote_get($url, array('timeout' => 15, 'sslverify' => false));
        
        if (is_wp_error($response)) {
             error_log('AIMatic: Pixabay API error: ' . $response->get_error_message());
             return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        $images = array();
        if (isset($data['hits']) && is_array($data['hits'])) {
            foreach ($data['hits'] as $hit) {
                // Pixabay doesn't support easy dynamic resize via URL. best effort: pick closest larger
                // Using largeImageURL is safest.
                $image_url = isset($hit['largeImageURL']) ? $hit['largeImageURL'] : (isset($hit['webformatURL']) ? $hit['webformatURL'] : '');
                
                if (!empty($image_url)) {
                    $images[] = array(
                        'url' => $image_url,
                        'thumb' => isset($hit['previewURL']) ? $hit['previewURL'] : $image_url,
                        'alt' => isset($hit['tags']) ? $hit['tags'] : $query,
                        'source' => 'Pixabay'
                    );
                }
            }
        }
        
        return !empty($images) ? $images : false;
    }

    /**
     * Download and upload image to WordPress media library
     */
    public static function upload_to_media_library($image_url, $alt_text, $post_id = 0) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        // Fix: Disable SSL Verify & Increase Timeout
        add_filter('https_ssl_verify', '__return_false');
        add_filter('http_request_timeout', function() { return 20; });

        error_log('AIMatic: Attempting to download image from: ' . $image_url);

        $tmp = download_url($image_url, 20);
        
        // Remove filters immediately to avoid affecting other plugins
        remove_filter('https_ssl_verify', '__return_false');

        if (is_wp_error($tmp)) {
            error_log('AIMatic: Image download failed: ' . $tmp->get_error_message());
            error_log('AIMatic: Failed URL: ' . $image_url);
            return false;
        }

        error_log('AIMatic: Image downloaded successfully to: ' . $tmp);

        // Get file extension from URL or use jpg as default
        $file_extension = pathinfo(parse_url($image_url, PHP_URL_PATH), PATHINFO_EXTENSION);
        if (empty($file_extension) || !in_array(strtolower($file_extension), ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            $file_extension = 'jpg';
        }

        $file_array = array(
            'name' => 'aimatic-' . uniqid() . '.' . $file_extension,
            'tmp_name' => $tmp
        );

        error_log('AIMatic: Uploading to media library with filename: ' . $file_array['name']);

        $id = media_handle_sideload($file_array, $post_id, $alt_text);
        
        if (is_wp_error($id)) {
            error_log('AIMatic: Media upload failed: ' . $id->get_error_message());
            @unlink($file_array['tmp_name']);
            return false;
        }

        error_log('AIMatic: Image uploaded successfully with ID: ' . $id);

        // Set alt text
        update_post_meta($id, '_wp_attachment_image_alt', $alt_text);

        return $id;
    }

    /**
     * Download and upload multiple images in parallel (Batch)
     * 
     * @param array $items Array of array('url' => ..., 'alt' => ..., 'post_id' => ...)
     * @return array Array of Attachment IDs or Falses, keyed by original index
     */
    public static function download_and_upload_batch($items) {
        if (empty($items)) return array();

        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        $mh = curl_multi_init();
        $handles = array();
        $results = array();
        
        // Check for cURL multi support - though widely available
        if (!$mh) {
            // Fallback to sequential
            foreach ($items as $index => $item) {
                $results[$index] = self::upload_to_media_library($item['url'], $item['alt'], $item['post_id']);
            }
            return $results;
        }

        // Initialize cURL handles
        foreach ($items as $index => $item) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, str_replace(' ', '%20', $item['url'])); // Ensure URL safe
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Higher timeout for batch
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_USERAGENT, 'WordPress/AIMatic');
            
            curl_multi_add_handle($mh, $ch);
            $handles[$index] = $ch;
        }

        // Execute Batch
        $running = null;
        do {
            curl_multi_exec($mh, $running);
            curl_multi_select($mh);
        } while ($running > 0);

        // Process Results
        foreach ($handles as $index => $ch) {
            $content = curl_multi_getcontent($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            
            if ($http_code == 200 && !empty($content)) {
                // Save to temp file
                $file_extension = 'jpg'; // Default
                $path_info = pathinfo(parse_url($items[$index]['url'], PHP_URL_PATH));
                if (isset($path_info['extension'])) {
                    $ext = strtolower($path_info['extension']);
                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                        $file_extension = $ext;
                    }
                }
                
                // Create unique temp file
                $tmp_name = wp_tempnam($items[$index]['alt']); // Use alt as hint
                if (!$tmp_name) $tmp_name = tempnam(sys_get_temp_dir(), 'aim');
                
                file_put_contents($tmp_name, $content);
                
                // Fix: Rename to have correct extension for WordPress to detect type
                $new_tmp_name = $tmp_name . '.' . $file_extension;
                rename($tmp_name, $new_tmp_name);
                $tmp_name = $new_tmp_name;

                $file_array = array(
                    'name' => sanitize_title($items[$index]['alt']) . '-' . uniqid() . '.' . $file_extension,
                    'tmp_name' => $tmp_name
                );

                // Upload to Media Library
                // Need to suppress output if any warning occurs during sideload
                $id = media_handle_sideload($file_array, $items[$index]['post_id'], $items[$index]['alt']);
                
                if (!is_wp_error($id)) {
                    update_post_meta($id, '_wp_attachment_image_alt', $items[$index]['alt']);
                    $results[$index] = $id;
                    error_log("AIMatic Batch: Uploaded image for index $index (ID: $id)");
                } else {
                    error_log('AIMatic Batch: Media upload failed for index ' . $index . ': ' . $id->get_error_message());
                    $results[$index] = false;
                    @unlink($tmp_name);
                }

            } else {
                error_log('AIMatic Batch: HTTP Request failed for index ' . $index . ' Code: ' . $http_code . ' Error: ' . $error);
                $results[$index] = false;
            }

            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }

        curl_multi_close($mh);
        
        return $results;
    }

    /**
     * Extract keywords from content
     */
    public static function extract_keywords($content, $title = '') {
        $keywords = array();
        
        // Add title as primary keyword
        if (!empty($title)) {
            $keywords[] = $title;
        }

        // Extract H2 headings from HTML
        preg_match_all('/<h2[^>]*>(.*?)<\/h2>/i', $content, $matches);
        if (!empty($matches[1])) {
            foreach ($matches[1] as $heading) {
                $heading = strip_tags($heading);
                $heading = trim($heading);
                if (!empty($heading)) {
                    $keywords[] = $heading;
                }
            }
        }

        return array_unique($keywords);
    }
}
