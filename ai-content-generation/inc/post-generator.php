<?php
namespace WPWAND;

if (!defined('ABSPATH')) {
    exit('You are not allowed');
}
class Post_Generator_FR
{
    private $total_tasks = 0;
    private $completed_tasks = 0;
    /**
     * @var string
     */
    protected $prefix = 'wpwand';

    /**
     * @var string
     */
    protected $action = 'post_generate';

    public function __construct()
    {


        add_action('admin_menu', [$this, 'register_menu']);
        add_action('wp_ajax_wpwand_post_generator', [$this, 'generate_title']);
        add_action('wp_ajax_nopriv_wpwand_post_generator', [$this, 'generate_title']);

    }



    /**
     * Summary of register_menu
     * @return void
     */
    function register_menu()
    {
        add_submenu_page('wpwand', __('Bulk Posts', 'wpwand'), __('Bulk Posts', 'wpwand'), 'manage_options', 'wpwand-post-generator', [$this, 'post_generate_page']);
    }

    /**
     * Summary of generate_title
     * @return void
     */
    function generate_title()
    {
        if (empty($_POST['topic'])) {
            wp_send_json_error('error');
        }

        $topic = sanitize_text_field($_POST['topic'] ?? '');
        $count = sanitize_text_field($_POST['count'] ?? '');

        $language = wpwand_get_option('wpwand_language', 'English');
        $rawResponse = isset($_POST['rawResponse']) && true == $_POST['rawResponse'] ? true : false;
        $is_table_format_prompt = $rawResponse ? '' : 'You must give output with html tags';


        $content = wpwand_openAi(
            "I will give a topic and you will write one high converting blog title. This title should have a hook and high potential to go viral on social media. My topic is" . $topic . ". You must write in $language.",
            (int) $count
        );


        $text = '';
        $i = 0;
        if (isset($content->choices)) {

            foreach ($content->choices as $choice) {
                $i++;
                $reply = isset($choice->message) ? $choice->message->content : $choice->text;


                $text .= '
                <div class="wpwand-pcgf-heading-item">
                    <div class="wpwand-pcgf-heading-content">
                        <input type="checkbox" id="selected_headings' . $i . '" name="selected_headings[]" value="' . $reply . '"> 
                        <label for="selected_headings' . $i . '">' . rtrim(ltrim($reply, '"'), '"') . '</label>
                    </div>
                    <div class="wpwand-pcgf-heading-action">
                        <a class="remove" href="' . admin_url('admin.php?page=wpwand-post-generator&delete=') . '" onclick="return confirm(\'Are you sure you want to delete?\')">Remove</a>
                    </div>
                </div>
                ';


            }
        } elseif (isset($content->error)) {
            $text .= '<div class="wpwand-content wpwand-prompt-error">';
            $text .= wpwand_openAi_error($content->error);
            $text .= '  </div>';
        }
        wp_send_json($text);
    }




    /**
     * Summary of post_generate_page
     * @return void
     */
    function post_generate_page()
    {

        include 'view/post-generator.php';



    }
}


$woocommerce = new Post_Generator_FR();