<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Template to preview the maintentance mode.
 * @since      2.9.0
 */

$class_maintenance_mode     = new WPMastertoolkit_Maintenance_Mode();
$preview_nonce              = sanitize_text_field( wp_unslash( $_GET[ $class_maintenance_mode->preview_param ] ?? '' ) );
if ( ! wpmastertoolkit_is_pro() || ! wp_verify_nonce( $preview_nonce, $class_maintenance_mode->nonce_action ) ) {
	exit;
}

$is_pro                     = wpmastertoolkit_is_pro();
$title_text                 = sanitize_text_field( wp_unslash( $_GET['title_text'] ?? '' ) );
$headline_text              = sanitize_text_field( wp_unslash( $_GET['headline_text'] ?? '' ) );
$body_text                  = wp_kses_post( wp_unslash( $_GET['body_text'] ?? '' ) );
$footer_text                = sanitize_text_field( wp_unslash( $_GET['footer_text'] ?? '' ) );
$background_color           = sanitize_text_field( wp_unslash( $_GET['background_color'] ?? '' ) );
$text_color                 = sanitize_text_field( wp_unslash( $_GET['text_color'] ?? '' ) );
$logo                       = sanitize_text_field( wp_unslash( $_GET['logo'] ?? '' ) );
$background_image           = sanitize_text_field( wp_unslash( $_GET['background_image'] ?? '' ) );
$logo_height                = sanitize_text_field( wp_unslash( $_GET['logo_height'] ?? '' ) );
$logo_width                 = sanitize_text_field( wp_unslash( $_GET['logo_width'] ?? '' ) );
$countdown_status           = $is_pro ? sanitize_text_field( wp_unslash( $_GET['countdown_status'] ?? '0' ) ) : '0';
$countdown_end_date         = sanitize_text_field( wp_unslash( $_GET['countdown_end_date'] ?? '' ) );
$countdown_end_date         = strtotime( $countdown_end_date );
$countdown_text_color       = sanitize_text_field( wp_unslash( $_GET['countdown_text_color'] ?? "#0000" ) );
$countdown_background_color = sanitize_text_field( wp_unslash( $_GET['countdown_background_color'] ?? "#FFFFFF" ) );

include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/maintenance-mode/html-template.php';
