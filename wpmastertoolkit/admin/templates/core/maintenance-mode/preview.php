<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Template to preview the maintentance mode.
 * @since      2.9.0
 */

$wpmtk_class_maintenance_mode     = new WPMastertoolkit_Maintenance_Mode();
$wpmtk_preview_nonce              = sanitize_text_field( wp_unslash( $_GET[ $wpmtk_class_maintenance_mode->preview_param ] ?? '' ) );
if ( ! wpmastertoolkit_is_pro() || ! wp_verify_nonce( $wpmtk_preview_nonce, $wpmtk_class_maintenance_mode->nonce_action ) ) {
	exit;
}

$wpmtk_is_pro                     = wpmastertoolkit_is_pro();
$wpmtk_title_text                 = sanitize_text_field( wp_unslash( $_GET['title_text'] ?? '' ) );
$wpmtk_headline_text              = sanitize_text_field( wp_unslash( $_GET['headline_text'] ?? '' ) );
$wpmtk_body_text                  = wp_kses_post( wp_unslash( $_GET['body_text'] ?? '' ) );
$wpmtk_footer_text                = sanitize_text_field( wp_unslash( $_GET['footer_text'] ?? '' ) );
$wpmtk_background_color           = sanitize_text_field( wp_unslash( $_GET['background_color'] ?? '' ) );
$wpmtk_text_color                 = sanitize_text_field( wp_unslash( $_GET['text_color'] ?? '' ) );
$wpmtk_logo                       = sanitize_text_field( wp_unslash( $_GET['logo'] ?? '' ) );
$wpmtk_background_image           = sanitize_text_field( wp_unslash( $_GET['background_image'] ?? '' ) );
$wpmtk_logo_height                = sanitize_text_field( wp_unslash( $_GET['logo_height'] ?? '' ) );
$wpmtk_logo_width                 = sanitize_text_field( wp_unslash( $_GET['logo_width'] ?? '' ) );
$wpmtk_countdown_status           = $wpmtk_is_pro ? sanitize_text_field( wp_unslash( $_GET['countdown_status'] ?? '0' ) ) : '0';
$wpmtk_countdown_end_date         = sanitize_text_field( wp_unslash( $_GET['countdown_end_date'] ?? '' ) );
$wpmtk_countdown_end_date         = strtotime( $wpmtk_countdown_end_date );
$wpmtk_countdown_text_color       = sanitize_text_field( wp_unslash( $_GET['countdown_text_color'] ?? "#0000" ) );
$wpmtk_countdown_background_color = sanitize_text_field( wp_unslash( $_GET['countdown_background_color'] ?? "#FFFFFF" ) );

include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/maintenance-mode/html-template.php';
