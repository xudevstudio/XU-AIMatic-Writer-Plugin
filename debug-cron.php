<?php
// Load WordPress environment
require_once(dirname(__FILE__) . '/../../../wp-load.php');

if (!current_user_can('manage_options')) {
    echo "Permission Denied.";
    exit;
}

header('Content-Type: text/plain');

echo "=== AIMatic Cron Debugger ===\n";
echo "Server Time: " . date('Y-m-d H:i:s', time()) . " (Timestamp: " . time() . ")\n";
echo "WordPress Time: " . current_time('mysql') . "\n";
echo "Timezone: " . get_option('timezone_string') . "\n\n";

// Check Master Switch
$enabled = get_option('aimatic_writer_cron_enabled', 1);
echo "Master Cron Switch: " . ($enabled ? "ENABLED" : "DISABLED") . "\n\n";

// Check Campaigns
echo "=== Campaigns ===\n";
$campaigns = get_option('aimatic_writer_campaigns', array());
if (empty($campaigns)) {
    echo "No campaigns found.\n";
} else {
    foreach ($campaigns as $id => $c) {
        echo "ID: $id\n";
        echo "Name: {$c['name']}\n";
        echo "Status: {$c['status']}\n";
        echo "Schedule: {$c['schedule']}\n";
        
        $interval = 3600;
        if ($c['schedule'] === 'daily') $interval = 86400;
        if ($c['schedule'] === 'twice_daily') $interval = 43200;
        if ($c['schedule'] === 'custom') {
            $minutes = isset($c['custom_schedule_minutes']) ? intval($c['custom_schedule_minutes']) : 60;
            $interval = $minutes * 60;
        }
        
        $last_run = isset($c['last_run']) ? $c['last_run'] : 0;
        $next_run = $last_run + $interval;
        $due_in = $next_run - time();
        
        echo "Last Run: " . ($last_run ? date('Y-m-d H:i:s', $last_run) : 'Never') . "\n";
        echo "Interval: $interval seconds\n";
        echo "Next Run Calculation: " . date('Y-m-d H:i:s', $next_run) . "\n";
        echo "Due In: $due_in seconds (" . round($due_in/60, 1) . " minutes)\n";
        echo "Is Due? " . ($due_in <= 0 ? "YES" : "NO") . "\n";
        echo "--------------------------\n";
    }
}

// Check WP Cron
echo "\n=== WP Cron Schedules ===\n";
$schedules = wp_get_schedules();
foreach ($schedules as $key => $s) {
    echo "Key: $key | Interval: {$s['interval']} | Display: {$s['display']}\n";
}

echo "\n=== Scheduled Events (aimatic_campaign_cron_event) ===\n";
$crons = _get_cron_array();
if (!empty($crons)) {
    foreach ($crons as $timestamp => $cronhooks) {
        foreach ($cronhooks as $hook => $events) {
            if (strpos($hook, 'aimatic') !== false) {
                echo "Time: " . date('Y-m-d H:i:s', $timestamp) . " ($timestamp)\n";
                echo "Hook: $hook\n";
                print_r($events);
                echo "----------------\n";
            }
        }
    }
} else {
    echo "No cron events found.\n";
}
