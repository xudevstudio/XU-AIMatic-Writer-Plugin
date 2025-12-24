<?php
// Load WordPress environment
require_once(dirname(__FILE__) . '/../../../wp-load.php');

if (!current_user_can('manage_options')) {
    echo "Permission Denied.";
    exit;
}

header('Content-Type: text/plain');

echo "=== AIMatic Media Debugger ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

// 1. Check Settings
echo "1. Checking Settings:\n";
$auto_images = get_option('aimatic_writer_auto_images', 1);
$enable_youtube = get_option('aimatic_writer_enable_youtube', 1);
$youtube_key = get_option('aimatic_writer_youtube_key');
$image_source = get_option('aimatic_writer_image_source', 'pollinations');

echo "- Auto Images Enabled: " . ($auto_images ? "YES" : "NO") . "\n";
echo "- Enable YouTube: " . ($enable_youtube ? "YES" : "NO") . "\n";
echo "- YouTube Key Present: " . (!empty($youtube_key) ? "YES" : "NO") . "\n";
echo "- Image Source: " . $image_source . "\n\n";

// 2. Test Image Fetching
echo "2. Testing Image Fetching (Source: $image_source)...\n";
$topic = "artificial intelligence";
$images = AIMatic_Image_Handler::fetch_images($topic, 1);

if ($images && !empty($images)) {
    echo "SUCCESS: Fetched " . count($images) . " images.\n";
    echo "First Image URL: " . $images[0]['url'] . "\n";
    
    // 3. Test Image Download
    echo "\n3. Testing Image Download & Upload to Media Library...\n";
    $url = $images[0]['url'];
    $id = AIMatic_Image_Handler::upload_to_media_library($url, $topic);
    
    if ($id && !is_wp_error($id)) {
        echo "SUCCESS: Image uploaded! Attachment ID: $id\n";
        // Clean up
        wp_delete_attachment($id, true);
        echo "Cleanup: Deleted test attachment $id.\n";
    } else {
        echo "FAILURE: Could not upload image.\n";
        if (is_wp_error($id)) {
            echo "Error: " . $id->get_error_message() . "\n";
        }
    }

} else {
    echo "FAILURE: Could not fetch images.\n";
    if (is_wp_error($images)) {
        echo "Error: " . $images->get_error_message() . "\n";
    }
}

// 4. Test YouTube Search
echo "\n4. Testing YouTube Search...\n";
if ($enable_youtube && !empty($youtube_key)) {
    $youtube = new AIMatic_YouTube();
    $videos = $youtube->search_videos($topic, 1);
    
    if ($videos && !is_wp_error($videos)) {
        echo "SUCCESS: Found " . count($videos) . " videos.\n";
        echo "First Video ID: " . $videos[0] . "\n";
    } else {
        echo "FAILURE: Could not find videos.\n";
         if (is_wp_error($videos)) {
            echo "Error: " . $videos->get_error_message() . "\n";
        }
    }
} else {
    echo "YouTube test skipped (Disabled or No Key).\n";
}
