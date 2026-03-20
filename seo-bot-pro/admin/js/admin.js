/**
 * SEO Bot Pro – Admin JavaScript
 */
(function ($) {
    'use strict';

    var data = window.sbpData || {};

    // ── Settings page: Provider toggle ──────────────
    $('#sbp-provider').on('change', function () {
        var provider = $(this).val();

        if (provider === 'claude') {
            $('.sbp-openai-row').hide();
            $('.sbp-claude-row').show();
            $('.sbp-model-openai').hide().prop('selected', false);
            $('.sbp-model-claude').show();
            // Select first Claude model if none selected
            if (!$('.sbp-model-claude:selected').length) {
                $('.sbp-model-claude').first().prop('selected', true);
            }
        } else {
            $('.sbp-claude-row').hide();
            $('.sbp-openai-row').show();
            $('.sbp-model-claude').hide().prop('selected', false);
            $('.sbp-model-openai').show();
            if (!$('.sbp-model-openai:selected').length) {
                $('.sbp-model-openai').first().prop('selected', true);
            }
        }
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
    $('#sbp-generate-post-btn').on('click', function () {
        var btn    = $(this);
        var result = $('#sbp-gen-result');
        var topic  = $('#sbp-gen-topic').val();

        if (!topic) {
            result.html('<div class="sbp-result-error">Please enter a topic.</div>');
            return;
        }

        btn.prop('disabled', true);
        result.html('<span class="sbp-spinner"></span> ' + data.i18n.publishing);

        $.post(data.ajaxUrl, {
            action:       'sbp_generate_post',
            nonce:        data.nonce,
            topic:        topic,
            post_type:    $('#sbp-gen-type').val(),
            status:       $('#sbp-gen-status').val(),
            category_id:  $('#sbp-gen-category').val(),
            length:       $('#sbp-gen-length').val(),
            auto_seo:     $('#sbp-gen-autoseo').is(':checked') ? '1' : '0',
            auto_faq:     $('#sbp-gen-autofaq').is(':checked') ? '1' : '0',
            instructions: $('#sbp-gen-instructions').val()
        })
        .done(function (res) {
            if (res.success) {
                var d    = res.data;
                var html = '<div class="sbp-result-success">';
                html += '<strong>Post created!</strong><br>';
                html += 'Title: ' + escHtml(d.title) + '<br>';
                if (d.edit_url) {
                    html += '<a href="' + escAttr(d.edit_url) + '" class="button" target="_blank">Edit Post</a> ';
                }
                if (d.view_url) {
                    html += '<a href="' + escAttr(d.view_url) + '" class="button" target="_blank">View Post</a>';
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
                var html = '<span class="sbp-text-success">';
                if (d.google) html += 'Google: ' + (d.google.success ? 'OK' : 'Failed') + ' | ';
                if (d.bing) html += 'Bing: ' + (d.bing.success ? 'OK' : 'Failed') + ' | ';
                if (d.indexnow) html += 'IndexNow: ' + (d.indexnow.success ? 'OK' : 'Failed');
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
