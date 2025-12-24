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
        $temp_files = array();

        // Initialize cURL handles
        foreach ($items as $index => $item) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $item['url']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
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
                
                $tmp_name = wp_tempnam('aimatic-' . $index . '-' . uniqid()) . '.' . $file_extension;
                file_put_contents($tmp_name, $content);

                $file_array = array(
                    'name' => 'aimatic-' . uniqid() . '.' . $file_extension,
                    'tmp_name' => $tmp_name
                );

                // Upload to Media Library
                $id = media_handle_sideload($file_array, $items[$index]['post_id'], $items[$index]['alt']);
                
                if (!is_wp_error($id)) {
                    update_post_meta($id, '_wp_attachment_image_alt', $items[$index]['alt']);
                    $results[$index] = $id;
                } else {
                    error_log('AIMatic Batch: Media upload failed for index ' . $index . ': ' . $id->get_error_message());
                    $results[$index] = false;
                }
                
                // Cleanup temp file (media_handle_sideload moves it usually, but if it failed/copied verify)
                 // media_handle_sideload handles moving or copying. If it failed, we must delete.
                 if (file_exists($tmp_name)) @unlink($tmp_name);

            } else {
                error_log('AIMatic Batch: HTTP Request failed for index ' . $index . ' Code: ' . $http_code);
                $results[$index] = false;
            }

            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);
        }

        curl_multi_close($mh);
        
        return $results;
    }
