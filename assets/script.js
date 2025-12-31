jQuery(document).ready(function ($) {
    console.log("🚀 AIMatic Script 1.1.7 LOADED - Debug Mode");
    // alert("AIMatic Debug: Script v1.1.7 Loaded! If you see this, the update worked.");

    const apiKey = aimatic_writer_vars.api_key;
    const modelId = aimatic_writer_vars.model_id || 'openai/gpt-3.5-turbo';
    const autoImages = aimatic_writer_vars.auto_images;

    // --- Persistence Logic: Auto-Save Settings ---
    const persistenceMap = {
        'aimatic-target-category': 'val',
        'aimatic-target-author': 'val',
        'aimatic-max-words': 'val',
        'aimatic-article-style': 'val',
        'aimatic-prompt': 'val',       // Custom Instructions
        'aimatic-post-status': 'val',
        'aimatic-internal-links': 'check',
        'aimatic-outbound-links': 'check',
        'aimatic-read-also': 'check',
        'aimatic-enable-video': 'check'
    };

    // 1. Load Saved Settings on Load
    $.each(persistenceMap, function (id, type) {
        const saved = localStorage.getItem(id);
        if (saved !== null) {
            if (type === 'val') {
                $('#' + id).val(saved);
            } else if (type === 'check') {
                $('#' + id).prop('checked', saved === 'true');
            }
        }
    });

    // 2. Save Settings on Input Change
    $.each(persistenceMap, function (id, type) {
        $('#' + id).on('change input paste keyup', function () {
            const val = type === 'val' ? $(this).val() : $(this).is(':checked');
            localStorage.setItem(id, val);
        });
    });

    // 3. Keep "Advanced Options" Open if it was open
    const advancedOpen = localStorage.getItem('aimatic-advanced-open');
    if (advancedOpen === 'true') {
        $('#aimatic-advanced-options').show();
    }
    // Toggle Logic Update (to save state)
    window.toggleAdvancedOptions = function () {
        const el = document.getElementById('aimatic-advanced-options');
        const isHidden = el.style.display === 'none';
        el.style.display = isHidden ? 'block' : 'none';
        localStorage.setItem('aimatic-advanced-open', isHidden); // Save new state
    };
    // Re-bind the onclick in HTML or just use JQuery here if possible. 
    // The HTML has onclick="...", so let's override the button behavior or ensuring the global function works.
    // Simpler: Clean up the HTML button in next step or just attach click event here using jQuery.

    $('.aimatic-wrap button:contains("Show Advanced Options")').attr('onclick', '').on('click', function (e) {
        e.preventDefault();
        $('#aimatic-advanced-options').toggle();
        localStorage.setItem('aimatic-advanced-open', $('#aimatic-advanced-options').is(':visible'));
    });
    const imageCount = parseInt(aimatic_writer_vars.image_count) || 3;

    const generateBtn = $('#aimatic-generate-btn');
    const publishBtn = $('#aimatic-publish-btn');
    const status = $('#aimatic-status');
    const publishMessage = $('#aimatic-publish-message');

    let uploadedImages = [];
    let featuredImageId = null;
    let imagesProcessing = false;
    let articleComplete = false;

    function getEditor() {
        return (typeof tinymce !== 'undefined') ? tinymce.get('aimatic_editor') : null;
    }

    function setEditorContent(content) {
        const editor = getEditor();
        if (editor && editor.initialized) {
            editor.setContent(content);
        } else {
            $('#aimatic_editor').val(content);
        }
    }

    function getEditorContent() {
        const editor = getEditor();
        return editor ? editor.getContent() : $('#aimatic_editor').val();
    }

    function insertImageAtPosition(imageUrl, alt, afterElement) {
        console.log(`🖼️ Inserting image at position: ${afterElement}, URL: ${imageUrl}`);

        const editor = getEditor();
        if (!editor || !editor.initialized) {
            console.error('❌ Editor not initialized');
            return;
        }

        try {
            const editorBody = editor.getBody();

            // Create image with 1200x630 size and center alignment
            const imgElement = editor.dom.create('img', {
                src: imageUrl,
                alt: alt,
                width: '1200',
                height: '630',
                class: 'aligncenter',
                style: 'display:block;margin-left:auto;margin-right:auto;max-width:100%;height:auto;'
            });

            if (afterElement === 'title') {
                const h1 = editorBody.querySelector('h1');
                if (h1) {
                    h1.parentNode.insertBefore(imgElement, h1.nextSibling);
                    console.log('✅ Image inserted after H1');
                } else {
                    editorBody.insertBefore(imgElement, editorBody.firstChild);
                    console.log('✅ Image inserted at top');
                }
            } else {
                const allParagraphs = Array.from(editorBody.querySelectorAll('p'));
                const paragraphsWithoutImages = allParagraphs.filter(p => !p.querySelector('img'));

                if (paragraphsWithoutImages.length > afterElement && paragraphsWithoutImages[afterElement]) {
                    const targetParagraph = paragraphsWithoutImages[afterElement];
                    targetParagraph.parentNode.insertBefore(imgElement, targetParagraph.nextSibling);
                    console.log(`✅ Image inserted after paragraph ${afterElement}`);
                } else {
                    editorBody.appendChild(imgElement);
                    console.log('✅ Image appended at end');
                }
            }

            editor.fire('change');
            editor.fire('input');
            console.log(`✅ Total images in editor: ${editorBody.querySelectorAll('img').length}`);

        } catch (e) {
            console.error('❌ Image insertion failed:', e);
        }
    }

    function showProgressModal() {
        $('#aimatic-image-progress-modal').css('display', 'flex');
        $('#aimatic-progress-content').html('<p>⏳ Starting image processing...</p>');
    }

    function hideProgressModal() {
        $('#aimatic-image-progress-modal').css('display', 'none');
    }

    function updateProgress(message, percent) {
        const content = $('#aimatic-progress-content');
        content.append(`<p>${message}</p>`);
        content.scrollTop(content[0].scrollHeight);

        $('#aimatic-progress-bar').css('width', percent + '%');
        $('#aimatic-progress-percent').text(Math.round(percent) + '%');
    }

    function checkIfReadyToPublish() {
        if (articleComplete && !imagesProcessing) {
            status.text('✅ Ready to publish!');
            publishBtn.prop('disabled', false).show();
            publishMessage.removeClass('error').addClass('success').text('Article and images are ready!');
        } else if (articleComplete && imagesProcessing) {
            status.text('⏳ Please wait, processing images...');
            publishBtn.prop('disabled', true).show();
            publishMessage.removeClass('success').addClass('error').text('Please wait while images are being uploaded...');
        }
    }

    async function processImagesInBackground(topic, options = {}) {
        if (imagesProcessing) return;
        imagesProcessing = true;

        status.text('Generating AI images...');
        showProgressModal();

        try {
            const content = getEditorContent();
            if (!content) {
                console.error('No content found');
                return;
            }

            // Parse content to find headings
            const parser = new DOMParser();
            const doc = parser.parseFromString(content, 'text/html');
            const headings = doc.querySelectorAll('h2, h3');

            // --- Images Logic ---
            // Proceed only if Global Auto Images is ON (we assume the user wants images if they enabled the plugin feature)
            // But we can also add a specific toggle for images if needed. For now, we stick to Global.
            if (autoImages == 1) {

                if (headings.length === 0) {
                    console.log('No headings found, using topic');
                    updateProgress('No headings found. Using topic...', 100);
                    await new Promise(r => setTimeout(r, 1000));
                    // If no headings, we skip image insertion logic based on headings, 
                    // but we might want one hero image?
                } else {

                    // Limit to max images setting (Global)
                    const maxImages = parseInt(aimatic_writer_vars.image_count) || 3;
                    const interval = parseInt(aimatic_writer_vars.heading_interval) || 2;

                    // Filter Headings by Interval
                    let validHeadings = [];
                    for (let i = 0; i < headings.length; i++) {
                        if ((i + 1) % interval === 0) {
                            validHeadings.push({
                                headingText: headings[i].textContent.trim(),
                                index: i
                            });
                        }
                    }

                    // Limit valid slots to maxImages
                    let imagesToProcess = validHeadings.slice(0, maxImages);

                    // If no valid headings found via interval, pick at least one
                    if (imagesToProcess.length === 0 && headings.length > 0 && maxImages > 0) {
                        const mid = Math.floor(headings.length / 2);
                        imagesToProcess.push({
                            headingText: headings[mid].textContent.trim(),
                            index: mid
                        });
                    }

                    // Sort back by index
                    imagesToProcess.sort((a, b) => a.index - b.index);

                    let processedCount = 0;
                    const total = imagesToProcess.length;

                    for (let i = 0; i < total; i++) {
                        const item = imagesToProcess[i];
                        const percent = Math.round((i / total) * 100);

                        status.text(`Generating image ${i + 1}/${total}: "${item.headingText}"...`);
                        updateProgress(`Generating image for: "${item.headingText}"...`, percent);

                        try {
                            const fetchResponse = await $.post(aimatic_writer_vars.ajax_url, {
                                action: 'aimatic_writer_fetch_images',
                                nonce: aimatic_writer_vars.nonce,
                                query: item.headingText,
                                count: 1
                            });

                            if (!fetchResponse.success || !fetchResponse.data || fetchResponse.data.length === 0) {
                                updateProgress(`❌ Failed to find image for "${item.headingText}". Skipping.`, percent);
                                continue;
                            }

                            const imageUrl = fetchResponse.data[0].url;

                            status.text(`Uploading image ${i + 1}/${total}...`);
                            updateProgress(`Uploading image to library...`, percent + (50 / total));

                            const uploadResponse = await $.post(aimatic_writer_vars.ajax_url, {
                                action: 'aimatic_writer_upload_image',
                                nonce: aimatic_writer_vars.nonce,
                                image_url: imageUrl,
                                alt_text: item.headingText
                            });

                            if (uploadResponse.success) {
                                const attachment = uploadResponse.data;
                                uploadedImages.push(attachment.id);

                                if (!featuredImageId) {
                                    featuredImageId = attachment.id;
                                    console.log('Featured Image Set:', featuredImageId);
                                }

                                insertImageAfterHeadingText(item.headingText, attachment.url, item.headingText);
                                processedCount++;

                                updateProgress(`✅ Image added!`, percent + (100 / total));
                                await new Promise(r => setTimeout(r, 500));

                            } else {
                                console.error(`Upload failed`, uploadResponse);
                                updateProgress(`❌ Upload failed.`, percent);
                            }

                        } catch (error) {
                            console.error(`Error processing image`, error);
                            updateProgress(`❌ Error processing image.`, percent);
                        }
                    }

                    status.text(`Added ${processedCount} generated images.`);
                }
            } // End Auto Images

            updateProgress(`🎉 Images done. Checking for video...`, 90);

            // --- Video Processing ---
            // Now strictly controlled by options.enableVideo
            if (options.enableVideo) {
                try {
                    updateProgress(`Searching for related video...`, 92);

                    let searchQuery = topic.replace(/^(How to|Guide to|Best|Top \d+)\s+/i, '');
                    const videoCount = parseInt(aimatic_writer_vars.video_count) || 1;

                    // Force at least 1 video if enabled
                    let loopCount = Math.max(videoCount, 1);
                    let insertedCount = 0;

                    for (let v = 0; v < loopCount; v++) {
                        const videoResponse = await $.post(aimatic_writer_vars.ajax_url, {
                            action: 'aimatic_writer_fetch_video',
                            nonce: aimatic_writer_vars.nonce,
                            query: searchQuery + (v > 0 ? " part " + (v + 1) : "")
                        });

                        if (videoResponse.success && videoResponse.data) {
                            // Normalize response (single vs array?)
                            // Current PHP fetch_video returns {video_id, embed_html} for single
                            // We need to support multiple?
                            // Let's stick to the existing response format which seems single.
                            // If we want multiple, we need to change PHP or make multiple unique calls.
                            // For this patch, let's just insert what we get.

                            const embedHtml = videoResponse.data.embed_html;
                            if (embedHtml) {
                                const editor = getEditor();
                                if (editor) {
                                    let content = editor.getContent();
                                    // Append to bottom for safety, or smart insert?
                                    // User complained about "not linking". 
                                    // Let's append firmly at the bottom for now to ensure visibility.

                                    const videoBlock = `<div class="aimatic-video-embed" style="margin: 30px auto; text-align:center;"><h3>Related Video</h3>${embedHtml}</div>`;
                                    editor.setContent(content + videoBlock);

                                    insertedCount++;
                                    updateProgress(`✅ Video inserted!`, 98);
                                }
                            }
                        } else {
                            console.log('No video found:', videoResponse);
                        }
                    }

                    if (insertedCount === 0) {
                        updateProgress(`⚠️ No video found for "${searchQuery}".`, 95);
                    }

                } catch (videoError) {
                    console.error('Video error:', videoError);
                    updateProgress(`⚠️ Video search failed.`, 95);
                }
            } else {
                updateProgress(`ℹ️ Video disabled.`, 95);
            }

            updateProgress(`🎉 All Done!`, 100);
            await new Promise(r => setTimeout(r, 1000));

        } catch (fatalError) {
            console.error('Fatal error in media processing:', fatalError);
            alert('Something went wrong processing media: ' + fatalError.message);
        } finally {
            finishImageProcessing();
            hideProgressModal();
        }
    }

    function insertImageAfterHeadingText(headingText, imageUrl, altText) {
        const editor = getEditor();
        if (!editor) return;

        const imgHtml = `<img src="${imageUrl}" alt="${altText}" class="aligncenter size-large" style="max-width: 100%; height: auto; display: block; margin: 20px auto;" />`;
        const content = editor.getContent();

        // Find the heading in the content and insert image after it
        // We look for the heading text inside H2 or H3 tags
        const regex = new RegExp(`(<h[23][^>]*>\\s*${escapeRegExp(headingText)}\\s*<\\/h[23]>)`, 'i');

        if (regex.test(content)) {
            const newContent = content.replace(regex, `$1\n${imgHtml}\n`);
            editor.setContent(newContent);
        } else {
            console.log(`Heading "${headingText}" not found for insertion, appending image.`);
            editor.insertContent(imgHtml);
        }
    }

    function escapeRegExp(string) {
        return string.replace(/[.*+?^$${}()|[\]\\]/g, '\\$&');
    }

    function finishImageProcessing() {
        imagesProcessing = false;
        checkIfReadyToPublish();
    }

    generateBtn.on('click', async function () {
        const topic = $('#aimatic-topic').val();
        const customPrompt = $('#aimatic-prompt').val();

        // Advanced Options
        const maxWords = $('#aimatic-max-words').val();
        const articleStyle = $('#aimatic-article-style').val();
        const internalLinks = $('#aimatic-internal-links').is(':checked');
        const outboundLinks = $('#aimatic-outbound-links').is(':checked');
        const readAlso = $('#aimatic-read-also').is(':checked');
        const enableVideo = $('#aimatic-enable-video').is(':checked');

        if (maxWords && parseInt(maxWords) < 300) {
            alert('Maximum word count must be at least 300.');
            return;
        }

        if (!topic) {
            alert('Please enter a topic.');
            return;
        }

        if (!apiKey) {
            alert('Please set your OpenRouter API Key in the settings first.');
            return;
        }

        let attempts = 0;
        while (attempts < 20) {
            const editor = getEditor();
            if (editor && editor.initialized) {
                console.log('✓ TinyMCE ready');
                break;
            }
            await new Promise(r => setTimeout(r, 200));
            attempts++;
        }

        setEditorContent('');
        status.text('Connecting...');
        generateBtn.prop('disabled', true);
        publishBtn.hide();
        publishMessage.text('');
        uploadedImages = [];
        featuredImageId = null;
        imagesProcessing = false;
        articleComplete = false;

        console.log('=== AIMatic Writer Started ===');

        // Style Map Definition
        const styleMap = {
            'generic': "Standard Blog Post",
            'how-to': "Step-by-Step How-To Guide (Numbered Steps)",
            'listicle': "Listicle (Top 10 style)",
            'informative': "Deep Dive Educational Article",
            'guide': "Ultimate Guide (Comprehensive)",
            'comparison': "Comparison (X vs Y)",
            'review': "Product Review",
            'trend': "News/Trend Analysis",
            'case-study': "Case Study",
            'editorial': "Opinion Piece",
            'faq': "FAQ Format"
        };

        // --- CONSTRUCT SYSTEM PROMPT (STRICT CONSTRAINTS) ---
        let systemDirectives = [
            "You are a professional article writer.",
            "Write ONLY in pure Markdown.",
            "Do NOT use HTML tags.",
            "Do NOT include the Main Title H1 at the start."
        ];

        // 1. WORD COUNT (Strict)
        if (maxWords) {
            systemDirectives.push(`CRITICAL: You MUST write approximately ${maxWords} words. Do not be lazy. Write a full, long-form article.`);
        }

        // 2. ARTICLE STYLE
        if (styleMap[articleStyle] && articleStyle !== 'generic') {
            systemDirectives.push(`STYLE: The user wants a "${styleMap[articleStyle]}". Adhere strictly to this format.`);
        }

        // Detect Link Counts (Outer Scope)
        let intLinkCount = "5-7";
        if (parseInt(maxWords) >= 1500) intLinkCount = "10-12";
        else if (parseInt(maxWords) >= 1000) intLinkCount = "7-9";

        let outLinkCount = "3-4";
        if (parseInt(maxWords) >= 1500) outLinkCount = "6-8";
        else if (parseInt(maxWords) >= 800) outLinkCount = "4-5";

        // 3. INTERNAL LINKS (Context Aware)
        if (internalLinks) {

            systemDirectives.push(`INTERNAL LINKS (BODY): You MUST include ${intLinkCount} internal links to other articles embedded naturally in the body text.`);
            systemDirectives.push(`LINK PLACEMENT: Distribute these ${intLinkCount} links evenly from Introduction to Conclusion. Insert them into random paragraphs. Do NOT clump them.`);


            if (aimatic_writer_vars.existing_posts && aimatic_writer_vars.existing_posts.length > 20) {
                systemDirectives.push(`LINK CONTEXT: Here are the ONLY valid internal URLs you can use. Pick ${intLinkCount} relevant ones and link them naturally in the text:\n${aimatic_writer_vars.existing_posts}`);
            } else {
                const homeUrl = aimatic_writer_vars.site_url;
                systemDirectives.push(`LINK FORMAT: Use [Keyword](${homeUrl}/keyword-slug/).`);
            }
        }

        // 4. OUTBOUND LINKS
        if (outboundLinks) {

            systemDirectives.push(`EXTERNAL LINKS: You MUST include ${outLinkCount} citations to high-authority domains (Wikipedia, BBC, Forbes, etc). Real URLs only.`);
        }

        // 5. READ ALSO
        if (readAlso) {
            systemDirectives.push("READ ALSO SECTION: Add a separate 'Read Also' section at the end with 3 distinct links. (NOTE: These do NOT count towards the Internal Links limit above).");
        }

        // 6. CUSTOM INSTRUCTIONS (Highest Priority)
        if (customPrompt && customPrompt.trim() !== "") {
            systemDirectives.push(`USER SPECIAL INSTRUCTIONS (OVERRIDE ALL ELSE): ${customPrompt}`);
        }

        const finalSystemPrompt = systemDirectives.join("\n\n");

        // --- REDUNDANT USER PROMPT (Forcing AI compliance) ---
        let userDirectives = [`TOPIC: ${topic}`];

        if (maxWords) userDirectives.push(`(Length: ~${maxWords} words)`);

        if (internalLinks) {
            const homeUrl = aimatic_writer_vars.site_url;
            userDirectives.push(`IMPORTANT: You must include ${intLinkCount} internal links to ${homeUrl} in the body (excluding 'Read Also').`);
            if (aimatic_writer_vars.existing_posts && aimatic_writer_vars.existing_posts.length > 5) {
                userDirectives.push("(Check System Prompt for list of valid URLs to use)");
            }
        }

        if (outboundLinks) {
            userDirectives.push("IMPORTANT: Include 4+ external citations (Wikipedia/News).");
        }

        const userPrompt = userDirectives.join("\n") + "\n\nStart writing the article now:";

        console.log('📝 System Prompt:', finalSystemPrompt);
        console.log('📝 User Prompt:', userPrompt);
        console.log('🔗 Internal Links Enabled:', internalLinks);
        console.log('🔗 Existing Posts Available:', aimatic_writer_vars.existing_posts ? aimatic_writer_vars.existing_posts.length : 0);

        let apiUrl = "https://openrouter.ai/api/v1/chat/completions";
        if (aimatic_writer_vars.provider === 'openai') {
            apiUrl = "https://api.openai.com/v1/chat/completions";
        } else if (aimatic_writer_vars.provider === 'zhipu') {
            apiUrl = "https://open.bigmodel.cn/api/paas/v4/chat/completions";
        }

        console.log('📡 Calling AI Provider:', {
            provider: aimatic_writer_vars.provider,
            url: apiUrl,
            model: modelId,
            site: aimatic_writer_vars.site_url
        });

        try {
            const response = await fetch(apiUrl, {
                method: "POST",
                headers: {
                    "Authorization": `Bearer ${apiKey}`,
                    "Content-Type": "application/json",
                    "HTTP-Referer": aimatic_writer_vars.site_url,
                    "X-Title": aimatic_writer_vars.site_title
                },
                body: JSON.stringify({
                    "model": modelId,
                    "messages": [
                        { "role": "system", "content": finalSystemPrompt },
                        { "role": "user", "content": userPrompt }
                    ],
                    "stream": true
                })
            });

            if (!response.ok) {
                throw new Error(`API Error: ${response.statusText}`);
            }

            status.text('Writing article...');

            const reader = response.body.getReader();
            const decoder = new TextDecoder("utf-8");
            let fullMarkdown = "";
            let wordCount = 0;
            let buffer = "";

            while (true) {
                const { done, value } = await reader.read();
                if (done) break;

                buffer += decoder.decode(value, { stream: true });
                const lines = buffer.split("\n");

                // Keep the last partial line in the buffer
                buffer = lines.pop();

                for (const line of lines) {
                    if (line.startsWith("data: ")) {
                        const dataStr = line.slice(6);
                        if (dataStr === "[DONE]") continue;

                        try {
                            const data = JSON.parse(dataStr);
                            const content = data.choices[0]?.delta?.content || "";

                            if (content) {
                                fullMarkdown += content;
                                wordCount = fullMarkdown.split(/\s+/).filter(w => w.length > 0).length;
                                status.text(`Writing... (${wordCount} words)`);

                                // Only update editor occasionally or at end to save performance? 
                                // For now, keep real-time but maybe throttle?

                                // Simple markdown to html
                                if (typeof marked !== 'undefined') {
                                    let cleanMarkdown = fullMarkdown.replace(/^#\s+.+\n/g, '');
                                    cleanMarkdown = cleanMarkdown.replace(/^\s*#\s+[^\n]+\n/g, '');
                                    let html = marked.parse(cleanMarkdown);
                                    html = html.replace(/<h1[^>]*>.*?<\/h1>/gi, '');
                                    setEditorContent(html);
                                }

                                // Auto-scroll
                                setTimeout(() => {
                                    const iframe = document.getElementById('aimatic_editor_ifr');
                                    if (iframe && iframe.contentWindow) {
                                        iframe.contentWindow.scrollTo(0, iframe.contentWindow.document.body.scrollHeight);
                                    }
                                }, 50);
                            }
                        } catch (e) {
                            // JSON Parse error is expected for partial chunks if buffer logic fails, 
                            // but with buffer logic it should be rare.
                            console.error("Stream parse error:", e);
                        }
                    }
                }
            }

            console.log('=== Article Complete ===');
            status.text(`✅ Article Written (${wordCount} words)`);
            articleComplete = true;
            generateBtn.prop('disabled', false);

            console.log('🚀 Starting media processing...');
            processImagesInBackground(topic, {
                enableVideo: enableVideo,
                videoCount: 1
            });

        } catch (error) {
            console.error(error);
            status.text('Error: ' + error.message);
            generateBtn.prop('disabled', false);
        }
    });

    // --- Robust Media Insertion ---

    async function processImagesInBackground(topic, options = {}) {
        if (imagesProcessing) return;
        imagesProcessing = true;

        status.text('Generating images...');
        showProgressModal();

        try {
            const editor = getEditor();
            if (!editor) { console.error('No editor'); return; }

            // We must work with the HTML string or DOM. 
            // Using logic on the live DOM is risky if user is typing, but here it's auto-process.
            // Let's rely on Editor Content String -> DOMParser (safe) -> Manipulate -> SetBack.

            let content = editor.getContent();
            const parser = new DOMParser();
            const doc = parser.parseFromString(content, 'text/html');

            // Find all h2/h3 elements
            const headings = Array.from(doc.querySelectorAll('h2, h3'));
            let modified = false;

            // --- Image Insertion Logic ---
            if (autoImages == 1 && headings.length > 0) {
                const maxImages = parseInt(aimatic_writer_vars.image_count) || 3;
                const interval = parseInt(aimatic_writer_vars.heading_interval) || 2;

                let imagesUsed = 0;

                for (let i = 0; i < headings.length; i++) {
                    if (imagesUsed >= maxImages) break;

                    // Interval check (every Nth heading)
                    if ((i + 1) % interval !== 0) continue;

                    const hNode = headings[i];
                    const headingText = hNode.textContent.trim();
                    if (!headingText) continue;

                    status.text(`Generating image for: ${headingText}...`);

                    // Generate Image
                    try {
                        const fetchResponse = await $.post(aimatic_writer_vars.ajax_url, {
                            action: 'aimatic_writer_fetch_images',
                            nonce: aimatic_writer_vars.nonce,
                            query: headingText,
                            count: 1
                        });

                        if (fetchResponse.success && fetchResponse.data && fetchResponse.data.length > 0) {
                            const imageUrl = fetchResponse.data[0].url;

                            const uploadResponse = await $.post(aimatic_writer_vars.ajax_url, {
                                action: 'aimatic_writer_upload_image',
                                nonce: aimatic_writer_vars.nonce,
                                image_url: imageUrl,
                                alt_text: headingText
                            });

                            if (uploadResponse.success) {
                                const attachment = uploadResponse.data;
                                uploadedImages.push(attachment.id);
                                if (!featuredImageId) featuredImageId = attachment.id;

                                // Create Image Node
                                const imgDiv = doc.createElement('div');
                                imgDiv.className = 'aimatic-image-wrapper';
                                imgDiv.style.textAlign = 'center';
                                imgDiv.style.margin = '20px 0';
                                imgDiv.innerHTML = `<img src="${attachment.url}" alt="${headingText}" class="aligncenter size-large" style="max-width: 100%; height: auto;" />`;

                                // Insert AFTER the heading
                                if (hNode.nextSibling) {
                                    hNode.parentNode.insertBefore(imgDiv, hNode.nextSibling);
                                } else {
                                    hNode.parentNode.appendChild(imgDiv);
                                }

                                imagesUsed++;
                                modified = true;
                                updateProgress(`✅ Image added after "${headingText}"`, (imagesUsed / maxImages) * 80);
                            }
                        }
                    } catch (err) {
                        console.error('Image Gen Error', err);
                    }
                }
            }

            // --- Video Insertion Logic ---
            if (options.enableVideo) {
                updateProgress('Finding Video...', 90);
                try {
                    // Smart Placement: Find "Middle" paragraph
                    const paragraphs = Array.from(doc.querySelectorAll('p'));

                    if (paragraphs.length > 3) {
                        let searchQuery = topic.replace(/^(How to|Guide to|Best|Top \d+)\s+/i, '');
                        const videoResponse = await $.post(aimatic_writer_vars.ajax_url, {
                            action: 'aimatic_writer_fetch_video',
                            nonce: aimatic_writer_vars.nonce,
                            query: searchQuery
                        });

                        if (videoResponse.success && videoResponse.data && videoResponse.data.embed_html) {
                            const embedHtml = videoResponse.data.embed_html;

                            // Insert at roughly 60% of the article
                            const targetIndex = Math.floor(paragraphs.length * 0.6);
                            const pNode = paragraphs[targetIndex];

                            const vidDiv = doc.createElement('div');
                            vidDiv.className = 'aimatic-video-embed';
                            vidDiv.style.textAlign = 'center';
                            vidDiv.style.margin = '30px auto';
                            vidDiv.innerHTML = `${embedHtml}`; // Removed Heading

                            if (pNode.nextSibling) {
                                pNode.parentNode.insertBefore(vidDiv, pNode.nextSibling);
                            } else {
                                pNode.parentNode.appendChild(vidDiv);
                            }
                            modified = true;
                            updateProgress('✅ Video inserted at middle of article.', 95);
                        }
                    }
                } catch (err) {
                    console.log('Video error', err);
                }
            }

            // --- Apply Changes ---
            if (modified) {
                // Serialize back to string
                const serializer = new XMLSerializer();
                // body content only
                const newContent = doc.body ? doc.body.innerHTML : serializer.serializeToString(doc); // XMLSerializer might serialize full doc?
                // Actually DOMParser 'text/html' gives a full doc. We want innerHTML of body.
                editor.setContent(doc.body.innerHTML);
            }

            updateProgress('🎉 All Media Processed!', 100);
            await new Promise(r => setTimeout(r, 1000));

        } catch (fatalError) {
            console.error('Fatal error in media processing:', fatalError);
            alert('Something went wrong processing media: ' + fatalError.message);
        } finally {
            finishImageProcessing();
            hideProgressModal();
        }
    }

    // REMOVED old processImagesInBackground and insertImageAfterHeadingText
    // This function replaces BOTH.

    function insertImageAfterHeadingText(headingText, imageUrl, altText) {
        // Deprecated dummy to prevent errors if called elsewhere
    }

    $('#aimatic-post-status').on('change', function () {
        if ($(this).val() === 'future') {
            $('#aimatic-schedule-date').show();
        } else {
            $('#aimatic-schedule-date').hide();
        }
    });

    publishBtn.on('click', async function () {
        const title = $('#aimatic-topic').val();
        const postStatus = $('#aimatic-post-status').val();
        const date = $('#aimatic-schedule-date').val();
        const content = getEditorContent();

        // Advanced Publishing Options
        const categoryId = $('#aimatic-target-category').val();
        const authorId = $('#aimatic-target-author').val();

        if (!content) {
            alert('No content to publish.');
            return;
        }

        if (postStatus === 'future' && !date) {
            alert('Please select a date.');
            return;
        }

        publishBtn.prop('disabled', true).text('Publishing...');

        $.post(aimatic_writer_vars.ajax_url, {
            action: 'aimatic_writer_publish',
            nonce: aimatic_writer_vars.nonce,
            title: title,
            content: content,
            status: postStatus,
            date: date,
            featured_image_id: featuredImageId,
            category_id: categoryId,
            author_id: authorId
        }, function (response) {
            if (response.success) {
                let msg = 'Published!';
                if (postStatus === 'draft') msg = 'Saved as Draft!';
                if (postStatus === 'future') msg = 'Scheduled!';

                publishMessage.removeClass('error').addClass('success').html(`${msg} <a href="${response.data.post_url}" target="_blank">View</a>`);
                publishBtn.text('Done');
            } else {
                publishMessage.removeClass('success').addClass('error').text('Error: ' + response.data);
                publishBtn.prop('disabled', false).text('Publish Article');
            }
        });
    }); // End of publishBtn.on('click')

    // --- AI Image Generator Logic ---

    // --- Tab Switching Logic ---
    $('.nav-tab-wrapper a').on('click', function (e) {
        e.preventDefault();

        // Remove active class from all tabs
        $('.nav-tab-wrapper a').removeClass('nav-tab-active');
        // Add active class to clicked tab
        $(this).addClass('nav-tab-active');

        // Hide all sections
        $('.aimatic-section').hide();

        // Show target section
        const target = $(this).attr('href'); // #writer or #campaigns

        if (target === '#writer') {
            $('#aimatic-writer-section').show();
        } else if (target === '#campaigns') {
            $('#aimatic-campaigns-section').show();
        } else if (target === '#image-generator') { // Added for image generator tab
            $('#aimatic-image-generator-section').show();
        }
    });

    // Default to Writer tab or maintain state if possible
    const urlParams = new URLSearchParams(window.location.search);
    const tabParam = urlParams.get('tab');

    if (window.location.hash === '#campaigns' || tabParam === 'campaigns') {
        $('#tab-campaigns').click();
    } else if (window.location.hash === '#image-generator' || tabParam === 'image-generator') {
        $('#tab-image-generator').click();
    } else {
        $('#tab-writer').click(); // Default
    }

    // Image Generation
    $('#aimatic-generate-image-btn').on('click', function () {
        const prompt = $('#aimatic-image-prompt').val().trim();
        const previewArea = $('#aimatic-image-preview');
        const statusSpan = $('#aimatic-image-status');
        const actionArea = $('#aimatic-image-actions');

        const width = aimatic_writer_vars.pollinations_width || 1024;
        const height = aimatic_writer_vars.pollinations_height || 1024;

        if (!prompt) {
            alert('Please enter an image description.');
            return;
        }

        $(this).prop('disabled', true);
        statusSpan.text('⏳ Generating...');
        previewArea.html('<p>⏳ Generating image... This is instant with Pollinations.ai!</p>');
        actionArea.hide();

        // Construct URL
        const encodedPrompt = encodeURIComponent(prompt);
        const imageUrl = `https://image.pollinations.ai/prompt/${encodedPrompt}?width=${width}&height=${height}&nologo=true`;

        const img = new Image();
        img.onload = function () {
            previewArea.html('');
            img.style.maxWidth = '100%';
            img.style.borderRadius = '4px';
            img.style.boxShadow = '0 2px 10px rgba(0,0,0,0.1)';
            previewArea.append(img);

            statusSpan.text('✅ Done!');
            $('#aimatic-generate-image-btn').prop('disabled', false);

            $('#aimatic-download-image-btn').attr('href', imageUrl);
            $('#aimatic-insert-image-btn').data('url', imageUrl).data('alt', prompt);
            actionArea.css('display', 'flex');
        };
        img.onerror = function () {
            previewArea.html('<p style="color:red;">❌ Failed to load image. Please try again.</p>');
            statusSpan.text('❌ Error');
            $('#aimatic-generate-image-btn').prop('disabled', false);
        };

        img.src = imageUrl;
    });

    // Insert Image into Article
    $('#aimatic-insert-image-btn').on('click', function () {
        const url = $(this).data('url');
        const alt = $(this).data('alt');

        const editor = getEditor();
        if (editor && editor.initialized) {
            editor.insertContent(`<img src="${url}" alt="${alt}" class="aligncenter" style="display:block;margin-left:auto;margin-right:auto;max-width:100%;height:auto;" width="800" />`);
            alert('Image inserted into article!');
        } else {
            const textarea = $('#aimatic_editor');
            const currentContent = textarea.val();
            textarea.val(currentContent + `\n\n![${alt}](${url})\n\n`);
            alert('Image inserted into article editor (text mode)!');
        }
    });

    // --- Campaign Manager Logic ---

    let currentCampaignId = null;

    // Edit Campaign Handler
    $(document).on('click', '.btn-edit-campaign', function (e) {
        e.preventDefault();
        const btn = $(this);

        // Populate Form
        $('#camp-name').val(btn.data('name'));
        $('#camp-category').val(btn.data('category'));
        $('#camp-schedule').val(btn.data('schedule')).trigger('change');
        $('#camp-custom-minutes').val(btn.data('custom-minutes') || 60);
        $('#camp-posts-per-run').val(btn.data('posts-per-run') || 1);
        $('#camp-max-words').val(btn.data('max-words') || 1500);
        $('#camp-prompts').val(btn.data('prompts'));
        $('#camp-article-style').val(btn.data('article-style') || 'generic'); // Populate Style
        $('#campaign-auto-keywords').prop('checked', btn.data('auto-kw') == 1);
        $('#campaign-keyword-prompt').val(btn.data('kw-prompt'));

        // Populate Keyword Source
        const source = btn.data('keyword-source') || 'ai';
        $('input[name="keyword_source"][value="' + source + '"]').prop('checked', true);

        // Show/Hide fields based on source
        toggleKeywordInputs();

        // Populate Advanced Fields
        $('#camp-author').val(btn.data('author'));
        $('#camp-internal-links').prop('checked', btn.data('internal-links') == 1);
        $('#camp-outbound-links').prop('checked', btn.data('outbound-links') == 1);
        $('#camp-read-also').prop('checked', btn.data('read-also') == 1);
        $('#camp-enable-video').prop('checked', btn.data('enable-video') == 1);

        // Update State
        currentCampaignId = btn.data('id');
        $('#btn-save-campaign').text('Update Campaign');

        // Scroll to top
        $('html, body').animate({ scrollTop: 0 }, 'fast');

        // Fetch and Render Keyword List
        // Strategy: Use preloaded data attribute (JSON) if available for speed/robustness.
        // Fallback to AJAX.
        let queue = [];
        const preloadedJson = btn.data('keywords-json'); // JSON Encoded

        if (preloadedJson && Array.isArray(preloadedJson)) {
            queue = preloadedJson;
        }

        if (queue.length > 0) {
            const editor = $('#aimatic-visual-editor');
            let html = '';
            queue.forEach(kw => {
                const k = kw.trim();
                if (k) html += `<div>${k}</div>`;
            });
            html += '<div><br></div>';
            editor.html(html);
        } else {
            fetchCampaignKeywords(currentCampaignId);
        }
    });

    // Helper: Fetch Keywords into Visual Editor
    function fetchCampaignKeywords(id) {
        const editor = $('#aimatic-visual-editor');

        // Silent load (no "Loading..." text to avoid jarring shifts if possible, 
        // but initial load needs something? Let's keep it blank or subtle)
        editor.html('<div style="color:#999;">Loading...</div>');

        $.post(aimatic_writer_vars.ajax_url, {
            action: 'aimatic_writer_get_campaign_keywords',
            id: id,
            nonce: aimatic_writer_vars.nonce
        }, function (response) {
            if (response.success) {
                const queue = response.data.queue || [];
                const completed = response.data.completed || [];

                let html = '';

                // 1. Completed Keywords (Green, Non-Editable)
                completed.forEach(kw => {
                    html += `<div class="kw-completed" contenteditable="false" style="background-color: #d4edda; color: #155724; padding: 2px 5px; margin-bottom: 2px; border-radius: 3px; user-select: none;">✅ ${kw}</div>`;
                });

                // 2. Pending Keywords (Editable Divs)
                queue.forEach(kw => {
                    html += `<div>${kw}</div>`;
                });

                // 3. Ensure trailing empty div for new input
                html += '<div><br></div>';

                editor.html(html);
            } else {
                editor.html('<div style="color:red;">Failed to load.</div>');
            }
        });
    }

    // --- Bulk Keyword UI Logic ---
    function toggleKeywordInputs() {
        const autoKw = $('#campaign-auto-keywords').is(':checked');
        const source = $('input[name="keyword_source"]:checked').val();

        if (autoKw) {
            $('#keyword-source-container').show();
            if (source === 'file') {
                $('#bulk-keyword-settings').show();
                $('#ai-keyword-settings').hide();
            } else {
                $('#bulk-keyword-settings').hide();
                $('#ai-keyword-settings').show();
            }
        } else {
            $('#keyword-source-container').hide();
            $('#bulk-keyword-settings').hide();
            $('#ai-keyword-settings').hide();
        }
    }

    $('#campaign-auto-keywords').on('change', toggleKeywordInputs);
    $('input[name="keyword_source"]').on('change', toggleKeywordInputs);

    // Initialize UI State
    toggleKeywordInputs();

    // --- Visual Editor Input Logic ---
    // Handle File Upload: Append to Editor
    $('#campaign-bulk-file').on('change', function (e) {
        const file = e.target.files[0];
        if (!file) return;

        const reader = new FileReader();
        reader.onload = function (e) {
            // Send to backend via save_bulk_keywords hack? 
            // Wait, logic says we convert to base64, put in textarea, and save.
            // But now we have Visual Editor which is "State of Truth".
            // So we should append formatted content to Visual Editor?
            // Actually, for binary files (xlsx), we rely on Backend Parsing.
            // So we must stick to the existing flow: Put Base64 in hidden textarea, 
            // and maybe let the backend handle the append.

            // PROBLEM: If we put base64 in textarea, `save_campaign` will send it.
            // But `save_campaign` also needs to send the *edited* text from Visual Editor.
            // CONFLICT: We have "New File Upload" AND "Edited Text".

            // SOLUTION: 
            // If file is uploaded, we set it in `campaign-bulk-content` (hidden).
            // The Visual Editor content is also sent (maybe as a separate field or merged?).
            // Let's keep it simple: File Upload is an "Action". 
            // We'll put the Base64 in the hidden textarea. Use a flag?
            // On Save: If hidden textarea has data: data...base64 -> Backend treats as "Append File".
            //          AND we also send the "Visual Editor Queue" -> Backend treats as "Update Queue".

            // Let's update `campaign-bulk-content` with the file data.
            const content = e.target.result;
            $('#campaign-bulk-content').val(content);

            // Visual feedback
            const editor = $('#aimatic-visual-editor');
            editor.append('<div style="color:blue;">[File Ready To Upload on Save]</div>');
        };
        reader.readAsDataURL(file);
    });


    $('#btn-save-campaign').on('click', function (e) {
        e.preventDefault();
        const name = $('#camp-name').val().trim();
        const category = $('#camp-category').val();
        const frequency = $('#camp-schedule').val();
        const customMinutes = $('#camp-custom-minutes').val();
        const postsPerRun = $('#camp-posts-per-run').val();
        const maxWords = $('#camp-max-words').val();
        const prompts = $('#camp-prompts').val();
        const articleStyle = $('#camp-article-style').val(); // Get Style
        const autoKeywords = $('#campaign-auto-keywords').is(':checked');
        const keywordPrompt = $('#campaign-keyword-prompt').val();

        // New Bulk Fields
        const keywordSource = $('input[name="keyword_source"]:checked').val();

        // Prepare Bulk Content from Visual Editor
        // We need to parse the editor and extract ONLY pending keywords.
        // We do NOT send completed keywords back (backend has them).
        // We only send the "Queue".

        let visualQueue = '';
        // Robust Extraction: Handle text nodes, divs, p tags, etc.
        const editor = $('#aimatic-visual-editor');

        // We want to get text from everything EXCEPT .kw-completed
        // Strategy: Clone content, remove .kw-completed, get text? 
        // No, that loses line breaks.
        // Better: Iterate contents()

        editor.contents().each(function () {
            const node = this;
            const $node = $(node);

            // Skip "Completed" items
            if (node.nodeType === 1 && $node.hasClass('kw-completed')) {
                return; // continue
            }

            // Skip "File Ready" marker
            if ($node.text().indexOf('[File Ready') !== -1) {
                return;
            }

            // Get text
            let text = $node.text().replace(/[\r\n]+/g, '').trim();
            // Note: .text() on a block element might strip line breaks we want if we are iterating?
            // Actually, simply collecting non-empty text from blocks is usually enough if "One per line".

            if (text) {
                visualQueue += text + '\n';
            }
        });

        // If file upload is pending (in hidden textarea), we prioritize that OR combine?
        // Backend `save_bulk_keywords` overwrites or appends? 
        // Currently it treats input as "The Content".
        // If we want to support both: 
        // 1. File Upload (Append)
        // 2. Editor Changes (Overwrite Queue)

        // Simplest for User: 
        // They edit text -> We save text.
        // They upload file -> We append file.

        let bulkContent = visualQueue;
        const hiddenVal = $('#campaign-bulk-content').val();
        if (hiddenVal && hiddenVal.startsWith('data:')) {
            // File upload taking precedence for "Append" action
            // We will send this as `bulk_content` like before.
            // But we risk losing manual edits made at the same time.
            // Let's just use the hidden field for File.
            bulkContent = hiddenVal;
        } else {
            $('#campaign-bulk-content').val(visualQueue); // Sync for form submission
            bulkContent = visualQueue;
        }

        const statusSpan = $('#camp-status');

        // Advanced Fields
        const authorId = $('#camp-author').val();
        const internalLinks = $('#camp-internal-links').is(':checked');
        const outboundLinks = $('#camp-outbound-links').is(':checked');
        const readAlso = $('#camp-read-also').is(':checked');
        const enableVideo = $('#camp-enable-video').is(':checked');

        if (!name || !category) {
            alert('Please provide a name and select a category.');
            return;
        }

        const validBulk = bulkContent.trim() || $('#aimatic-visual-editor .kw-completed').length > 0;
        if (autoKeywords && keywordSource === 'file' && !validBulk) {
            alert('Please provide keywords in the Visual Editor or upload a file.');
            return;
        }

        const btn = $(this);
        const originalText = btn.text();
        btn.prop('disabled', true).text('Saving...');

        const data = {
            action: 'aimatic_writer_save_campaign',
            nonce: aimatic_writer_vars.nonce,
            id: currentCampaignId,
            name: name,
            category_id: category,
            schedule: frequency,
            custom_schedule_minutes: customMinutes,
            posts_per_run: postsPerRun,
            max_words: maxWords,
            status: $('#camp-status-select').val(),
            prompts: prompts,
            article_style: articleStyle, // Pass Style
            auto_keywords: autoKeywords ? 1 : 0,
            keyword_prompt: keywordPrompt,
            // Bulk
            keyword_source: keywordSource,
            bulk_content: bulkContent,
            // Advanced
            author_id: authorId,
            internal_links: internalLinks ? 1 : 0,
            outbound_links: outboundLinks ? 1 : 0,
            read_also: readAlso ? 1 : 0,
            enable_video: enableVideo ? 1 : 0
        };

        // Add ID if editing
        if (currentCampaignId) {
            data.id = currentCampaignId;
        }

        $.post(aimatic_writer_vars.ajax_url, data, function (response) {
            if (response.success) {
                statusSpan.text('✅ Saved!').css('color', 'green');
                // Reload with tab=campaigns to stay on the correct page
                setTimeout(() => {
                    const url = new URL(window.location.href);
                    url.searchParams.set('tab', 'campaigns');
                    window.location.href = url.toString();
                }, 1000);
            } else {
                statusSpan.text('❌ Error: ' + response.data).css('color', 'red');
                btn.prop('disabled', false).text(originalText);
            }
        });
    });

    $(document).on('click', '.btn-run-campaign', function () {
        const campaignId = $(this).data('id');
        const btn = $(this);

        // Step 1: Write Article
        btn.prop('disabled', true).text('⏳ Starting...');

        $.post(aimatic_writer_vars.ajax_url, {
            action: 'aimatic_writer_run_campaign',
            nonce: aimatic_writer_vars.nonce,
            campaign_id: campaignId,
            skip_images: 0 // Background process will handle images
        }, function (response) {

            if (response.success) {
                // Background start success
                btn.css('background-color', '#46b450').text('✅ Started in Background');

                // Reset after 3 seconds
                setTimeout(() => {
                    btn.css('background-color', '').text('Run Now').prop('disabled', false);
                }, 3000);

            } else {
                btn.prop('disabled', false).text('Run Now');
                alert('Error: ' + response.data);
            }
        }).fail(function () {
            btn.prop('disabled', false).text('Run Now');
            alert('Request failed. Check console.');
        });
    });

    $('.btn-delete-campaign').on('click', function () {
        if (!confirm('Are you sure you want to delete this campaign?')) return;

        const id = $(this).data('id');
        const row = $(this).closest('tr');

        $.post(aimatic_writer_vars.ajax_url, {
            action: 'aimatic_writer_delete_campaign',
            nonce: aimatic_writer_vars.nonce,
            id: id
        }, function (response) {
            if (response.success) {
                row.fadeOut(300, function () { $(this).remove(); });
            } else {
                alert('Error deleting campaign.');
            }
        });
    });

    // Test Gemini API
    $('#aimatic-test-gemini-btn').on('click', function () {
        var apiKey = $('#aimatic_gemini_key_input').val();
        var $btn = $(this);
        var $result = $('#aimatic-gemini-test-result');

        $btn.prop('disabled', true).text('Testing...');
        $result.text('');

        $.post(aimatic_writer_vars.ajax_url, {
            action: 'aimatic_writer_test_gemini',
            api_key: apiKey
        }, function (response) {
            $btn.prop('disabled', false).text('Test Connection');
            if (response.success) {
                $result.css('color', 'green').text(response.data);
            } else {
                $result.css('color', 'red').text(response.data);
            }
        });
    });

    // Persistent Error Dismissal
    $('#aimatic-persistent-error').on('click', '.notice-dismiss', function () {
        $.post(aimatic_writer_vars.ajax_url, {
            action: 'aimatic_writer_clear_error',
            nonce: aimatic_writer_vars.nonce
        });
    });

    // Custom Schedule Toggle
    $('#camp-schedule').on('change', function () {
        if ($(this).val() === 'custom') {
            $('#camp-custom-container').show();
        } else {
            $('#camp-custom-container').hide();
        }
    });

});
