<?php
if (!defined('ABSPATH')) {
    exit;
}

if (isset($_GET['settings-updated'])) {
    add_settings_error('aimatic_writer_messages', 'aimatic_writer_message', 'Settings Saved', 'updated');
}
settings_errors('aimatic_writer_messages');
?>

<div class="wrap">
    <h1>AIMatic Writer Settings</h1>
    
    <?php 
    $last_error = get_option('aimatic_last_error');
    if ($last_error) {
        echo '<div id="aimatic-persistent-error" class="notice notice-error is-dismissible inline"><p><strong>⚠️ Last Error:</strong> ' . esc_html($last_error) . '</p></div>';
    }
    ?>
    
    <div style="background: #fff; padding: 30px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); max-width: 1200px;">
        
        <form method="post" action="options.php">
            <?php settings_fields('aimatic_writer_settings_group'); ?>

            <!-- AI Generator Settings -->
            <h2 class="title">🤖 AI Generator Settings</h2>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Select AI Provider</th>
                    <td>
                        <select name="aimatic_writer_ai_provider" id="aimatic_writer_ai_provider">
                            <option value="pollinations" <?php selected(get_option('aimatic_writer_ai_provider'), 'pollinations'); ?>>Free AI (Pollinations)</option>
                            <option value="aihorde" <?php selected(get_option('aimatic_writer_ai_provider'), 'aihorde'); ?>>Free AI (AI Horde)</option>
                            <option value="openrouter" <?php selected(get_option('aimatic_writer_ai_provider'), 'openrouter'); ?>>OpenRouter (Aggregator)</option>
                            <option value="gemini" <?php selected(get_option('aimatic_writer_ai_provider'), 'gemini'); ?>>Google Gemini API</option>
                            <option value="openai" <?php selected(get_option('aimatic_writer_ai_provider'), 'openai'); ?>>OpenAI (ChatGPT)</option>
                            <option value="zhipu" <?php selected(get_option('aimatic_writer_ai_provider'), 'zhipu'); ?>>Z.AI (Zhipu GLM)</option>
                        </select>
                        <p class="description">Choose which AI service to use.</p>
                    </td>
                </tr>

                <!-- Pollinations (Free) Settings -->
                <tr valign="top" class="provider-settings provider-pollinations" style="display:none;">
                    <th scope="row">Free AI Settings</th>
                    <td>
                        <p style="color:green; font-weight:bold;">✅ No API Key Required!</p>
                        <p>You are using <strong>Pollinations.ai</strong>. It is completely free and requires no configuration.</p>
                        <p class="description">Just click "Save Settings" and you are ready to generate articles.</p>
                        <button type="button" class="button button-secondary aimatic-test-custom-btn" data-provider="pollinations">Test Generation</button>
                        <span id="test-result-pollinations" style="margin-left: 10px; font-weight: bold;"></span>
                    </td>
                </tr>

                <!-- AI Horde (Free) Settings -->
                <tr valign="top" class="provider-settings provider-aihorde" style="display:none;">
                    <th scope="row">AI Horde Settings</th>
                    <td>
                        <p style="color:green; font-weight:bold;">✅ No API Key Required! (Anonymous Mode)</p>
                        <p><strong>Status:</strong> Uses a distributed network of volunteers. <br>
                        <em>Note: Speed varies (30s - 2 mins) as it relies on available workers. It is slower than Pollinations but supports more complex models.</em></p>
                        <button type="button" class="button button-secondary aimatic-test-custom-btn" data-provider="aihorde">Test Generation (Wait ~60s)</button>
                        <span id="test-result-aihorde" style="margin-left: 10px; font-weight: bold;"></span>
                    </td>
                </tr>

                <!-- OpenRouter Settings -->
                <tr valign="top" class="provider-settings provider-openrouter">
                    <th scope="row">OpenRouter API Key</th>
                    <td>
                        <input type="password" id="aimatic_api_key_input" name="aimatic_writer_api_key" value="<?php echo esc_attr(get_option('aimatic_writer_api_key')); ?>" class="regular-text" style="width: 400px;" />
                        <button type="button" id="aimatic-test-api-btn" class="button button-secondary">Test Connection</button>
                        <span id="aimatic-api-test-result" style="margin-left: 10px; font-weight: bold;"></span>
                        <p class="description">Get your API key from <a href="https://openrouter.ai/keys" target="_blank">OpenRouter</a></p>
                    </td>
                </tr>
                
                <tr valign="top" class="provider-settings provider-openrouter">
                    <th scope="row">AI Model ID</th>
                    <td>
                        <input type="text" list="aimatic_models" name="aimatic_writer_model_id" value="<?php echo esc_attr(get_option('aimatic_writer_model_id', 'google/gemini-2.0-flash-exp:free')); ?>" class="regular-text" style="width: 400px;" placeholder="Select or type model ID..." />
                        <datalist id="aimatic_models">
                            <option value="google/gemini-2.0-flash-exp:free">Gemini 2.0 Flash (Free)</option>
                            <option value="meta-llama/llama-3.2-3b-instruct:free">Llama 3.2 (Free)</option>
                            <option value="openai/gpt-4o-mini">GPT-4o Mini</option>
                            <option value="anthropic/claude-3.5-sonnet">Claude 3.5 Sonnet</option>
                        </datalist>
                        <p class="description">Model ID from OpenRouter (default: google/gemini-2.0-flash-exp:free)</p>
                    </td>
                </tr>

                <!-- Gemini Settings -->
                <tr valign="top" class="provider-settings provider-gemini" style="display:none;">
                    <th scope="row">Google Gemini API Key</th>
                    <td>
                        <input type="password" id="aimatic_gemini_key_input" name="aimatic_writer_gemini_key" value="<?php echo esc_attr(get_option('aimatic_writer_gemini_key')); ?>" class="regular-text" style="width: 400px;" />
                        <button type="button" class="button button-secondary aimatic-test-custom-btn" data-provider="gemini">Test Connection</button>
                        <span id="test-result-gemini" style="margin-left: 10px; font-weight: bold;"></span>
                        <p class="description">Get a free key from <a href="https://aistudio.google.com/app/apikey" target="_blank">Google AI Studio</a>.</p>
                        <p class="description"><strong>💡 Pro Tip:</strong> To bypass rate limits, enter multiple keys separated by commas (e.g. <code>key1, key2, key3</code>).</p>
                    </td>
                </tr>
                <tr valign="top" class="provider-settings provider-gemini" style="display:none;">
                    <th scope="row">Gemini Model</th>
                    <td>
                        <input type="text" name="aimatic_writer_gemini_model" value="<?php echo esc_attr(get_option('aimatic_writer_gemini_model', 'gemini-2.0-flash-exp')); ?>" class="regular-text" style="width: 400px;" />
                        <p class="description">Default: <strong>gemini-2.0-flash-exp</strong> (Fast & Free). Other options: <code>gemini-1.5-flash</code>, <code>gemini-1.5-pro</code>.</p>
                    </td>
                </tr>

                <!-- OpenAI Settings -->
                <tr valign="top" class="provider-settings provider-openai" style="display:none;">
                    <th scope="row">OpenAI API Key</th>
                    <td>
                        <input type="password" id="aimatic_openai_key_input" name="aimatic_writer_openai_key" value="<?php echo esc_attr(get_option('aimatic_writer_openai_key')); ?>" class="regular-text" style="width: 400px;" />
                        <button type="button" class="button button-secondary aimatic-test-custom-btn" data-provider="openai">Test Connection</button>
                        <span id="test-result-openai" style="margin-left: 10px; font-weight: bold;"></span>
                        <p class="description">Get your API key from <a href="https://platform.openai.com/api-keys" target="_blank">OpenAI</a>.</p>
                    </td>
                </tr>
                <tr valign="top" class="provider-settings provider-openai" style="display:none;">
                    <th scope="row">OpenAI Model</th>
                    <td>
                        <input type="text" name="aimatic_writer_openai_model" value="<?php echo esc_attr(get_option('aimatic_writer_openai_model', 'gpt-4o-mini')); ?>" class="regular-text" style="width: 400px;" />
                        <p class="description">Default: <code>gpt-4o-mini</code>. Others: <code>gpt-4o</code>, <code>o1-mini</code>.</p>
                    </td>
                </tr>

                <!-- Z.AI / Zhipu Settings -->
                <tr valign="top" class="provider-settings provider-zhipu" style="display:none;">
                    <th scope="row">Z.AI (Zhipu) API Key</th>
                    <td>
                        <input type="password" id="aimatic_zhipu_key_input" name="aimatic_writer_zhipu_key" value="<?php echo esc_attr(get_option('aimatic_writer_zhipu_key')); ?>" class="regular-text" style="width: 400px;" />
                        <button type="button" class="button button-secondary aimatic-test-custom-btn" data-provider="zhipu">Test Connection</button>
                        <span id="test-result-zhipu" style="margin-left: 10px; font-weight: bold;"></span>
                        <p class="description">Enter API Key from <a href="https://open.bigmodel.cn/usercenter/apikeys" target="_blank">Zhipu AI (BigModel)</a>.</p>
                    </td>
                </tr>
                <tr valign="top" class="provider-settings provider-zhipu" style="display:none;">
                    <th scope="row">Zhipu Model</th>
                    <td>
                        <input type="text" name="aimatic_writer_zhipu_model" value="<?php echo esc_attr(get_option('aimatic_writer_zhipu_model', 'glm-4.6')); ?>" class="regular-text" style="width: 400px;" />
                        <p class="description">Default: <code>glm-4.6</code>. Others: <code>glm-4-plus</code>, <code>glm-4-0520</code>.</p>
                    </td>
                </tr>
            </table>

            <hr>
            
            <!-- (REMAINING SETTINGS UNDISTURBED) -->
            <?php // We skip rest of form for brevity in replacement, but ensure we don't overwrite if not needed.
                  // Wait, tool doesn't support 'skip'. I need to replace block-by-block or whole file section.
                  // I will assume the rest is standard.
                  // Actually, to be safe, I will replace the "AI Generator Settings" BLOCK only.
            ?>


            <!-- Image Settings -->
            <h2 class="title">🖼️ Image Generation Settings</h2>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Enable Auto Images</th>
                    <td>
                        <label>
                            <input type="checkbox" name="aimatic_writer_auto_images" value="1" <?php checked(1, get_option('aimatic_writer_auto_images', 1)); ?> />
                            Automatically generate and insert images
                        </label>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Preferred Image Source</th>
                    <td>
                        <select name="aimatic_writer_image_source">
                            <option value="pollinations" <?php selected(get_option('aimatic_writer_image_source'), 'pollinations'); ?>>Pollinations AI (Standard/Fast)</option>
                            <option value="flux" <?php selected(get_option('aimatic_writer_image_source'), 'flux'); ?>>Flux Gen (Cinematic & Realistic - Best)</option>
                            <option value="lexica" <?php selected(get_option('aimatic_writer_image_source'), 'lexica'); ?>>Lexica.art (Search Existing AI - High Quality/Fast)</option>
                            <option value="deepai" <?php selected(get_option('aimatic_writer_image_source'), 'deepai'); ?>>DeepAI (Requires Key)</option>
                            <option value="pexels" <?php selected(get_option('aimatic_writer_image_source'), 'pexels'); ?>>Pexels (Real Stock Photos)</option>
                            <option value="pixabay" <?php selected(get_option('aimatic_writer_image_source'), 'pixabay'); ?>>Pixabay (Free Stock Photos)</option>
                            <option value="unsplash" <?php selected(get_option('aimatic_writer_image_source'), 'unsplash'); ?>>Unsplash (Free Stock Photos)</option>
                        </select>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Images per Article</th>
                    <td>
                        <input type="number" name="aimatic_writer_image_count" value="<?php echo esc_attr(get_option('aimatic_writer_image_count', 3)); ?>" min="0" max="100" class="small-text" />
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Insert After Every (Headings)</th>
                    <td>
                        <input type="number" name="aimatic_writer_heading_interval" value="<?php echo esc_attr(get_option('aimatic_writer_heading_interval', 2)); ?>" min="1" max="10" class="small-text" />
                        <p class="description">e.g. 2 means insert an image after every 2nd H2/H3 heading.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Pexels API Key</th>
                    <td>
                        <input type="password" id="aimatic_pexels_key_input" name="aimatic_writer_pexels_key" value="<?php echo esc_attr(get_option('aimatic_writer_pexels_key')); ?>" class="regular-text" />
                        <button type="button" class="button button-secondary aimatic-test-key-btn" data-provider="pexels">Test Key</button>
                        <span class="aimatic-test-result" id="test-result-pexels"></span>
                        <p class="description">Get a free key from <a href="https://www.pexels.com/api/" target="_blank">Pexels API</a>. (High limits: 20,000 requests/month)</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Pixabay API Key</th>
                    <td>
                        <input type="password" id="aimatic_pixabay_key_input" name="aimatic_writer_pixabay_key" value="<?php echo esc_attr(get_option('aimatic_writer_pixabay_key')); ?>" class="regular-text" />
                        <button type="button" class="button button-secondary aimatic-test-key-btn" data-provider="pixabay">Test Key</button>
                        <span class="aimatic-test-result" id="test-result-pixabay"></span>
                        <p class="description">Get a free key from <a href="https://pixabay.com/api/docs/" target="_blank">Pixabay API</a>. (High limits, Free)</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Unsplash API Key</th>
                    <td>
                        <input type="password" id="aimatic_unsplash_key_input" name="aimatic_writer_unsplash_key" value="<?php echo esc_attr(get_option('aimatic_writer_unsplash_key')); ?>" class="regular-text" />
                        <button type="button" class="button button-secondary aimatic-test-key-btn" data-provider="unsplash">Test Key</button>
                        <span class="aimatic-test-result" id="test-result-unsplash"></span>
                        <p class="description">Get a free key from <a href="https://unsplash.com/developers" target="_blank">Unsplash API</a>. (50 requests/hour free)</p>
                    </td>
                </tr>
                 <tr valign="top">
                    <th scope="row">Global Image Size</th>
                     <td>
                        <input type="number" name="aimatic_writer_pollinations_width" placeholder="Width" value="<?php echo esc_attr(get_option('aimatic_writer_pollinations_width', 1200)); ?>" class="small-text"> x 
                        <input type="number" name="aimatic_writer_pollinations_height" placeholder="Height" value="<?php echo esc_attr(get_option('aimatic_writer_pollinations_height', 636)); ?>" class="small-text">
                        <p class="description">Width x Height (Default: 1200x636) - Applies to generated images.</p>
                     </td>
                </tr>
                 <tr valign="top">
                    <th scope="row">DeepAI API Key</th>
                    <td>
                        <input type="password" name="aimatic_writer_deepai_key" value="<?php echo esc_attr(get_option('aimatic_writer_deepai_key')); ?>" class="regular-text" />
                        <p class="description">Required only if using DeepAI.</p>
                    </td>
                </tr>
            </table>

            <hr>

            <!-- YouTube Settings -->
            <h2 class="title">📺 YouTube & Video Settings</h2>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Enable YouTube Videos</th>
                    <td>
                        <label>
                            <input type="checkbox" name="aimatic_writer_enable_youtube" value="1" <?php checked(1, get_option('aimatic_writer_enable_youtube', 1)); ?> />
                            Automatically embed relevant videos
                        </label>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">YouTube API Key</th>
                    <td>
                        <input type="password" id="aimatic_youtube_key_input" name="aimatic_writer_youtube_key" value="<?php echo esc_attr(get_option('aimatic_writer_youtube_key')); ?>" class="regular-text" style="width: 400px;" />
                        <button type="button" class="button button-secondary aimatic-test-key-btn" data-provider="youtube">Test Key</button>
                        <span class="aimatic-test-result" id="test-result-youtube"></span>
                        <p class="description">Required for searching and embedding videos (<a href="https://console.cloud.google.com/apis/credentials" target="_blank">Google Cloud Console</a>).</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Videos per Article</th>
                    <td>
                        <input type="number" name="aimatic_writer_video_count" value="<?php echo esc_attr(get_option('aimatic_writer_video_count', 1)); ?>" min="0" max="5" class="small-text" />
                    </td>
                </tr>
            </table>

            <hr>

            <!-- Cron / Automation Settings (MOVED TO BOTTOM) -->
            <!-- Server Cron Configuration -->
            <h2 class="title">⚡ Server Cron Configuration (Recommended for Background Tasks)</h2>
            <div style="background: #fff; padding: 15px; border: 1px solid #ccd0d4; border-radius: 4px; margin-bottom: 20px;">
                <p>To ensure "Run Now" buttons and Auto-Campaigns work reliably in the background even if you close this tab, you <strong>must</strong> set up a cron job on your server (cPanel / VPS).</p>
                
                <table class="form-table">
                    <!-- NEW: Master Switch -->
                    <tr valign="top">
                        <th scope="row">Enable Cron / Scheduler</th>
                        <td>
                            <label>
                                <input type="checkbox" name="aimatic_writer_cron_enabled" value="1" <?php checked(1, get_option('aimatic_writer_cron_enabled', 1)); ?> />
                                Enable Background Processing (Master Switch)
                            </label>
                            <p class="description">Turn this ON to allow scheduled campaigns to run.</p>
                        </td>
                    </tr>
                    
                    <tr valign="top">
                        <th scope="row">Cron Command (cPanel/VPS)</th>
                        <td>
                            <code style="display:block; padding:10px; background:#f0f0f1; border-radius:4px; margin-bottom:5px;">wget -q -O - <?php echo site_url('wp-cron.php?doing_wp_cron'); ?> >/dev/null 2>&1</code>
                            <p class="description">Run this command <strong>Every Minute (* * * * *)</strong> in your server's Cron Job settings.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">External Cron URL</th>
                        <td>
                            <input type="text" value="<?php echo site_url('wp-cron.php?doing_wp_cron'); ?>" class="regular-text" readonly onclick="this.select();" />
                            <p class="description">If using an external service (like cron-job.org), use this URL.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">VPS SSH Command (One-line)</th>
                        <td>
                            <code style="display:block; padding:10px; background:#f0f0f1; border-radius:4px; margin-bottom:5px; word-break: break-all;">(crontab -l 2>/dev/null; echo "* * * * * wget -q -O - <?php echo site_url('wp-cron.php?doing_wp_cron'); ?> >/dev/null 2>&1") | crontab -</code>
                            <p class="description"><strong>Smart Add:</strong> Paste this into your VPS Terminal to add the job safely without deleting existing ones.</p>
                        </td>
                    </tr>
                </table>
                <div style="margin-top: 15px; border-top: 1px solid #eee; padding-top: 10px;">
                    <button type="button" id="aimatic-force-cron-btn" class="button button-secondary">⚡ Force Check Schedules Now</button>
                    <span style="margin-left:10px; color:#666; font-size:12px;">(Click to manually run the scheduler check)</span>
                </div>
            </div>

            <!-- Campaign Health Check (Debug) -->
            <h2 class="title">🩺 Campaign Schedule Status</h2>
            <div style="background: #fff; padding: 15px; border: 1px solid #ccd0d4; border-radius: 4px; margin-bottom: 20px;">
                <p><strong>Server Time:</strong> <?php echo date("Y-m-d H:i:s", time()); ?> (UTC)</p>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>Campaign Name</th>
                            <th>Status</th>
                            <th>Schedule</th>
                            <th>Last Run</th>
                            <th>Next Run (Est.)</th>
                            <th>Due?</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $campaigns = get_option('aimatic_writer_campaigns', array());
                        if (empty($campaigns)) {
                            echo '<tr><td colspan="6">No campaigns found.</td></tr>';
                        } else {
                            foreach ($campaigns as $camp_id => $camp) {
                                $sch = isset($camp['schedule']) ? $camp['schedule'] : 'hourly';
                                $last = isset($camp['last_run']) ? intval($camp['last_run']) : 0;
                                $status = isset($camp['status']) ? $camp['status'] : 'paused';
                                
                                $interval = 3600;
                                if ($sch === 'daily') $interval = 86400;
                                if ($sch === 'twice_daily') $interval = 43200;
                                if ($sch === 'custom') {
                                    $mins = isset($camp['custom_schedule_minutes']) ? intval($camp['custom_schedule_minutes']) : 60;
                                    $interval = $mins * 60;
                                }
                                
                                $next = $last + $interval;
                                $diff = $next - time();
                                
                                $is_active = ($status === 'active');
                                $is_due = ($diff <= 0);
                                
                                $row_style = '';
                                if ($is_active && $is_due) $row_style = 'style="background-color: #d4edda;"'; // Greenish for due
                                
                                echo "<tr $row_style>";
                                echo "<td><strong>" . esc_html($camp['name']) . "</strong></td>";
                                echo "<td>" . esc_html(ucfirst($status)) . "</td>";
                                echo "<td>" . esc_html($sch . ($sch==='custom' ? " ($mins min)" : "")) . "</td>";
                                echo "<td>" . ($last ? date("Y-m-d H:i:s", $last) : 'Never') . "</td>";
                                echo "<td>" . date("Y-m-d H:i:s", $next) . "</td>";
                                echo "<td>";
                                if (!$is_active) echo '<span class="dashicons dashicons-controls-pause"></span> Paused';
                                else if ($is_due) echo '<strong style="color:green;">YES (Run Pending)</strong>';
                                else echo '<span style="color:gray;">Wait ' . round($diff/60) . 'm</span>';
                                echo "</td>";
                                echo "</tr>";
                            }
                        }
                        ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Settings Import/Export -->
            <h2 class="title">⚙️ Backup & Restore Settings</h2>
            <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; margin-bottom: 20px;">
                <p>Export all plugin settings (API keys, Image settings, etc) to a JSON file, or restore from a backup.</p>
                
                <div style="display: flex; gap: 20px; align-items: flex-start; flex-wrap: wrap;">
                    <div style="flex: 1; min-width: 250px;">
                        <h3>Export</h3>
                         <a href="<?php echo admin_url('admin-ajax.php?action=aimatic_writer_export_settings&nonce=' . wp_create_nonce('aimatic_export_nonce')); ?>" class="button button-primary" target="_blank">
                            ⬇️ Export Settings
                        </a>
                    </div>
                    <div style="flex: 1; min-width: 250px; border-left: 1px solid #eee; padding-left: 20px;">
                        <h3>Import</h3>
                        <input type="file" id="aimatic-import-settings-file" accept=".json" style="margin-bottom: 10px;" />
                        <br>
                        <button type="button" class="button button-secondary" id="aimatic-import-settings-btn">⬆️ Import Settings</button>
                        <span id="aimatic-import-settings-result" style="margin-left: 10px; font-weight: bold;"></span>
                    </div>
                </div>
            </div>

            <!-- Activity Logs -->
            <h2 class="title">📜 Activity Logs</h2>
            <div style="background: #fff; padding: 15px; border: 1px solid #ccd0d4; border-radius: 4px; margin-bottom: 20px;">
                <p>View the latest activity logs for debugging purpose.</p>
                <div style="margin-bottom: 10px;">
                    <button type="button" class="button button-secondary" onclick="location.reload();">Refresh Logs</button>
                    <a href="<?php echo admin_url('admin-ajax.php?action=aimatic_writer_download_log'); ?>" class="button button-primary" target="_blank">Download Full Log</a>
                    <button type="button" class="button button-link-delete" id="aimatic-clear-log" style="float:right;">Clear Logs</button>
                </div>
                <textarea id="aimatic-log-viewer" class="large-text code" rows="15" readonly style="background: #f0f0f1; font-family: monospace; white-space: pre; overflow-x: scroll;"><?php 
                    if (class_exists('AIMatic_Logger')) {
                        $logs = AIMatic_Logger::read_logs(100); 
                        echo esc_textarea(implode("\n", $logs)); 
                    } else {
                        echo "Logger class not loaded.";
                    }
                    ?></textarea>
            </div>

            <?php submit_button(); ?>
        </form>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Provider Toggle Logic
    function toggleProviders() {
        var provider = $('#aimatic_writer_ai_provider').val();
        $('.provider-settings').hide();
        $('.provider-' + provider).show();
    }
    $('#aimatic_writer_ai_provider').on('change', toggleProviders);
    toggleProviders(); // Run on load

    // Test API Button Logic (OpenRouter)
    $('#aimatic-test-api-btn').on('click', function() {
        var btn = $(this);
        var apiKey = $('#aimatic_api_key_input').val();
         var model = $('input[name="aimatic_writer_model_id"]').val();
        
        btn.prop('disabled', true).text('Testing...');
        $('#aimatic-api-test-result').text('');

        $.post(ajaxurl, {
            action: 'aimatic_writer_test_api',
             api_key: apiKey,
             model: model
        }, function(response) {
            btn.prop('disabled', false).text('Test Connection');
            if (response.success) {
                $('#aimatic-api-test-result').text('✅ Connection Successful!').css('color', 'green');
                alert('Success! AI Responded: ' + response.data.message);
            } else {
                $('#aimatic-api-test-result').text('❌ Failed').css('color', 'red');
                alert('Connection Failed: ' + response.data);
            }
        }).fail(function() {
             btn.prop('disabled', false).text('Test Connection');
             alert('Server Error: Could not reach site backend.');
        });
    });

    // Universal Test Custom Provider (Gemini, OpenAI, Zhipu/Z.AI, Pollinations)
    $('.aimatic-test-custom-btn').on('click', function() {
        var btn = $(this);
        var provider = btn.data('provider');
        var inputId = '#aimatic_' + provider + '_key_input';
        var resultSpan = '#test-result-' + provider;
        var apiKey = $(inputId).length ? $(inputId).val() : 'free'; // Fake key for free provider
        
        // Pick model if needed (optional for simple test)
        var model = '';
        if (provider === 'openai') model = $('input[name="aimatic_writer_openai_model"]').val();
        if (provider === 'zhipu') model = $('input[name="aimatic_writer_zhipu_model"]').val();
        if (provider === 'gemini') model = $('input[name="aimatic_writer_gemini_model"]').val();
        if (provider === 'pollinations' || provider === 'aihorde') model = 'openai'; 

        if(!apiKey && provider !== 'pollinations' && provider !== 'aihorde') {
            alert('Please enter an API key first.');
            return;
        }

        btn.prop('disabled', true).text('Testing...');
        $(resultSpan).text('');

        $.post(ajaxurl, {
            action: 'aimatic_writer_test_api', // Reuse same handler, but pass provider
            api_key: apiKey,
            provider: provider,
            model: model
        }, function(response) {
            btn.prop('disabled', false).text('Test Connection');
            if (response.success) {
                $(resultSpan).text('✅ Success!').css('color', 'green');
            } else {
                $(resultSpan).text('❌ ' + response.data).css('color', 'red');
            }
        }).fail(function() {
             btn.prop('disabled', false).text('Test Connection');
             $(resultSpan).text('❌ Server Error').css('color', 'red');
        });
    });

    // Force Cron Button Logic
    $('#aimatic-force-cron-btn').on('click', function() {
        var btn = $(this);
        btn.prop('disabled', true).text('Running...');
        $.post(ajaxurl, {
            action: 'aimatic_writer_force_cron',
            nonce: '<?php echo wp_create_nonce('aimatic_cron_nonce'); ?>'
        }, function(response) {
            alert('Cron executed! Check "Last Checked" after refresh.');
            location.reload();
        }).fail(function() {
            alert('Failed to run cron.');
            btn.prop('disabled', false).text('⚡ Force Run Cron');
        });
    });

    // Universal Test Key Button (Images/Youtube)
    $('.aimatic-test-key-btn').on('click', function() {
        var btn = $(this);
        var provider = btn.data('provider');
        var inputId = '#aimatic_' + provider + '_key_input';
        var resultSpan = '#test-result-' + provider;
        var apiKey = $(inputId).val();

        if(!apiKey) {
            alert('Please enter an API key first.');
            return;
        }

        btn.prop('disabled', true).text('Testing...');
        $(resultSpan).text('');

        $.post(ajaxurl, {
            action: 'aimatic_writer_test_provider_key',
            provider: provider,
            api_key: apiKey
        }, function(response) {
            btn.prop('disabled', false).text('Test Key');
            if (response.success) {
                $(resultSpan).text('✅ Success!').css('color', 'green');
            } else {
                $(resultSpan).text('❌ ' + response.data).css('color', 'red');
            }
        }).fail(function() {
            btn.prop('disabled', false).text('Test Key');
            $(resultSpan).text('❌ Server Error').css('color', 'red');
        });
    });

    // Clear Logs
    $('#aimatic-clear-log').on('click', function() {
        if (!confirm('Are you sure you want to clear all logs?')) return;
        
        var btn = $(this);
        btn.prop('disabled', true).text('Clearing...');
        
        $.post(ajaxurl, {
             action: 'aimatic_writer_clear_log',
             nonce: '<?php echo wp_create_nonce('aimatic_writer_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                $('#aimatic-log-viewer').val('');
                alert('Logs cleared.');
            } else {
                alert('Failed to clear logs.');
            }
            btn.prop('disabled', false).text('Clear Logs');
        });
    });

    // Import Settings Handler
    $('#aimatic-import-settings-btn').on('click', function() {
        var fileInput = $('#aimatic-import-settings-file')[0];
        var resultSpan = $('#aimatic-import-settings-result');
        var btn = $(this);
        
        if (fileInput.files.length === 0) {
            alert('Please select a file first.');
            return;
        }
        
        var file = fileInput.files[0];
        var reader = new FileReader();
        
        btn.prop('disabled', true).text('Importing...');
        resultSpan.text('');
        
        reader.onload = function(e) {
            var jsonContent = e.target.result;
            
            $.post(ajaxurl, {
                action: 'aimatic_writer_import_settings',
                nonce: '<?php echo wp_create_nonce('aimatic_import_nonce'); ?>',
                json_data: jsonContent
            }, function(response) {
                btn.prop('disabled', false).text('⬆️ Import Settings');
                if (response.success) {
                    resultSpan.text('✅ Imported Successfully!').css('color', 'green');
                    alert('Settings imported successfully! Page will reload.');
                    location.reload();
                } else {
                    resultSpan.text('❌ Error: ' + response.data).css('color', 'red');
                }
            }).fail(function() {
                btn.prop('disabled', false).text('⬆️ Import Settings');
                resultSpan.text('❌ Server Error').css('color', 'red');
            });
        };
        
        reader.readAsText(file);
    });
});
</script>
