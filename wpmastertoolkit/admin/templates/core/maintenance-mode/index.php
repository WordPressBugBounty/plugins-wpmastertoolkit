<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Template for the maintentance mode.
 * @since      1.3.0
 */

$is_pro                     = wpmastertoolkit_is_pro();
$class_maintenance_mode     = new WPMastertoolkit_Maintenance_Mode();
$settings                   = $class_maintenance_mode->get_settings();
$title_text                 = $settings['title_text'] ?? '';
$headline_text              = $settings['headline_text'] ?? '';
$body_text                  = $settings['body_text'] ?? '';
$footer_text                = $settings['footer_text'] ?? '';
$background_color           = $settings['background_color'] ?? '';
$text_color                 = $settings['text_color'] ?? '';
$logo                       = $settings['logo'] ?? '';
$background_image           = $settings['background_image'] ?? '';
$logo_height                = $settings['logo_height'] ?? '';
$logo_width                 = $settings['logo_width'] ?? '';
$countdown_status           = $is_pro ? $settings['countdown_status'] ?? '0' : '0';
$countdown_end_date         = $settings['countdown_end_date'] ?? time();
$countdown_text_color       = $settings['countdown_text_color'] ?? "#0000";
$countdown_background_color = $settings['countdown_background_color'] ?? "#FFFFFF";
?>

<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <title><?php echo esc_html( $title_text ); ?></title>
    <meta charset="<?php echo esc_attr( get_bloginfo( 'charset' ) ); ?>" />
    <meta name="viewport" content="width=device-width, maximum-scale=1, initial-scale=1, minimum-scale=1">
    <meta name="description" content="<?php echo esc_attr( get_bloginfo( 'description' ) ); ?>"/>
    <meta http-equiv="X-UA-Compatible" content="<?php echo esc_attr( get_bloginfo( 'description' ) );?>"/>
    <meta property="og:site_name" content="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">  
    <meta property="og:title" content="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>"/>
    <meta property="og:type" content="Maintenance"/>
    <meta property="og:url" content="<?php echo esc_url( site_url() ); ?>"/>
    <meta property="og:description" content="<?php echo esc_attr( get_bloginfo( 'description' ) );?>"/>
    <?php if ( $countdown_status == '1' ): ?>
        <script>
            const wpmastertoolkit_maintenance_mode_countdown_end_date = "<?php echo esc_js( $countdown_end_date ); ?>";
        </script>
    <?php endif; ?>
    <style>
        :root {
            --text-color: <?php echo ! empty( $text_color ) ? esc_attr( $text_color ) : esc_attr( '#000000' ); ?>;
            --background-color: <?php echo ! empty( $background_color ) ? esc_attr( $background_color ) : esc_attr( '#FFFFFF' ); ?>;
            --logo-height: <?php echo ! empty( $logo_height ) ? esc_attr( $logo_height ) : esc_attr( '180' ); ?>px;
            --logo-width: <?php echo ! empty( $logo_width ) ? esc_attr( $logo_width ) : esc_attr( '180' ); ?>px;
            --countdown-text-color: <?php echo esc_attr( $countdown_text_color ); ?>;
            --countdown-background-color: <?php echo esc_attr( $countdown_background_color ); ?>;
        }
    </style>
	<?php //phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet ?>
    <link rel="stylesheet" type="text/css" href="<?php echo esc_attr( WPMASTERTOOLKIT_PLUGIN_URL . "admin/assets/build/core/maintenance-mode-template.css" ); ?>">
	<?php //phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript ?>
    <script src="<?php echo esc_attr( WPMASTERTOOLKIT_PLUGIN_URL . "admin/assets/build/core/maintenance-mode-template.js" ); ?>"></script>
    <link rel="pingback" href="<?php echo esc_url( get_bloginfo( 'pingback_url' ) ); ?>" />
</head>
 
<body class="wp-mastertoolkit">

    <div class="wp-mastertoolkit__container">

        <main class="wp-mastertoolkit__main">

            <header class="wp-mastertoolkit__header">

                <?php if ( ! empty( $logo ) ): ?>

                    <div class="wp-mastertoolkit__header__logo">
						<?php //phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage ?>
                        <img src="<?php echo esc_url( $logo ); ?>" alt="<?php esc_attr_e( 'site logo', 'wpmastertoolkit' ); ?>">
                    </div>
                    <div class="wp-mastertoolkit__header__title screen-reader-text">
                        <h1><?php echo esc_html( $title_text ); ?></h1>
                    </div>

                <?php else: ?>

                    <div class="wp-mastertoolkit__header__title">
                        <h1><?php echo esc_html( $title_text ); ?></h1>
                    </div>

                <?php endif; ?>

            </header>

            <div class="wp-mastertoolkit__body">

                <h2 class="wp-mastertoolkit__body__heading"><?php echo esc_html( $headline_text ); ?></h2>

                <div class="wp-mastertoolkit__body__description">
                    <?php echo wp_kses_post( wpautop( stripslashes( $body_text ) ) ); ?>
                </div>
                
                <?php if ( $countdown_status == '1' ): ?>
                    <ul class="wp-mastertoolkit__body__countdown">
                        <li><span id="days"></span>
                            <?php esc_html_e( 'Days', 'wpmastertoolkit' ); ?>
                        </li>
                        <li><span id="hours"></span>
                            <?php esc_html_e( 'Hours', 'wpmastertoolkit' ); ?>
                        </li>
                        <li><span id="minutes"></span>
                            <?php esc_html_e( 'Minutes', 'wpmastertoolkit' ); ?>
                        </li>
                        <li><span id="seconds"></span>
                            <?php esc_html_e( 'Seconds', 'wpmastertoolkit' ); ?>
                        </li>
                    </ul>
                <?php endif; ?>
            </div>

        </main>

        <footer class="wp-mastertoolkit__footer">
            <p><?php echo esc_html( $footer_text ); ?></p>
        </footer>

        <?php if ( ! empty( $background_image ) ): ?>

            <picture class="wp-mastertoolkit__background">
                <source media="(max-width: 100vh)" srcset="<?php echo esc_url( $background_image ); ?>">
				<?php //phpcs:ignore PluginCheck.CodeAnalysis.ImageFunctions.NonEnqueuedImage ?>
                <img src="<?php echo esc_url( $background_image ); ?>">
            </picture>

        <?php endif; ?>

    </div>

</body>

</html>