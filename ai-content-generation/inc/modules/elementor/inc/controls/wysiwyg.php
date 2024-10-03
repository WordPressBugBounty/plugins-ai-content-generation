<?php
namespace Elementor;

use Elementor\Modules\DynamicTags\Module as TagsModule;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Elementor WYSIWYG control.
 *
 * A base control for creating WYSIWYG control. Displays a WordPress WYSIWYG
 * (TinyMCE) editor.
 *
 * @since 1.0.0
 */
class FDWSELT_Control_Wysiwyg extends Base_Data_Control {

	/**
	 * Get wysiwyg control type.
	 *
	 * Retrieve the control type, in this case `wysiwyg`.
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @return string Control type.
	 */
	public function get_type() {
		return 'wysiwyg';
	}

	// public function enqueue() {
    //         wp_enqueue_script( 'wpwand-admin', WPWAND_PLUGIN_URL . 'assets/js/admin.js', ['jquery'] );

    // }


	/**
	 * Render wysiwyg control output in the editor.
	 *
	 * Used to generate the control HTML in the editor using Underscore JS
	 * template. The variables for the class are available using `data` JS
	 * object.
	 *
	 * @since 1.0.0
	 * @access public
	 */
	public function content_template() {
		?>
		<div class="elementor-control-field">
			<div class="elementor-control-title">{{{ data.label }}}</div>
                <# if ( data.ai.active == true ) { #>
                    <span class="wdelmtr-prompt-trigger"><img src="<?php echo esc_url(wpwand_loago_icon_url() )?>" alt=""/></span>
                <# } #>
			<div class="elementor-control-input-wrapper elementor-control-tag-area"></div>
		</div>
		<# if ( data.description ) { #>
		<div class="elementor-control-field-description">{{{ data.description }}}</div>
		<# } #>

        
        
        
        <# if ( data.ai.active == true ) {  #>
        <?php wpwand_frontend_callback(); ?>
        <# 

        


            var $widgetEditor = view.$el
       
                   // Add your custom button to the widget editor
       
            $widgetEditor.on('click', 'span.wdelmtr-prompt-trigger', function (event) {
                var $wpwand_floating = jQuery(event.currentTarget).parent().siblings('.wpwand-floating'),
                    $wpwand_trigger = jQuery(event.currentTarget).parent().siblings('.wpwand-floating').find('.wpwand-trigger'),
                    $wpwand_result = jQuery(event.currentTarget).parent().siblings('.wpwand-floating').find('.wpwand-result-box').find('.wpwand-content-wrap');



                $wpwand_floating.attr('active in-elementor');
                $wpwand_floating.attr('data-elementor-id', view.editorID);
                $wpwand_floating.attr('data-type', data.type);
                $wpwand_trigger.toggleClass('active');
                $wpwand_floating.toggleClass('active in-elementor');
            });


            
            #>
        <# } #>


		<?php
        
	}

	/**
	 * Retrieve textarea control default settings.
	 *
	 * Get the default settings of the textarea control. Used to return the
	 * default settings while initializing the textarea control.
	 *
	 * @since 2.0.0
	 * @access protected
	 *
	 * @return array Control default settings.
	 */
	protected function get_default_settings() {
		return [
			'label_block' => true,
			'ai' => [
				'active' => true,
				'type' => 'textarea',
			],
			'dynamic' => [
				'active' => true,
				'categories' => [ TagsModule::TEXT_CATEGORY ],
			],
		];
	}
}