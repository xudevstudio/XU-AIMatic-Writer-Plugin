<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIMatic_Engine {

    private $api_key;
    private $model_id;

    public function __construct() {
        $this->api_key = get_option('aimatic_writer_api_key');
        $this->model_id = get_option('aimatic_writer_model_id', 'openai/gpt-3.5-turbo');
    }

    /**
     * Generate an article based on a topic
     */

        
    /**
     * Generate an article based on a topic
     */
    /**
     * Generate an article based on a topic
     * Updated to support advanced options: Internal Links, Outbound Links, Read Also
     */
    public function generate_article($topic, $custom_prompt = '', $options = array()) {
        // Build base system prompt based on Style
        $style = isset($options['article_style']) ? $options['article_style'] : 'generic';
        $style_map = array(
            'how-to' => "Write a 'How-To' guide/tutorial. Structure with clear steps (Step 1, Step 2...), using H2/H3 headings. Focus on actionable advice.",
            'listicle' => "Write a 'Listicle' article (e.g., Top 10, Best 7). Use H2 for each item in the list. Make it engaging and easy to skim.",
            'informative' => "Write an Educational/Informative article. Explain concepts clearly (What is, How it works, Benefits). Use definitions and bullet points.",
            'guide' => "Write an 'Ultimate Guide'. This should be long-form, comprehensive, and cover every aspect of the topic depth. Use detailed H2/H3 hierarchy.",
            'comparison' => "Write a Comparison article (X vs Y). structured with features, pros/cons, and a final verdict/recommendation.",
            'review' => "Write a comprehensive Product/Service Review. Include Features, Pros & Cons, Pricing, and a Final Verdict.",
            'trend' => "Write a News/Trend article. Focus on what's new, why it matters, and future implications. Keep the tone fresh and urgent.",
            'case-study' => "Write a Case Study / Success Story format. Structure: Problem -> Solution -> Results. Use a narrative tone.",
            'editorial' => "Write an Opinion/Editorial piece. Express a strong, expert viewpoint. Use persuasive language and first-person analysis where appropriate.",
            'faq' => "Write an FAQ-style article. Structure the entire content as questions (H2) and answers. Target rich snippets."
        );
        
        $base_instruction = isset($style_map[$style]) ? $style_map[$style] : "Write a comprehensive, SEO-optimized article.";
        
        $system_prompt = "You are an expert content writer. {$base_instruction} Topic: '$topic'. \n\nIMPORTANT STRUCTURE RULES:\n1. Use HTML formatting (<p>, <h2>, <h3>, <ul>, <li>).\n2. **You MUST use at least 3-4 <h2> headings** to structure the content. This is critical for formatting.\n3. Do NOT include the Main Title (H1) at the beginning.\n4. Start directly with the Introduction.\n5. Do NOT convert the first paragraph into a heading.";
        
        // --- 1. Internal Linking Context ---
        $internal_links_array = array();
        
        // --- 1. Internal Linking Context ---
        $internal_links_array = array();
        
        // Always try to fetch context if either option is enabled
        if (!empty($options['internal_links']) || !empty($options['read_also'])) {
            $internal_links_array = $this->fetch_internal_links_context($topic);
            
            if (!empty($internal_links_array)) {
                $context_str = "";
                foreach ($internal_links_array as $link) {
                    $context_str .= "- Title: " . $link['title'] . " | URL: " . $link['url'] . "\n";
                }

                $system_prompt .= "\n\nCONTEXT - EXISTING ARTICLES (Internal Links): \n" . $context_str;
                
                if (!empty($options['internal_links'])) {
                     $system_prompt .= "\n\nINSTRUCTION: **You MUST include 2-3 internal links** to the articles listed above. Insert them naturally within the text using the exact titles or close variations as anchor text.";
                }
            } else {
                 AIMatic_Logger::log("DEBUG: No internal links found for topic '$topic'", 'WARNING');
            }
        }
        
        // --- 2. Outbound Links ---
        if (!empty($options['outbound_links'])) {
            $system_prompt .= "\n\nINSTRUCTION: **You MUST include 2-3 outbound hyperlinks** to high-authority sources (e.g., Wikipedia, Forbes, reputable news sites) that substantiate claims. Ensure the href attributes are valid URLs.";
        }

        if (!empty($custom_prompt)) {
            $system_prompt .= "\n\nCustom Instructions: " . $custom_prompt;
        }
        
        $content = $this->request_ai_completion($system_prompt, "Topic: " . $topic);
        if (is_wp_error($content)) return $content;
        
        // Post-processing: Remove <H1> if AI ignored instructions
        $content = preg_replace('/<h1[^>]*>.*?<\/h1>/si', '', $content);
        
        // --- FORCE APPEND READ ALSO ---
        if (!empty($options['read_also']) && !empty($internal_links_array)) {
            $read_also_html = "\n\n<h3>Read Also</h3><ul>";
            $count = 0;
            // Use up to 3 links from the context
            foreach ($internal_links_array as $link) {
                if ($count >= 3) break;
                $read_also_html .= "<li><a href='" . esc_url($link['url']) . "'>" . esc_html($link['title']) . "</a></li>";
                $count++;
            }
            $read_also_html .= "</ul>";
            
            // Append to content
            $content .= $read_also_html;
        }
        
        return $content;
    }
    
    /**
     * Helper: Fetch relevant internal posts for context
     */
    private function fetch_internal_links_context($topic) {
        // Simple keyword search
        $keywords = explode(' ', $topic);
        $search_query = isset($keywords[0]) ? $keywords[0] : $topic; // Use first word or full topic
        
        // If topic is long, try to pick the most significant word (basic logic: longest word > 3 chars)
        $longest_word = '';
        foreach ($keywords as $word) {
            if (strlen($word) > strlen($longest_word) && strlen($word) > 3) {
                $longest_word = $word;
            }
        }
        if ($longest_word) $search_query = $longest_word;
        
        $args = array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => 5,
            's' => $search_query,
            'orderby' => 'relevance'
        );
        
        $query = new WP_Query($args);
        $links = array();
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                 $links[] = array(
                    'title' => get_the_title(),
                    'url' => get_permalink()
                );
            }
            wp_reset_postdata();
        } 
        
        // Fallback: Get recent posts if no search match (Ensure we ALWAYS have links)
        if (empty($links)) {
            $recent_args = array(
                'post_type' => 'post',
                'post_status' => 'publish',
                'posts_per_page' => 5,
                'orderby' => 'date',
                'order' => 'DESC'
            );
            $recent = new WP_Query($recent_args);
            if ($recent->have_posts()) {
                while ($recent->have_posts()) {
                    $recent->the_post();
                    $links[] = array(
                        'title' => get_the_title(),
                        'url' => get_permalink()
                    );
                }
                wp_reset_postdata();
            }
        }
        
        return $links;
    }

    /**
     * Generate a list of trending/relevant topics for a category
     */
    public function generate_topics_for_category($category_name, $count = 5) {
        $system_prompt = "You are a creative content strategist. Your task is to generate unique, engaging article titles.";
        $user_prompt = "Generate list of {$count} unique, engaging, and SEO-friendly article titles related to the category: '{$category_name}'. Return ONLY the titles, one per line. Do not number them. IMPORTANT: Do NOT include the category name '{$category_name}' itself in the titles. Make them sound natural and catchy.";
        
        $response = $this->request_ai_completion($system_prompt, $user_prompt);

        if (is_wp_error($response)) {
            return $response;
        }

        // Split by newlines and clean up
        $topics = array_filter(array_map('trim', explode("\n", $response)));
        return array_values($topics);
    }

    /**
     * Generate keywords using AI with custom user prompt
     */
    public function generate_ai_keywords($topic, $custom_prompt = '') {
        // Default strategy if user removed it
        if (empty($custom_prompt)) {
            $custom_prompt = "Generate low-competition, long-tail SEO keywords and semantic LSI keywords.";
        }

        $system_prompt = "You are an SEO expert. Your task is to generate strict SEO keywords for a specific topic. Return ONLY the keywords separated by commas. Do not explain.";
        $user_prompt = "Topic: {$topic}\nStrategy: {$custom_prompt}\n\nProvide 5-8 focused keywords.";

        $response = $this->request_ai_completion($system_prompt, $user_prompt);
        
        if (is_wp_error($response)) {
             return '';
        }
        
        // Clean up: valid CSV
        $content = str_replace(array('.', '"', "\n"), '', $response);
        return $content;
    }

    /**
     * Unified AI Request Handler (Private)
     */
    private function request_ai_completion($system_prompt, $user_prompt) {
        $provider = get_option('aimatic_writer_ai_provider', 'openrouter');

        // --- OPENAI ---
        if ($provider === 'openai') {
            $api_key = get_option('aimatic_writer_openai_key');
            if (empty($api_key)) return new WP_Error('missing_key', 'OpenAI API Key is missing.');
            $model = get_option('aimatic_writer_openai_model', 'gpt-4o-mini');

            $url = 'https://api.openai.com/v1/chat/completions';
            $body = array(
                'model' => $model,
                'messages' => array(
                    array('role' => 'system', 'content' => $system_prompt),
                    array('role' => 'user', 'content' => $user_prompt)
                )
            );
            
            $response = wp_remote_post($url, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode($body),
                'timeout' => 120, 'sslverify' => false
            ));
            
            if (is_wp_error($response)) {
                update_option('aimatic_last_error', $response->get_error_message());
                return $response;
            }

            $data = json_decode(wp_remote_retrieve_body($response), true);
            if (isset($data['choices'][0]['message']['content'])) {
                return $data['choices'][0]['message']['content'];
            } else {
                 $err = isset($data['error']['message']) ? $data['error']['message'] : 'Unknown OpenAI Error';
                 update_option('aimatic_last_error', $err);
                 return new WP_Error('openai_error', $err);
            }
        }

        // --- Z.AI (Zhipu GLM) ---
        if ($provider === 'zhipu') {
            $api_key = get_option('aimatic_writer_zhipu_key');
            if (empty($api_key)) return new WP_Error('missing_key', 'Zhipu API Key is missing.');
            $model = get_option('aimatic_writer_zhipu_model', 'glm-4.6');
            
            // Generate JWT Token
            $token = $this->generate_zhipu_token($api_key);
            if (!$token) return new WP_Error('invalid_key_format', 'Invalid Zhipu API Key format (id.secret expected).');

            $url = 'https://api.z.ai/api/paas/v4/chat/completions';
            $body = array(
                'model' => $model,
                'messages' => array(
                    array('role' => 'system', 'content' => $system_prompt),
                    array('role' => 'user', 'content' => $user_prompt)
                ),
                'temperature' => 0.7 
            );
            
            $response = wp_remote_post($url, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token, // Use generated JWT
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode($body),
                'timeout' => 120, 'sslverify' => false
            ));

            if (is_wp_error($response)) {
                update_option('aimatic_last_error', $response->get_error_message());
                return $response;
            }

            $data = json_decode(wp_remote_retrieve_body($response), true);
            if (isset($data['choices'][0]['message']['content'])) {
                return $data['choices'][0]['message']['content'];
            } else {
                 $err = isset($data['error']['message']) ? $data['error']['message'] : 'Unknown Zhipu Error';
                 update_option('aimatic_last_error', $err);
                 return new WP_Error('zhipu_error', $err);
            }
        }

        // --- GOOGLE GEMINI NATIVE ---
        if ($provider === 'gemini') {
            $raw_keys = get_option('aimatic_writer_gemini_key');
            if (empty($raw_keys)) return new WP_Error('missing_key', 'Google Gemini API Key is missing.');
            
            // Support Multi-Key Rotation
            $keys = array_filter(array_map('trim', explode(',', $raw_keys)));
            if (empty($keys)) return new WP_Error('missing_key', 'No valid Gemini API Keys found.');
            
            // Pick a random key from the pool
            $api_key = $keys[array_rand($keys)];

            // Get selected model, default to 2.0 Flash Exp as requested
            $model = get_option('aimatic_writer_gemini_model', 'gemini-2.0-flash-exp');
            
            // Construct URL dynamically
            $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . $api_key;
            
            $body = array(
                'contents' => array(
                    array('parts' => array(array('text' => $system_prompt . "\n\n" . $user_prompt)))
                )
            );
            
            $max_retries = 2;
            $attempt = 0;
            $response = null;

            while ($attempt <= $max_retries) {
                $attempt++;
                $response = wp_remote_post($url, array(
                    'headers' => array('Content-Type' => 'application/json'),
                'body' => json_encode($body),
                'timeout' => 120, 'sslverify' => false
                ));

                if (is_wp_error($response)) {
                    // Network error, maybe retry?
                    if ($attempt <= $max_retries) { sleep(5); continue; }
                    
                    $err = $response->get_error_message();
                    update_option('aimatic_last_error', $err);
                    return $response;
                }

                $response_code = wp_remote_retrieve_response_code($response);
                
                // Success
                if ($response_code === 200) {
                    break;
                }
                
                // Rate Limit (429) or Overloaded (503)
                if ($response_code === 429 || $response_code === 503) {
                    if ($attempt <= $max_retries) {
                        // Wait 30 seconds before retrying (Gemini Free Tier often asks for >30s)
                        sleep(30); 
                        continue;
                    }
                }
                
                // Other fatal error, stop.
                break;
            }

            if (is_wp_error($response)) { // Should catch final network error
                 update_option('aimatic_last_error', $response->get_error_message());
                 return $response;
            }

            $data = json_decode(wp_remote_retrieve_body($response), true);
            if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                return $data['candidates'][0]['content']['parts'][0]['text'];
            } else {
                update_option('aimatic_last_error', 'Gemini Error: ' . print_r($data, true));
                return new WP_Error('gemini_error', 'Invalid response from Gemini API');
            }
        }

        // --- POLLINATIONS.AI (FREE) ---
        if ($provider === 'pollinations') {
            // Pollinations Text API (GET/POST)
            // Endpoint: https://text.pollinations.ai/{prompt} or POST 
            
            // Note: Pollinations might expect GET for simple use, but supports POST.
            // Let's try POST for larger prompts to avoid URL length limits.
            $url = 'https://text.pollinations.ai/';

            $body = array(
                'messages' => array(
                    array('role' => 'system', 'content' => $system_prompt),
                    array('role' => 'user', 'content' => $user_prompt)
                ),
                'model' => 'openai' // Default helpful model
            );
            
            $response = wp_remote_post($url, array(
                'headers' => array('Content-Type' => 'application/json'),
                'body' => json_encode($body),
                'timeout' => 120, 'sslverify' => false
            ));

            if (is_wp_error($response)) {
                update_option('aimatic_last_error', $response->get_error_message());
                return $response;
            }

            $content = wp_remote_retrieve_body($response);
            
            // Pollinations returns raw text usually, but check if we need to decode
            if (empty($content)) {
                 update_option('aimatic_last_error', 'Pollinations returned empty content.');
                 return new WP_Error('api_error', 'Empty response from Free AI.');
            }
            
            return $content; 
        }

        // --- AI HORDE (FREE) ---
        if ($provider === 'aihorde') {
            // 1. Initiate Async Request
            $url = 'https://stablehorde.net/api/v2/generate/text/async';
            
            $body = array(
                'prompt' => $system_prompt . "\n\n" . $user_prompt,
                'params' => array(
                    'n' => 1,
                    'max_context_length' => 2048,
                    'max_length' => 1024,
                    'temperature' => 0.7
                ),
                'models' => array('koboldcpp/Llama-3-8B-Instruct'), // Default to a popular model, or empty to let Horde pick
                'apikey' => '0000000000' // Anonymous
            );
            
            // Allow models to be flexible if specific one fails? 
            // Better to pass no models if we want max availability, but quality varies.
            // Let's rely on a decent default or fallback.
            
            $response = wp_remote_post($url, array(
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'Client-Agent' => 'AIMaticPlugin:1.0:admin@example.com',
                    'apikey' => '0000000000'
                ),
                'body' => json_encode($body),
                'timeout' => 30, 'sslverify' => false
            ));

            if (is_wp_error($response)) {
                update_option('aimatic_last_error', $response->get_error_message());
                return $response;
            }

            $data = json_decode(wp_remote_retrieve_body($response), true);
            if (!isset($data['id'])) {
                 $err = isset($data['message']) ? $data['message'] : 'Unknown Horde Error';
                 update_option('aimatic_last_error', $err);
                 return new WP_Error('horde_error', $err);
            }
            
            $task_id = $data['id'];
            
            // 2. Polling Loop (Wait for completion)
            $wait_time = 0;
            $max_wait = 90; // 90 seconds timeout
            
            while ($wait_time < $max_wait) {
                sleep(3); // Wait 3 seconds
                $wait_time += 3;
                
                $status_url = "https://stablehorde.net/api/v2/generate/text/status/$task_id";
                 $status_res = wp_remote_get($status_url, array(
                    'headers' => array(
                        'Client-Agent' => 'AIMaticPlugin:1.0:admin@example.com',
                        'apikey' => '0000000000'
                    ),
                    'timeout' => 15, 'sslverify' => false
                ));
                
                if (is_wp_error($status_res)) {
                    // Network glitch? Retry once more or fail
                    continue; 
                }
                
                $status = json_decode(wp_remote_retrieve_body($status_res), true);
                
                if (isset($status['done']) && $status['done'] === true) {
                     // Completed!
                     if (isset($status['generations'][0]['text'])) {
                         return $status['generations'][0]['text'];
                     }
                     break;
                }
                
                if (isset($status['faulted']) && $status['faulted']) {
                    return new WP_Error('horde_fault', 'Horde worker faulted.');
                }
                
                // Still processing...
                if (isset($status['wait_time']) && $status['wait_time'] > 0) {
                    // Logic to maybe adjust sleep?
                }
            }
            
            return new WP_Error('horde_timeout', 'AI Horde request timed out or queue full.');
        }

        // --- OPENROUTER (DEFAULT) ---
        if (empty($this->api_key)) {
            return new WP_Error('no_api_key', 'OpenRouter API Key is missing.');
        }

        $body = array(
            'model' => $this->model_id,
            'messages' => array(
                array('role' => 'system', 'content' => $system_prompt),
                array('role' => 'user', 'content' => $user_prompt)
            )
        );

        $response = wp_remote_post('https://openrouter.ai/api/v1/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
                'HTTP-Referer' => home_url(),
                'X-Title' => get_bloginfo('name')
            ),
            'body' => json_encode($body),
            'timeout' => 120, 'sslverify' => false 
        ));

        if (is_wp_error($response)) {
            $err_msg = 'API Connection Error: ' . $response->get_error_message();
            update_option('aimatic_last_error', $err_msg);
            return $response;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($data['choices'][0]['message']['content'])) {
            return $data['choices'][0]['message']['content'];
        }
        
        $err_msg = 'API Provider Error: ' . (isset($data['error']['message']) ? $data['error']['message'] : 'Unknown');
        update_option('aimatic_last_error', $err_msg);
        return new WP_Error('api_error', $err_msg);
    }

    /**
     * Generate Zhipu AI JWT Token
     * Zhipu API uses a JWT token signed with the API Secret.
     */
    private function generate_zhipu_token($api_key, $ttl_seconds = 3600) {
        if (!strpos($api_key, '.')) return false;
        
        list($id, $secret) = explode('.', $api_key);
        
        $now = time() * 1000; // Milliseconds
        $exp = $now + ($ttl_seconds * 1000);
        
        $header = array(
            'alg' => 'HS256',
            'sign_type' => 'SIGN'
        );
        
        $payload = array(
            'api_key' => $id,
            'exp' => $exp,
            'timestamp' => $now
        );
        
        $base64UrlHeader = $this->base64url_encode(json_encode($header));
        $base64UrlPayload = $this->base64url_encode(json_encode($payload));
        
        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, $secret, true);
        $base64UrlSignature = $this->base64url_encode($signature);
        
        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }
    
    private function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Generate SEO keywords for a category using Google Suggest (Fallback)
     */
    public function generate_seo_keywords($category_name, $count = 10) {
        $endpoint = 'http://suggestqueries.google.com/complete/search?client=chrome&q=' . urlencode($category_name);
        
        $response = wp_remote_get($endpoint, array(
            'timeout' => 10,
            'sslverify' => false 
        ));

        if (is_wp_error($response)) {
            return '';
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        // Google Suggest format: ["query", ["sug1", "sug2", ...], ...]
        if (isset($data[1]) && is_array($data[1])) {
            $suggestions = $data[1];
            // Limit to requested count
            $suggestions = array_slice($suggestions, 0, $count);
            return implode(', ', $suggestions);
        }

        return '';
    }
}
