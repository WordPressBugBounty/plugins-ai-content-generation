(function () {

    var $ = jQuery;
    const createLoadingSpinner = function (selectedNode, placement, loadingSpinnerId) {

        let spinnerHtml = '';
        if (['li'].includes(selectedNode.tagName.toLowerCase())) {
            spinnerHtml = '<' + selectedNode.tagName + ' id="' + loadingSpinnerId + '" style="color:#3767fb" class="wpwand-mce-loading">AI is thinking...</' + selectedNode.tagName + '>';
        } else {
            spinnerHtml = '<p id="' + loadingSpinnerId + '" class="wpwand-mce-loading" style="color:#3767fb">AI is thinking...</p>';
        }

        return spinnerHtml;
    }
    tinymce.PluginManager.add('wpwandeditor', function (editor, url) {
        let wpwand_menus = [];
        if (typeof wpwandTinymceEditorMenus === "object") {
            for (let i = 0; i < wpwandTinymceEditorMenus.length; i++) {
                let wpwandTinymceEditorMenu = wpwandTinymceEditorMenus[i];
                if (typeof wpwandTinymceEditorMenu.name !== "undefined" && wpwandTinymceEditorMenu.name !== '') {
                    wpwand_menus.push({
                        text: wpwandTinymceEditorMenu.name,
                        classes: wpwandTinymceEditorMenu.is_pro ? 'wpwand is_pro' : '', // Add your desired custom class here\
                        onclick: function () {
                            let selected_html = editor.selection.getContent({
                                'format': 'html'
                            });
                            let selected_text = editor.selection.getContent({
                                'format': 'text'
                            });
                            if (selected_text === '') {
                                alert('Please select text');
                            } else {
                                wpwandSendEditorPrompt(selected_text, selected_html, wpwandTinymceEditorMenu.prompt, editor);
                            }
                        }
                    })
                }
            }
        }

        editor.addButton('wpwandeditor', {
            title: 'WP Wand',
            image: wpwand_plugin_url + 'assets/img/logo.png',
            icon: false,
            type: 'menubutton',
            menu: wpwand_menus
        });
    });

    function wpwandSendEditorPrompt(text, html, prompt, editor) {
        prompt = prompt.replace('[text]', text);
        let dom = tinymce.activeEditor.dom;
        let $ = tinymce.dom.DomQuery;
        let selectionRange = editor.selection.getRng();
        const loadingSpinnerId = Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);
        if (typeof wpwandEditorChangeAction === 'undefined') {
            wpwandEditorChangeAction = 'below';
        }
        jQuery.ajax({
            url: wpwand_editor_ajax_url,
            data: {
                action: 'wpwand_editor_request',
                prompt: prompt,
                // nonce: wpwand_editor_wp_nonce
            },
            dataType: 'JSON',
            type: 'POST',
            beforeSend: function () {

                if (wpwandEditorChangeAction === 'below') {
                    let selectedNode = editor.selection.getEnd();
                    let spinnerHtml = createLoadingSpinner(
                        selectedNode,
                        wpwandEditorChangeAction,
                        loadingSpinnerId,
                    )
                    let spinnerDom = $(spinnerHtml)[0];

                    let parentNode = selectionRange.endContainer.parentNode;
                    // if parent node is li then we need to create a new li
                    if (parentNode.tagName.toLowerCase() === 'li') {
                        $(selectedNode).after(spinnerDom);
                    } else if (selectedNode.textContent) {
                        selectionRange.collapse(false);
                        selectionRange.insertNode(spinnerDom);
                        editor.selection.collapse();
                    } else {
                        $(selectedNode).after(spinnerDom);
                    }


                } else { // above
                    let selectedNode = editor.selection.getStart();
                    let spinnerHtml = createLoadingSpinner(
                        selectedNode,
                        wpwandEditorChangeAction,
                        loadingSpinnerId,
                    )
                    let spinnerDom = $(spinnerHtml)[0];

                    let parentNode = selectionRange.startContainer.parentNode;
                    // if parent node is li then we need to create a new li
                    if (parentNode.tagName.toLowerCase() === 'li') {
                        $(selectedNode).before(spinnerDom);
                    } else if (selectedNode.textContent) {
                        selectionRange.collapse(true);
                        selectionRange.insertNode(spinnerDom);
                        editor.selection.collapse();
                    } else {
                        $(selectedNode).before(spinnerDom);
                    }
                }
                editor.selection.collapse();
            },
            success: function (res) {
                let spinner = dom.select('#' + loadingSpinnerId);
                if (res.status === 'success') {
                    let dataContent = res.data;
                    if (wpwandEditorChangeAction === 'below') {
                        dataContent = '<br>' + dataContent;
                    } else {
                        dataContent = dataContent + '<br>';
                    }
                    $(spinner).removeAttr('class');
                    $(spinner).removeAttr('style');
                    $(spinner).removeAttr('id');
                    $(spinner).html(dataContent);
                } else {
                    $(spinner).remove();
                    alert(res.msg);
                }
            }
        })
    }
})();