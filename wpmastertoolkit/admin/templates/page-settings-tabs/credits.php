<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * The credits tap of the settings page.
 * 
 * @since      1.0.0
 */
?>

<div class="wp-mastertoolkit__body__sections__item hide-in-all" data-key="credits">
    <div class="wp-mastertoolkit__body__sections__item__top">
        <div class="wp-mastertoolkit__body__sections__item__title"><?php esc_html_e( 'Credits', 'wpmastertoolkit' ); ?></div>
    </div>
    <div class="wp-mastertoolkit__body__sections__item__top">
        <div class="wp-mastertoolkit__body__sections__item__description">
            <?php
                printf(
                    wp_kses(
                        __( 'This plugin is proudly developed by the %s team, passionate about creating innovative solutions to improve and enrich the WordPress experience.', 'wpmastertoolkit' ),
                        array( 'a' => array( 'href' => array() ) ),
                    ),
                    '<a href="https://webdeclic.com/" target="_blank">Webdeclic</a>',
                );
            ?>
        </div>
    </div>
    <div class="wp-mastertoolkit__body__sections__item__space"></div>
    <div class="wp-mastertoolkit__body__sections__item__top">
        <div class="wp-mastertoolkit__body__sections__item__title"><?php esc_html_e( 'Support our work', 'wpmastertoolkit' ); ?></div>
    </div>
    <div class="wp-mastertoolkit__body__sections__item__top">
        <div class="wp-mastertoolkit__body__sections__item__description">
            <?php _e( "If this plugin contributes to your success or simplifies your use of WordPress, consider supporting our work. Your contribution helps us maintain the project, develop new features, and provide ongoing support. Here's how you can contribute:", 'wpmastertoolkit' ); ?>
            <ul class="custom-list">
                <li>
                    <?php
                        printf(
                            wp_kses(
                                __( '%s - Your comments are valuable in improving our solutions.', 'wpmastertoolkit' ),
                                array( 'a' => array( 'href' => array() ) ),
                            ),
                            '<a href="https://wordpress.org/support/plugin/wpmastertoolkit/" target="_blank">' . __( 'Offer your feedback', 'wpmastertoolkit' ) . '</a>',
                        );
                    ?>
                </li>
                <li>
                    <span class="fade"><?php esc_html_e( 'Buy the pro version - coming soon', 'wpmastertoolkit' ); ?></span>
                </li>
            </ul>
        </div>
    </div>
    <div class="wp-mastertoolkit__body__sections__item__space"></div>
    <div class="wp-mastertoolkit__body__sections__item__top">
        <div class="wp-mastertoolkit__body__sections__item__title"><?php esc_html_e( 'Explore our other plugins', 'wpmastertoolkit' ); ?></div>
    </div>
    <div class="wp-mastertoolkit__body__sections__item__top">
        <div class="wp-mastertoolkit__body__sections__item__description">
            <?php
                printf(
                    wp_kses(
                        __( "We have a variety of plugins available to meet different needs and features on WordPress. Check them out at %s and expand your website's capabilities with reliable, easy-to-use tools.", 'wpmastertoolkit' ),
                        array( 'a' => array( 'href' => array() ) ),
                    ),
                    '<a href=https://wordpress.org/plugins/search/webdeclic/" target="_blank">wordpress.org</a>',
                );
            ?>
        </div>
    </div>
    <div class="wp-mastertoolkit__body__sections__item__top">
        <div class="wp-mastertoolkit__body__sections__item__description">
            <?php _e( "We are proud to contribute to the WordPress community and are committed to providing quality solutions, accessible to everyone. A big thank you to all our users and supporters!", 'wpmastertoolkit' ); ?>
        </div>
    </div>
</div>
