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

include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/maintenance-mode/html-template.php';