<div class="wrap" id="wpwand-bulk-post-generator">
    <h1><?php esc_html_e('Create Bulk Posts', 'wp-wand'); ?></h1>
    <p><?php esc_html_e('Bulk generation is a premium feature. You need to upgrade to use the full feature.', 'wp-wand'); ?></p>

    <?php // $this->limit_text(); ?>

    <div class="wpwand-pgs-wrap">
        <div class="wpwand-pgs-header">
            <div class="step active" data-id="step-1">
                <h4><?php esc_html_e('Step 1', 'wp-wand'); ?></h4>
                <p><?php esc_html_e('Add info', 'wp-wand'); ?></p>
            </div>
            <div class="step" data-id="step-2">
                <h4><?php esc_html_e('Step 2', 'wp-wand'); ?></h4>
                <p><?php esc_html_e('Review titles', 'wp-wand'); ?></p>
            </div>
            <div class="step" data-id="step-3">
                <h4><?php esc_html_e('Step 3', 'wp-wand'); ?></h4>
                <p><?php esc_html_e('Confirmation', 'wp-wand'); ?></p>
            </div>
        </div>
        <div class="wpwand-pgs-content">
            <!-- step 1 -->
            <div id="step-1" class="step-content active">
                <div class="wpwand-nasted-tabs">
                    <a href="" class="active" data-id="wpwand-pgf-custom-wrap"><?php esc_html_e('Custom Headlines', 'wp-wand'); ?></a>
                    <a href="" data-id="wpwand-pgf-ai-wrap"><?php esc_html_e('AI Generated Headlines', 'wp-wand'); ?></a>
                </div>

                <div class="wpwand-nasted-item active" id="wpwand-pgf-custom-wrap">
                    <form action="" method="post" class=" wpwand-pg-form" id="wpwand-pgf-custom">

                        <div class="wpwand-pgf-row">
                            <div class="wpwand-pgf-label">
                                <label for="topic"><?php esc_html_e('Add Your Own Headlines', 'wp-wand'); ?></label>
                                <p><?php esc_html_e('Enter each headline in a single line. You can add as many as you want.', 'wp-wand'); ?></p>
                            </div>
                            <div class="wpwand-pgf-field">
                                <textarea name="titles" id="titles" cols="30" rows="10" placeholder="<?php esc_html_e('Your first headline goes here.', 'wp-wand'); ?>
<?php esc_html_e('Your second headline goes here.', 'wp-wand'); ?>
<?php esc_html_e('Your third headline goes here.', 'wp-wand'); ?>" required></textarea>
                            </div>
                        </div>

                        <div class="epwand-pgf-row wpwand-pgf-submit-wrap">
                            <?php wp_nonce_field('post_generate_nonce_action', 'post_generate_nonce'); ?>
                            <button type="submit"><?php esc_html_e('Next', 'wp-wand'); ?></button>
                        </div>
                    </form>
                </div>
                <div class="wpwand-nasted-item " id="wpwand-pgf-ai-wrap">
                    <form action="" method="post" class="wpwand-pg-form" id="wpwand-pgf-ai">

                        <div class="wpwand-pgf-row">
                            <div class="wpwand-pgf-label">
                                <label for="topic"><?php esc_html_e('Topic', 'wp-wand'); ?></label>
                                <p><?php esc_html_e('Add a topic of your bulk post', 'wp-wand'); ?></p>
                            </div>
                            <div class="wpwand-pgf-field">
                                <input type="text" id="topic" name="topic" placeholder="Digital Marketing" required />
                            </div>
                        </div>
                        <div class="wpwand-pgf-row">
                            <div class="wpwand-pgf-label">
                                <label for="post_count"><?php esc_html_e('Number of Posts', 'wp-wand'); ?></label>
                                <p><?php esc_html_e('How many posts do you want to generate at once, maximum 20 posts at a time.', 'wp-wand'); ?></p>
                            </div>
                            <div class="wpwand-pgf-field">
                                <input type="number" id="post_count" max="20" name="post_count"
                                    placeholder="<?php esc_html_e('Post to generate', 'wp-wand'); ?>" required />
                            </div>
                        </div>
                        <div class="epwand-pgf-row wpwand-pgf-submit-wrap">
                            <?php wp_nonce_field('post_generate_nonce_action', 'post_generate_nonce'); ?>
                            <button type="submit"><?php esc_html_e('Next', 'wp-wand'); ?></button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Step 2 -->
            <div id="step-2" class="step-content">
                <form action="" method="post" id="wpwand-post-content-generate-form">
                    <div class="wpwand-pcgf-titles-wrap">
                        <div class="wpwand-pg-info-wrap">
                            <div class="wpwand-pg-info ">
                                <strong><?php esc_html_e('Topic:', 'wp-wand'); ?></strong>
                                <span class="wpwand-pg-info-topic"></span>
                            </div>
                            <div class="wpwand-pg-info ">
                                <strong><?php esc_html_e('Number of Posts:', 'wp-wand'); ?></strong>
                                <span class="wpwand-pg-info-count"></span>
                            </div>
                        </div>
                        <div class="wpwand-pg-titles-wrap">
                            <div class="wpwand-pg-titles-header">
                                <h4> <span class="wpwand-pg-info-count"> </span> <?php esc_html_e('Titles Generated', 'wp-wand'); ?> </h4>
                                <a href="" onclick="" class="remove-selected"><?php esc_html_e('Remove Selected', 'wp-wand'); ?></a>
                            </div>
                            <div class="wpwand-pcgf-title-list">

                            </div>
                        </div>
                    </div>
                    <a href="" data-target="step-1" class="wpwand-pgs-back-button"><?php esc_html_e('Back', 'wp-wand'); ?></a>
                    <button type="button"><?php esc_html_e('Next', 'wp-wand'); ?></button>
                </form>
            </div>

            <!-- Step 3 -->
            <div id="step-3" class="step-content">
                <div class="wpwand-pgs-confirmation-wrap">
                    <div class="wpwand-pgs-confirmation">
                        <div class="wpwand-pg-info-wrap">
                            <div class="wpwand-pg-info ">
                                <strong><?php esc_html_e('Topic:', 'wp-wand'); ?></strong>
                                <span class="wpwand-pg-info-topic"></span>
                            </div>
                            <div class="wpwand-pg-info ">
                                <strong><?php esc_html_e('Number of Titles Selected:', 'wp-wand'); ?></strong>
                                <span class="wpwand-pg-info-total-selected"></span>
                            </div>
                        </div>
                        <p><?php esc_html_e('Bulk generation is a premium feature. You need to upgrade to use the full feature.', 'wp-wand'); ?></p>
                        <a href="" data-target="step-2" class="wpwand-pgs-back-button"><?php esc_html_e('Back', 'wp-wand'); ?></a>
                        <?php wpwand_upgrade_to_pro_button(); ?>
                    </div>

                    <div class="wpwand-pgs-free-htw-video">
                        <h2><?php esc_html_e('How to Generate Bulk Blog Post Using WP Wand AI', 'wp-wand'); ?></h2>
                        <iframe width="640" src="https://www.youtube.com/embed/w2nR1mgsiiE"
                            title="<?php esc_html_e('How to Generate Bulk Blog Post Using AI Inside WordPress', 'wp-wand'); ?>" frameborder="0"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                            allowfullscreen></iframe>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>