<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIMatic_Campaigns {

    private $option_name = 'aimatic_writer_campaigns';
    private $engine;

    public function __construct() {
        $this->engine = new AIMatic_Engine();
        
        // Register Cron
        add_filter('cron_schedules', array($this, 'add_cron_intervals'));
        add_action('aimatic_campaign_cron_event', array($this, 'process_campaigns'));
        
        // Single Event Hook for Background "Run Now"
        add_action('aimatic_run_campaign_single', array($this, 'process_single_campaign_event'));
        
        // Non-Cron Fallback (Run on Admin Load) - DISABLED to prevent multiple runs on refresh
        // add_action('admin_init', array($this, 'check_campaigns_on_load'));

        // FIX: Force Clear of Stuck "Single" Events on Load (Temporary Safety)
        if (is_admin()) {
            add_action('init', function() {
                // Clear any pending single runs that might be stuck in a loop
                wp_clear_scheduled_hook('aimatic_run_campaign_single');
            });
        }
        
        // AJAX Actions
        add_action('wp_ajax_aimatic_writer_save_campaign', array($this, 'handle_save_campaign'));
        add_action('wp_ajax_aimatic_writer_delete_campaign', array($this, 'handle_delete_campaign'));
        add_action('wp_ajax_aimatic_writer_get_campaign_keywords', array($this, 'get_campaign_keywords'));
        add_action('wp_ajax_aimatic_writer_run_campaign', array($this, 'handle_run_campaign'));
        add_action('wp_ajax_aimatic_writer_process_campaign_images', array($this, 'handle_process_campaign_images'));

        // Clear old hourly schedule if exists, start new minute schedule
        $timestamp = wp_next_scheduled('aimatic_campaign_cron_event');
        if ($timestamp) {
            // Check if it's hourly (we want to upgrade to every_minute)
            // But we can't check the schedule directly easily. 
            // Let's just unschedule and reschedule to be safe if we want to force the change.
            // Or just check if *we* scheduled it.
            // For safety: if we want every_minute, and it's running, we should ensure it is.
            // But we don't want to clear it on every page load.
            // Let's rely on deactivation hook normally, but here we do soft migration:
        }

        if (!wp_next_scheduled('aimatic_campaign_cron_event')) {
            wp_schedule_event(time(), 'every_minute', 'aimatic_campaign_cron_event');
        } else {
             // If it exists, we might need to reschedule to 'every_minute' if it was 'hourly'
             // This is a bit tricky without reactivation.
             // Let's force reschedule if needed (or user can toggle off/on in settings).
             // Ideally we should check if the schedule is correct. 
             // We can use wp_get_schedule()
             $schedule = wp_get_schedule('aimatic_campaign_cron_event');
             if ($schedule !== 'every_minute') {
                 wp_clear_scheduled_hook('aimatic_campaign_cron_event');
                 wp_schedule_event(time(), 'every_minute', 'aimatic_campaign_cron_event');
             }
        }
    }

    /**
     * Fallback: Check campaigns when admin loads (for sites with poor cron)
     */
    public function check_campaigns_on_load() {
        // Run max once per 60 seconds to prevent lag
        if (get_transient('aimatic_campaign_check_lock')) {
            return;
        }
        set_transient('aimatic_campaign_check_lock', 1, 60);

        // Run processor logic without blocking (best effort)
        $this->process_campaigns();
    }

    public static function init() {
        $instance = new self();
    }

    /**
     * Add custom cron schedules
     */
    public function add_cron_intervals($schedules) {
        $schedules['every_minute'] = array(
            'interval' => 60,
            'display'  => __('Every Minute')
        );
        return $schedules;
    }

    /**
     * Get all campaigns
     */
    public function get_campaigns() {
        return get_option($this->option_name, array());
    }

    /**
     * Save a campaign
     */
    public function save_campaign($data) {
        $campaigns = $this->get_campaigns();
        
        // Use !empty to avoid empty string ID
        $id = (!empty($data['id'])) ? sanitize_text_field($data['id']) : uniqid();
        
        error_log("AIMatic: Saving campaign ID: $id (Input ID: " . (isset($data['id']) ? $data['id'] : 'NULL') . ")");

        $campaign = array(
            'id' => $id,
            'name' => isset($data['name']) ? sanitize_text_field($data['name']) : (isset($campaigns[$id]['name']) ? $campaigns[$id]['name'] : 'New Campaign'),
            'category_id' => isset($data['category_id']) ? intval($data['category_id']) : (isset($campaigns[$id]['category_id']) ? $campaigns[$id]['category_id'] : 0),
            'schedule' => isset($data['schedule']) ? sanitize_text_field($data['schedule']) : (isset($campaigns[$id]['schedule']) ? $campaigns[$id]['schedule'] : 'hourly'), 
            'custom_schedule_minutes' => isset($data['custom_schedule_minutes']) ? intval($data['custom_schedule_minutes']) : (isset($campaigns[$id]['custom_schedule_minutes']) ? $campaigns[$id]['custom_schedule_minutes'] : 60),
            'status' => isset($data['status']) ? sanitize_text_field($data['status']) : (isset($campaigns[$id]['status']) ? $campaigns[$id]['status'] : 'active'),
            'last_run' => isset($data['last_run']) ? $data['last_run'] : (isset($campaigns[$id]['last_run']) ? $campaigns[$id]['last_run'] : 0),
            'prompts' => isset($data['prompts']) ? $data['prompts'] : (isset($campaigns[$id]['prompts']) ? $campaigns[$id]['prompts'] : ''),
            'auto_keywords' => isset($data['auto_keywords']) ? intval($data['auto_keywords']) : (isset($campaigns[$id]['auto_keywords']) ? $campaigns[$id]['auto_keywords'] : 0),
            'keyword_source' => isset($data['keyword_source']) ? sanitize_text_field($data['keyword_source']) : (isset($campaigns[$id]['keyword_source']) ? $campaigns[$id]['keyword_source'] : 'ai'),
            'keyword_prompt' => isset($data['keyword_prompt']) ? sanitize_textarea_field($data['keyword_prompt']) : (isset($campaigns[$id]['keyword_prompt']) ? $campaigns[$id]['keyword_prompt'] : ''),
            
            // New Advanced Fields
            'author_id' => isset($data['author_id']) ? intval($data['author_id']) : (isset($campaigns[$id]['author_id']) ? $campaigns[$id]['author_id'] : get_current_user_id()),
            'internal_links' => isset($data['internal_links']) ? intval($data['internal_links']) : (isset($campaigns[$id]['internal_links']) ? $campaigns[$id]['internal_links'] : 0),
            'outbound_links' => isset($data['outbound_links']) ? intval($data['outbound_links']) : (isset($campaigns[$id]['outbound_links']) ? $campaigns[$id]['outbound_links'] : 0),
            'read_also' => isset($data['read_also']) ? intval($data['read_also']) : (isset($campaigns[$id]['read_also']) ? $campaigns[$id]['read_also'] : 0),
            'enable_video' => isset($data['enable_video']) ? intval($data['enable_video']) : (isset($campaigns[$id]['enable_video']) ? $campaigns[$id]['enable_video'] : 0),

            'posts_per_run' => isset($data['posts_per_run']) ? max(1, intval($data['posts_per_run'])) : (isset($campaigns[$id]['posts_per_run']) ? $campaigns[$id]['posts_per_run'] : 1),
            'max_words' => isset($data['max_words']) ? intval($data['max_words']) : (isset($campaigns[$id]['max_words']) ? $campaigns[$id]['max_words'] : 1500),
            'article_style' => isset($data['article_style']) ? sanitize_text_field($data['article_style']) : (isset($campaigns[$id]['article_style']) ? $campaigns[$id]['article_style'] : 'generic'),
            // FIX: Prioritize passed data for keywords (internal updates) over DB fallback
            'keywords_list' => isset($data['keywords_list']) ? $data['keywords_list'] : (isset($campaigns[$id]['keywords_list']) ? $campaigns[$id]['keywords_list'] : array()),
            'keywords_completed' => isset($data['keywords_completed']) ? $data['keywords_completed'] : (isset($campaigns[$id]['keywords_completed']) ? $campaigns[$id]['keywords_completed'] : array())
        );

        // Handle Bulk Content (Merge into DB Array)
        // Note: We check if content is present, even if source is 'ai' (user might have forgotten to switch).
        // If content is sent, we assume intent to save it.
        $has_bulk_content = isset($data['bulk_content']) && !empty(trim($data['bulk_content']));
        if ($has_bulk_content) {
             // Process bulk content (parse file or raw text)
             $new_keywords = $this->process_bulk_content($id, $data['bulk_content']);
             
             if (!empty($new_keywords)) {
                 // Append or Overwrite? 
                 // If it's a file upload (base64), we append.
                 // If it's raw text (Visual Editor), we overwrite the pending list because the editor represents the "current state".
                 
                 $is_file_upload = (strpos($data['bulk_content'], 'base64') !== false);
                 
                 if ($is_file_upload) {
                     $campaign['keywords_list'] = array_merge($campaign['keywords_list'], $new_keywords);
                     AIMatic_Logger::log("Appended " . count($new_keywords) . " keywords to DB for Campaign $id", 'INFO');
                 } else {
                     // Editor Save: Overwrite
                     $campaign['keywords_list'] = $new_keywords;
                     AIMatic_Logger::log("Updated DB keywords to " . count($new_keywords) . " for Campaign $id", 'INFO');
                 }
                 
                 // Unique
                 $campaign['keywords_list'] = array_values(array_unique($campaign['keywords_list']));
             } else {
                 // Empty content meant clearing?
                 if (!(strpos($data['bulk_content'], 'base64') !== false)) {
                     $campaign['keywords_list'] = array();
                 }
             }
        }

        $campaigns[$id] = $campaign;
        update_option($this->option_name, $campaigns);
        
        return $id;
    }

    /**
     * Process bulk content string (Base64 file or Text) -> Array
     */
    private function process_bulk_content($campaign_id, $content) {
        $is_file_upload = false;
        $final_content = "";

        // Check for Base64 Data URI (File Upload)
        if (preg_match('/^data:([^;]*);base64,(.*)$/s', $content, $matches)) {
            $is_file_upload = true;
            $mime = $matches[1];
            $data = base64_decode($matches[2]);
            
            // Map Mime to Extension
            $ext = 'txt';
            if (strpos($mime, 'sheet') !== false || strpos($mime, 'excel') !== false) $ext = 'xlsx';
            if (strpos($mime, 'word') !== false) $ext = 'docx';
            if ($mime === 'text/csv' || $mime === 'application/csv') $ext = 'csv';
            
            if ($ext === 'xlsx' || $ext === 'docx') {
                $temp_file = tempnam(sys_get_temp_dir(), 'aimatic_');
                file_put_contents($temp_file, $data);
                $parsed_content = AIMatic_File_Parser::parse($temp_file, $ext);
                unlink($temp_file);
                
                if (is_wp_error($parsed_content)) {
                    AIMatic_Logger::log("Error parsing bulk file: " . $parsed_content->get_error_message(), 'ERROR');
                    return array();
                }
                $final_content = $parsed_content; // Already string
            } else {
                $final_content = $data;
            }
        } else {
            $final_content = $content;
        }
        
        // Split and Clean (Newlines AND Commas)
        // Regex: Split by newline OR comma
        $keywords = preg_split('/[\r\n,]+/', $final_content);
        $clean_lines = array();
        foreach ($keywords as $kw) {
            $l = trim($kw);
            if (!empty($l)) {
                $clean_lines[] = $l;
            }
        }
        return $clean_lines;

        return $id;
    }
    
    /**
     * Save Bulk Keywords to File (Append File / Overwrite Editor)
     */
    private function save_bulk_keywords($campaign_id, $content) {
        $upload_dir = wp_upload_dir();
        $base_dir = $upload_dir['basedir'] . '/aimatic-campaigns';
        
        if (!file_exists($base_dir)) {
            wp_mkdir_p($base_dir);
            file_put_contents($base_dir . '/index.php', '<?php // Silence is golden');
            file_put_contents($base_dir . '/.htaccess', 'deny from all');
        }
        
        $is_file_upload = false;

        // Check for Base64 Data URI (File Upload)
        if (preg_match('/^data:([^;]*);base64,(.*)$/s', $content, $matches)) {
            $is_file_upload = true;
            $mime = $matches[1];
            $data = base64_decode($matches[2]);
            
            // Map Mime to Extension
            $ext = 'txt';
            if (strpos($mime, 'sheet') !== false || strpos($mime, 'excel') !== false) $ext = 'xlsx';
            if (strpos($mime, 'word') !== false) $ext = 'docx';
            if ($mime === 'text/csv' || $mime === 'application/csv') $ext = 'csv';
            
            if ($ext === 'xlsx' || $ext === 'docx') {
                $temp_file = tempnam(sys_get_temp_dir(), 'aimatic_');
                file_put_contents($temp_file, $data);
                $parsed_content = AIMatic_File_Parser::parse($temp_file, $ext);
                unlink($temp_file);
                
                if (is_wp_error($parsed_content)) {
                    AIMatic_Logger::log("Error parsing bulk file for Campaign {$campaign_id}: " . $parsed_content->get_error_message(), 'ERROR');
                    return;
                }
                $content = $parsed_content;
            } else {
                $content = $data;
            }
        }
        
        // Strict Sanitize: split, trim, remove empty
        $lines = preg_split('/\r\n|\r|\n/', $content);
        $clean_lines = array();
        foreach ($lines as $line) {
            $l = trim($line);
            if (!empty($l)) {
                $clean_lines[] = $l;
            }
        }
        
        error_log("AIMatic: Processed " . count($clean_lines) . " keywords for ID: $campaign_id. Is File Upload: " . ($is_file_upload ? 'YES' : 'NO'));

        if (!empty($clean_lines)) {
            // Write to .queue file
            $file_path = $base_dir . '/keywords-' . $campaign_id . '.queue';
            $data_to_write = implode("\n", $clean_lines) . "\n";
            
            if ($is_file_upload) {
                // Formatting for append: ensure newline start if file exists
                if (file_exists($file_path) && filesize($file_path) > 0) {
                     $data_to_write = "\n" . implode("\n", $clean_lines) . "\n";
                }
                file_put_contents($file_path, $data_to_write, FILE_APPEND);
                AIMatic_Logger::log("Appended " . count($clean_lines) . " keywords to Campaign {$campaign_id} queue from file", 'INFO');
            } else {
                // Text Edit: Overwrite Queue (Visual Editor is source of truth)
                file_put_contents($file_path, $data_to_write);
                AIMatic_Logger::log("Updated Queue to " . count($clean_lines) . " keywords for Campaign {$campaign_id}", 'INFO');
            }
        } elseif (!$is_file_upload) {
            // Use cleared the list in Editor -> Empty the queue file
            $file_path = $base_dir . '/keywords-' . $campaign_id . '.queue';
            if (file_exists($file_path)) {
                file_put_contents($file_path, ""); // Clear
                AIMatic_Logger::log("Cleared keyword queue for Campaign {$campaign_id}", 'INFO');
            }
        }
    }

    /**
     * Get next keyword (Move from Queue to Completed)
     */
    private function get_next_bulk_keyword($campaign_id) {
        $upload_dir = wp_upload_dir();
        $base_dir = $upload_dir['basedir'] . '/aimatic-campaigns';
        $queue_path = $base_dir . '/keywords-' . $campaign_id . '.queue';
        $completed_path = $base_dir . '/keywords-' . $campaign_id . '.completed';
        
        // Check legacy .txt file and migrate if needed
        $legacy_path = $base_dir . '/keywords-' . $campaign_id . '.txt';
        if (file_exists($legacy_path)) {
            rename($legacy_path, $queue_path);
        }
        
        if (!file_exists($queue_path)) return false;
        
        // Lock file for reading/writing
        $fp = fopen($queue_path, 'r+');
        if (flock($fp, LOCK_EX)) {
            $lines = array();
            while (($line = fgets($fp)) !== false) {
                // Remove whitespace but keep content
                $trimmed = trim($line);
                if (!empty($trimmed)) {
                    $lines[] = $trimmed;
                }
            }
            
            // Find first keyword
            $keyword = null;
            $remaining = array();
            if (!empty($lines)) {
                $keyword = array_shift($lines); // Take first
                $remaining = $lines;
            }
            
            // Rewrite remaining to queue
            ftruncate($fp, 0);
            rewind($fp);
            if (!empty($remaining)) {
                fwrite($fp, implode("\n", $remaining) . "\n");
            }
            fflush($fp);
            flock($fp, LOCK_UN);
            fclose($fp);
            
            if ($keyword) {
                // Append to Completed File
                file_put_contents($completed_path, $keyword . "\n", FILE_APPEND);
                return $keyword;
            }
        } else {
             fclose($fp);
        }
        
        return false;
    }

    /**
     * Delete a campaign
     */
    public function delete_campaign($id) {
        $campaigns = $this->get_campaigns();
        if (isset($campaigns[$id])) {
            unset($campaigns[$id]);
            update_option($this->option_name, $campaigns);
            
            // Clean up files
            $upload_dir = wp_upload_dir();
            $base_dir = $upload_dir['basedir'] . '/aimatic-campaigns';
            if (file_exists($base_dir . '/keywords-' . $id . '.txt')) {
                unlink($base_dir . '/keywords-' . $id . '.txt');
            }
            if (file_exists($base_dir . '/keywords-used-' . $id . '.log')) {
                unlink($base_dir . '/keywords-used-' . $id . '.log');
            }
            
            return true;
        }
        return false;
    }

    /**
     * Duplicate a campaign
     */
    public function duplicate_campaign($id) {
        $campaigns = $this->get_campaigns();
        
        if (!isset($campaigns[$id])) return false;
        
        $original = $campaigns[$id];
        $new_id = uniqid();
        
        // Clone Data
        $new_campaign = $original;
        $new_campaign['id'] = $new_id;
        $new_campaign['name'] = "Copy of " . $original['name'];
        $new_campaign['status'] = 'paused'; // Defaults to paused for safety
        $new_campaign['last_run'] = 0;
        $new_campaign['keywords_completed'] = array(); // Reset completed history
        
        // Logic for Bulk Keywords File Cloning?
        // Since we moved to DB storage for bulk keywords ($campaign['keywords_list']), 
        // the array copy above ($new_campaign = $original) ALREADY copied the pending keywords!
        // So we don't need to touch files.
        // However, if we were using the old file system (legacy), we might miss it.
        // But since we are pushing for DB-based storage, we assume $new_campaign has the list.
        
        $campaigns[$new_id] = $new_campaign;
        update_option($this->option_name, $campaigns);
        
        AIMatic_Logger::log("Campaign Duplicated: '{$original['name']}' -> '{$new_campaign['name']}'", 'INFO');
        
        return $new_id;
    }

    /**
     * Main Cron Processor
     */
    public function process_campaigns($force = false) {
        // Increase Time Limit & Ignore Abort for heavy tasks
        if (function_exists('set_time_limit')) set_time_limit(600); // 10 Minutes
        ignore_user_abort(true);
        // Increase Memory Limit
        ini_set('memory_limit', '512M');

        // Master Switch Check
        $enabled = get_option('aimatic_writer_cron_enabled', 1);
        
        if (!$force && !$enabled) {
            AIMatic_Logger::log("CRON SKIP: Master switch disabled.", 'WARNING');
            return; // Silently skip if disabled and not forced
        }

        // Track last run time for debugging
        update_option('aimatic_cron_last_run', time());

        // Get all active campaigns
        $campaigns = $this->get_campaigns();
        
        if (empty($campaigns)) {
             // Log occasionally
             AIMatic_Logger::log("DEBUG: Cron Heartbeat. No campaigns found.", 'INFO');
             return;
        }

        $now = time();
        // Log heartbeat
        AIMatic_Logger::log("DEBUG: Cron Heartbeat. Checking " . count($campaigns) . " campaigns. Current Time: " . date('Y-m-d H:i:s', $now), 'INFO');

        // COLLECT ALL DUE CAMPAIGNS
        $due_candidates = array();

        foreach ($campaigns as $id => $campaign) {
            if ($campaign['status'] !== 'active' && !$force) continue;

            $last_run = isset($campaign['last_run']) ? $campaign['last_run'] : 0;
            $schedule = isset($campaign['schedule']) ? $campaign['schedule'] : 'hourly';
            
            // Interval
            $interval = 3600; 
            if ($schedule === 'daily') $interval = 86400;
            elseif ($schedule === 'twice_daily') $interval = 43200;
            elseif ($schedule === 'custom') {
                 $minutes = isset($campaign['custom_schedule_minutes']) ? intval($campaign['custom_schedule_minutes']) : 60;
                 if ($minutes < 1) $minutes = 60;
                 $interval = $minutes * 60;
            }

            // Time Checks
            $is_due = (($now - $last_run) >= $interval);
            
            // Auto-heal future dates
            if ($last_run > ($now + 300)) {
                 $is_due = true; // Force run to reset
                 $campaign['last_run'] = 0; // Soft reset logic handled by execution success
            }
            // Auto-heal broken dates
            if ($last_run <= 0) $is_due = true;

            if ($is_due || $force) {
                // Check Lock (Skip if already running)
                if (get_transient('aimatic_campaign_lock_' . $id)) continue;

                $campaign['interval_seconds'] = $interval; // Pass for reference
                $due_candidates[] = $campaign;
            }
        }

        // SEQUENTIAL LOGIC: Sort by "Last Attempt Time" (Fairness/Round Robin)
        // If 'last_attempt_time' is missing, assume 0 (Priority 1)
        usort($due_candidates, function($a, $b) {
            $t_a = isset($a['last_attempt_time']) ? $a['last_attempt_time'] : 0;
            $t_b = isset($b['last_attempt_time']) ? $b['last_attempt_time'] : 0;
            return $t_a - $t_b; // Ascending: Oldest attempt info goes first (or never attempted)
        });

        if (empty($due_candidates)) {
             AIMatic_Logger::log("DEBUG: No campaigns due.", 'INFO');
             return;
        }

        // ONE BY ONE EXECUTION
        // Pick the top candidate
        $candidate = $due_candidates[0];
        $id = $candidate['id'];

        AIMatic_Logger::log("SEQUENTIAL SCHEDULER: Selected Campaign '{$candidate['name']}' (ID: $id). Candidates in queue: " . count($due_candidates), 'INFO');

        // 1. Mark Attempt (Updates rotation)
        $candidate['last_attempt_time'] = $now; 
        // We must save this lightweight update immediately so next cron respects rotation
        $campaigns[$id]['last_attempt_time'] = $now;
        update_option($this->option_name, $campaigns);

        // 2. Lock & Run
        set_transient('aimatic_campaign_lock_' . $id, time(), 600);

        // Run
        // FIX: The candidate array might be stale after update_option? 
        // No, we modify memory copy. 
        // Run Logic
        $count = isset($candidate['posts_per_run']) ? intval($candidate['posts_per_run']) : 1;
        $count = max(1, $count);
        
        // Execute Iterations (Only update last_run if ALL succeed? Or at least one?)
        // Strategy: If API fails, we return error.
        // We will update last_run ONLY if the run was "Successful enough".
        
        $execution_success = true;
        
        for ($i = 0; $i < $count; $i++) {
            // Fresh reload to be safe
            $fresh_campaigns = $this->get_campaigns();
            if (!isset($fresh_campaigns[$id])) break;
            
            // Run
            $result = $this->run_campaign($fresh_campaigns[$id]);
            
            if (is_wp_error($result)) {
                AIMatic_Logger::log("CAMPAIGN FAILED: '{$candidate['name']}' - " . $result->get_error_message(), 'ERROR');
                $execution_success = false;
                break; // Stop iterations on failure
            } else {
                 // Individual success
            }

            if ($i < $count - 1) sleep(5);
        }

        // 3. Update Schedule ONLY on Success
        if ($execution_success) {
            $fresh_campaigns = $this->get_campaigns();
            if (isset($fresh_campaigns[$id])) {
                $fresh_campaigns[$id]['last_run'] = $now;
                // Keep the attempt time we set earlier
                update_option($this->option_name, $fresh_campaigns);
                AIMatic_Logger::log("CAMPAIGN SUCCESS: '{$candidate['name']}' schedule updated to NOW.", 'INFO');
            }
        } else {
            AIMatic_Logger::log("CAMPAIGN RETRY MODE: '{$candidate['name']}' schedule NOT updated. Will retry later (Round Robin).", 'WARNING');
        }

        delete_transient('aimatic_campaign_lock_' . $id);
    }

    /**
     * Handler for Single Campaign Event (Background Run)
     */
    /**
     * Handler for Single Campaign Event (Background Run)
     */
    public function process_single_campaign_event($campaign_id) {
        // Increase Time Limit & Ignore Abort
        if (function_exists('set_time_limit')) set_time_limit(600); // 10 Minutes
        ignore_user_abort(true);

        AIMatic_Logger::log("BACKGROUND EVENT FIRED: Starting campaign ID $campaign_id", 'INFO');
        
        // LOCK CHECK: Prevent duplicate background runs for the same campaign
        $lock_key = 'aimatic_campaign_bg_lock_' . $campaign_id;
        if (get_transient($lock_key)) {
            AIMatic_Logger::log("BACKGROUND SKIP: Campaign ID $campaign_id is already running (Locked).", 'WARNING');
            return;
        }
        set_transient($lock_key, time(), 600); // 10 Minute Lock

        $campaigns = $this->get_campaigns();
        if (isset($campaigns[$campaign_id])) {
            // Run without skipping images
            $result = $this->run_campaign($campaigns[$campaign_id], false);
            if (is_wp_error($result)) {
                AIMatic_Logger::log("BACKGROUND RUN FAILED for ID $campaign_id: " . $result->get_error_message(), 'ERROR');
            } else {
                AIMatic_Logger::log("BACKGROUND RUN SUCCESS for ID $campaign_id. Post ID: $result", 'INFO');
            }
        } else {
            AIMatic_Logger::log("BACKGROUND ERROR: Campaign ID $campaign_id not found in DB.", 'ERROR');
        }
        
        // Release Lock
        delete_transient($lock_key);
    }

    /**
     * AJAX Handler: Run Campaign Now (Schedule Background)
     */
    public function handle_run_campaign() {
        check_ajax_referer('aimatic_writer_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        $id = isset($_POST['campaign_id']) ? sanitize_text_field($_POST['campaign_id']) : '';

        // Check if ID exists
        $campaigns = $this->get_campaigns();
        if (!isset($campaigns[$id])) {
            wp_send_json_error('Campaign not found.');
        }

        // 1. Schedule the event
        $schedule_result = wp_schedule_single_event(time(), 'aimatic_run_campaign_single', array($id));
        
        if ($schedule_result === false) {
             AIMatic_Logger::log("Failed to schedule background event for ID $id. It might be already scheduled.", 'WARNING');
             // Attempt to calculate a new time if duplicate?
             // Or just force run via alternate method?
             // Let's try +1 second just in case
             $schedule_result = wp_schedule_single_event(time()+1, 'aimatic_run_campaign_single', array($id));
        }

        if ($schedule_result === false) {
             wp_send_json_error('System Error: Could not schedule background task.');
        } else {
             AIMatic_Logger::log("Scheduled background run for ID $id.", 'INFO');
             
             // 2. Spawn Cron (Non-blocking attempt)
             $cron_url = site_url('wp-cron.php');
             $args = array('blocking' => false, 'sslverify' => false, 'timeout' => 0.01);
             $spawn = wp_remote_post($cron_url, $args);
             
             if (is_wp_error($spawn)) {
                 AIMatic_Logger::log("Cron Spawn Failed (User might need to run cron manually): " . $spawn->get_error_message(), 'WARNING');
             }
             
             wp_send_json_success(array(
                'message' => 'Campaign started in background!',
                'background' => true
             ));
        }
    }

    /**
     * AJAX Handler: Process Images for Campaign Post
     */
    public function handle_process_campaign_images() {
        check_ajax_referer('aimatic_writer_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $topic = get_the_title($post_id);
        $content = get_post_field('post_content', $post_id);

        if (!$post_id || !$topic) {
            wp_send_json_error('Invalid Post');
        }

        // Disable timeout for image processing
        if (function_exists('set_time_limit')) {
            set_time_limit(300);
        }

        $this->process_images_for_post($post_id, $topic, $content);
        
        wp_send_json_success('Images Processed');
    }

    /**
     * Force run a campaign by ID (for testing)
     */
    public function force_run_campaign($id, $skip_images = false) {
        $campaigns = $this->get_campaigns();
        if (isset($campaigns[$id])) {
            return $this->run_campaign($campaigns[$id], $skip_images);
        }
        return new WP_Error('not_found', 'Campaign not found');
    }

    /**
     * Check if post already exists by title
     */
    private function post_exists_by_title($title) {
        $query = new WP_Query(array(
            'post_type' => 'post',
            'title' => $title,
            'post_status' => 'any',
            'fields' => 'ids',
            'posts_per_page' => 1,
            'no_found_rows' => true
        ));
        return $query->have_posts();
    }

    /**
     * Run a single campaign
     */
    private function run_campaign($campaign, $skip_images = false) {
        // Reset time limit for this specific campaign execution
        if (function_exists('set_time_limit')) set_time_limit(600); 
        
        AIMatic_Logger::log("DEBUG: run_campaign started for '{$campaign['name']}'", 'INFO');

        AIMatic_Logger::log("DEBUG: run_campaign started for '{$campaign['name']}'", 'INFO');

        // Note: Optimistic Scheduling Removed to verify correct sequential retry logic.
        // We only update last_run on SUCCESS (in the caller, or at end).

        // 1. Determine Topic Strategy
        $use_bulk_keywords = (isset($campaign['keyword_source']) && $campaign['keyword_source'] === 'file');
        $topic = '';
        $keywords_str = ''; // SEO keywords to optimize for

        if ($use_bulk_keywords) {
            // Bulk File Strategy (Now DB Based)
            if (!empty($campaign['keywords_list'])) {
                 // Get first keyword
                 $bulk_keyword = array_shift($campaign['keywords_list']);
                 
                 // Move to completed
                 if (!isset($campaign['keywords_completed'])) $campaign['keywords_completed'] = array();
                 $campaign['keywords_completed'][] = $bulk_keyword;
                 
                 // Save State Immediately (Atomic consumption)
                 $this->save_campaign($campaign);
                 
                 $topic = $bulk_keyword;
                 $keywords_str = $bulk_keyword; 
                 
                 AIMatic_Logger::log("AIMatic Campaign '{$campaign['name']}': Consumed keyword: $topic", 'INFO');
            } else {
                 $bulk_keyword = $this->get_next_bulk_keyword($campaign['id']);
                 if ($bulk_keyword) {
                      $topic = $bulk_keyword;
                      $keywords_str = $bulk_keyword;
                      AIMatic_Logger::log("AIMatic Campaign '{$campaign['name']}': Consumed keyword from LEGACY FILE: $topic", 'INFO');
                 } else {
                      error_log("AIMatic Campaign '{$campaign['name']}': Keyword list empty. Falling back to AI.");
                      AIMatic_Logger::log("DEBUG: Bulk keyword list empty. Fallback to AI.", 'WARNING');
                      $use_bulk_keywords = false; 
                 }
            }
        }
        
        if (!$use_bulk_keywords) {
            AIMatic_Logger::log("DEBUG: Generating topic via AI...", 'INFO');
            // Traditional AI Strategy
            $category = get_category($campaign['category_id']);
            if (!$category) return new WP_Error('invalid_category', 'Category not found or deleted.');
            
            $topics = $this->engine->generate_topics_for_category($category->name, 1);
            if (empty($topics) || is_wp_error($topics)) {
                 $err = is_wp_error($topics) ? $topics->get_error_message() : 'AI failed to generate topic';
                 AIMatic_Logger::log("DEBUG: Topic generation failed: $err", 'ERROR');
                 error_log('AIMatic Campaign Error: Failed to generate topic');
                 return is_wp_error($topics) ? $topics : new WP_Error('topic_generation_failed', 'AI failed to generate a topic. Check API Key.');
            }
            $topic = $topics[0];
            AIMatic_Logger::log("DEBUG: Generated topic: $topic", 'INFO');
            
            // 1.5 Auto-Generate Keywords (AI)
            if (!empty($campaign['auto_keywords'])) {
                if (!empty($campaign['keyword_prompt'])) {
                    $keywords_str = $this->engine->generate_ai_keywords($topic, $campaign['keyword_prompt']);
                }
                if (empty($keywords_str)) {
                    $keywords_str = $this->engine->generate_seo_keywords($topic);
                }
            }
        }

        // DUPLICATE CHECK: Before generating expensive content, check if title exists
        if ($this->post_exists_by_title($topic)) {
            AIMatic_Logger::log("DUPLICATE SKIPPED: Post with title '$topic' already exists. Marking run as complete to advance schedule.", 'WARNING');
            // Return TRUE to count as a "successful run" (skipped), so the scheduler updates last_run 
            // and we don't get stuck in an infinite retry loop for the same topic.
            return true; 
        }

        // 2. Generate Article
        AIMatic_Logger::log("DEBUG: Starting Article Generation for '$topic'...", 'INFO');
        
        // Base Custom Prompt
        $instructions = isset($campaign['prompts']) ? $campaign['prompts'] . "\n\n" : "";

        // Apply Article Style
        $style = isset($campaign['article_style']) ? $campaign['article_style'] : 'generic';
        $style_map = array(
            'generic'     => "Write a standard, well-structured blog post.",
            'how-to'      => "Write a Step-by-Step How-To Guide/Tutorial. Use numbered steps (H2/H3) and clear instructions.",
            'listicle'    => "Write a Listicle (e.g., Top 10). Use H2 for each item. Make it scannable.",
            'informative' => "Write an in-depth Informative/Educational article explaining the topic clearly.",
            'guide'       => "Write an Ultimate Guide. Be extremely comprehensive, covering all aspects. Long-form.",
            'comparison'  => "Write a Comparison article (X vs Y). Use tables if possible (markdown tables) and pros/cons lists.",
            'review'      => "Write a Product/Service Review. detailed analysis, features, pros and cons, and verdict.",
            'trend'       => "Write a News/Trend update. Focus on what's new, why it matters, and future implications.",
            'case-study'  => "Write a Case Study style article. Focus on problem, solution, and results.",
            'editorial'   => "Write an Opinion/Editorial piece. Express a strong, expert viewpoint.",
            'faq'         => "Write in an FAQ format. Use questions as Headings and provide direct answers."
        );
        
        if (isset($style_map[$style]) && $style !== 'generic') {
             $instructions .= "STYLE INSTRUCTION: " . $style_map[$style] . "\n\n";
        }

        if (!empty($keywords_str)) {
            $instructions .= "IMPORTANT: Optimize the article for the following SEO keywords: " . $keywords_str . "\n\n";
        }

        // Add Word Count Instruction
        $max_words = isset($campaign['max_words']) ? intval($campaign['max_words']) : 1500;
        if ($max_words < 300) $max_words = 1500; // Safety default
        
        $instructions .= "TARGET LENGTH: Approximately $max_words words. Do not be too brief.";

        // Pass Advanced Options to Engine
        $options = array(
            'internal_links' => !empty($campaign['internal_links']),
            'outbound_links' => !empty($campaign['outbound_links']),
            'read_also' => !empty($campaign['read_also'])
        );

        $content = $this->engine->generate_article($topic, $instructions, $options);
        if (is_wp_error($content)) {
             AIMatic_Logger::log("DEBUG: Article Generation Failed: " . $content->get_error_message(), 'ERROR');
             return $content;
        }

        // 2.5 Check Word Count (Min 300 Validation)
        $word_count = str_word_count(strip_tags($content));
        if ($word_count < 300) {
            AIMatic_Logger::log("QUALITY CHECK FAILED: Article too short ($word_count words). Minimum 300 required. Topic: $topic", 'ERROR');
            return new WP_Error('article_too_short', "Article too short ($word_count words).");
        }

        AIMatic_Logger::log("DEBUG: Article Generated ($word_count words). Creating Post...", 'INFO');

        $html_content = $content;

        // 3. Create Post (DRAFT first)
        $slug = sanitize_title($topic);
        if (empty($slug)) $slug = sanitize_title("post-" . time());
        
        $author_id = !empty($campaign['author_id']) ? intval($campaign['author_id']) : 1;

        $post_id = wp_insert_post(array(
            'post_title' => $topic,
            'post_name'  => $slug, // Force Toggle Slug
            'post_content' => $html_content,
            'post_status' => 'draft', // Draft first to check images
            'post_category' => array($campaign['category_id']),
            'post_author' => $author_id
        ));
        
        if ($post_id && !is_wp_error($post_id)) {
            update_post_meta($post_id, 'aimatic_campaign_id', $campaign['id']);
            AIMatic_Logger::log("DEBUG: Draft Post Created ID: $post_id", 'INFO');
        }

        if (is_wp_error($post_id)) {
            AIMatic_Logger::log("DEBUG: Failed to insert post: " . $post_id->get_error_message(), 'ERROR');
            return $post_id;
        }

        // 4. Generate & Insert Images (Only if not skipped)
        $media_success = true;
        // Debug Log for Image Setting
        $auto_images_enabled = get_option('aimatic_writer_auto_images', 1);
        
        // Check Video Capability (Global or Campaign Override)
        $global_video_enable = get_option('aimatic_writer_enable_youtube', 1);
        $campaign_video_enable = isset($campaign['enable_video']) ? (bool)$campaign['enable_video'] : $global_video_enable;
        
        AIMatic_Logger::log("DEBUG: Media Settings - Auto Images: " . ($auto_images_enabled ? "ON" : "OFF") . ", Video: " . ($campaign_video_enable ? "ON" : "OFF"), 'INFO');
        
        if (!$skip_images && ($auto_images_enabled || $campaign_video_enable)) {
            // Pass campaign video setting override
            AIMatic_Logger::log("DEBUG: Starting Media Processing for Post $post_id...", 'INFO');
            $enable_video_override = isset($campaign['enable_video']) ? (bool)$campaign['enable_video'] : null; // Pass null if not set to fallback in method
            $media_success = $this->process_images_for_post($post_id, $topic, $html_content, $enable_video_override);
            AIMatic_Logger::log("DEBUG: Media Processing Result: " . ($media_success ? 'Success' : 'Failed'), 'INFO');
        } elseif (!$auto_images_enabled && !$campaign_video_enable) {
             AIMatic_Logger::log("DEBUG: Skipping media because both Images and Videos are DISABLED.", 'WARNING');
        }

        // 5. Validate & Publish
        // We now PRIORITIZE publishing. If media failed, we still publish but log it.
        
        if ($media_success) {
             AIMatic_Logger::log("DEBUG: Media processing OK.", 'INFO');
        } else {
             AIMatic_Logger::log("QUALITY CHECK FAILED: Media processing failed (No images found/inserted). Deleting post.", 'ERROR');
             wp_delete_post($post_id, true);
             return new WP_Error('media_failed', 'Post generation failed because Image/Video insertion failed.');
        }
        
        // Force Publish
        $publish_result = wp_publish_post($post_id);
        
        if (!$publish_result || is_wp_error($publish_result)) {
             AIMatic_Logger::log("CRITICAL ERROR: Failed to publish post $post_id.", 'ERROR');
        } else {
             $campaign['last_run'] = time();
             $this->save_campaign($campaign);
             AIMatic_Logger::log("DEBUG: Campaign Run Complete. Post Published: $post_id", 'INFO');
        }
        
        return $post_id;
    }

    /**
     * Process images for the auto-generated post
     */
    private function process_images_for_post($post_id, $topic, $content, $enable_video_override = null) {
        $width = get_option('aimatic_writer_pollinations_width', 1200);
        $height = get_option('aimatic_writer_pollinations_height', 632);

        // --- 1. Featured Image Handling (Immediate) ---
        $featured_img_url = '';
        $featured_img_alt = $topic;
        
        // Prepare list for batch download
        $batch_items = array();
        
        $auto_images_enabled = get_option('aimatic_writer_auto_images', 1);

        if ($auto_images_enabled) {
            $images = AIMatic_Image_Handler::fetch_images($topic, 1, $width, $height);
            
            if ($images && !empty($images)) {
                $featured_img_url = $images[0]['url'];
                 $batch_items[] = array(
                     'url' => $images[0]['url'],
                     'alt' => $topic,
                     'post_id' => $post_id,
                     'type' => 'featured',
                     'heading_index' => -1 
                 );
            } else {
                 error_log("AIMatic: No images found for topic: $topic");
            }
        }

        // --- 2. Plan Content Images ---
        $dom = new DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        
        $xpath = new DOMXPath($dom);
        $headings = $xpath->query('//h2 | //h3');

        $interval = get_option('aimatic_writer_heading_interval', 2);
        
        // Fallback: If no headings, use Paragraphs
        $using_paragraphs = false;
        if ($headings->length === 0) {
            AIMatic_Logger::log("DEBUG: No headings found for topic '$topic'. Fallback to Paragraphs.", 'WARNING');
            $headings = $xpath->query('//p');
            $interval = max(3, $interval * 2); // Increase interval for paragraphs (e.g. every 4-6 paragraphs)
            $using_paragraphs = true;
        } else {
            AIMatic_Logger::log("DEBUG: Found " . $headings->length . " headings for topic '$topic'.", 'INFO');
        }

        // Limits
        $max_images = get_option('aimatic_writer_image_count', 3);
        
        // Logic for Video Limit:
        // If override is provided (from campaign), use it.
        // If override is null (legacy/global), use global setting.
        $global_enable = get_option('aimatic_writer_enable_youtube', 1);
        $enable_video = ($enable_video_override !== null) ? $enable_video_override : $global_enable;
        
        $max_videos = $enable_video ? get_option('aimatic_writer_video_count', 1) : 0;
        
        $images_planned = 0;
        $videos_planned = 0;
        
        // Initialize YouTube if key is present
        $youtube = new AIMatic_YouTube();
        $video_pool = array();
        
        // Fetch videos upfront
        if ($max_videos > 0) {
            $found_videos = $youtube->search_videos($topic, $max_videos);
            if (!is_wp_error($found_videos)) {
                $video_pool = $found_videos;
                AIMatic_Logger::log("DEBUG: Found " . count($video_pool) . " videos for topic '$topic'.", 'INFO');
            } else {
                AIMatic_Logger::log("DEBUG: Video Search Failed: " . $found_videos->get_error_message(), 'WARNING');
            }
        }
        
        // Planning Arrays
        $insertion_plan = array(); // [index => ['type' => 'image'|'video', 'content' => ...]]

        foreach ($headings as $index => $heading) {
            // Skip first paragraph if using fallback to avoid images at very top
            if ($using_paragraphs && $index < 2) continue; 
            
            if ($images_planned >= $max_images && $videos_planned >= $max_videos) break;

            // EXCLUSION: Skip "Read Also", "Related", "Conclusion" sections
            $heading_text = strtolower($heading->textContent);
            if (
                strpos($heading_text, 'read also') !== false || 
                strpos($heading_text, 'related') !== false || 
                strpos($heading_text, 'see also') !== false ||
                strpos($heading_text, 'conclusion') !== false
            ) {
                continue;
            }

            if (($index + 1) % $interval === 0) {
                // Decide Video vs Image
                // Logic: Videos prefer middle or end, mixed with images.
                // Simple logic: If we have videos, insert video after 1st image? Or interleave.
                // Current Logic: Video if (images >= 1 AND videos < max)
                
                $do_video = ($videos_planned < $max_videos) && !empty($video_pool); 
                
                if ($do_video && $videos_planned < $max_videos) {
                    $vid_id = array_shift($video_pool);
                    $insertion_plan[$index] = array('type' => 'video', 'id' => $vid_id);
                    $videos_planned++;
                } elseif ($images_planned < $max_images && $auto_images_enabled) {
                    // Plan Image
                    $heading_text = $heading->textContent;
                    
                    // Extract Context (First 30 words of next paragraph)
                    $context = '';
                    $next_node = $heading->nextSibling;
                    while ($next_node) {
                        if ($next_node->nodeName === 'p') {
                            $context = implode(' ', array_slice(explode(' ', $next_node->textContent), 0, 30));
                            break;
                        }
                        $next_node = $next_node->nextSibling;
                    }
                    
                    $prompt = $heading_text . ' ' . $context;
                    // Clean prompt
                    $prompt = trim(preg_replace('/\s+/', ' ', $prompt));
                    
                    // Fetch URL (Fast)
                    $imgs = AIMatic_Image_Handler::fetch_images($prompt, 1, $width, $height);
                    if ($imgs && !empty($imgs)) {
                         $batch_items[] = array(
                             'url' => $imgs[0]['url'],
                             'alt' => $heading_text,
                             'post_id' => $post_id,
                             'type' => 'content',
                             'heading_index' => $index
                         );
                         $insertion_plan[$index] = array('type' => 'image', 'placeholder_index' => $index);
                         $images_planned++;
                    }
                }
            }
        }
        
        error_log("AIMatic Media Plan: Planned " . count($batch_items) . " images (including featured) and $videos_planned videos.");

        // --- 3. Batch Download & Upload ---
        $results = AIMatic_Image_Handler::download_and_upload_batch($batch_items);
        
        // --- 4. Apply Changes ---
        
        // Map results back
        $content_images_map = array(); // [heading_index => attachment_id]
        
        foreach ($batch_items as $k => $item) {
            $att_id = isset($results[$k]) ? $results[$k] : false;
            
            if ($att_id && !is_wp_error($att_id)) {
                if ($item['type'] === 'featured') {
                    set_post_thumbnail($post_id, $att_id);
                } elseif ($item['type'] === 'content') {
                    $content_images_map[$item['heading_index']] = $att_id;
                }
            }
        }
        
        // Insert into DOM
        foreach ($insertion_plan as $index => $plan) {
            $heading = $headings->item($index);
            if (!$heading) continue;
            
            if ($plan['type'] === 'video') {

                 // Use DOM API to avoid appendXML crashes
                 $div = $dom->createElement('div');
                 $div->setAttribute('class', 'aimatic-video-container');
                 $div->setAttribute('style', 'margin: 20px 0; text-align: center;');
                 
                 $iframe = $dom->createElement('iframe');
                 $iframe->setAttribute('width', '560');
                 $iframe->setAttribute('height', '315');
                 $iframe->setAttribute('src', 'https://www.youtube.com/embed/' . esc_attr($plan['id']));
                 $iframe->setAttribute('title', 'YouTube video player');
                 $iframe->setAttribute('frameborder', '0');
                 // Note: 'allow' attribute can be complex, verify support
                 $iframe->setAttribute('allow', 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share');
                 $iframe->setAttribute('allowfullscreen', 'true');
                 
                 $div->appendChild($iframe);
                 
                 if ($heading->nextSibling) {
                    $heading->parentNode->insertBefore($div, $heading->nextSibling);
                 } else {
                    $heading->parentNode->appendChild($div);
                 }
                 
                 AIMatic_Logger::log("DEBUG: Inserted Video ID {$plan['id']}", 'INFO');
                 
            } elseif ($plan['type'] === 'image') {
                 $h_idx = $plan['placeholder_index'];
                 if (isset($content_images_map[$h_idx])) {
                      $att_id = $content_images_map[$h_idx];
                      $src = wp_get_attachment_url($att_id);
                      
                      $img_node = $dom->createElement('img');
                      $img_node->setAttribute('src', $src);
                      $img_node->setAttribute('alt', $heading->textContent);
                      $img_node->setAttribute('class', 'aligncenter size-large');
                      $img_node->setAttribute('style', 'max-width: 100%; height: auto; display: block; margin: 20px auto;');

                      if ($heading->nextSibling) {
                        $heading->parentNode->insertBefore($img_node, $heading->nextSibling);
                      } else {
                        $heading->parentNode->appendChild($img_node);
                      }
                 }
            }
        }

        // Fix: saveHTML() might add <html><body> wrapper. We need only the body content.
        // Also ensure iframes aren't escaped.
        $updated_content = $dom->saveHTML();
        
        // Strip <html> and <body> tags if present
        $updated_content = preg_replace('/^<!DOCTYPE.+?>/', '', $updated_content);
        $updated_content = str_replace(array('<html>', '</html>', '<body>', '</body>'), array('', '', '', ''), $updated_content);
        $updated_content = trim($updated_content);

        // Remove extra encoding that loadHTML might have added (like %C2%A0 for nbsp)
        // $updated_content = urldecode($updated_content); // Careful with this

        // Important: Prevent Kses stripping iframes during update (if running as cron/admin)
        kses_remove_filters();

        wp_update_post(array(
            'ID' => $post_id,
            'post_content' => $updated_content
        ));
        
        kses_init_filters(); // Restore filters
        
        // Return success only if we actually processed something OR if images weren't strictly required
        // But user requested "agar image insert na ho to fail ho jaye"
        if ($auto_images_enabled && empty($batch_items)) {
            // Strict check: User wants images. If zero images were batched, fail.
            // But wait, what if we found no headings?
            // If topic search returned 0 results, $batch_items is empty.
            // We should check if we *tried* to get images but got none.
            return false;
        }

        return true;
    }

    /**
     * AJAX Handler: Save Campaign
     */
    public function handle_save_campaign() {
        if (!check_ajax_referer('aimatic_writer_nonce', 'nonce', false)) {
             wp_send_json_error('Nonce verification failed. Please reload the page.');
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        try {
            $id = $this->save_campaign($_POST);
            
            if ($id) {
                wp_send_json_success(array('id' => $id));
            } else {
                wp_send_json_error('Failed to save (ID empty)');
            }
        } catch (Exception $e) {
            wp_send_json_error('Exception: ' . $e->getMessage());
        } catch (Error $e) {
            wp_send_json_error('Fatal Error: ' . $e->getMessage());
        }
    }

    /**
     * AJAX Handler: Delete Campaign
     */
    public function handle_delete_campaign() {
        check_ajax_referer('aimatic_writer_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        $id = isset($_POST['id']) ? sanitize_text_field($_POST['id']) : '';
        if ($this->delete_campaign($id)) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Failed to delete');
        }
    }



    /**
     * AJAX Handler: Get Campaign Keywords (Pending & Completed)
     */
    public function handle_get_campaign_keywords() {
        check_ajax_referer('aimatic_writer_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        $id = isset($_POST['id']) ? sanitize_text_field($_POST['id']) : '';
        if (empty($id)) wp_send_json_error('Missing ID');

        $campaigns = $this->get_campaigns();
        $campaign = isset($campaigns[$id]) ? $campaigns[$id] : false;
        
        $queue = array();
        $completed = array();
        
        if ($campaign) {
            // Primary Source: Database
            if (isset($campaign['keywords_list']) && is_array($campaign['keywords_list'])) {
                $queue = $campaign['keywords_list'];
            }
            if (isset($campaign['keywords_completed']) && is_array($campaign['keywords_completed'])) {
                $completed = $campaign['keywords_completed'];
            }
            
            // Fallback: Check Legacy File if DB Queue is empty
            if (empty($queue)) {
                $upload_dir = wp_upload_dir();
                $base_dir = $upload_dir['basedir'] . '/aimatic-campaigns';
                $queue_file = $base_dir . '/keywords-' . $id . '.queue';
                
                if (file_exists($queue_file)) {
                     $lines = file($queue_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                     if ($lines) $queue = array_values(array_map('trim', $lines));
                     // Note: We don't migrate here to avoid side effects in GET request, 
                     // but frontend will show it.
                }
            }
        }
        
        wp_send_json_success(array(
            'queue' => $queue,
            'completed' => $completed
        ));
    }

}
