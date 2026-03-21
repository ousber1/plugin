/**
 * SEO Bot Pro – Admin JavaScript
 */
(function ($) {
    'use strict';

    var data = window.sbpData || {};

    // ── Settings page: Provider toggle ──────────────
    $('#sbp-provider').on('change', function () {
        var provider = $(this).val();

        // Hide all provider-specific rows
        $('.sbp-openai-row, .sbp-claude-row, .sbp-gemini-row').hide();
        $('.sbp-model-openai, .sbp-model-claude, .sbp-model-gemini').hide().prop('selected', false);

        if (provider === 'claude') {
            $('.sbp-openai-row').show(); // Keep for DALL-E
            $('.sbp-claude-row').show();
            $('.sbp-model-claude').show();
            if (!$('.sbp-model-claude:selected').length) {
                $('.sbp-model-claude').first().prop('selected', true);
            }
        } else if (provider === 'gemini') {
            $('.sbp-openai-row').show(); // Keep for DALL-E
            $('.sbp-gemini-row').show();
            $('.sbp-model-gemini').show();
            if (!$('.sbp-model-gemini:selected').length) {
                $('.sbp-model-gemini').first().prop('selected', true);
            }
        } else {
            $('.sbp-openai-row').show();
            $('.sbp-model-openai').show();
            if (!$('.sbp-model-openai:selected').length) {
                $('.sbp-model-openai').first().prop('selected', true);
            }
        }
    });

    // ── Settings page: Tab navigation ─────────────
    $('.sbp-settings-tabs .nav-tab').on('click', function (e) {
        e.preventDefault();
        var tab = $(this).data('tab');

        // Update active tab
        $('.sbp-settings-tabs .nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');

        // Show/hide tab content
        $('.sbp-tab-content').hide();
        $('#sbp-tab-' + tab).show();

        // Update URL without reload
        if (history.replaceState) {
            var url = new URL(window.location);
            url.searchParams.set('tab', tab);
            history.replaceState(null, '', url);
        }
    });

    // ── Settings page: Image provider toggle ─────────
    $('#sbp-image-provider').on('change', function () {
        var prov = $(this).val();
        $('.sbp-img-dalle-row, .sbp-img-unsplash-row, .sbp-img-pixabay-row, .sbp-img-pexels-row').hide();
        $('.sbp-img-' + prov + '-row').show();
    });

    // ── Settings page: Test API connection ──────────
    $(document).on('click', '.sbp-test-api-btn', function () {
        var btn = $(this);
        var resultSpan = btn.next('.sbp-test-result');

        btn.prop('disabled', true);
        resultSpan.html('<span class="sbp-spinner"></span> Testing...');

        $.post(data.ajaxUrl, {
            action: 'sbp_test_api',
            nonce: data.nonce
        })
        .done(function (res) {
            if (res.success) {
                resultSpan.html('<span class="sbp-text-success">Connected! Provider: ' + escHtml(res.data.provider) + ', Model: ' + escHtml(res.data.model) + '</span>');
            } else {
                resultSpan.html('<span class="sbp-text-danger">Failed: ' + escHtml(res.data.message || 'Unknown error') + '</span>');
            }
        })
        .fail(function () {
            resultSpan.html('<span class="sbp-text-danger">Network error</span>');
        })
        .always(function () {
            btn.prop('disabled', false);
        });
    });

    // ── Settings Export ──────────────────────────────
    $('#sbp-export-settings-btn').on('click', function () {
        $.post(data.ajaxUrl, {
            action: 'sbp_export_settings',
            nonce: data.nonce
        })
        .done(function (res) {
            if (res.success) {
                $('#sbp-settings-json').val(res.data.json);
            }
        });
    });

    // ── Settings Import ──────────────────────────────
    $('#sbp-import-settings-btn').on('click', function () {
        var json = $('#sbp-settings-json').val();
        if (!json) {
            alert('Please paste settings JSON first.');
            return;
        }
        if (!confirm('Import these settings? Your current settings (except API keys) will be overwritten.')) {
            return;
        }

        $.post(data.ajaxUrl, {
            action: 'sbp_import_settings',
            nonce: data.nonce,
            settings_json: json
        })
        .done(function (res) {
            if (res.success) {
                alert('Settings imported! Reloading...');
                location.reload();
            } else {
                alert('Import failed: ' + (res.data.message || 'Invalid JSON'));
            }
        });
    });

    // ── Single post optimize button ─────────────────
    $(document).on('click', '.sbp-optimize-btn', function () {
        var btn    = $(this);
        var postId = btn.data('post-id');
        var result = $('#sbp-action-result');

        btn.prop('disabled', true).text(data.i18n.optimizing);
        result.html('<span class="sbp-spinner"></span> ' + data.i18n.optimizing);

        $.post(data.ajaxUrl, {
            action:  'sbp_optimize_post',
            nonce:   data.nonce,
            post_id: postId
        })
        .done(function (res) {
            if (res.success) {
                var d = res.data;
                $('#sbp-meta-title').text(d.meta_title || '—');
                $('#sbp-meta-desc').text(d.meta_description || '—');
                if (d.meta_keywords) {
                    var kwEl = $('#sbp-keywords');
                    if (kwEl.length) {
                        kwEl.text(d.meta_keywords);
                    }
                }
                if (d.og_title) {
                    var ogEl = $('#sbp-og-title');
                    if (ogEl.length) {
                        ogEl.text(d.og_title);
                    }
                }
                result.html('<div class="sbp-result-success">' + data.i18n.success + '</div>');
            } else {
                result.html('<div class="sbp-result-error">' + data.i18n.error + ' ' + (res.data.message || '') + '</div>');
            }
        })
        .fail(function () {
            result.html('<div class="sbp-result-error">' + data.i18n.error + ' Network error.</div>');
        })
        .always(function () {
            btn.prop('disabled', false).text('Optimize with AI');
        });
    });

    // ── Generate keywords button ────────────────────
    $(document).on('click', '.sbp-keywords-btn', function () {
        var btn    = $(this);
        var postId = btn.data('post-id');
        var result = $('#sbp-action-result');

        btn.prop('disabled', true);
        result.html('<span class="sbp-spinner"></span> ' + data.i18n.generating);

        $.post(data.ajaxUrl, {
            action:  'sbp_generate_keywords',
            nonce:   data.nonce,
            post_id: postId
        })
        .done(function (res) {
            if (res.success) {
                var d    = res.data;
                var html = '<div class="sbp-result-success">';
                if (d.primary) {
                    html += '<strong>Focus Keyword:</strong> ' + escHtml(d.primary) + '<br>';
                    $('#sbp-focus-kw').text(d.primary);
                    $('#sbp-keyword').val(d.primary);
                }
                if (d.keywords && d.keywords.length) {
                    html += '<strong>Keywords:</strong> ' + escHtml(d.keywords.join(', '));
                }
                html += '</div>';
                result.html(html);
            } else {
                result.html('<div class="sbp-result-error">' + data.i18n.error + ' ' + (res.data.message || '') + '</div>');
            }
        })
        .fail(function () {
            result.html('<div class="sbp-result-error">' + data.i18n.error + ' Network error.</div>');
        })
        .always(function () {
            btn.prop('disabled', false);
        });
    });

    // ── Generate FAQ button ─────────────────────────
    $(document).on('click', '.sbp-faq-btn', function () {
        var btn    = $(this);
        var postId = btn.data('post-id');
        var result = $('#sbp-action-result');

        btn.prop('disabled', true);
        result.html('<span class="sbp-spinner"></span> ' + data.i18n.generating);

        $.post(data.ajaxUrl, {
            action:  'sbp_generate_faq',
            nonce:   data.nonce,
            post_id: postId
        })
        .done(function (res) {
            if (res.success) {
                var html = '<div class="sbp-result-success">FAQ generated and inserted!</div>';
                if (res.data.faqs) {
                    html += '<ul>';
                    $.each(res.data.faqs, function (i, faq) {
                        html += '<li><strong>' + escHtml(faq.question) + '</strong></li>';
                    });
                    html += '</ul>';
                }
                result.html(html);
            } else {
                result.html('<div class="sbp-result-error">' + data.i18n.error + ' ' + (res.data.message || '') + '</div>');
            }
        })
        .fail(function () {
            result.html('<div class="sbp-result-error">' + data.i18n.error + ' Network error.</div>');
        })
        .always(function () {
            btn.prop('disabled', false);
        });
    });

    // ── Suggest internal links ──────────────────────
    $(document).on('click', '.sbp-links-btn', function () {
        var btn    = $(this);
        var postId = btn.data('post-id');
        var result = $('#sbp-action-result');

        btn.prop('disabled', true);
        result.html('<span class="sbp-spinner"></span> ' + data.i18n.generating);

        $.post(data.ajaxUrl, {
            action:  'sbp_suggest_links',
            nonce:   data.nonce,
            post_id: postId
        })
        .done(function (res) {
            if (res.success && res.data.length) {
                var html = '<div class="sbp-link-suggestions"><strong>Suggested Links:</strong><ul>';
                $.each(res.data, function (i, link) {
                    html += '<li><a href="' + escAttr(link.url) + '" target="_blank">'
                          + escHtml(link.title) + '</a> — anchor: "' + escHtml(link.anchor) + '"</li>';
                });
                html += '</ul></div>';
                result.html(html);
            } else {
                result.html('<div class="sbp-result-success">No link suggestions found.</div>');
            }
        })
        .fail(function () {
            result.html('<div class="sbp-result-error">' + data.i18n.error + ' Network error.</div>');
        })
        .always(function () {
            btn.prop('disabled', false);
        });
    });

    // ── Fix image ALTs ──────────────────────────────
    $(document).on('click', '.sbp-alt-btn', function () {
        var btn    = $(this);
        var postId = btn.data('post-id');
        var result = $('#sbp-action-result');

        btn.prop('disabled', true);
        result.html('<span class="sbp-spinner"></span> ' + data.i18n.optimizing);

        $.post(data.ajaxUrl, {
            action:  'sbp_fix_image_alts',
            nonce:   data.nonce,
            post_id: postId
        })
        .done(function (res) {
            if (res.success) {
                result.html('<div class="sbp-result-success">Fixed ' + res.data.fixed + ' of ' + res.data.total + ' images.</div>');
            } else {
                result.html('<div class="sbp-result-error">' + data.i18n.error + ' ' + (res.data.message || '') + '</div>');
            }
        })
        .fail(function () {
            result.html('<div class="sbp-result-error">' + data.i18n.error + ' Network error.</div>');
        })
        .always(function () {
            btn.prop('disabled', false);
        });
    });

    // ── Optimize slug ───────────────────────────────
    $(document).on('click', '.sbp-slug-btn', function () {
        var btn    = $(this);
        var postId = btn.data('post-id');
        var result = $('#sbp-action-result');

        btn.prop('disabled', true);
        result.html('<span class="sbp-spinner"></span> ' + data.i18n.optimizing);

        $.post(data.ajaxUrl, {
            action:  'sbp_optimize_slug',
            nonce:   data.nonce,
            post_id: postId
        })
        .done(function (res) {
            if (res.success) {
                var html = '<div class="sbp-result-success">Slug updated to: <code>' + escHtml(res.data.slug) + '</code>';
                if (res.data.permalink) {
                    html += '<br><a href="' + escAttr(res.data.permalink) + '" target="_blank">View post</a>';
                }
                html += '</div>';
                result.html(html);
            } else {
                result.html('<div class="sbp-result-error">' + data.i18n.error + ' ' + (res.data.message || '') + '</div>');
            }
        })
        .fail(function () {
            result.html('<div class="sbp-result-error">' + data.i18n.error + ' Network error.</div>');
        })
        .always(function () {
            btn.prop('disabled', false);
        });
    });

    // ── Content analysis ────────────────────────────
    $(document).on('click', '.sbp-analyze-btn', function () {
        var btn     = $(this);
        var postId  = btn.data('post-id');
        var keyword = $('#sbp-keyword').val();
        var result  = $('#sbp-analysis-result');

        btn.prop('disabled', true);
        result.html('<span class="sbp-spinner"></span> ' + data.i18n.analyzing);

        $.post(data.ajaxUrl, {
            action:  'sbp_analyze_content',
            nonce:   data.nonce,
            post_id: postId,
            keyword: keyword
        })
        .done(function (res) {
            if (res.success) {
                renderAnalysis(res.data, result);
            } else {
                result.html('<div class="sbp-result-error">' + data.i18n.error + '</div>');
            }
        })
        .fail(function () {
            result.html('<div class="sbp-result-error">' + data.i18n.error + ' Network error.</div>');
        })
        .always(function () {
            btn.prop('disabled', false);
        });
    });

    function renderAnalysis(d, container) {
        var cls = d.score >= 70 ? 'score-good' : d.score >= 40 ? 'score-ok' : 'score-bad';
        var html = '<div class="sbp-score ' + cls + '">SEO Score: ' + d.score + '/100</div>';

        // Word count
        if (d.word_count !== undefined) {
            html += '<div class="sbp-word-count">' + d.word_count + ' words</div>';
        }

        // SEO checks
        if (d.checks && d.checks.length) {
            $.each(d.checks, function (i, c) {
                var icon = c.pass ? 'dashicons-yes' : 'dashicons-no';
                html += '<div class="sbp-check"><span class="dashicons ' + icon + '"></span> '
                      + escHtml(c.label) + '</div>';
            });
        }

        // SEO suggestions
        if (d.suggestions && d.suggestions.length) {
            html += '<div class="sbp-suggestions"><strong>SEO Suggestions:</strong><ul>';
            $.each(d.suggestions, function (i, s) {
                html += '<li>' + escHtml(s) + '</li>';
            });
            html += '</ul></div>';
        }

        // Readability section
        if (d.readability) {
            var r    = d.readability;
            var rCls = r.level === 'good' ? 'score-good' : r.level === 'ok' ? 'score-ok' : 'score-bad';

            html += '<div class="sbp-readability">';
            html += '<h4>Readability</h4>';
            html += '<div class="sbp-score ' + rCls + '" style="font-size:18px;">Flesch: '
                  + (r.flesch_score || 0) + ' – ' + escHtml(r.grade) + '</div>';
            html += '<div class="sbp-check">Avg sentence length: ' + (r.avg_sentence_length || 0) + ' words</div>';

            if (r.suggestions && r.suggestions.length) {
                html += '<div class="sbp-suggestions"><ul>';
                $.each(r.suggestions, function (i, s) {
                    html += '<li>' + escHtml(s) + '</li>';
                });
                html += '</ul></div>';
            }
            html += '</div>';
        }

        container.html(html);
    }

    // ── Generate excerpt button ─────────────────────
    $(document).on('click', '.sbp-excerpt-btn', function () {
        var btn    = $(this);
        var postId = btn.data('post-id');
        var result = $('#sbp-action-result');

        btn.prop('disabled', true);
        result.html('<span class="sbp-spinner"></span> ' + data.i18n.generating);

        $.post(data.ajaxUrl, {
            action:  'sbp_generate_excerpt',
            nonce:   data.nonce,
            post_id: postId
        })
        .done(function (res) {
            if (res.success) {
                result.html('<div class="sbp-result-success">Excerpt generated: ' + escHtml(res.data.excerpt) + '</div>');
            } else {
                result.html('<div class="sbp-result-error">' + data.i18n.error + ' ' + (res.data.message || '') + '</div>');
            }
        })
        .fail(function () {
            result.html('<div class="sbp-result-error">' + data.i18n.error + ' Network error.</div>');
        })
        .always(function () {
            btn.prop('disabled', false);
        });
    });

    // ── Rewrite content button ──────────────────────
    $(document).on('click', '.sbp-rewrite-btn', function () {
        var btn    = $(this);
        var postId = btn.data('post-id');
        var result = $('#sbp-action-result');

        if (!confirm('This will rewrite the entire post content using AI. Continue?')) {
            return;
        }

        btn.prop('disabled', true);
        result.html('<span class="sbp-spinner"></span> ' + data.i18n.rewriting);

        $.post(data.ajaxUrl, {
            action:  'sbp_rewrite_content',
            nonce:   data.nonce,
            post_id: postId
        })
        .done(function (res) {
            if (res.success) {
                result.html('<div class="sbp-result-success">Content rewritten successfully! Reload the editor to see changes.</div>');
            } else {
                result.html('<div class="sbp-result-error">' + data.i18n.error + ' ' + (res.data.message || '') + '</div>');
            }
        })
        .fail(function () {
            result.html('<div class="sbp-result-error">' + data.i18n.error + ' Network error.</div>');
        })
        .always(function () {
            btn.prop('disabled', false);
        });
    });

    // ── Save advanced SEO settings ──────────────────
    $(document).on('click', '.sbp-save-advanced-btn', function () {
        var btn    = $(this);
        var postId = btn.data('post-id');
        var result = $('#sbp-action-result');

        btn.prop('disabled', true);
        result.html('<span class="sbp-spinner"></span> ' + data.i18n.optimizing);

        $.post(data.ajaxUrl, {
            action:      'sbp_save_robots_meta',
            nonce:       data.nonce,
            post_id:     postId,
            noindex:     $('#sbp-noindex').is(':checked') ? '1' : '0',
            nofollow:    $('#sbp-nofollow').is(':checked') ? '1' : '0',
            canonical:   $('#sbp-canonical').val(),
            schema_type: $('#sbp-schema-type').val()
        })
        .done(function (res) {
            if (res.success) {
                result.html('<div class="sbp-result-success">' + data.i18n.saved + '</div>');
            } else {
                result.html('<div class="sbp-result-error">' + data.i18n.error + ' ' + (res.data.message || '') + '</div>');
            }
        })
        .fail(function () {
            result.html('<div class="sbp-result-error">' + data.i18n.error + ' Network error.</div>');
        })
        .always(function () {
            btn.prop('disabled', false);
        });
    });

    // ── AI Post Generator page ──────────────────────

    // Template descriptions for preview
    var templateInfo = {
        blog:       '<div class="sbp-tpl-structure"><span class="sbp-tpl-tag">H2</span> Introduction<br><span class="sbp-tpl-tag">H2</span> Main Section 1 → <span class="sbp-tpl-tag">H3</span> Subsections<br><span class="sbp-tpl-tag">H2</span> Main Section 2<br><span class="sbp-tpl-tag">H2</span> Main Section 3<br><span class="sbp-tpl-tag">H2</span> Conclusion + CTA<br><span class="sbp-tpl-feature">+ Internal links + External authority links + Focus keyword</span></div>',
        listicle:   '<div class="sbp-tpl-structure"><span class="sbp-tpl-tag">Intro</span> Hook + overview<br><span class="sbp-tpl-tag">H2</span> #1 Item Name → description<br><span class="sbp-tpl-tag">H2</span> #2 Item Name → description<br><span class="sbp-tpl-tag">H2</span> #3-#10 Items...<br><span class="sbp-tpl-tag">H2</span> Final Thoughts + Recommendation<br><span class="sbp-tpl-feature">+ Internal links + External authority links + Focus keyword</span></div>',
        howto:      '<div class="sbp-tpl-structure"><span class="sbp-tpl-tag">Intro</span> What you will learn<br><span class="sbp-tpl-tag">H2</span> Prerequisites / What You Need<br><span class="sbp-tpl-tag">H2</span> Step 1 – [Action]<br><span class="sbp-tpl-tag">H2</span> Step 2 – [Action]<br><span class="sbp-tpl-tag">H2</span> Tips & Best Practices<br><span class="sbp-tpl-tag">H2</span> Conclusion<br><span class="sbp-tpl-feature">+ Internal links + External authority links + HowTo schema ready</span></div>',
        review:     '<div class="sbp-tpl-structure"><span class="sbp-tpl-tag">Intro</span> What & why review<br><span class="sbp-tpl-tag">H2</span> Overview / What is [Product]?<br><span class="sbp-tpl-tag">H2</span> Key Features<br><span class="sbp-tpl-tag">H2</span> Pros and Cons<br><span class="sbp-tpl-tag">H2</span> Pricing<br><span class="sbp-tpl-tag">H2</span> Who Is It For?<br><span class="sbp-tpl-tag">H2</span> Verdict<br><span class="sbp-tpl-feature">+ Internal links + External links + Review schema ready</span></div>',
        comparison: '<div class="sbp-tpl-structure"><span class="sbp-tpl-tag">Intro</span> What is compared & why<br><span class="sbp-tpl-tag">H2</span> Quick Comparison Table<br><span class="sbp-tpl-tag">H2</span> [Option A] – Overview<br><span class="sbp-tpl-tag">H2</span> [Option B] – Overview<br><span class="sbp-tpl-tag">H2</span> Head-to-Head Comparison<br><span class="sbp-tpl-tag">H2</span> Which Should You Choose?<br><span class="sbp-tpl-feature">+ Internal links + External links + Comparison table</span></div>'
    };

    // Show template preview on change
    function updateTemplatePreview() {
        var tpl  = $('#sbp-gen-template').val();
        var desc = templateInfo[tpl] || templateInfo.blog;
        $('#sbp-template-desc').html(desc);
    }
    $('#sbp-gen-template').on('change', updateTemplatePreview);
    // Initialize on load
    updateTemplatePreview();

    $('#sbp-generate-post-btn').on('click', function () {
        var btn    = $(this);
        var result = $('#sbp-gen-result');
        var topic  = $('#sbp-gen-topic').val();

        if (!topic) {
            result.html('<div class="sbp-result-error">Please enter a topic.</div>');
            return;
        }

        var autoImage = $('#sbp-gen-autoimage').is(':checked');

        btn.prop('disabled', true);

        // Show progress steps
        var steps = ['Generating article content...'];
        if (autoImage) steps.push('Creating AI featured image...');
        if ($('#sbp-gen-autoseo').is(':checked')) steps.push('Optimizing SEO...');
        if ($('#sbp-gen-autofaq').is(':checked')) steps.push('Generating FAQ...');

        result.html('<div class="sbp-gen-progress"><span class="sbp-spinner"></span> <strong>' + steps[0] + '</strong>'
                   + '<div class="sbp-gen-steps">' + steps.map(function(s, i) {
                       return '<div class="sbp-gen-step" data-step="' + i + '">' + escHtml(s) + '</div>';
                   }).join('') + '</div></div>');

        $.post(data.ajaxUrl, {
            action:       'sbp_generate_post',
            nonce:        data.nonce,
            topic:        topic,
            post_type:    $('#sbp-gen-type').val(),
            status:       $('#sbp-gen-status').val(),
            category_id:  $('#sbp-gen-category').val(),
            length:       $('#sbp-gen-length').val(),
            template:     $('#sbp-gen-template').val(),
            auto_seo:     $('#sbp-gen-autoseo').is(':checked') ? '1' : '0',
            auto_faq:     $('#sbp-gen-autofaq').is(':checked') ? '1' : '0',
            auto_image:   autoImage ? '1' : '0',
            auto_links:   $('#sbp-gen-autolinks').is(':checked') ? '1' : '0',
            instructions: $('#sbp-gen-instructions').val()
        })
        .done(function (res) {
            if (res.success) {
                var d    = res.data;
                var html = '<div class="sbp-result-success sbp-gen-success">';
                html += '<div class="sbp-gen-success-header">';
                html += '<span class="dashicons dashicons-yes-alt sbp-gen-check"></span>';
                html += '<strong>Article Created Successfully!</strong>';
                html += '</div>';
                html += '<div class="sbp-gen-details">';
                html += '<div class="sbp-gen-detail"><span class="sbp-gen-label">Title:</span> ' + escHtml(d.title) + '</div>';
                html += '<div class="sbp-gen-detail"><span class="sbp-gen-label">Template:</span> ' + escHtml(d.template || 'blog') + '</div>';
                html += '<div class="sbp-gen-detail"><span class="sbp-gen-label">Status:</span> ' + escHtml(d.status) + '</div>';

                if (d.image_generated) {
                    html += '<div class="sbp-gen-detail"><span class="sbp-gen-label">Featured Image:</span> <span class="sbp-text-success">Generated</span></div>';
                } else if (d.image_error) {
                    html += '<div class="sbp-gen-detail"><span class="sbp-gen-label">Featured Image:</span> <span class="sbp-text-danger">Failed: ' + escHtml(d.image_error) + '</span></div>';
                }
                if (d.has_links) {
                    html += '<div class="sbp-gen-detail"><span class="sbp-gen-label">Links:</span> <span class="sbp-text-success">Internal + External included</span></div>';
                }

                html += '</div>';
                html += '<div class="sbp-gen-actions">';
                if (d.edit_url) {
                    html += '<a href="' + escAttr(d.edit_url) + '" class="button button-primary" target="_blank">Edit Post</a> ';
                }
                if (d.view_url) {
                    html += '<a href="' + escAttr(d.view_url) + '" class="button" target="_blank">View Post</a>';
                }
                html += '</div>';
                html += '</div>';
                result.html(html);
            } else {
                result.html('<div class="sbp-result-error">' + data.i18n.error + ' ' + (res.data.message || '') + '</div>');
            }
        })
        .fail(function () {
            result.html('<div class="sbp-result-error">' + data.i18n.error + ' Network error.</div>');
        })
        .always(function () {
            btn.prop('disabled', false);
        });
    });

    // ── Generator: toggle category row based on post type ──
    $('#sbp-gen-type').on('change', function () {
        if ($(this).val() === 'post') {
            $('.sbp-gen-category-row').show();
        } else {
            $('.sbp-gen-category-row').hide();
        }
    });

    // ── Bulk optimize ───────────────────────────────
    $('#sbp-select-all').on('change', function () {
        $('.sbp-post-check').prop('checked', this.checked);
    });

    $('#sbp-bulk-optimize-btn').on('click', function () {
        var ids = [];
        $('.sbp-post-check:checked').each(function () {
            ids.push($(this).val());
        });

        if (!ids.length) {
            alert(data.i18n.noSelection);
            return;
        }

        if (!confirm(data.i18n.confirm)) {
            return;
        }

        var btn      = $(this);
        var progress = $('#sbp-bulk-progress');
        var total    = ids.length;
        var done     = 0;
        var errors   = 0;

        btn.prop('disabled', true);
        progress.text('0 / ' + total);

        function processNext() {
            if (!ids.length) {
                btn.prop('disabled', false);
                progress.text(done + ' / ' + total + ' done' + (errors ? ' (' + errors + ' errors)' : ''));
                return;
            }

            var postId = ids.shift();
            var row    = $('tr[data-post-id="' + postId + '"]');
            row.find('.sbp-cell-status').html('<span class="sbp-spinner"></span>');

            $.post(data.ajaxUrl, {
                action:  'sbp_bulk_optimize',
                nonce:   data.nonce,
                post_id: postId
            })
            .done(function (res) {
                if (res.success) {
                    row.find('.sbp-cell-meta-title').text(res.data.meta_title || '—');
                    row.find('.sbp-cell-meta-desc').text((res.data.meta_description || '').substring(0, 60) || '—');
                    row.find('.sbp-cell-status').html('<span class="sbp-status sbp-status-success">Done</span>');
                } else {
                    row.find('.sbp-cell-status').html('<span class="sbp-status sbp-status-error">Error</span>');
                    errors++;
                }
            })
            .fail(function () {
                row.find('.sbp-cell-status').html('<span class="sbp-status sbp-status-error">Error</span>');
                errors++;
            })
            .always(function () {
                done++;
                progress.text(done + ' / ' + total);
                processNext();
            });
        }

        processNext();
    });

    // ── Rank Booster: Ping engines ─────────────────
    $('#sbp-ping-engines-btn').on('click', function () {
        var btn    = $(this);
        var result = $('#sbp-ping-result');

        btn.prop('disabled', true);
        result.html('<span class="sbp-spinner"></span> ' + data.i18n.pinging);

        $.post(data.ajaxUrl, {
            action: 'sbp_ping_engines',
            nonce:  data.nonce
        })
        .done(function (res) {
            if (res.success) {
                var d    = res.data;
                var html = '';

                function pingStatus(name, obj) {
                    if (!obj) return '';
                    var ok  = obj.success;
                    var cls = ok ? 'sbp-text-success' : 'sbp-text-danger';
                    var msg = ok ? 'OK' : (obj.error || obj.message || 'Failed');
                    return '<span class="' + cls + '">' + name + ': ' + escHtml(msg) + '</span> ';
                }

                html += pingStatus('Google', d.google);
                html += pingStatus('Bing', d.bing);
                html += pingStatus('IndexNow', d.indexnow);

                result.html(html || '<span class="sbp-text-danger">No engines configured</span>');
            } else {
                result.html('<span class="sbp-text-danger">' + (res.data.message || 'Error') + '</span>');
            }
        })
        .fail(function () {
            result.html('<span class="sbp-text-danger">Network error</span>');
        })
        .always(function () {
            btn.prop('disabled', false);
        });
    });

    // ── Rank Booster: Submit sitemap ────────────────
    $('#sbp-submit-sitemap-btn').on('click', function () {
        var btn    = $(this);
        var result = $('#sbp-sitemap-result');

        btn.prop('disabled', true);
        result.html('<span class="sbp-spinner"></span> ' + data.i18n.submitting);

        $.post(data.ajaxUrl, {
            action: 'sbp_submit_sitemap',
            nonce:  data.nonce
        })
        .done(function (res) {
            if (res.success) {
                var d    = res.data;
                var html = '<span class="sbp-text-success">';
                if (d.google) html += 'Google: ' + (d.google.success ? 'OK' : 'Failed') + ' | ';
                if (d.bing) html += 'Bing: ' + (d.bing.success ? 'OK' : 'Failed');
                html += '</span>';
                result.html(html);
            } else {
                result.html('<span class="sbp-text-danger">' + (res.data.message || 'Error') + '</span>');
            }
        })
        .fail(function () {
            result.html('<span class="sbp-text-danger">Network error</span>');
        })
        .always(function () {
            btn.prop('disabled', false);
        });
    });

    // ── Rank Booster: Bulk IndexNow ─────────────────
    $('#sbp-bulk-indexnow-btn').on('click', function () {
        var btn    = $(this);
        var result = $('#sbp-indexnow-result');

        btn.prop('disabled', true);
        result.html('<span class="sbp-spinner"></span> ' + data.i18n.submitting);

        $.post(data.ajaxUrl, {
            action: 'sbp_bulk_indexnow',
            nonce:  data.nonce
        })
        .done(function (res) {
            if (res.success) {
                result.html('<span class="sbp-text-success">' + res.data.url_count + ' URLs submitted to IndexNow!</span>');
            } else {
                result.html('<span class="sbp-text-danger">' + (res.data.message || 'Error') + '</span>');
            }
        })
        .fail(function () {
            result.html('<span class="sbp-text-danger">Network error</span>');
        })
        .always(function () {
            btn.prop('disabled', false);
        });
    });

    // ── Rank Booster: Refresh stale content ─────────
    $('#sbp-refresh-stale-btn').on('click', function () {
        var btn    = $(this);
        var result = $('#sbp-refresh-result');

        btn.prop('disabled', true);
        result.html('<span class="sbp-spinner"></span> ' + data.i18n.refreshing);

        $.post(data.ajaxUrl, {
            action: 'sbp_refresh_stale',
            nonce:  data.nonce
        })
        .done(function (res) {
            if (res.success) {
                result.html('<span class="sbp-text-success">Refreshed ' + res.data.refreshed + ' of ' + res.data.total + ' stale posts.</span>');
            } else {
                result.html('<span class="sbp-text-danger">' + (res.data.message || 'Error') + '</span>');
            }
        })
        .fail(function () {
            result.html('<span class="sbp-text-danger">Network error</span>');
        })
        .always(function () {
            btn.prop('disabled', false);
        });
    });

    // ── Helpers ─────────────────────────────────────
    function escHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str || ''));
        return div.innerHTML;
    }

    function escAttr(str) {
        return (str || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;')
                          .replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

})(jQuery);
