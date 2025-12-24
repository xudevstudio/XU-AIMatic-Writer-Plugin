<?php
if (!defined('ABSPATH')) {
    exit;
}

// Check if user has admin access
if (!current_user_can('manage_options')) {
    wp_die('You do not have permission to access this page.');
}
?>

<div class="wrap">
    <h1>AIMatic Writer - API Test</h1>
    
    <div class="aimatic-writer-container" style="max-width: 800px;">
        <h2>Test Your Image APIs</h2>
        
        <div style="background: #f9f9f9; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
            <h3>Current Settings:</h3>
            <ul>
                <li><strong>Auto Images Enabled:</strong> <?php echo get_option('aimatic_writer_auto_images') ? 'Yes ✓' : 'No ✗'; ?></li>
                <li><strong>Image Source:</strong> <?php echo ucfirst(get_option('aimatic_writer_image_source', 'pixabay')); ?></li>
                <li><strong>Image Count:</strong> <?php echo get_option('aimatic_writer_image_count', 3); ?></li>
                <li><strong>Pixabay Key:</strong> <?php echo !empty(get_option('aimatic_writer_pixabay_key')) ? 'Set ✓' : 'Not Set ✗'; ?></li>
                <li><strong>Pexels Key:</strong> <?php echo !empty(get_option('aimatic_writer_pexels_key')) ? 'Set ✓' : 'Not Set ✗'; ?></li>
                <li><strong>Unsplash Key:</strong> <?php echo !empty(get_option('aimatic_writer_unsplash_key')) ? 'Set ✓' : 'Not Set ✗'; ?></li>
            </ul>
        </div>

        <div style="margin-bottom: 20px;">
            <label><strong>Test Query:</strong></label>
            <input type="text" id="test-query" value="technology" style="width: 100%; padding: 10px; margin-top: 5px;">
        </div>

        <button id="test-unsplash-btn" class="button button-primary">Test Unsplash API</button>
        <button id="test-pexels-btn" class="button button-primary">Test Pexels API</button>
        <button id="test-pixabay-btn" class="button button-primary">Test Pixabay API</button>

        <div id="test-results" style="margin-top: 20px; padding: 20px; background: #fff; border: 1px solid #ddd; border-radius: 4px; display: none;">
            <h3>Test Results:</h3>
            <div id="test-output"></div>
        </div>

        <div style="margin-top: 30px; padding: 20px; background: #fffbcc; border-left: 4px solid #ffb900; border-radius: 4px;">
            <h3>📋 How to Get API Keys:</h3>
            <ol>
                <li><strong>Unsplash:</strong> Go to <a href="https://unsplash.com/developers" target="_blank">unsplash.com/developers</a> → Create App → Copy "Access Key"</li>
                <li><strong>Pexels:</strong> Go to <a href="https://www.pexels.com/api/" target="_blank">pexels.com/api</a> → Request API Key → Copy the key</li>
                <li><strong>Pixabay:</strong> Go to <a href="https://pixabay.com/api/docs/" target="_blank">pixabay.com/api/docs</a> → Sign up → Copy API Key</li>
            </ol>
        </div>

        <div style="margin-top: 20px; padding: 20px; background: #e7f3ff; border-left: 4px solid #2271b1; border-radius: 4px;">
            <h3>🔍 Check WordPress Error Logs:</h3>
            <p>After testing, check your WordPress debug log for detailed error messages:</p>
            <code style="background: #fff; padding: 10px; display: block; margin-top: 10px;">
                wp-content/debug.log
            </code>
            <p style="margin-top: 10px;">Enable debugging by adding this to wp-config.php:</p>
            <code style="background: #fff; padding: 10px; display: block; margin-top: 10px;">
                define('WP_DEBUG', true);<br>
                define('WP_DEBUG_LOG', true);
            </code>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    function testAPI(source) {
        const query = $('#test-query').val();
        const resultsDiv = $('#test-results');
        const outputDiv = $('#test-output');
        
        resultsDiv.show();
        outputDiv.html('<p>Testing ' + source + ' API...</p>');
        
        $.post(ajaxurl, {
            action: 'aimatic_writer_test_api',
            nonce: '<?php echo wp_create_nonce('aimatic_writer_nonce'); ?>',
            source: source,
            query: query
        }, function(response) {
            if (response.success) {
                let html = '<p style="color: green;"><strong>✓ Success!</strong> Found ' + response.data.count + ' images</p>';
                html += '<div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-top: 15px;">';
                response.data.images.forEach(function(img) {
                    html += '<div style="border: 1px solid #ddd; padding: 10px; border-radius: 4px;">';
                    html += '<img src="' + img.thumb + '" style="width: 100%; height: auto; border-radius: 4px;">';
                    html += '<p style="font-size: 12px; margin-top: 5px;">Source: ' + img.source + '</p>';
                    html += '</div>';
                });
                html += '</div>';
                outputDiv.html(html);
            } else {
                outputDiv.html('<p style="color: red;"><strong>✗ Error:</strong> ' + response.data + '</p>');
            }
        }).fail(function(xhr, status, error) {
            outputDiv.html('<p style="color: red;"><strong>✗ AJAX Error:</strong> ' + error + '</p>');
        });
    }
    
    $('#test-unsplash-btn').on('click', function() { testAPI('unsplash'); });
    $('#test-pexels-btn').on('click', function() { testAPI('pexels'); });
    $('#test-pixabay-btn').on('click', function() { testAPI('pixabay'); });
});
</script>
