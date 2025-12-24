<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIMatic_YouTube {

    private $api_key;

    public function __construct() {
        $this->api_key = get_option('aimatic_writer_youtube_key');
    }

    /**
     * Search for videos on YouTube
     * 
     * @param string $query The search term
     * @param int $max_results Number of videos to return
     * @return array|WP_Error Array of video IDs or WP_Error
     */
    public function search_videos($query, $max_results = 2) {
        if (empty($this->api_key)) {
            return new WP_Error('no_key', 'YouTube API Key is missing.');
        }

        $url = 'https://www.googleapis.com/youtube/v3/search';
        $params = array(
            'part' => 'snippet',
            'q' => $query,
            'type' => 'video',
            'key' => $this->api_key,
            'maxResults' => $max_results,
            'relevanceLanguage' => 'en',
            'safeSearch' => 'moderate'
        );

        $response = wp_remote_get(add_query_arg($params, $url), array(
            'timeout' => 15,
            'sslverify' => false 
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        // Debug Logging
        if (class_exists('AIMatic_Logger')) {
             if (isset($data['error'])) {
                 AIMatic_Logger::log("YouTube API Error: " . json_encode($data['error']), 'ERROR');
             } else {
                 AIMatic_Logger::log("YouTube API Value: Found " . (isset($data['items']) ? count($data['items']) : 0) . " items.", 'INFO');
             }
        }

        if (isset($data['error'])) {
            return new WP_Error('api_error', $data['error']['message']);
        }

        $videos = array();
        if (isset($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $item) {
                if (isset($item['id']['videoId'])) {
                    $videos[] = $item['id']['videoId'];
                }
            }
        }

        return $videos;
    }

    /**
     * Get Embed HTML for a video
     */
    public static function get_embed_html($video_id) {
        return sprintf(
            '<div class="aimatic-video-container" style="margin: 20px 0; text-align: center;">
                <iframe width="560" height="315" src="https://www.youtube.com/embed/%s" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen="true"></iframe>
            </div>',
            esc_attr($video_id)
        );
    }
}
