<?php
if (!defined('ABSPATH')) {
    exit;
}

class AIMatic_Updater {

    private $slug;
    private $pluginData;
    private $updateUrl;
    private $pluginFile;

    public function __construct($pluginFile, $updateUrl) {
        $this->pluginFile = $pluginFile;
        $this->updateUrl = $updateUrl;
        $this->slug = plugin_basename($pluginFile);
    }

    public function init() {
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_update'));
        add_filter('plugins_api', array($this, 'check_info'), 10, 3);
        
        // Force Enable Auto-Update UI
        add_filter('auto_update_plugin', array($this, 'auto_update_specific_plugin'), 10, 2);
        add_filter('plugin_auto_update_setting_html', array($this, 'auto_update_setting_html'), 10, 3);
    }

    public function auto_update_specific_plugin($update, $item) {
        $slug = $item->slug;
        $my_slug = $this->slug;
        
        // Normalize
        if (function_exists('wp_normalize_path')) {
            $slug = wp_normalize_path($slug);
            $my_slug = wp_normalize_path($my_slug);
        }

        // Check for full path or just the folder name
        if ($slug === $my_slug || $slug === dirname($my_slug)) {
            return true;
        }
        return $update;
    }

    public function auto_update_setting_html($html, $plugin_file, $plugin_data) {
        // Normalize
        $normalized_file = $plugin_file;
        $normalized_slug = $this->slug;
        if (function_exists('wp_normalize_path')) {
            $normalized_file = wp_normalize_path($normalized_file);
            $normalized_slug = wp_normalize_path($normalized_slug);
        }

        if ($normalized_file === $normalized_slug && empty($html)) {
            // Render standard WP Auto-Update Link (Enable)
            $html = __('Auto-updates enabled');
        }
        return $html;
    }

    private function get_remote_info() {
        $response = wp_remote_get($this->updateUrl, array(
            'timeout' => 10,
            'headers' => array('Accept' => 'application/json')
        ));

        if (is_wp_error($response) || 200 !== wp_remote_retrieve_response_code($response)) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        return json_decode($body);
    }

    public function check_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        $remote = $this->get_remote_info();
        if (!$remote) return $transient;

        $local_version = get_plugin_data($this->pluginFile)['Version'];
        
        if (version_compare($local_version, $remote->version, '<')) {
            $obj = new stdClass();
            $obj->slug = dirname($this->slug); // Actual slug (folder name)
            $obj->plugin = $this->slug; // Full path (folder/file.php) - Critical for WP < 5.8+ compatibility
            $obj->new_version = $remote->version;
            $obj->url = isset($remote->homepage) ? $remote->homepage : '';
            $obj->package = $remote->download_url;
            $obj->tested = isset($remote->tested) ? $remote->tested : '6.4';
            
            $transient->response[$this->slug] = $obj;
        }

        return $transient;
    }

    public function check_info($false, $action, $arg) {
        if ($action !== 'plugin_information') return $false;
        if ($this->slug !== $arg->slug && dirname($this->slug) !== $arg->slug) return $false;

        $remote = $this->get_remote_info();
        if (!$remote) return $false;

        $obj = new stdClass();
        $obj->name = $remote->name;
        $obj->slug = $this->slug;
        $obj->version = $remote->version;
        $obj->author = isset($remote->author) ? $remote->author : '';
        $obj->homepage = isset($remote->homepage) ? $remote->homepage : '';
        $obj->requires = isset($remote->requires) ? $remote->requires : '5.0';
        $obj->tested = isset($remote->tested) ? $remote->tested : '6.4';
        $obj->last_updated = isset($remote->last_updated) ? $remote->last_updated : date('Y-m-d H:i:s');
        $obj->sections = array(
            'description' => isset($remote->sections->description) ? $remote->sections->description : '',
            'changelog' => isset($remote->sections->changelog) ? $remote->sections->changelog : ''
        );
        $obj->download_link = $remote->download_url;

        return $obj;
    }
}
