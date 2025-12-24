<?php
/*
 Plugin Name: XU-AIMatic
 Plugin URI: https://www.fiverr.com/rankwriter2020?public_mode=true
 Description: AI Writer plugin with OpenRouter integration, real-time writing, and direct publishing.
 Version: 1.1.5
 Author: rankwriter2020
 Author URI: https://www.fiverr.com/rankwriter2020?public_mode=true
 License: GPLv2 or later
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('AIMATIC_WRITER_PATH', plugin_dir_path(__FILE__));
define('AIMATIC_WRITER_URL', plugin_dir_url(__FILE__));

// Include image handler
require_once AIMATIC_WRITER_PATH . 'includes/image-handler.php';
require_once AIMATIC_WRITER_PATH . 'includes/class-aimatic-logger.php';
require_once AIMATIC_WRITER_PATH . 'includes/class-aimatic-file-parser.php';
require_once AIMATIC_WRITER_PATH . 'includes/class-aimatic-engine.php';
require_once AIMATIC_WRITER_PATH . 'includes/class-aimatic-campaigns.php';

// Include Updater Class
require_once AIMATIC_WRITER_PATH . 'includes/class-aimatic-updater.php';

// Init Classes
add_action('plugins_loaded', array('AIMatic_Logger', 'init'));
add_action('plugins_loaded', array('AIMatic_Campaigns', 'init'));

// Define Update URL (Currently hosting on GitHub Raw, change this later for your own server)
define('AIMATIC_UPDATE_URL', 'https://raw.githubusercontent.com/xudevstudio/XU-AIMatic-Writer-Plugin/main/update.json');

// Init Updater
if (is_admin()) {
    $updater = new AIMatic_Updater(__FILE__, AIMATIC_UPDATE_URL);
    $updater->init();
}

// Enqueue scripts and styles
function aimatic_writer_enqueue_scripts($hook) {
    if (strpos($hook, 'aimatic-writer') === false) {
        return;
    }

    wp_enqueue_style('aimatic-writer-style', AIMATIC_WRITER_URL . 'assets/style.css', array(), '1.1.5');
    wp_enqueue_script('marked-js', 'https://cdn.jsdelivr.net/npm/marked/marked.min.js', array(), '4.3.0', true);
    wp_enqueue_script('aimatic-writer-script', AIMATIC_WRITER_URL . 'assets/script.js', array('jquery', 'marked-js'), '1.1.5', true);

    wp_localize_script('aimatic-writer-script', 'aimatic_writer_vars', array(
        'api_key' => get_option('aimatic_writer_api_key'),
        'model_id' => get_option('aimatic_writer_model_id'),
        'auto_images' => get_option('aimatic_writer_auto_images'),
        'image_count' => get_option('aimatic_writer_image_count', 3), // Default 3
        'heading_interval' => get_option('aimatic_writer_heading_interval', 2), // Default 2
        'video_count' => get_option('aimatic_writer_video_count', 1), // Default 1
        'enable_video' => get_option('aimatic_writer_enable_youtube', 1), // Default On
        'nonce' => wp_create_nonce('aimatic_writer_nonce'),
        'ajax_url' => admin_url('admin-ajax.php'),
        'pollinations_width' => get_option('aimatic_writer_pollinations_width', 1200),
        'pollinations_height' => get_option('aimatic_writer_pollinations_height', 636)
    ));
}
add_action('admin_enqueue_scripts', 'aimatic_writer_enqueue_scripts');

// Add Admin Menu
function aimatic_writer_menu() {
    // Auto Writer Page (Campaigns)
    add_menu_page(
        'AIMatic Writer',
        'AIMatic Writer',
        'manage_options',
        'aimatic-writer',
        'aimatic_writer_page_html',
        'dashicons-edit',
        20
    );

    // Settings Page
    add_submenu_page(
        'aimatic-writer',
        'Settings',
        'Settings',
        'manage_options',
        'aimatic-writer-settings',
        'aimatic_writer_settings_page_html'
    );
    
    // Test API Page
    add_submenu_page(
        'aimatic-writer',
        'Test API',
        'Test API',
        'manage_options',
        'aimatic-writer-test-api',
        'aimatic_writer_test_api_page_html'
    );
}
add_action('admin_menu', 'aimatic_writer_menu');

// Render Writer Page
function aimatic_writer_page_html() {
    include AIMATIC_WRITER_PATH . 'admin/writer-page.php';
}

// Render Settings Page
function aimatic_writer_settings_page_html() {
    include AIMATIC_WRITER_PATH . 'admin/settings-page.php';
}

// Render Test API Page
function aimatic_writer_test_api_page_html() {
    include AIMATIC_WRITER_PATH . 'admin/test-api-page.php';
}

// Register Settings
function aimatic_writer_register_settings() {
    register_setting('aimatic_writer_settings_group', 'aimatic_writer_ai_provider'); // openrouter, gemini
    register_setting('aimatic_writer_settings_group', 'aimatic_writer_api_key'); // OpenRouter Key
    register_setting('aimatic_writer_settings_group', 'aimatic_writer_gemini_key'); // Google Gemini Key
    register_setting('aimatic_writer_settings_group', 'aimatic_writer_gemini_model'); // Custom Gemini Model
    register_setting('aimatic_writer_settings_group', 'aimatic_writer_gemini_model'); // Custom Gemini Model
    register_setting('aimatic_writer_settings_group', 'aimatic_writer_model_id'); // OpenRouter Model
    
    // OpenAI & Z.AI (Zhipu)
    register_setting('aimatic_writer_settings_group', 'aimatic_writer_openai_key');
    register_setting('aimatic_writer_settings_group', 'aimatic_writer_openai_model');
    register_setting('aimatic_writer_settings_group', 'aimatic_writer_zhipu_key');
    register_setting('aimatic_writer_settings_group', 'aimatic_writer_zhipu_model');

    register_setting('aimatic_writer_settings_group', 'aimatic_writer_cron_enabled'); // New Master Switch
    register_setting('aimatic_writer_settings_group', 'aimatic_writer_auto_images');
    register_setting('aimatic_writer_settings_group', 'aimatic_writer_image_source');
    register_setting('aimatic_writer_settings_group', 'aimatic_writer_auto_images');
    register_setting('aimatic_writer_settings_group', 'aimatic_writer_image_source');
    register_setting('aimatic_writer_settings_group', 'aimatic_writer_deepai_key');
    register_setting('aimatic_writer_settings_group', 'aimatic_writer_pexels_key'); // NEW: Pexels Key
    register_setting('aimatic_writer_settings_group', 'aimatic_writer_pixabay_key'); // NEW: Pixabay Key
    register_setting('aimatic_writer_settings_group', 'aimatic_writer_unsplash_key'); // NEW: Unsplash Key
    register_setting('aimatic_writer_settings_group', 'aimatic_writer_image_count');
    register_setting('aimatic_writer_settings_group', 'aimatic_writer_heading_interval');
    register_setting('aimatic_writer_settings_group', 'aimatic_writer_heading_interval');
    register_setting('aimatic_writer_settings_group', 'aimatic_writer_images_to_fetch');
    register_setting('aimatic_writer_settings_group', 'aimatic_writer_pollinations_width');
    register_setting('aimatic_writer_settings_group', 'aimatic_writer_pollinations_height');
    
    // Youtube & Enhanced Media
    register_setting('aimatic_writer_settings_group', 'aimatic_writer_enable_youtube'); // NEW Global Toggle
    register_setting('aimatic_writer_settings_group', 'aimatic_writer_youtube_key');
    register_setting('aimatic_writer_settings_group', 'aimatic_writer_video_count');
}
add_action('admin_init', 'aimatic_writer_register_settings');

// Includes
require_once AIMATIC_WRITER_PATH . 'includes/class-aimatic-engine.php';
require_once AIMATIC_WRITER_PATH . 'includes/class-aimatic-campaigns.php';
require_once AIMATIC_WRITER_PATH . 'includes/image-handler.php';
require_once AIMATIC_WRITER_PATH . 'includes/class-aimatic-youtube.php'; 

// AJAX Handler for Force Cron
add_action('wp_ajax_aimatic_writer_force_cron', 'aimatic_writer_handle_force_cron');
function aimatic_writer_handle_force_cron() {
    check_ajax_referer('aimatic_cron_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }
    
    // Instantiate and run
    $campaigns = new AIMatic_Campaigns();
    $campaigns->process_campaigns(true); // Force run
    
    wp_send_json_success('Cron Executed');
}

// AJAX Handler for Test API
// AJAX Handler for Test API
add_action('wp_ajax_aimatic_writer_test_api', 'aimatic_writer_handle_test_api');
function aimatic_writer_handle_test_api() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }
    
    $api_key = sanitize_text_field($_POST['api_key']);
    $model = isset($_POST['model']) ? sanitize_text_field($_POST['model']) : '';
    $provider = isset($_POST['provider']) ? sanitize_text_field($_POST['provider']) : 'openrouter';
    
    if (empty($api_key)) {
        wp_send_json_error('API Key is empty.');
    }

    $url = '';
    $headers = array('Content-Type' => 'application/json');
    $body_data = array();

    if ($provider === 'openai') {
        $url = 'https://api.openai.com/v1/chat/completions';
        $headers['Authorization'] = 'Bearer ' . $api_key;
        $body_data = array(
            'model' => !empty($model) ? $model : 'gpt-4o-mini',
            'messages' => array(array('role' => 'user', 'content' => 'Say "Hello OpenAI" if you can hear me.'))
        );
    } elseif ($provider === 'zhipu') {
        // Zhipu Test Logic (JWT Generation)
        if (strpos($api_key, '.')) {
             list($id, $secret) = explode('.', $api_key);
             $now = time() * 1000;
             $exp = $now + 3600000; // 1hr
             $header = base64_encode(json_encode(array('alg' => 'HS256', 'sign_type' => 'SIGN')));
             $payload = base64_encode(json_encode(array('api_key' => $id, 'exp' => $exp, 'timestamp' => $now)));
             
             // Base64URL safe
             $header = rtrim(strtr($header, '+/', '-_'), '=');
             $payload = rtrim(strtr($payload, '+/', '-_'), '=');
             
             $signature = hash_hmac('sha256', $header . "." . $payload, $secret, true);
             $signature = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
             $token = $header . "." . $payload . "." . $signature;
             
             $url = 'https://api.z.ai/api/paas/v4/chat/completions';
             $headers['Authorization'] = 'Bearer ' . $token;
             $body_data = array(
                 'model' => !empty($model) ? $model : 'glm-4.6',
                 'messages' => array(array('role' => 'user', 'content' => 'Say "Hello GLM"'))
             );
        } else {
             wp_send_json_error('Invalid Zhipu API Key format.');
        }

    } elseif ($provider === 'gemini') {
         // Gemini Direct (via URL param or Header? Usually header for Studio keys now)
         // Actually, Google Generative AI API is: https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=API_KEY
         // But for test consistency let's stick to OpenRouter or check Engine implementation.
         // Let's rely on class-aimatic-engine.php logic? No, this is simple test.
         // Let's implement basics for Gemini 
         $model_name = !empty($model) ? $model : 'gemini-2.0-flash-exp';
         $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model_name}:generateContent?key=" . $api_key;
         $headers = array('Content-Type' => 'application/json');
         $body_data = array(
             'contents' => array(
                 array('parts' => array(array('text' => 'Say hello')))
             )
         );
         $body_data = array(
             'contents' => array(
                 array('parts' => array(array('text' => 'Say hello')))
             )
         );
    } elseif ($provider === 'pollinations') {
        $url = 'https://text.pollinations.ai/';
        $body_data = array(
            'messages' => array(array('role' => 'user', 'content' => 'Say "Hello Pollinations"')),
            'model' => 'openai'
        );
        $headers['Content-Type'] = 'application/json';
        
    } elseif ($provider === 'aihorde') {
        $url = 'https://stablehorde.net/api/v2/generate/text/async';
        $body_data = array(
            'prompt' => 'Say "Hello AI Horde"',
            'params' => array('n' => 1, 'max_context_length' => 1024, 'max_length' => 50),
            'models' => array('koboldcpp/Llama-3-8B-Instruct'),
            'apikey' => '0000000000'
        );
        $headers = array(
            'Content-Type' => 'application/json',
            'apikey' => '0000000000',
            'Client-Agent' => 'AIMaticPlugin:1.0:test'
        );
    } else {
        // Default: OpenRouter
        $url = 'https://openrouter.ai/api/v1/chat/completions';
        $headers['Authorization'] = 'Bearer ' . $api_key;
        $headers['HTTP-Referer'] = home_url();
        $headers['X-Title'] = get_bloginfo('name');
        
        $body_data = array(
            'model' => !empty($model) ? $model : 'google/gemini-2.0-flash-exp:free',
            'messages' => array(array('role' => 'user', 'content' => 'Say "Hello" if you can hear me.'))
        );
    }

    $response = wp_remote_post($url, array(
        'headers' => $headers,
        'body' => json_encode($body_data),
        'timeout' => 15,
        'sslverify' => false
    ));

    if (is_wp_error($response)) {
        wp_send_json_error('WP Error: ' . $response->get_error_message());
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    
    // Normalize Error Response
    if (isset($data['error'])) {
        $msg = is_array($data['error']) ? (isset($data['error']['message']) ? $data['error']['message'] : json_encode($data['error'])) : $data['error'];
        wp_send_json_error('API Error: ' . $msg);
    }
    
    // Normalize Success Response
    $reply = '';
    if ($provider === 'gemini') {
         if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
             $reply = $data['candidates'][0]['content']['parts'][0]['text'];
         }
    } else {
         if (isset($data['choices'][0]['message']['content'])) {
             $reply = $data['choices'][0]['message']['content'];
         }
    }

    // --- Pollinations Response ---
    if ($provider === 'pollinations') {
        // Pollinations returns raw string usually, but sometimes JSON if error?
        // wp_remote_retrieve_body returned $body.
        // It is raw text.
        $reply = $body; 
    }

    // --- AI Horde Response (Async Polling) ---
    if ($provider === 'aihorde') {
        if (isset($data['id'])) {
            $task_id = $data['id'];
            // Poll for result (Quick version for Test)
            $waited = 0;
            while($waited < 60) { // Wait up to 60s for test
                sleep(2);
                $waited += 2;
                $check = wp_remote_get("https://stablehorde.net/api/v2/generate/text/status/$task_id", array(
                    'headers' => array('apikey' => '0000000000', 'Client-Agent' => 'AIMaticPlugin:1.0:test'),
                    'sslverify' => false
                ));
                $stat = json_decode(wp_remote_retrieve_body($check), true);
                
                if (isset($stat['done']) && $stat['done']) {
                    if (isset($stat['generations'][0]['text'])) {
                        $reply = $stat['generations'][0]['text'];
                    }
                    break;
                }
            }
            if (empty($reply)) $reply = "Request sent to Horde (ID: $task_id), but timed out waiting for demo response. It usually works in background!";
        } else {
            // Error handled by generic error check above if 'error' key exists, 
            // but Horde uses 'message'.
             if (isset($data['message'])) {
                 wp_send_json_error('Horde Error: ' . $data['message']);
             }
        }
    }

    if (!empty($reply)) {
        wp_send_json_success(array('message' => $reply));
    } else {
        wp_send_json_error('Invalid Response: ' . substr($body, 0, 100));
    }
}

// AJAX Handler for Testing Provider Keys
function aimatic_writer_test_provider_key() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }

    $provider = sanitize_text_field($_POST['provider']);
    $api_key = sanitize_text_field($_POST['api_key']);

    if (empty($api_key)) wp_send_json_error('Missing API Key');

    $url = '';
    $headers = array();

    switch ($provider) {
        case 'pexels':
            $url = 'https://api.pexels.com/v1/search?query=test&per_page=1';
            $headers = array('Authorization' => $api_key);
            break;
        case 'pixabay':
            // Pixabay passes key in URL
            $url = 'https://pixabay.com/api/?key=' . $api_key . '&q=test&image_type=photo';
            break;
        case 'unsplash':
            $url = 'https://api.unsplash.com/search/photos?page=1&query=test&per_page=1';
            $headers = array('Authorization' => 'Client-ID ' . $api_key);
            break;
        case 'youtube':
            $url = 'https://www.googleapis.com/youtube/v3/search?part=snippet&q=test&type=video&key=' . $api_key . '&maxResults=1';
            break;
        default:
            wp_send_json_error('Unknown provider');
    }

    $args = array(
        'timeout' => 10,
        'sslverify' => false
    );
    
    if (!empty($headers)) {
        $args['headers'] = $headers;
    }

    $response = wp_remote_get($url, $args);

    if (is_wp_error($response)) {
        wp_send_json_error('Connection Error: ' . $response->get_error_message());
    }

    $code = wp_remote_retrieve_response_code($response);
    if ($code >= 200 && $code < 300) {
        wp_send_json_success('Connection Successful!');
    } else {
        $body = wp_remote_retrieve_body($response);
        wp_send_json_error('API Error (' . $code . '): ' . substr($body, 0, 100));
    }
}
add_action('wp_ajax_aimatic_writer_test_provider_key', 'aimatic_writer_test_provider_key');

// AJAX Handler for Publishing Post
function aimatic_writer_publish_post() {
    check_ajax_referer('aimatic_writer_nonce', 'nonce');

    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Permission denied');
    }

    $title = sanitize_text_field($_POST['title']);
    $content = wp_kses_post($_POST['content']);
    $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'publish';
    $date = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : '';
    
    // New Advanced Options
    $author_id = isset($_POST['author_id']) && !empty($_POST['author_id']) ? intval($_POST['author_id']) : get_current_user_id();
    $category_id = isset($_POST['category_id']) && !empty($_POST['category_id']) ? intval($_POST['category_id']) : 0;

    $post_data = array(
        'post_title'    => $title,
        'post_content'  => $content,
        'post_status'   => $status,
        'post_author'   => $author_id,
        'post_type'     => 'post'
    );
    
    if ($category_id > 0) {
        $post_data['post_category'] = array($category_id);
    }

    if ($status === 'future' && !empty($date)) {
        $post_data['post_date'] = $date;
        $post_data['post_date_gmt'] = get_gmt_from_date($date);
    }

    // DUPLICATE CHECK
    $existing = new WP_Query(array(
        'post_type' => 'post',
        'title' => $title,
        'post_status' => 'any',
        'fields' => 'ids',
        'posts_per_page' => 1,
        'no_found_rows' => true
    ));

    if ($existing->have_posts()) {
         wp_send_json_error("A post with this title already exists.");
    }

    $post_id = wp_insert_post($post_data);

    if (is_wp_error($post_id)) {
        wp_send_json_error($post_id->get_error_message());
    } else {
        // Handle featured image if provided
        if (isset($_POST['featured_image_id']) && !empty($_POST['featured_image_id'])) {
            set_post_thumbnail($post_id, intval($_POST['featured_image_id']));
        }
        
        wp_send_json_success(array('post_id' => $post_id, 'post_url' => get_permalink($post_id)));
    }
}
add_action('wp_ajax_aimatic_writer_publish', 'aimatic_writer_publish_post');

// AJAX Handler for Fetching Images
function aimatic_writer_fetch_images() {
    check_ajax_referer('aimatic_writer_nonce', 'nonce');

    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Permission denied');
    }

    $query = sanitize_text_field($_POST['query']);
    $count = isset($_POST['count']) ? intval($_POST['count']) : 3;

    // Check if auto images is enabled
    if (!get_option('aimatic_writer_auto_images')) {
        wp_send_json_error('Auto images is not enabled in settings');
    }

    // Check if any API key is configured
    $pixabay_key = get_option('aimatic_writer_pixabay_key');
    $pexels_key = get_option('aimatic_writer_pexels_key');
    $unsplash_key = get_option('aimatic_writer_unsplash_key');
    
    if (empty($pixabay_key) && empty($pexels_key) && empty($unsplash_key)) {
        wp_send_json_error('No image API keys configured. Please add at least one API key in settings.');
    }

    $width = get_option('aimatic_writer_pollinations_width', 1200);
    $height = get_option('aimatic_writer_pollinations_height', 636);

    $images = AIMatic_Image_Handler::fetch_images($query, $count, $width, $height);

    if ($images) {
        wp_send_json_success($images);
    } else {
        wp_send_json_error('No images found. Please check your API keys and try again.');
    }
}
add_action('wp_ajax_aimatic_writer_fetch_images', 'aimatic_writer_fetch_images');

// AJAX Handler for Fetching Video
function aimatic_writer_fetch_video() {
    check_ajax_referer('aimatic_writer_nonce', 'nonce');

    if (!current_user_can('edit_posts')) {
        wp_send_json_error('Permission denied');
    }

    $query = sanitize_text_field($_POST['query']);
    
    $youtube = new AIMatic_YouTube();
    $videos = $youtube->search_videos($query, 1);
    
    if (is_wp_error($videos)) {
        wp_send_json_error('YouTube API Error: ' . $videos->get_error_message());
    }
    
    if (!empty($videos)) {
        $video_id = $videos[0];
        $embed_html = AIMatic_YouTube::get_embed_html($video_id);
        wp_send_json_success(array(
            'video_id' => $video_id,
            'embed_html' => $embed_html
        ));
    } else {
        wp_send_json_error('No videos found.');
    }
}
add_action('wp_ajax_aimatic_writer_fetch_video', 'aimatic_writer_fetch_video');

// AJAX Handler for Uploading Image to Media Library
function aimatic_writer_upload_image() {
    check_ajax_referer('aimatic_writer_nonce', 'nonce');

    if (!current_user_can('upload_files')) {
        wp_send_json_error('Permission denied');
    }

    $image_url = esc_url_raw($_POST['image_url']);
    $alt_text = sanitize_text_field($_POST['alt_text']);

    $attachment_id = AIMatic_Image_Handler::upload_to_media_library($image_url, $alt_text);

    if ($attachment_id) {
        $image_data = array(
            'id' => $attachment_id,
            'url' => wp_get_attachment_url($attachment_id)
        );
        wp_send_json_success($image_data);
    } else {
        wp_send_json_error('Failed to upload image');
    }
}
add_action('wp_ajax_aimatic_writer_upload_image', 'aimatic_writer_upload_image');



// AJAX Handler for Saving Campaigns
function aimatic_writer_save_campaign() {
    check_ajax_referer('aimatic_writer_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }

    $campaigns_handler = new AIMatic_Campaigns();
    $id = $campaigns_handler->save_campaign($_POST);

    if ($id) {
        wp_send_json_success(array('id' => $id, 'message' => 'Campaign saved successfully!'));
    } else {
        wp_send_json_error('Failed to save campaign');
    }
}
add_action('wp_ajax_aimatic_writer_save_campaign', 'aimatic_writer_save_campaign');

// AJAX Handler for Deleting Campaigns
function aimatic_writer_delete_campaign() {
    check_ajax_referer('aimatic_writer_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }

    $id = sanitize_text_field($_POST['id']);
    $campaigns_handler = new AIMatic_Campaigns();
    
    if ($campaigns_handler->delete_campaign($id)) {
        wp_send_json_success(array('message' => 'Campaign deleted successfully!'));
    } else {
        wp_send_json_error('Failed to delete campaign');
    }
}

add_action('wp_ajax_aimatic_writer_delete_campaign', 'aimatic_writer_delete_campaign');

// AJAX Handler for Duplicating Campaigns
function aimatic_writer_duplicate_campaign() {
    check_ajax_referer('aimatic_writer_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }

    $id = sanitize_text_field($_POST['id']);
    $campaigns_handler = new AIMatic_Campaigns();
    
    if ($campaigns_handler->duplicate_campaign($id)) {
        wp_send_json_success(array('message' => 'Campaign duplicated successfully!'));
    } else {
        wp_send_json_error('Failed to duplicate campaign');
    }
}
add_action('wp_ajax_aimatic_writer_duplicate_campaign', 'aimatic_writer_duplicate_campaign');

// AJAX Handler for Running Campaign Now
function aimatic_writer_run_campaign_now() {
    check_ajax_referer('aimatic_writer_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }

    $id = sanitize_text_field($_POST['id']);
    $campaigns_handler = new AIMatic_Campaigns();
    
    // force_run_campaign returns void or error in current implementation, 
    // but let's check if it generated an output. 
    // Actually run_campaign does internal error logging but returns void on success path normally.
    // I should probably update force_run_campaign to return something meaningful, 
    // but for now, if it doesn't crash, it's likely success.
    
    // Wait, force_run_campaign calls run_campaign. 
    // Let's modify run_campaign to return the post_id or WP_Error for better feedback.
    
    $result = $campaigns_handler->force_run_campaign($id);

    if (is_wp_error($result)) {
        wp_send_json_error($result->get_error_message());
    } else {
        $permalink = get_permalink($result);
        wp_send_json_success(array(
            'message' => 'Campaign ran successfully! Article published.',
            'link' => $permalink
        ));
    }
}
add_action('wp_ajax_aimatic_writer_run_campaign_now', 'aimatic_writer_run_campaign_now');

// AJAX Handler for Clearing Errors
function aimatic_writer_clear_error() {
    check_ajax_referer('aimatic_writer_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }
    delete_option('aimatic_last_error');
    wp_send_json_success();
}
add_action('wp_ajax_aimatic_writer_clear_error', 'aimatic_writer_clear_error');

// AJAX Handler for Downloading Logs
add_action('wp_ajax_aimatic_writer_download_log', 'aimatic_writer_download_log');
function aimatic_writer_download_log() {
    if (!current_user_can('manage_options')) {
        wp_die('Permission denied');
    }
    
    $file = AIMatic_Logger::get_log_path();
    if (file_exists($file)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="aimatic-activity.log"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    } else {
        wp_die('Log file not found or empty.');
    }
}

// AJAX Handler for Clearing Logs
add_action('wp_ajax_aimatic_writer_clear_log', 'aimatic_writer_clear_log');
function aimatic_writer_clear_log() {
    check_ajax_referer('aimatic_writer_nonce', 'nonce');
    if (!current_user_can('manage_options')) wp_send_json_error('Permission denied');
    
    if (AIMatic_Logger::clear_logs()) {
        wp_send_json_success();
    } else {
        wp_send_json_error('Failed to clear logs.');
    }
}

// AJAX Hook for Fetching Keywords
add_action('wp_ajax_aimatic_writer_get_campaign_keywords', array(new AIMatic_Campaigns(), 'handle_get_campaign_keywords'));

// --- Import / Export Handlers ---

// Export Settings
add_action('wp_ajax_aimatic_writer_export_settings', 'aimatic_writer_export_settings');
function aimatic_writer_export_settings() {
    if (!current_user_can('manage_options')) wp_die('Permission denied');
    check_ajax_referer('aimatic_export_nonce', 'nonce');

    $options_to_export = array(
        'aimatic_writer_ai_provider',
        'aimatic_writer_api_key',
        'aimatic_writer_gemini_key',
        'aimatic_writer_gemini_model',
        'aimatic_writer_model_id',
        'aimatic_writer_openai_key',
        'aimatic_writer_openai_model',
        'aimatic_writer_zhipu_key',
        'aimatic_writer_zhipu_model',
        'aimatic_writer_cron_enabled',
        'aimatic_writer_auto_images',
        'aimatic_writer_image_source',
        'aimatic_writer_deepai_key',
        'aimatic_writer_pexels_key',
        'aimatic_writer_pixabay_key',
        'aimatic_writer_unsplash_key',
        'aimatic_writer_image_count',
        'aimatic_writer_heading_interval',
        'aimatic_writer_images_to_fetch',
        'aimatic_writer_pollinations_width',
        'aimatic_writer_pollinations_height',
        'aimatic_writer_enable_youtube',
        'aimatic_writer_youtube_key',
        'aimatic_writer_video_count'
    );

    $data = array();
    foreach ($options_to_export as $opt) {
        $data[$opt] = get_option($opt, '');
    }

    $filename = 'aimatic-settings-' . date('Y-m-d') . '.json';
    
    header('Content-Description: File Transfer');
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

// Import Settings
add_action('wp_ajax_aimatic_writer_import_settings', 'aimatic_writer_import_settings');
function aimatic_writer_import_settings() {
    if (!current_user_can('manage_options')) wp_send_json_error('Permission denied');
    check_ajax_referer('aimatic_import_nonce', 'nonce');
    
    $json = stripslashes($_POST['json_data']); // WP handles slashes
    $data = json_decode($json, true);
    
    if (empty($data) || !is_array($data)) {
        wp_send_json_error('Invalid JSON data');
    }

    $allowed_options = array(
        'aimatic_writer_ai_provider',
        'aimatic_writer_api_key',
        'aimatic_writer_gemini_key',
        'aimatic_writer_gemini_model',
        'aimatic_writer_model_id',
        'aimatic_writer_openai_key',
        'aimatic_writer_openai_model',
        'aimatic_writer_zhipu_key',
        'aimatic_writer_zhipu_model',
        'aimatic_writer_cron_enabled',
        'aimatic_writer_auto_images',
        'aimatic_writer_image_source',
        'aimatic_writer_deepai_key',
        'aimatic_writer_pexels_key',
        'aimatic_writer_pixabay_key',
        'aimatic_writer_unsplash_key',
        'aimatic_writer_image_count',
        'aimatic_writer_heading_interval',
        'aimatic_writer_images_to_fetch',
        'aimatic_writer_pollinations_width',
        'aimatic_writer_pollinations_height',
        'aimatic_writer_enable_youtube',
        'aimatic_writer_youtube_key',
        'aimatic_writer_video_count'
    );

    foreach ($data as $key => $value) {
        if (in_array($key, $allowed_options)) {
            update_option($key, sanitize_text_field($value));
        }
    }
    
    wp_send_json_success('Settings imported!');
}

// Export Campaigns
add_action('wp_ajax_aimatic_writer_export_campaigns', 'aimatic_writer_export_campaigns');
function aimatic_writer_export_campaigns() {
    if (!current_user_can('manage_options')) wp_die('Permission denied');
    check_ajax_referer('aimatic_export_nonce', 'nonce');
    
    $campaigns = get_option('aimatic_writer_campaigns', array());
    
    $filename = 'aimatic-campaigns-' . date('Y-m-d') . '.json';
    
    header('Content-Description: File Transfer');
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo json_encode($campaigns, JSON_PRETTY_PRINT);
    exit;
}

// Import Campaigns
add_action('wp_ajax_aimatic_writer_import_campaigns', 'aimatic_writer_import_campaigns');
function aimatic_writer_import_campaigns() {
    if (!current_user_can('manage_options')) wp_send_json_error('Permission denied');
    check_ajax_referer('aimatic_import_nonce', 'nonce');
    
    $json = stripslashes($_POST['json_data']);
    $new_campaigns = json_decode($json, true);
    
    if (empty($new_campaigns) || !is_array($new_campaigns)) {
        wp_send_json_error('Invalid JSON data');
    }
    
    $existing_campaigns = get_option('aimatic_writer_campaigns', array());
    
    // Merge Strategy: Overwrite by ID
    foreach ($new_campaigns as $id => $camp) {
        // Sanitize check
        if (!isset($camp['id'])) $camp['id'] = $id; // Ensure ID matches
        $existing_campaigns[$id] = $camp;
    }
    
    update_option('aimatic_writer_campaigns', $existing_campaigns);
    
    wp_send_json_success('Campaigns imported!');
}


