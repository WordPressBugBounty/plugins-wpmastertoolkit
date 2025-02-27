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
                wp_kses(
                	printf(
						/* translators: %s: Webdeclic link */
                        esc_html__( 'This plugin is proudly developed by the %s team, passionate about creating innovative solutions to improve and enrich the WordPress experience.', 'wpmastertoolkit' ),
						'<a href="https://webdeclic.com/" target="_blank">Webdeclic</a>',
                    ),
					array( 'a' => array( 'href' => array() ) ),
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
            <?php esc_html_e( "If this plugin contributes to your success or simplifies your use of WordPress, consider supporting our work. Your contribution helps us maintain the project, develop new features, and provide ongoing support. Here's how you can contribute:", 'wpmastertoolkit' ); ?>
            <ul class="custom-list">
                <li>
                    <?php
                        wp_kses(
                        	printf(
								/* translators: %s: feedback link */
                                esc_html__( '%s - Your comments are valuable in improving our solutions.', 'wpmastertoolkit' ),
								'<a href="https://wordpress.org/support/plugin/wpmastertoolkit/" target="_blank">' . esc_html__( 'Offer your feedback', 'wpmastertoolkit' ) . '</a>',
                            ),
							array( 'a' => array( 'href' => array() ) ),
                        );
                    ?>
                </li>
                <li>
                    <a href="<?php echo esc_url( 'https://wpmastertoolkit.com/en/products-2/wpmastertoolkit-pro/' ); ?>" target="_blank">
                        <?php esc_html_e( 'Buy PRO version.', 'wpmastertoolkit' ); ?>
                    </a>
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
                wp_kses(
                	printf(
						/* translators: %s: wordpress.org link */
                        esc_html__( "We have a variety of plugins available to meet different needs and features on WordPress. Check them out at %s and expand your website's capabilities with reliable, easy-to-use tools.", 'wpmastertoolkit' ),
						'<a href=https://wordpress.org/plugins/search/webdeclic/" target="_blank">wordpress.org</a>',
                    ),
					array( 'a' => array( 'href' => array() ) ),
                );
            ?>
        </div>
    </div>
    <div class="wp-mastertoolkit__body__sections__item__top">
        <div class="wp-mastertoolkit__body__sections__item__description">
            <?php esc_html_e( "We are proud to contribute to the WordPress community and are committed to providing quality solutions, accessible to everyone. A big thank you to all our users and supporters!", 'wpmastertoolkit' ); ?>
        </div>
    </div>
</div>
