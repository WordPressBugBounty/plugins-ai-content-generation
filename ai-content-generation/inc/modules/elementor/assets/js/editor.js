(function ($) {

    jQuery(document).ready(function ($) {
        // console.log('hey');
    })

    $(window).on('elementor:init', function () {


        elementor.hooks.addAction('panel/open_editor/widget', function (panel, model, view, event) {

            if (model.get('widgetType') !== 'text-editor') {
                return;
            }


            var $widgetEditor = panel.$el.find('.elementor-control-wdelmtr_prompt_trigger'),
                $wpwand_floating = panel.$el.parents('body').find('.wpwand-floating'),
                $wpwand_trigger = panel.$el.parents('body').find('.wpwand-trigger'),
                $wpwand_result = panel.$el.parents('body').find('.wpwand-result-box .wpwand-content-wrap');


            // Add your custom button to the widget editor

            $widgetEditor.on('click', function (event) {
                // $promt_wrapper.show()

                event.stopPropagation();

                // $wpwand_trigger.toggleClass('active');
                // $wpwand_floating.toggleClass('active in-elementor');
                // $wpwand_floating.attr('active in-elementor');


            })

            $wpwand_floating.on('click', '*', function (event) {
                event.stopPropagation();
            })

            // panel.$el.closest('body').on('change', $wpwand_result, function (event) {
            //     console.log($wpwand_result.find('.wpwand-content').html());
            // })



        });
       



    })






}(jQuery))