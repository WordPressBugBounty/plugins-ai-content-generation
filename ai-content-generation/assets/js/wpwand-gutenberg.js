(() => {
    "use strict";
    var t, e = {
            401: () => {
                const t = window.wp.element,
                    e = window.wp.richText,
                    o = window.wp.blockEditor,
                    r = window.wp.components;


                async function c() {

                    let t = arguments.length > 0 && void 0 !== arguments[0] ? arguments[0] : "below",
                        e = l(),
                        [o, r] = p(e),
                        c = r.clientId,
                        a = o.clientId,
                        n = wp.data.select("core/block-editor").getBlock(c),
                        s = '<span id="' + (Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15)) + '" class="wpwand-editor-loading" style="color:#3767fb">AI is thinking...</span>';


                    if ("above" === t) {
                        let t = wp.blocks.createBlock("core/paragraph", {
                                content: s
                            }),
                            e = wp.data.select("core/block-editor").getBlockIndex(a),
                            o = wp.data.select("core/block-editor").getBlockRootClientId(a);
                        return await wp.data.dispatch("core/block-editor").insertBlock(t, e, o), t
                    }
                    if (e.length > 1 || "core/paragraph" !== n.name) {
                        let t = wp.blocks.createBlock("core/paragraph", {
                                content: s
                            }),
                            e = wp.data.select("core/block-editor").getBlockRootClientId(c),
                            o = wp.data.select("core/block-editor").getBlockIndex(c) + 1;
                        if (!wp.data.select("core/block-editor").canInsertBlockType("core/paragraph", e))
                            for (; e && (o = wp.data.select("core/block-editor").getBlockIndex(e) + 1, e = wp.data.select("core/block-editor").getBlockRootClientId(e), !wp.data.select("core/block-editor").canInsertBlockType("core/paragraph", e)););
                        return await wp.data.dispatch("core/block-editor").insertBlock(t, o, e), t
                    }
                    let d = wp.data.select("core/block-editor").getBlockRootClientId(c);
                    if (!wp.data.select("core/block-editor").canInsertBlockType("core/paragraph", d)) {
                        for (; d && (d = wp.data.select("core/block-editor").getBlockRootClientId(d), !wp.data.select("core/block-editor").canInsertBlockType("core/paragraph", d)););
                        let t = wp.blocks.createBlock("core/paragraph", {
                            content: s
                        });
                        return await wp.data.dispatch("core/block-editor").insertBlock(t, void 0, d), t
                    }
                    let w = i(n),
                        u = wp.richText.create({
                            html: w
                        }),
                        g = w.length;
                    "offset" in r && (g = r.offset);
                    let b = wp.richText.slice(u, 0, g),
                        k = wp.richText.slice(u, g, u.text.length),
                        h = wp.richText.toHTMLString({
                            value: b
                        }),
                        f = wp.richText.toHTMLString({
                            value: k
                        }),
                        m = n.attributes;
                    const v = r.attributeKey;
                    let B = m;
                    B[v] = h;
                    const I = wp.blocks.createBlock(n.name, B);
                    let x = m;
                    x[v] = s;
                    let _ = wp.blocks.createBlock("core/paragraph", x),
                        T = m;
                    T[v] = f;
                    let y = [I, _, wp.blocks.createBlock(n.name, T)];
                    return 0 === k.text.trim().length && (y = [I, _]), await wp.data.dispatch("core/block-editor").replaceBlock(c, y), _
                }
                async function a(t, e, o) {

                    let r = "";
                    e = e.replace("[text]", o);
                    try {
                        r = await async function (t) {
                            let e = new FormData;
                            e.append("nonce", wpwand_glb.nonce); // Include the nonce

                            e.append("prompt", t), e.append("action", "wpwand_editor_request") /* , e.append("nonce", wpwand_editor_wp_nonce) */ ;
                            const o = await fetch(wpwand_gutenberg_editor.editor_ajax_url, {
                                method: "POST",
                                body: e
                            }).catch((async t => {
                                throw new Error(await t.text())
                            }));


                            if (!o.ok) throw new Error(await o.text());

                            return (await o.json()).data
                        }(e)
                    } catch (e) {
                        return await wp.data.dispatch("core/block-editor").removeBlocks(t.clientId), void alert("An API error occurred with the following response body: \n\n" + e.message)
                    }

                    console.log(r)
                    const c = r.replace(/\n/g, "<br>");
                    let a = t.attributes;
                    a.content = c, wp.data.dispatch("core/block-editor").updateBlock(t.clientId, a)
                }

                function n() {

                    let t = l(),
                        [e, o] = p(t);
                    return s(t, e, o).trim()
                }

                function l() {
                    let t = wp.data.select("core/block-editor").getMultiSelectedBlockClientIds();
                    return 0 === t.length && (t = [wp.data.select("core/block-editor").getSelectedBlockClientId()]), t
                }

                function i(t) {
                    let e = "";
                    return "content" in t.attributes ? e = t.attributes.content : "citation" in t.attributes ? e = t.attributes.citation : "value" in t.attributes ? e = t.attributes.value : "values" in t.attributes ? e = t.attributes.values : "text" in t.attributes && (e = t.attributes.text), e
                }

                function s(t, e, o) {
                    let r = "";
                    return t.forEach((t => {
                        const c = wp.data.select("core/block-editor").getBlock(t);
                        let a = i(c),
                            n = wp.richText.create({
                                html: a
                            }).text,
                            l = 0,
                            p = n.length;
                        e.clientId === t && "offset" in e && (l = e.offset), o.clientId === t && "offset" in o && (p = o.offset), n = n.substring(l, p), r += "\n" + n, c.innerBlocks.length > 0 && (r += s(c.innerBlocks.map((t => t.clientId))))
                    })), r
                }

                function p(t) {
                    const e = wp.data.select("core/block-editor").getSelectionStart(),
                        o = wp.data.select("core/block-editor").getSelectionEnd();
                    if (e.clientId === o.clientId) return [e, o];
                    let r = e,
                        c = o;
                    return t.length > 0 && t[0] === o.clientId && (r = o, c = e), [r, c]
                }

                function d() {
                    let t = n();

                    return t.length > 0 && t
                }(0, e.registerFormatType)("wpwand/custom-buttons", {
                    title: "WP Wand",
                    tagName: "wpwand",
                    className: 'wpwand-editor-prompt-item',
                    edit: e => {
                        let {
                            isActive: n,
                            onChange: l,
                            value: i
                        } = e, s = [];


                        if ("object" == typeof wpwand_gutenberg_editor && "object" == typeof wpwand_gutenberg_editor.editor_menus) {
                            let t = wpwand_gutenberg_editor.change_action;
                            for (let e = 0; e < wpwand_gutenberg_editor.editor_menus.length; e++) {
                                let o = wpwand_gutenberg_editor.editor_menus[e];
                                void 0 !== o.name && "" !== o.name && s.push({
                                    title: o.name,
                                    icon: o.is_pro ? 'wpwand-pro' : '',
                                    onClick: async () => {
                                        const e = d();
                                        if (e && !o.is_pro) {
                                            const r = await c(t);
                                            await a(r, o.prompt, e)
                                        } else if (o.is_pro) {
                                            window.open('https://wpwand.com/pro-plugin', '_blank');
                                        } else alert("Please select text")
                                    }
                                })

                            }
                        }


                        return (0, t.createElement)(o.BlockControls, null, (0, t.createElement)(r.ToolbarGroup, null, (0, t.createElement)(r.ToolbarDropdownMenu, {
                            className: "wpwand_editor_icon",
                            icon: !1,
                            label: "WP Wand",
                            controls: s

                        })))
                    }
                })
            }
        },
        o = {};

    function r(t) {
        var c = o[t];
        if (void 0 !== c) return c.exports;
        var a = o[t] = {
            exports: {}
        };
        return e[t](a, a.exports, r), a.exports
    }
    r.m = e, t = [], r.O = (e, o, c, a) => {

        if (!o) {
            var n = 1 / 0;
            for (p = 0; p < t.length; p++) {
                for (var [o, c, a] = t[p], l = !0, i = 0; i < o.length; i++)(!1 & a || n >= a) && Object.keys(r.O).every((t => r.O[t](o[i]))) ? o.splice(i--, 1) : (l = !1, a < n && (n = a));
                if (l) {
                    t.splice(p--, 1);
                    var s = c();
                    void 0 !== s && (e = s)
                }
            }
            return e
        }
        a = a || 0;
        for (var p = t.length; p > 0 && t[p - 1][2] > a; p--) t[p] = t[p - 1];
        t[p] = [o, c, a]
    }, r.o = (t, e) => Object.prototype.hasOwnProperty.call(t, e), (() => {
        var t = {
            826: 0,
            431: 0
        };
        r.O.j = e => 0 === t[e];
        var e = (e, o) => {
                var c, a, [n, l, i] = o,
                    s = 0;
                if (n.some((e => 0 !== t[e]))) {
                    for (c in l) r.o(l, c) && (r.m[c] = l[c]);
                    if (i) var p = i(r)
                }
                for (e && e(o); s < n.length; s++) a = n[s], r.o(t, a) && t[a] && t[a][0](), t[a] = 0;
                return r.O(p)
            },
            o = globalThis.webpackChunkgutenpride = globalThis.webpackChunkgutenpride || [];
        o.forEach(e.bind(null, 0)), o.push = e.bind(null, o.push.bind(o))
    })();
    var c = r.O(void 0, [431], (() => r(401)));

    c = r.O(c)
})();

(function ($) {
    $(document).ready(function () {
        let isEventBound = false;
        let promptBarAdded = false;
        let editorCheckInterval = null;

        // Function to inject the prompt bar
        function injectPromptBar() {
            if (promptBarAdded) return;
            
            // Double check if form already exists in DOM
            if ($('#wpwand-prompt-form').length > 0) {
                promptBarAdded = true;
                return;
            }
            
            // Check if we're in the block editor
            if (!$('body').hasClass('block-editor-page') && !$('body').hasClass('wp-admin')) {
                return;
            }

            // Clear any existing interval
            if (editorCheckInterval) {
                clearInterval(editorCheckInterval);
            }

            // Wait for the editor interface to be ready
            editorCheckInterval = setInterval(function() {
                // Check again if form exists before proceeding
                if ($('#wpwand-prompt-form').length > 0) {
                    clearInterval(editorCheckInterval);
                    promptBarAdded = true;
                    return;
                }

                // Try to find the editor interface - use the interface root
                const $interfaceRoot = $('.interface-interface-skeleton__content');
                const $editorCanvas = $('.editor-canvas-container, .edit-post-visual-editor');
                
                let $targetContainer = null;
                
                if ($interfaceRoot.length) {
                    $targetContainer = $interfaceRoot;
                } else if ($editorCanvas.length) {
                    $targetContainer = $editorCanvas;
                }

                if ($targetContainer && $targetContainer.length) {
                    clearInterval(editorCheckInterval);
                    
                    // Create the prompt form
                    const promptForm = `
                        <div class="wpwand-prompt-form" id="wpwand-prompt-form">
                            <div class="wpwand-dr-prompt-input">
                                <img src="${wpwand_glb.logo}">
                                <a class="wpwand-ai-bar-hiw" href="https://wpwand.com/how-ai-assistant-work" target="_blank">See how it works</a>
                                <input type="text" placeholder="Ask AI to write anything...">
                            </div>
                        </div>
                    `;

                    // Append to body instead of trying to find editor wrapper
                    $('body').append(promptForm);
                    promptBarAdded = true;

                    // Bind the enter key event
                    if (!isEventBound) {
                        $(document).on('keydown', '#wpwand-prompt-form input', function(event) {
                            if (event.keyCode === 13 || event.which === 13) {
                                event.preventDefault();
                                event.stopPropagation();
                                const $this = $(this);
                                if ($this.val().trim()) {
                                    wpwand_prompt_ajax($this);
                                }
                                return false;
                            }
                        });
                        isEventBound = true;
                    }

                    console.log('WPWand prompt form injected successfully');
                }
            }, 500);

            // Stop checking after 10 seconds
            setTimeout(function() {
                if (editorCheckInterval) {
                    clearInterval(editorCheckInterval);
                }
            }, 10000);
        }

        // Function to add top trigger
        function addTopTrigger() {
            if ('top' != wpwand_glb.toggler_positions) return;
            if ($('#wpwand-trigger-btn').length > 0) return; // Already exists
            
            // Try multiple selectors for the header toolbar
            const toolbarSelectors = [
                '.edit-post-header__toolbar',
                '.edit-post-header-toolbar', 
                '.editor-header__toolbar',
                '.interface-pinned-items'
            ];
            
            let $toolbar = null;
            for (let selector of toolbarSelectors) {
                const $el = $(selector);
                if ($el.length) {
                    $toolbar = $el;
                    console.log('Found toolbar with selector:', selector);
                    break;
                }
            }
            
            if ($toolbar && $toolbar.length) {
                const triggerButton = `
                    <a class="wpwand-trigger" href="#" id="wpwand-trigger-btn">
                        <img src="${wpwand_glb.logo}">AI Assistant
                    </a>
                `;
                
                $toolbar.append(triggerButton);
                console.log('WPWand trigger button added to toolbar');
            } else {
                console.log('Toolbar not found, will retry...');
            }
        }

        // Wait for editor to be fully loaded using WordPress data API
        let hasInitialized = false;
        let topTriggerInterval = null;
        
        if (window.wp && window.wp.data) {
            // Subscribe to editor ready state
            const unsubscribe = wp.data.subscribe(function() {
                const editor = wp.data.select('core/editor');
                if (editor && typeof editor.isCleanNewPost === 'function' && !hasInitialized) {
                    hasInitialized = true;
                    
                    // Try to add trigger immediately
                    setTimeout(addTopTrigger, 100);
                    
                    // Keep trying for 5 seconds in case toolbar loads later
                    topTriggerInterval = setInterval(function() {
                        if ($('#wpwand-trigger-btn').length === 0) {
                            addTopTrigger();
                        } else {
                            clearInterval(topTriggerInterval);
                        }
                    }, 500);
                    
                    setTimeout(function() {
                        if (topTriggerInterval) clearInterval(topTriggerInterval);
                    }, 5000);
                    
                    if (wpwand_glb.hide_ai_bar == 0) {
                        injectPromptBar();
                    }
                }
            });
        } else {
            // Fallback if wp.data is not available
            setTimeout(function() {
                if (!hasInitialized) {
                    hasInitialized = true;
                    addTopTrigger();
                    if (wpwand_glb.hide_ai_bar == 0) {
                        injectPromptBar();
                    }
                }
            }, 2000);
        }

        // Also try on window load as a fallback (only if not already initialized)
        $(window).on('load', function() {
            setTimeout(function() {
                if (!hasInitialized) {
                    hasInitialized = true;
                }
                // Always try to add trigger on load
                addTopTrigger();
                if (wpwand_glb.hide_ai_bar == 0) {
                    injectPromptBar();
                }
            }, 1000);
        });
    });

    function wpwand_prompt_ajax($this) {
        const prompt = $this.val();
        const wordToFind = 'table format';
        const regex = new RegExp(wordToFind, 'i');
        const table_format_match = prompt.match(regex);
        const is_table_format = table_format_match ? true : false;

        // Create "thinking" block
        const thinkingBlock = wp.blocks.createBlock("core/paragraph", {
            content: '<span style="color:#3767fb">AI is thinking...</span>'
        });
        
        wp.data.dispatch('core/block-editor').insertBlocks(thinkingBlock);
        $this.attr("disabled", 'disabled');

        $.post({
            url: wpwand_glb.ajax_url,
            data: {
                action: 'wpwand_only_prompt',
                nonce: wpwand_glb.nonce,
                prompt: prompt,
                is_table_format: is_table_format
            },
            success: function (response) {
                console.log('AI Response:', response);
                $this.removeAttr('disabled');
                $this.val('');
                
                // Remove thinking block
                wp.data.dispatch('core/block-editor').removeBlock(thinkingBlock.clientId);

                // Parse HTML response into blocks
                const blocks = wp.blocks.pasteHandler({
                    HTML: response,
                });

                // Insert blocks
                wp.data.dispatch('core/block-editor').insertBlocks(blocks);
                wp.data.dispatch('core/block-editor').synchronizeTemplate();
            },
            error: function (xhr) {
                console.error('AJAX Error:', xhr);
                wp.data.dispatch('core/block-editor').removeBlock(thinkingBlock.clientId);
                $this.removeAttr('disabled');
                
                // Show error message
                const errorBlock = wp.blocks.createBlock("core/paragraph", {
                    content: '<span style="color:#dc3232">Error: ' + xhr.statusText + '</span>'
                });
                wp.data.dispatch('core/block-editor').insertBlocks(errorBlock);
            }
        });
    }

})(jQuery);