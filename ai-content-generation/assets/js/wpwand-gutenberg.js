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

    $(document).ready(function ($) {
        wp.data.subscribe(function () {
            if ('top' == wpwand_glb.toggler_positions && $('body').hasClass('block-editor-page')) {

                if (!$('.edit-post-header-toolbar').find('.wpwand-trigger').length) {

                    $('.edit-post-header-toolbar').append('<a class="wpwand-trigger" href="#"><img src="' + wpwand_glb.logo + '">AI Assistant</a>')

                }
            }
            if (0 == wpwand_glb.hide_ai_bar) {

                if (!$('.editor-styles-wrapper').find('.wpwand-prompt-form').length) {
                    let prompt_form = '<div class="wpwand-prompt-form" id="wpwand-prompt-form"><div class="wpwand-dr-prompt-input"><img src="' + wpwand_glb.logo + '">  <a class="wpwand-ai-bar-hiw" href="https://wpwand.com/how-ai-assistant-work" target="_blank">See how it works</a>      <input type="text" placeholder="Ask AI to write anything..."></div></div>';
                    $('.editor-styles-wrapper').append(prompt_form);


                    $('#wpwand-prompt-form input').keypress(function (event) {

                        var keycode = (event.keyCode ? event.keyCode : event.which);
                        if (keycode == '13') {
                            const $this = $(this);


                            wpwand_prompt_ajax($this);
                        }

                    });
                }
            }


        });
    })


    function wpwand_prompt_ajax($this) {
        const prompt = $this.val();
        const wordToFind = 'table format';

        const regex = new RegExp(wordToFind, 'i');
        const table_format_match = prompt.match(regex);
        const is_table_format = table_format_match ? true : false;
        console.log(is_table_format);


        const t = wp.blocks.createBlock("core/paragraph", {
            content: '<span style="color:#3767fb">AI is thinking...</span>'
        })
        wp.data.dispatch('core/block-editor').insertBlocks(t)
        $this.attr("disabled", 'disabled');

        $.post({
            url: wpwand_glb.ajax_url,
            data: {
                action: 'wpwand_only_prompt',
                prompt,
                is_table_format
            },
            success: function (response) {
                console.log(response);
                $this.removeAttr('disabled');
                $this.val('');
                wp.data.dispatch('core/block-editor').removeBlock(t.clientId)

                const htmlContent = response;

                // Get the pasteHandler function from the wp.blocks module
                const pasteHandler = wp.blocks.pasteHandler;

                // HTML content of the scrapped data
                // const htmlContent = '<p>Your scrapped HTML content goes here</p>';

                // Process the HTML content using the pasteHandler
                const blocks = pasteHandler({
                    HTML: htmlContent,
                });

                // Get the current editor instance
                const editor = wp.data.select('core/editor');

                // Dispatch the insert blocks action
                wp.data.dispatch('core/block-editor').insertBlocks(blocks);

                // Trigger a save action to update the post content
                wp.data.dispatch('core/block-editor').synchronizeTemplate();

                /*      var htmlToAdd = response; // Replace with the HTML value you want to add

                     // Create a temporary container element to hold the HTML content
                     var tempContainer = document.createElement('div');
                     tempContainer.innerHTML = htmlToAdd;

                     // Array to store the blocks
                     var blocks = [];

                     // Iterate through each <p> and heading tag
                     var elements = tempContainer.querySelectorAll('p, h1, h2, h3, h4, h5, h6');
                     elements.forEach(function (element) {
                         // Get the tag name
                         var tagName = element.tagName.toLowerCase();

                         if (tagName === 'h1' || tagName === 'h2' || tagName === 'h3' || tagName === 'h4' || tagName === 'h5' || tagName === 'h6') {
                             // Create block based on the tag name
                             var block = wp.blocks.createBlock('core/heading', {
                                 content: element.innerHTML
                             });

                         } else {
                             // Create block based on the tag name
                             var block = wp.blocks.createBlock('core/paragraph', {
                                 content: element.innerHTML
                             });

                         }

                         // Add the block to the array
                         blocks.push(block);
                     });

                     // Insert the blocks into Gutenberg editor
                     wp.data.dispatch('core/block-editor').insertBlocks(blocks); */



            },
            error: function (xhr) {
                // Handle AJAX errors
                wp.data.dispatch('core/block-editor').removeBlock(t.clientId)

                $this.parent().append('<span class="error">Error: ' + xhr.statusText + '</span>');
            }
        });
    }
})(jQuery);