<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Template for the maintentance mode.
 * @since      1.3.0
 */

$wpmtk_is_pro                     = wpmastertoolkit_is_pro();
$wpmtk_class_maintenance_mode     = new WPMastertoolkit_Maintenance_Mode();
$wpmtk_settings                   = $wpmtk_class_maintenance_mode->get_settings();
$wpmtk_title_text                 = $wpmtk_settings['title_text'] ?? '';
$wpmtk_headline_text              = $wpmtk_settings['headline_text'] ?? '';
$wpmtk_body_text                  = $wpmtk_settings['body_text'] ?? '';
$wpmtk_footer_text                = $wpmtk_settings['footer_text'] ?? '';
$wpmtk_background_color           = $wpmtk_settings['background_color'] ?? '';
$wpmtk_text_color                 = $wpmtk_settings['text_color'] ?? '';
$wpmtk_logo                       = $wpmtk_settings['logo'] ?? '';
$wpmtk_background_image           = $wpmtk_settings['background_image'] ?? '';
$wpmtk_logo_height                = $wpmtk_settings['logo_height'] ?? '';
$wpmtk_logo_width                 = $wpmtk_settings['logo_width'] ?? '';
$wpmtk_countdown_status           = $wpmtk_is_pro ? $wpmtk_settings['countdown_status'] ?? '0' : '0';
$wpmtk_countdown_end_date         = $wpmtk_settings['countdown_end_date'] ?? time();
$wpmtk_countdown_text_color       = $wpmtk_settings['countdown_text_color'] ?? "#0000";
$wpmtk_countdown_background_color = $wpmtk_settings['countdown_background_color'] ?? "#FFFFFF";

include WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/templates/core/maintenance-mode/html-template.php';