(function ($) {

    $(document).ready(function ($) {


        const wpwand_prompt_form = $('.wpwand-popup-prompt-wrap');

        $('.woocommerce-layout__header-wrapper').append(wpwand_prompt_form.html())

        $('body').on('click', '.wpwand-wc-prompt-toggle', function () {
            $(this).siblings().toggleClass('active')
        })
        // $('#titlewrap').append('<span class="wpwand-popup-prompt-toggle" data-type="title" data-title="Write your product topic/description" >Ai </span>')
        // $('#wp-content-wrap').append('<span class="wpwand-popup-prompt-toggle" data-type="content" data-title="describe your product" >Ai </span>')

        $('#wpwand-wc-prompt-form').on('submit', function (e) {
            e.preventDefault();
            const $this = $(this);

            wpwand_wc_prompt_ajax($this)


        })


        $('body').on('click', '.wpwand-wc-prompt-close', function (e) {
            $('.wpwand-wc-prompt-close').parent().removeClass('active');
        })

        $('body').on('click', '.wpwand-insert-to-wc-title', function (e) {
            // check if clicked on .wpwand-insert-to-wc-title
            $(this).text('Inserted!');
            $('#titlewrap').find('input').val($(this).siblings('.wpwand-ai-response').html())
            $('#title-prompt-text').hide();


        })
        $('body').on('click', '.wpwand-insert-to-wc-content', function (e) {
            // check if clicked on .wpwand-insert-to-wc-title
            $(this).text('Inserted!');
            wpwandSetClassicEditorContent($(this).siblings('.wpwand-ai-response').html())

        })



    })



    function wpwand_wc_prompt_ajax($this) {
        const prompt = $this.find('#wpwand-short_description').val();

        console.log(prompt);
        $this.find('.wpwand-submit-button').attr('disabled', 'disabled');
        $this.parent().find('.wpwand-result-box').show();
        $this.parent().find('.wpwand-content-wrap').html(
            '<div class="wpwand-content skeleton"><div class="skeleton-left"><div class="line"></div><div class="line w50"></div><div class="line w75"></div></div></div>'
        );

        $.post({
            url: wpwand_glb.ajax_url,
            data: {
                action: 'wpwand_wc_prompt',
                prompt,
            },
            success: function (response) {
                console.log(response);
                $this.find('.wpwand-submit-button').removeAttr('disabled');
                $this.parent().find('.wpwand-content-wrap').empty().html(response);

                var markdownContent = $('body').find('.wpwand-markdown');
                var converter = new showdown.Converter();
                var htmlContent = converter.makeHtml(markdownContent.text());
                markdownContent.html(htmlContent)
            },
            error: function (xhr) {
                // Handle AJAX errors
                wp.data.dispatch('core/block-editor').removeBlock(t.clientId)

                $this.parent().append('<span class="error">Error: ' + xhr.statusText + '</span>');
            }
        });
    }

    function wpwandSetClassicEditorContent(content) {
        var editorId = 'content'; // Replace with the actual TinyMCE ID

        if (typeof tinyMCE !== 'undefined' && tinyMCE.get(editorId)) {
            tinyMCE.get(editorId).setContent(content);
        } else {
            $('#content').val(content); // Fallback if TinyMCE is not initialized
        }
    }

}(jQuery))