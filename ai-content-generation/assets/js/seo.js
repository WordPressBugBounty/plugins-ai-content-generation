(function ($) {

    $(document).ready(function ($) {



        $('body').on('click', '.rank-math-edit-snippet', function () {
            setTimeout(function () {
                if (wpwand_glb.is_pro){
                    $('.variable-group.rank-math-description-variables').parent().addClass('wpwand-rankmath-wrapper').prepend(`<div class="wpwand-seo-generator-wrap"><span class="wpwand-seo-generator wpwand-rankmath-generator wpwand-button" data-type="rankmath-description"  "><img src="` + wpwand_glb.logo + `"> Generate with Ai</span></div>`)
                }else{
                    $('.variable-group.rank-math-description-variables').parent().addClass('wpwand-rankmath-wrapper').prepend(`
                    <div class="wpwand-seo-generator-wrap">
                    <span class="wpwand-seo-generator-locked wpwand-rankmath-generator wpwand-button" data-type="rankmath-description"  "><img src="` + wpwand_glb.logo + `"> Generate with Ai</span>
                    <span class="wpwand-seo-generator-locked-ooltip">
                    Available in WP Wand pro
                    </span></div>`)
                }
            }, 100);
        })

        const wpwand_seo_generator = '.wpwand-seo-generator';

        $('body').on('click', wpwand_seo_generator, function () {
            const $this = $(this);

            wpwand_wc_prompt_ajax($this)
        })



    })

    $(window).on('load', function () {
        $('.yst-replacevar__label:contains("Meta description")').parent().append('<a class="wpwand-yst-trigger wpwand-button" href="#"><img src="' + wpwand_glb.logo + '">Write with AI</a>')
        $('body').on('click', '.wpwand-yst-trigger', function (e) {
            e.preventDefault()
            $('.wpwand-trigger').toggleClass('active');
            $('.wpwand-floating').toggleClass('active');
            $('.wpwand-tiemplate-item h4:contains("Meta Description")').parent().next('.wpwand-prompt-form-wrap').addClass('active');
        })
    })

    function wpwand_wc_prompt_ajax($this) {

        const type = $this.data('type');
        const post_id = wpwand_glb.post_id;
        const title = $('.editor-post-title').text();
        $this.css('opacity','0.6').attr('disabled', 'disabled');

        $.post({
            url: wpwand_glb.ajax_url,
            data: {
                action: 'wpwand_seo_prompt',
                post_id,
                type,
                title
                // is_table_format
            },
            success: function (response) {
                console.log(response);

                $this.parent().siblings('.rank-math-description-variables').find('#rank-math-editor-description').val(response);
                $this.css('opacity','1').removeAttr('disabled');
            },
            error: function (xhr) {
                // Handle AJAX errors

            }
        });
    }



}(jQuery))