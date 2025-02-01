<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * The class responsible for stocking the data of the modules.
 *
 * @link       https://webdeclic.com
 * @since      1.0.0
 *
 * @package           WPMastertoolkit
 * @subpackage WP-Mastertoolkit/admin
 */
class WPMastertoolkit_Modules_Data {

	/**
	 * Return modules values without translation.
	 */
	public static function modules_normal_values() {

		$modules = array(
			'WPMastertoolkit_Hide_Admin_Notices' => array(
				'original_name' =>'Hide Admin Notices',
				'group'         => 'administration',
				'pro'           => false,
				'path'          => 'core/class-hide-admin-notices.php',
			),
			'WPMastertoolkit_Update_Logs' => array(
				'original_name' =>'Updates Logs',
				'group'         => 'administration',
				'pro'           => true,
				'path'          => 'pro/class-update-logs.php',
			),
			'WPMastertoolkit_Hide_Admin_Bar' => array(
				'original_name' =>'Hide Admin Bar',
				'group'         => 'administration',
				'pro'           => false,
				'path'          => 'core/class-hide-admin-bar.php',
			),
			'WPMastertoolkit_Last_Login_Column' => array(
				'original_name' =>'Last Login Column',
				'group'         => 'administration',
				'pro'           => false,
				'path'          => 'core/class-last-login-column.php',
			),
			'WPMastertoolkit_Svg_Upload' => array(
				'original_name' =>'SVG Upload',
				'group'         => 'content-media',
				'pro'           => false,
				'path'          => 'core/class-svg-upload.php',
			),
			'WPMastertoolkit_External_Links_New_Tabs' => array(
				'original_name' =>'Open All External Links in New Tab',
				'group'         => 'content-media',
				'pro'           => false,
				'path'          => 'core/class-external-links-new-tab.php',
			),
			'WPMastertoolkit_Custom_Link_Menu_New_Tab' => array(
				'original_name' =>'Allow Menu Custom Links to Open in New Tab',
				'group'         => 'content-media',
				'pro'           => false,
				'path'          => 'core/class-custom-link-menu-new-tab.php',
			),
			'WPMastertoolkit_Publish_Missed_Schedule_Posts' => array(
				'original_name' =>'Auto-Publish Posts with Missed Schedule',
				'group'         => 'content-media',
				'pro'           => false,
				'path'          => 'core/class-publish-missed-schedule-posts.php',
			),
			'WPMastertoolkit_Code_Snippets' => array(
				'original_name' =>'Code Snippets',
				'group'         => 'custom-code',
				'pro'           => false,
				'path'          => 'core/class-code-snippets.php',
			),
			'WPMastertoolkit_Disable_Comments' => array(
				'original_name' =>'Disable Comments',
				'group'         => 'disable-features',
				'pro'           => true,
				'path'          => 'pro/class-disable-comments.php',
			),
			'WPMastertoolkit_Disable_Feeds' => array(
				'original_name' =>'Disable Feeds',
				'group'         => 'disable-features',
				'pro'           => false,
				'path'          => 'core/class-disable-feeds.php',
			),
			'WPMastertoolkit_Disable_Gutenberg' => array(
				'original_name' =>'Disable Gutenberg',
				'group'         => 'disable-features',
				'pro'           => false,
				'path'          => 'core/class-disable-gutenberg.php',
			),
			'WPMastertoolkit_Disable_WP_Mail' => array(
				'original_name' =>'Disable wp_mail',
				'group'         => 'disable-features',
				'pro'           => false,
				'path'          => 'core/class-disable-wp-mail.php',
			),
			'WPMastertoolkit_Hide_WordPress_Version' => array(
				'original_name' =>'Hide WordPress Version',
				'group'         => 'security',
				'pro'           => false,
				'path'          => 'core/class-hide-wordpress-version.php',
			),
			'WPMastertoolkit_Disallow_WP_File_Edit' => array(
				'original_name' =>'Disallow WP File Edit',
				'group'         => 'security',
				'pro'           => false,
				'path'          => 'core/class-disallow-wp-file-edit.php',
			),
			'WPMastertoolkit_Disable_Xmlrpc' => array(
				'original_name' =>'Disable XML-RPC',
				'group'         => 'security',
				'pro'           => false,
				'path'          => 'core/class-disable-xmlrpc.php',
			),
			'WPMastertoolkit_Disallow_Register_User' => array(
				'original_name' =>'Disallow register user',
				'group'         => 'security',
				'pro'           => false,
				'path'          => 'core/class-disallow-register-user.php',
			),
			'WPMastertoolkit_Lock_Site_URL' => array(
				'original_name' =>'Lock Site URL',
				'group'         => 'security',
				'pro'           => false,
				'path'          => 'core/class-lock-site-url.php',
			),
			'WPMastertoolkit_Lock_Admin_Email' => array(
				'original_name' =>'Lock Admin Email',
				'group'         => 'security',
				'pro'           => false,
				'path'          => 'core/class-lock-admin-email.php',
			),
			'WPMastertoolkit_Blacklisted_Usernames' => array(
				'original_name' =>'Blacklisted Usernames',
				'group'         => 'security',
				'pro'           => false,
				'path'          => 'core/class-blacklisted-usernames.php',
			),
			'WPMastertoolkit_Force_Strong_Password' => array(
				'original_name' =>'Force Strong Password',
				'group'         => 'security',
				'pro'           => false,
				'path'          => 'core/class-force-strong-password.php',
			),
			'WPMastertoolkit_Move_Login_URL' => array(
				'original_name' =>'Move Login URL',
				'group'         => 'security',
				'pro'           => false,
				'path'          => 'core/class-move-login-url.php',
			),
			'WPMastertoolkit_Two_Factor_Authentication' => array(
				'original_name' =>"Two Factor Authentication",
				'group'         => 'security',
				'pro'           => true,
				'path'          => 'pro/class-two-factor-authentication.php',
			),
			'WPMastertoolkit_Hide_Login_Errors' => array(
				'original_name' =>'Hide Login Errors',
				'group'         => 'security',
				'pro'           => false,
				'path'          => 'core/class-hide-login-errors.php',
			),
			'WPMastertoolkit_Disallow_Theme_Upload' => array(
				'original_name' =>'Disallow Theme Upload',
				'group'         => 'security',
				'pro'           => false,
				'path'          => 'core/class-disallow-theme-upload.php',
			),
			'WPMastertoolkit_Disallow_Plugin_Upload' => array(
				'original_name' =>'Disallow Plugin Upload',
				'group'         => 'security',
				'pro'           => false,
				'path'          => 'core/class-disallow-plugin-upload.php',
			),
			'WPMastertoolkit_Disallow_Access_WP_Sensible_Files' => array(
				'original_name' =>'Disallow Access WP Sensible Files',
				'group'         => 'security',
				'pro'           => true,
				'path'          => 'pro/class-disallow-access-wp-sensible-files.php',
			),
			'WPMastertoolkit_Disallow_Countries_IP' => array(
				'original_name' =>'Disallow Countries IP',
				'group'         => 'security',
				'pro'           => true,
				'path'          => 'pro/class-disallow-countries-ip.php',
			),
			'WPMastertoolkit_Disallow_Dir_Listing' => array(
				'original_name' =>'Disallow Dir Listing',
				'group'         => 'security',
				'pro'           => false,
				'path'          => 'core/class-disallow-dir-listing.php',
			),
			'WPMastertoolkit_Manage_Admin_Emails_Notifications' => array(
				'original_name' =>'Manage Admin Emails Notifications',
				'group'         => 'security',
				'pro'           => true,
				'path'          => 'pro/class-manage-admin-emails-notifications.php',
			),
			'WPMastertoolkit_Disable_WP_Sitemap' => array(
				'original_name' =>'Disable WP Sitemap',
				'group'         => 'other-features',
				'pro'           => false,
				'path'          => 'core/class-disable-wp-sitemap.php',
			),
			'WPMastertoolkit_Force_Send_All_Email_To' => array(
				'original_name' =>"Force Send All Email To",
				'group'         => 'other-features',
				'pro'           => true,
				'path'          => 'pro/class-force-send-all-email-to.php',
			),
			'WPMastertoolkit_Plugin_Download' => array(
				'original_name' =>"Plugin Download",
				'group'         => 'other-features',
				'pro'           => true,
				'path'          => 'pro/class-plugin-download.php',
			),
			'WPMastertoolkit_Disallow_Malicious_File_Access_In_Upload' => array(
				'original_name' => "Disallow Malicious File Access in upload",
				'group'         => 'security',
				'pro'           => false,
				'path'          => 'core/class-disallow-malicious-file-access-in-upload.php',
			),
			'WPMastertoolkit_Disable_Cart_Fragments_Scripts' => array(
				'original_name' => "Disable cart fragments scripts",
				'group'         => 'woocommerce',
				'pro'           => false,
				'path'          => 'core/class-disable-cart-fragments-scripts.php',
			),
			'WPMastertoolkit_Revisions_Control' => array(
				'original_name' => "Revisions Control",
				'group'         => 'content-media',
				'pro'           => false,
				'path'          => 'core/class-revisions-control.php',
			),
			'WPMastertoolkit_Disable_Emoji_Support' => array(
				'original_name' => "Disable emoji support",
				'group'         => 'disable-features',
				'pro'           => false,
				'path'          => 'core/class-disable-emoji-support.php',
			),
			'WPMastertoolkit_Disable_Dashicons_CSS_JS_files' => array(
				'original_name' => "Disable dashicons CSS and JS files",
				'group'         => 'disable-features',
				'pro'           => false,
				'path'          => 'core/class-disable-dashicons-css-js-files.php',
			),
			'WPMastertoolkit_Disable_Shortlink_Tag' => array(
				'original_name' => "Disable WordPress shortlink <link> tag",
				'group'         => 'disable-features',
				'pro'           => false,
				'path'          => 'core/class-disable-shortlink-tag.php',
			),
			'WPMastertoolkit_Disable_Really_Simple_Discovery_Tag' => array(
				'original_name' => "Disable Really Simple Discovery (RSD) <link> tag",
				'group'         => 'disable-features',
				'pro'           => false,
				'path'          => 'core/class-disable-really-simple-discovery-tag.php',
			),
			'WPMastertoolkit_Disable_Windows_Live_Writer_Tag' => array(
				'original_name' => "Disable Windows Live Writer (WLW) manifest <link> tag",
				'group'         => 'disable-features',
				'pro'           => false,
				'path'          => 'core/class-disable-windows-live-writer-tag.php',
			),
			'WPMastertoolkit_Disable_Block_Based_Widgets_Settings_Screen' => array(
				'original_name' => "Disable Block-Based Widgets Settings Screen",
				'group'         => 'disable-features',
				'pro'           => false,
				'path'          => 'core/class-disable-block-widgets-settings-screen.php',
			),
			'WPMastertoolkit_Custom_Body_Class' => array(
				'original_name' => "Custom Body Class",
				'group'         => 'custom-code',
				'pro'           => false,
				'path'          => 'core/class-custom-body-class.php',
			),
			'WPMastertoolkit_Redirect_After_Logout' => array(
				'original_name' => "Redirect After Logout",
				'group'         => 'administration',
				'pro'           => false,
				'path'          => 'core/class-redirect-after-logout.php',
			),
			'WPMastertoolkit_Redirect_After_Login' => array(
				'original_name' => "Redirect After Login",
				'group'         => 'administration',
				'pro'           => false,
				'path'          => 'core/class-redirect-after-login.php',
			),
			'WPMastertoolkit_Wider_Admin_Menu' => array(
				'original_name' => "Wider Admin Menu",
				'group'         => 'administration',
				'pro'           => false,
				'path'          => 'core/class-wider-admin-menu.php',
			),
			'WPMastertoolkit_Disable_Dashboard_Widgets' => array(
				'original_name' => "Disable Dashboard Widgets",
				'group'         => 'administration',
				'pro'           => false,
				'path'          => 'core/class-disable-dashboard-widgets.php',
			),
			'WPMastertoolkit_Disallow_Bad_Requests' => array(
				'original_name' => "Disallow Bad Requests",
				'group'         => 'security',
				'pro'           => false,
				'path'          => 'core/class-disallow-bad-requests.php',
			),
			'WPMastertoolkit_Auto_Regenerate_Salt_Keys' => array(
				'original_name' => "Auto Regenerate Salt Keys",
				'group'         => 'security',
				'pro'           => false,
				'path'          => 'core/class-auto-regenerate-salt-keys.php',
			),
			'WPMastertoolkit_Hide_PHP_Versions' => array(
				'original_name' => "Hide PHP Versions",
				'group'         => 'security',
				'pro'           => false,
				'path'          => 'core/class-hide-php-versions.php',
			),
			'WPMastertoolkit_Nav_Menu_Visibility' => array(
				'original_name' => "Nav Menu Visibility",
				'group'         => 'content-media',
				'pro'           => false,
				'path'          => 'core/class-nav-menu-visibility.php',
			),
			'WPMastertoolkit_Export_Users' => array(
				'original_name' => "Export Users",
				'group'         => 'other-features',
				'pro'           => false,
				'path'          => 'core/class-export-users.php',
			),
			'WPMastertoolkit_Clean_Profiles' => array(
				'original_name' => "Clean Profiles",
				'group'         => 'other-features',
				'pro'           => false,
				'path'          => 'core/class-clean-profiles.php',
			),
			'WPMastertoolkit_Quick_Add_Post' => array(
				'original_name' => "Quick Add Post",
				'group'         => 'content-media',
				'pro'           => false,
				'path'          => 'core/class-quick-add-post.php',
			),
			'WPMastertoolkit_Export_Posts_Pages' => array(
				'original_name' => "Export Posts & Pages",
				'group'         => 'content-media',
				'pro'           => false,
				'path'          => 'core/class-export-posts-pages.php',
			),
			'WPMastertoolkit_Duplicate_Menu' => array(
				'original_name' => "Duplicate Menu",
				'group'         => 'other-features',
				'pro'           => false,
				'path'          => 'core/class-duplicate-menu.php',
			),
			'WPMastertoolkit_Child_Theme_Generator' => array(
				'original_name' => "Child theme generator",
				'group'         => 'other-features',
				'pro'           => false,
				'path'          => 'core/class-child-theme-generator.php',
			),
			'WPMastertoolkit_Redirect_404_Home' => array(
				'original_name' => "Redirect 404 to Homepage",
				'group'         => 'content-media',
				'pro'           => false,
				'path'          => 'core/class-redirect-404-home.php',
			),
			'WPMastertoolkit_Maintenance_Mode' => array(
				'original_name' => "Maintenance Mode",
				'group'         => 'administration',
				'pro'           => false,
				'path'          => 'core/class-maintenance-mode.php',
			),
			'WPMastertoolkit_Password_Protection' => array(
				'original_name' => "Password Protection",
				'group'         => 'administration',
				'pro'           => false,
				'path'          => 'core/class-password-protection.php',
			),
			'WPMastertoolkit_Content_Duplication' => array(
				'original_name' => "Content Duplication",
				'group'         => 'content-media',
				'pro'           => false,
				'path'          => 'core/class-content-duplication.php',
			),
			'WPMastertoolkit_Post_Per_Page' => array(
				'original_name' => "Post Per Page",
				'group'         => 'content-media',
				'pro'           => false,
				'path'          => 'core/class-post-per-page.php',
			),
			'WPMastertoolkit_Content_Order' => array(
				'original_name' => "Content Order",
				'group'         => 'content-media',
				'pro'           => false,
				'path'          => 'core/class-content-order.php',
			),
			'WPMastertoolkit_External_Permalinks' => array(
				'original_name' => "External Permalinks",
				'group'         => 'content-media',
				'pro'           => false,
				'path'          => 'core/class-external-permalinks.php',
			),
			'WPMastertoolkit_Meta_Debugger' => array(
				'original_name' => "Meta Debugger",
				'group'         => 'administration',
				'pro'           => false,
				'path'          => 'core/class-meta-debugger.php',
			),
			'WPMastertoolkit_Clean_Up_Admin_Bar' => array(
				'original_name' => "Clean Up Admin Bar",
				'group'         => 'administration',
				'pro'           => false,
				'path'          => 'core/class-clean-up-admin-bar.php',
			),
			'WPMastertoolkit_Enhance_List_Tables' => array(
				'original_name' => "Enhance List Tables",
				'group'         => 'administration',
				'pro'           => false,
				'path'          => 'core/class-enhance-list-tables.php',
			),
			'WPMastertoolkit_Login_Logout_Menu' => array(
				'original_name' => "Log In/Out Menu",
				'group'         => 'administration',
				'pro'           => false,
				'path'          => 'core/class-login-logout-menu.php',
			),
			'WPMastertoolkit_Custom_Admin_CSS' => array(
				'original_name' => "Custom Admin CSS",
				'group'         => 'custom-code',
				'pro'           => false,
				'path'          => 'core/class-custom-admin-css.php',
			),
			'WPMastertoolkit_Custom_Frontend_CSS' => array(
				'original_name' => "Custom Frontend CSS",
				'group'         => 'custom-code',
				'pro'           => false,
				'path'          => 'core/class-custom-frontend-css.php',
			),
			'WPMastertoolkit_Insert_Head_Body_Footer_Code' => array(
				'original_name' => "Insert <head>, <body> and <footer> Code",
				'group'         => 'custom-code',
				'pro'           => false,
				'path'          => 'core/class-insert-head-body-footer-code.php',
			),
			'WPMastertoolkit_Manage_Ads_Txt' => array(
				'original_name' => "Manage ads.txt and app-ads.txt",
				'group'         => 'custom-code',
				'pro'           => false,
				'path'          => 'core/class-manage-ads-txt.php',
			),
			'WPMastertoolkit_Manage_Robots_Txt' => array(
				'original_name' => "Manage robots.txt",
				'group'         => 'custom-code',
				'pro'           => false,
				'path'          => 'core/class-manage-robots-txt.php',
			),
			'WPMastertoolkit_Disable_REST_API' => array(
				'original_name' => "Disable REST API",
				'group'         => 'disable-features',
				'pro'           => false,
				'path'          => 'core/class-disable-rest-api.php',
			),
			'WPMastertoolkit_Disable_All_Updates' => array(
				'original_name' => "Disable All Updates",
				'group'         => 'disable-features',
				'pro'           => false,
				'path'          => 'core/class-disable-all-updates.php',
			),
			'WPMastertoolkit_Obfuscate_Author_Slugs' => array(
				'original_name' => "Obfuscate Author Slugs",
				'group'         => 'security',
				'pro'           => false,
				'path'          => 'core/class-obfuscate-author-slugs.php',
			),
			'WPMastertoolkit_Obfuscate_Email_Address' => array(
				'original_name' => "Obfuscate Email Addresses",
				'group'         => 'security',
				'pro'           => false,
				'path'          => 'core/class-obfuscate-email-address.php',
			),
			'WPMastertoolkit_Image_Upload_Control' => array(
				'original_name' => "Image Upload Control",
				'group'         => 'content-media',
				'pro'           => false,
				'path'          => 'core/class-image-upload-control.php',
			),
			'WPMastertoolkit_Heartbeat_Control' => array(
				'original_name' => "Heartbeat Control",
				'group'         => 'content-media',
				'pro'           => false,
				'path'          => 'core/class-heartbeat-control.php',
			),
			'WPMastertoolkit_Limit_Login_Attempts' => array(
				'original_name' => "Limit Login Attempts",
				'group'         => 'security',
				'pro'           => false,
				'path'          => 'core/class-limit-login-attempts.php',
			),
			'WPMastertoolkit_Block_User_Registration_From_Disposable_Email' => array(
				'original_name' => "Block User Registration from Disposable Email",
				'group'         => 'security',
				'pro'           => false,
				'path'          => 'core/class-block-user-registration-from-disposable-email.php',
			),
			'WPMastertoolkit_Ban_Emails' => array(
				'original_name' => "Ban Emails",
				'group'         => 'security',
				'pro'           => false,
				'path'          => 'core/class-ban-emails.php',
			),
			'WPMastertoolkit_SMTP_mailer' => array(
				'original_name' => "SMTP Mailer",
				'group'         => 'other-features',
				'pro'           => false,
				'path'          => 'core/class-smtp-mailer.php',
			),
			'WPMastertoolkit_Protect_Website_Headers' => array(
				'original_name' => "Protect Website Headers",
				'group'         => 'security',
				'pro'           => false,
				'path'          => 'core/class-protect-website-headers.php',
			),
			'WPMasterToolKit_Prevent_User_Enumeration' => array(
				'original_name' => "Prevent User Enumeration",
				'group'         => 'security',
				'pro'           => false,
				'path'          => 'core/class-prevent-user-enumeration.php',
			),
			'WPMastertoolkit_File_Manager' => array(
				'original_name' => "File Manager",
				'group'         => 'other-features',
				'pro'           => false,
				'path'          => 'core/class-file-manager.php',
			),
			'WPMastertoolkit_Disable_jQuery_Migrate' => array(
				'original_name' => "Disable jQuery Migrate",
				'group'         => 'disable-features',
				'pro'           => false,
				'path'          => 'core/class-disable-jquery-migrate.php',
			),
			'WPMastertoolkit_Plugin_Theme_Rollback' => array(
				'original_name' => "Plugin & Theme Rollback",
				'group'         => 'administration',
				'pro'           => false,
				'path'          => 'core/class-plugin-theme-rollback.php',
			),
			'WPMastertoolkit_Multiple_User_Roles' => array(
				'original_name' => "Multiple User Roles",
				'group'         => 'other-features',
				'pro'           => false,
				'path'          => 'core/class-multiple-user-roles.php',
			),
			'WPMastertoolkit_Adminer' => array(
				'original_name' => "Adminer",
				'group'         => 'other-features',
				'pro'           => false,
				'path'          => 'core/class-adminer.php',
			),
			'WPMastertoolkit_Apple_Touch_Icon' => array(
				'original_name' => "Apple Touch Icon",
				'group'         => 'other-features',
				'pro'           => false,
				'path'          => 'core/class-apple-touch-icon.php',
			),
			'WPMastertoolkit_Local_Avatars' => array(
				'original_name' => "Local avatars",
				'group'         => 'other-features',
				'pro'           => false,
				'path'          => 'core/class-local-avatars.php',
			),
			'WPMastertoolkit_Auto_Clean_Actionscheduler_Actions' => array(
				'original_name' => "Auto clean actionscheduler_actions",
				'group'         => 'woocommerce',
				'pro'           => true,
				'path'          => 'pro/class-auto-clean-actionscheduler-actions.php',
			),
			'WPMastertoolkit_Cron_Manager' => array(
				'original_name' => "CRON Manager",
				'group'         => 'other-features',
				'pro'           => true,
				'path'          => 'pro/class-cron-manager.php',
			),
			'WPMastertoolkit_Hook_Filter_Debugger' => array(
				'original_name' => "Hook And Filter Debugger",
				'group'         => 'other-features',
				'pro'           => true,
				'path'          => 'pro/class-hook-filter-debugger.php',
			),
			'WPMastertoolkit_Change_Database_Prefix' => array(
				'original_name' => "Change Database Prefix",
				'group'         => 'other-features',
				'pro'           => true,
				'path'          => 'pro/class-change-database-prefix.php',
			),
			'WPMastertoolkit_User_Switching' => array(
				'original_name' => "User Switching",
				'group'         => 'other-features',
				'pro'           => true,
				'path'          => 'pro/class-user-switching.php',
			),
			'WPMastertoolkit_Media_Encoder' => array(
				'original_name' => "Media Encoder",
				'group'         => 'content-media',
				'pro'           => false,
				'path'          => 'core/class-media-encoder.php',
			),
			'WPMastertoolkit_Media_Cleaner' => array(
				'original_name' => "Media Cleaner",
				'group'         => 'content-media',
				'pro'           => false,
				'path'          => 'core/class-media-cleaner.php',
			),
			'WPMastertoolkit_Paste_Image_In_Media' => array(
				'original_name' => "Paste Image In Media",
				'group'         => 'content-media',
				'pro'           => true,
				'path'          => 'pro/class-paste-image-in-media.php',
			),
			'WPMastertoolkit_Add_Essentials_Shortcodes' => array(
				'original_name' => "Add Essentials Shortcodes",
				'group'         => 'content-media',
				'pro'           => true,
				'path'          => 'pro/class-add-essentials-shortcodes.php',
			),
			'WPMastertoolkit_410_manager' => array(
				'original_name' => "410 Manager",
				'group'         => 'content-media',
				'pro'           => true,
				'path'          => 'pro/class-410-manager.php',
			),
			'WPMasterToolKit_No_Plugin_Management' => array(
				'original_name' => "No Plugin Activation / Deactivation / Deletion",
				'group'         => 'security',
				'pro'           => true,
				'path'          => 'pro/class-no-plugin-management.php',
			),
			'WPMastertoolkit_Link_Shortener' => array(
				'original_name' => "Link Shortener",
				'group'         => 'other-features',
				'pro'           => true,
				'path'          => 'pro/class-link-shortener.php',
			),
		);

		return $modules;
	}

	/**
	 * Return modules values with translation.
	 */
	public static function modules_translation_values() {

		$modules = array(
			'WPMastertoolkit_Hide_Admin_Notices' => array(
				'name' => esc_html_x('Hide Admin Notices', "Module name", 'wpmastertoolkit'),
				'desc' => esc_html_x('Improve user experience on admin pages by gathering all notifications in a popup (opened by clicking on the bell at the top right).', "Module description", 'wpmastertoolkit'),
			),
			'WPMastertoolkit_Update_Logs' => array(
				'name' => esc_html_x('Updates Logs', "Module name", 'wpmastertoolkit'),
				'desc' => esc_html_x('Track and record the most recent login activity of site users, then showcase the date and time in the users list table', "Module description", 'wpmastertoolkit'),
			),
			'WPMastertoolkit_Hide_Admin_Bar' => array(
				'name' => esc_html_x('Hide Admin Bar', "Module name", 'wpmastertoolkit'),
				'desc' => esc_html_x('Hide the admin bar on the front end of your website for either specific user roles or all users.', "Module description", 'wpmastertoolkit'),
			),
			'WPMastertoolkit_Last_Login_Column' => array(
				'name' => esc_html_x('Last Login Column', "Module name", 'wpmastertoolkit'),
				'desc' => esc_html_x('Track and record the most recent login activity of site users, then showcase the date and time in the users list table', "Module description", 'wpmastertoolkit'),
			),
			'WPMastertoolkit_Svg_Upload' => array(
				'name' => esc_html_x('SVG Upload', "Module name", 'wpmastertoolkit'),
				'desc' => esc_html_x('Enhance media library functionality to support the seamless uploading of SVG files.', "Module description", 'wpmastertoolkit'),
			),
			'WPMastertoolkit_External_Links_New_Tabs' => array(
				'name' => esc_html_x('Open All External Links in New Tab', "Module name", 'wpmastertoolkit'),
				'desc' => esc_html_x('Ensure that all external links within post content open in a new browser tab by implementing the "target="_blank"" attribute. Additionally, enhance security and SEO advantages by including the "rel="noopener noreferrer nofollow"" attribute.', "Module description", 'wpmastertoolkit'),
			),
			'WPMastertoolkit_Custom_Link_Menu_New_Tab' => array(
				'name' => esc_html_x('Allow Menu Custom Links to Open in New Tab', "Module name", 'wpmastertoolkit'),
				'desc' => esc_html_x('You can enable custom link menu items to open in a separate browser tab with just a simple checkbox. Additionally, to reinforce security and improve SEO performance, we\'ve implemented the "rel="noopener noreferrer nofollow"" attribute for these links.', "Module description", 'wpmastertoolkit'),
			),
			'WPMastertoolkit_Publish_Missed_Schedule_Posts' => array(
				'name' => esc_html_x('Auto-Publish Posts with Missed Schedule', "Module name", 'wpmastertoolkit'),
				'desc' => esc_html_x('Automatically initiate the publication of scheduled posts marked with "missed schedule" upon each visit to the website, across all post types.', "Module description", 'wpmastertoolkit'),
			),
			'WPMastertoolkit_Code_Snippets' => array(
				'name' => esc_html_x('Code Snippets', "Module name", 'wpmastertoolkit'),
				'desc' => esc_html_x("Add custom code snippets to your website without the need to edit the theme's functions.php file. This feature is especially useful for adding custom CSS, JavaScript, and PHP code to your website. For disable all snippets, add this line to your wp-config.php: define('WPMASTERTOOLKIT_SNIPPETS_SAFE_MODE', true);", "Module description", 'wpmastertoolkit'),
			),
			'WPMastertoolkit_Disable_Comments' => array(
				'name' => esc_html_x('Disable Comments', "Module name", 'wpmastertoolkit'),
				'desc' => esc_html_x('Manage the visibility of comments on your public posts by selectively disabling them for specific post types or across all posts. Once comments are disabled, any existing comments will seamlessly disappear from the front-end.', "Module description", 'wpmastertoolkit'),
			),
			'WPMastertoolkit_Disable_Feeds' => array(
				'name' => esc_html_x('Disable Feeds', "Module name", 'wpmastertoolkit'),
				'desc' => esc_html_x('Completely deactivate RSS, Atom, and RDF feeds across your website. This entails disabling feeds for various content elements, such as posts, categories, tags, comments, authors, and search. Additionally, it erases any remaining references to feed URLs from the <head> section of your web pages.', "Module description", 'wpmastertoolkit'),
			),
			'WPMastertoolkit_Disable_Gutenberg' => array(
				'name' => esc_html_x('Disable Gutenberg', "Module name", 'wpmastertoolkit'),
				'desc' => esc_html_x('Deactivate the Gutenberg block editor selectively, allowing you to control its usage for specific or all relevant post types.', "Module description", 'wpmastertoolkit'),
			),
			'WPMastertoolkit_Disable_WP_Mail' => array(
				'name' => esc_html_x('Disable wp_mail', "Module name", 'wpmastertoolkit'),
				'desc' => esc_html_x('Disable the wp_mail function, which is used by WordPress to send emails. This feature is useful for websites that do not send emails, as it prevents the wp_mail function from loading and consuming resources.', "Module description", 'wpmastertoolkit'),
			),
			'WPMastertoolkit_Hide_WordPress_Version' => array(
				'name' => esc_html_x('Hide WordPress Version', "Module name", 'wpmastertoolkit'),
				'desc' => esc_html_x("Hide the WordPress version from the source code.", "Module description", 'wpmastertoolkit'),
			),
			'WPMastertoolkit_Disallow_WP_File_Edit' => array(
				'name' => esc_html_x('Disallow WP File Edit', "Module name", 'wpmastertoolkit'),
				'desc' => esc_html_x("Prevent the modification of your website's core files through the WordPress admin panel.", "Module description", 'wpmastertoolkit'),
			),
			'WPMastertoolkit_Disable_Xmlrpc' => array(
				'name' => esc_html_x('Disable XML-RPC', "Module name", 'wpmastertoolkit'),
				'desc' => esc_html_x('Enhance your website\'s security by fortifying it against brute force, (DoS) and (DDoS) attacks through advanced XML-RPC protection. In addition, our solution proactively disables trackbacks and pingbacks, bolstering your site\'s defense mechanisms.', "Module description", 'wpmastertoolkit'),
			),
			'WPMastertoolkit_Disallow_Register_User' => array(
				'name' => esc_html_x('Disallow register user', "Module name", 'wpmastertoolkit'),
				'desc' => esc_html_x( "Prevent the creation of new user accounts on your website with the native WordPress registration form.", "Module description", 'wpmastertoolkit' ),
			),
			'WPMastertoolkit_Lock_Site_URL' => array(
				'name' => esc_html_x('Lock Site URL', "Module name", 'wpmastertoolkit'),
				'desc' => esc_html_x( "Prevent the modification of the site URL on your website.", "Module description", 'wpmastertoolkit' ),
			),
			'WPMastertoolkit_Lock_Admin_Email' => array(
				'name' => esc_html_x('Lock Admin Email', "Module name", 'wpmastertoolkit'),
				'desc' => esc_html_x( "Prevent the modification of the admin email address on your website.", "Module description", 'wpmastertoolkit' ),
			),
			'WPMastertoolkit_Blacklisted_Usernames' => array(
				'name' => esc_html_x('Blacklisted Usernames', "Module name", 'wpmastertoolkit'),
				'desc' => esc_html_x('Prevent the creation of new user accounts with predifined blacklisted usernames. Blacklist usernames that are too common.', "Module description", 'wpmastertoolkit'),
			),
			'WPMastertoolkit_Force_Strong_Password' => array(
				'name' => esc_html_x('Force Strong Password', "Module name", 'wpmastertoolkit'),
				'desc' => esc_html_x('Enforce the use of strong passwords for all users on your website. This feature is especially useful for websites with multiple users, as it ensures that all users have a strong password that is difficult to guess or crack.', "Module description", 'wpmastertoolkit'),
			),
			'WPMastertoolkit_Move_Login_URL' => array(
				'name' => esc_html_x('Move Login URL', "Module name", 'wpmastertoolkit'),
				'desc' => esc_html_x('Change the default login URL to a custom URL of your choice.', "Module description", 'wpmastertoolkit'),
			),
			'WPMastertoolkit_Two_Factor_Authentication' => array(
				'name' => esc_html_x("Two Factor Authentication", "Module name", 'wpmastertoolkit'),
				'desc' => esc_html_x("Add an extra layer of security to your website by enabling two-factor authentication for all users.", "Module description", 'wpmastertoolkit'),
			),
			'WPMastertoolkit_Hide_Login_Errors' => array(
				'name' => esc_html_x('Hide Login Errors', "Module name", 'wpmastertoolkit'),
				'desc' => esc_html_x("Hide the default WordPress login errors that appear when an incorrect username or password is entered.", "Module description", 'wpmastertoolkit'),
			),
			'WPMastertoolkit_Disallow_Theme_Upload' => array(
				'name' => esc_html_x('Disallow Theme Upload', "Module name", 'wpmastertoolkit'),
				'desc' => esc_html_x("Disable zip file uploads for themes, which are used to install themes on your website.", "Module description", 'wpmastertoolkit'),
			),
			'WPMastertoolkit_Disallow_Plugin_Upload' => array(
				'name' => esc_html_x('Disallow Plugin Upload', "Module name", 'wpmastertoolkit'),
				'desc' => esc_html_x("Disable zip file uploads for plugins, which are used to install plugins on your website.", "Module description", 'wpmastertoolkit'),
			),
			'WPMastertoolkit_Disallow_Access_WP_Sensible_Files' => array(
				'name' => esc_html_x('Disallow Access WP Sensible Files', "Module name", 'wpmastertoolkit'),
				'desc' => esc_html_x("Delete the wp-config-sample.php, block access to readme.html, license.txt", "Module description", 'wpmastertoolkit'),
			),
			'WPMastertoolkit_Disallow_Countries_IP' => array(
				'name' => esc_html_x('Disallow Countries IP', "Module name", 'wpmastertoolkit'),
				'desc' => esc_html_x("Include/Exclude countries IP", "Module description", 'wpmastertoolkit'),
			),
			'WPMastertoolkit_Disallow_Dir_Listing' => array(
				'name' => esc_html_x('Disallow Dir Listing', "Module name", 'wpmastertoolkit'),
				'desc' => esc_html_x("Disable the listing of the directories.", "Module description", 'wpmastertoolkit'),
			),
			'WPMastertoolkit_Manage_Admin_Emails_Notifications' => array(
				'name' => esc_html_x('Manage Admin Emails Notifications', "Module name", 'wpmastertoolkit'),
				'desc' => esc_html_x("Disable admin emails notifications.", "Module description", 'wpmastertoolkit'),
			),
			'WPMastertoolkit_Disable_WP_Sitemap' => array(
				'name' => esc_html_x('Disable WP Sitemap', "Module name", 'wpmastertoolkit'),
				'desc' => esc_html_x('Disable the default WordPress sitemap feature, which was introduced in WordPress 5.5.', "Module description", 'wpmastertoolkit'),
			),
			'WPMastertoolkit_Force_Send_All_Email_To' => array(
				'name' => esc_html_x("Force Send All Email To", "Module name", 'wpmastertoolkit'),
				'desc' => esc_html_x('Force all emails sent from your website to be sent to a specific email address. This feature is useful for testing email functionality on your website, as it ensures that all emails are sent to a single email address.', "Module description", 'wpmastertoolkit'),
			),
			'WPMastertoolkit_Plugin_Download' => array(
				'name' => esc_html_x("Plugin Download", "Module name", 'wpmastertoolkit'),
				'desc' => esc_html_x("Download plugins from the plugins page in the WordPress admin panel.", "Module description", 'wpmastertoolkit'),
			),
			'WPMastertoolkit_Disallow_Malicious_File_Access_In_Upload' => array(
				'name' => esc_html_x( "Disallow Malicious File Access in upload", "Module name", 'wpmastertoolkit' ),
				'desc' => esc_html_x( "Protect your website from malicious file access in the upload directory.", "Module description", 'wpmastertoolkit' ),
			),
			'WPMastertoolkit_Disable_Cart_Fragments_Scripts' => array(
				'name' => esc_html_x( "Disable cart fragments scripts", "Module name", 'wpmastertoolkit' ),
				'desc' => esc_html_x( "Disable cart fragments scripts on the front-end for public site visitors. This might break the functionality of the cart and checkout pages if they depend on cart fragments.", "Module description", 'wpmastertoolkit' ),
			),
			'WPMastertoolkit_Revisions_Control' => array(
				'name' => esc_html_x( "Revisions Control", "Module name", 'wpmastertoolkit' ),
				'desc' => esc_html_x( "Avoid overloading the database by setting a cap on the number of revisions to save for certain or all types of posts that support revisions.", "Module description", 'wpmastertoolkit' ),
			),
			'WPMastertoolkit_Disable_Emoji_Support' => array(
				'name' => esc_html_x( "Disable emoji support", "Module name", 'wpmastertoolkit' ),
				'desc' => esc_html_x( "Disable emoji support for pages, posts and custom post types on the admin and frontend. The support is primarily useful for older browsers that do not have native support for it. Most modern browsers across different OSes and devices now have native support for it.", "Module description", 'wpmastertoolkit' ),
			),
			'WPMastertoolkit_Disable_Dashicons_CSS_JS_files' => array(
				'name' => esc_html_x( "Disable dashicons CSS and JS files", "Module name", 'wpmastertoolkit' ),
				'desc' => esc_html_x( "Disable loading of Dashicons CSS and JS files on the front-end for public site visitors. This might break the layout or design of custom forms, including custom login forms, if they depend on Dashicons. Make sure to check those forms after disabling.", "Module description", 'wpmastertoolkit' ),
			),
			'WPMastertoolkit_Disable_Shortlink_Tag' => array(
				'name' => esc_html_x( "Disable WordPress shortlink <link> tag", "Module name", 'wpmastertoolkit' ),
				'desc' => esc_html_x( "Disable the default WordPress shortlink <link> tag in <head>. Ignored by search engines and has minimal practical use case. Usually, a dedicated shortlink plugin or service is preferred that allows for nice names in the short links and tracking of clicks when sharing the link on social media.", "Module description", 'wpmastertoolkit' ),
			),
			'WPMastertoolkit_Disable_Really_Simple_Discovery_Tag' => array(
				'name' => esc_html_x( "Disable Really Simple Discovery (RSD) <link> tag", "Module name", 'wpmastertoolkit' ),
				'desc' => esc_html_x( "Disable loading of Dashicons CSS and JS files on the front-end for public site visitors. This might break the layout or design of custom forms, including custom login forms, if they depend on Dashicons. Make sure to check those forms after disabling.", "Module description", 'wpmastertoolkit' ),
			),
			'WPMastertoolkit_Disable_Windows_Live_Writer_Tag' => array(
				'name' => esc_html_x( "Disable Windows Live Writer (WLW) manifest <link> tag", "Module name", 'wpmastertoolkit' ),
				'desc' => esc_html_x( "Disable the Windows Live Writer (WLW) manifest <link> tag in <head>. The WLW app was discontinued in 2017.", "Module description", 'wpmastertoolkit' ),
			),
			'WPMastertoolkit_Disable_Block_Based_Widgets_Settings_Screen' => array(
				'name' => esc_html_x( "Disable Block-Based Widgets Settings Screen", "Module name", 'wpmastertoolkit' ),
				'desc' => esc_html_x( "Disable block-based widgets settings screen. Restores the classic widgets settings screen when using a classic (non-block) theme. This has no effect on block themes.", "Module description", 'wpmastertoolkit' ),
			),
			'WPMastertoolkit_Custom_Body_Class' => array(
				'name' => esc_html_x( "Custom Body Class", "Module name", 'wpmastertoolkit' ),
				'desc' => esc_html_x( "Add custom <body> class(es) on the singular view of some or all public post types.", "Module description", 'wpmastertoolkit' ),
			),
			'WPMastertoolkit_Redirect_After_Logout' => array(
				'name' => esc_html_x( "Redirect After Logout", "Module name", 'wpmastertoolkit' ),
				'desc' => esc_html_x( "Set custom redirect URL for all or some user roles after logout.", "Module description", 'wpmastertoolkit' ),
			),
			'WPMastertoolkit_Redirect_After_Login' => array(
				'name' => esc_html_x( "Redirect After Login", "Module name", 'wpmastertoolkit' ),
				'desc' => esc_html_x( "Set custom redirect URL for all or some user roles after login.", "Module description", 'wpmastertoolkit' ),
			),
			'WPMastertoolkit_Wider_Admin_Menu' => array(
				'name' => esc_html_x( "Wider Admin Menu", "Module name", 'wpmastertoolkit' ),
				'desc' => esc_html_x( "Give the admin menu more room to better accommodate wider items.", "Module description", 'wpmastertoolkit' ),
			),
			'WPMastertoolkit_Disable_Dashboard_Widgets' => array(
				'name' => esc_html_x( "Disable Dashboard Widgets", "Module name", 'wpmastertoolkit' ),
				'desc' => esc_html_x( "Clean up and speed up the dashboard by completely disabling some or all widgets. Disabled widgets won't load any assets nor show up under Screen Options.", "Module description", 'wpmastertoolkit' ),
			),
			'WPMastertoolkit_Disallow_Bad_Requests' => array(
				'name' => esc_html_x( "Disallow Bad Requests", "Module name", 'wpmastertoolkit' ),
				'desc' => esc_html_x( "Protect your site against a wide range of threats. check all incoming traffic and quietly blocks bad requests containing nasty stuff like eval(, base64_, and excessively long request-strings.", "Module description", 'wpmastertoolkit' ),
			),
			'WPMastertoolkit_Auto_Regenerate_Salt_Keys' => array(
				'name' => esc_html_x( "Auto Regenerate Salt Keys", "Module name", 'wpmastertoolkit' ),
				'desc' => esc_html_x( "WordPress salt keys or security keys are codes that help protect important information on your website.", "Module description", 'wpmastertoolkit' ),
			),
			'WPMastertoolkit_Hide_PHP_Versions' => array(
				'name' => esc_html_x( "Hide PHP Versions", "Module name", 'wpmastertoolkit' ),
				'desc' => esc_html_x( "Some servers send a header called X-Powered-By that contains the PHP version used on your site. It may be a useful information for attackers, and should be removed.", "Module description", 'wpmastertoolkit' ),
			),
			'WPMastertoolkit_Nav_Menu_Visibility' => array(
				'name' => esc_html_x( "Nav Menu Visibility", "Module name", 'wpmastertoolkit' ),
				'desc' => esc_html_x( "Control your nav menu by allowing you to apply visibility controls to menu.", "Module description", 'wpmastertoolkit' ),
			),
			'WPMastertoolkit_Export_Users' => array(
				'name' => esc_html_x( "Export Users", "Module name", 'wpmastertoolkit' ),
				'desc' => esc_html_x( "Download your user data to a .csv format.", "Module description", 'wpmastertoolkit' ),
			),
			'WPMastertoolkit_Clean_Profiles' => array(
				'name' => esc_html_x( "Clean Profiles", "Module name", 'wpmastertoolkit' ),
				'desc' => esc_html_x( "Tidy up user profiles by removing sections you do not utilise.", "Module description", 'wpmastertoolkit' ),
			),
			'WPMastertoolkit_Quick_Add_Post' => array(
				'name' => esc_html_x( "Quick Add Post", "Module name", 'wpmastertoolkit' ),
				'desc' => esc_html_x( "A new button to quickly add new posts to speed up your workflow.", "Module description", 'wpmastertoolkit' ),
			),
			'WPMastertoolkit_Export_Posts_Pages' => array(
				'name' => esc_html_x( "Export Posts & Pages", "Module name", 'wpmastertoolkit' ),
				'desc' => esc_html_x( "Download your posts and pages to a .csv format.", "Module description", 'wpmastertoolkit' ),
			),
			'WPMastertoolkit_Duplicate_Menu' => array(
				'name' => esc_html_x( "Duplicate Menu", "Module name", 'wpmastertoolkit' ),
				'desc' => esc_html_x( "Easily duplicate your WordPress Menus", "Module description", 'wpmastertoolkit' ),
			),
			'WPMastertoolkit_Child_Theme_Generator' => array(
				'name' => esc_html_x( "Child theme generator", "Module name", 'wpmastertoolkit' ),
				'desc' => esc_html_x( "A simple tool to generate a child theme on your WordPress. You can disable it after generation.", "Module description", 'wpmastertoolkit' ),
			),
			'WPMastertoolkit_Redirect_404_Home' => array(
				'name' => esc_html_x( "Redirect 404 to Homepage", "Module name", 'wpmastertoolkit' ),
				'desc' => esc_html_x( "Sends visitors to your homepage if they try to access a page that doesn't exist, ensuring they stay on your site.", "Module description", 'wpmastertoolkit' ),
			),
			'WPMastertoolkit_Maintenance_Mode' => array(
				'name' => esc_html_x( "Maintenance Mode", "Module name", 'wpmastertoolkit' ),
				'desc' => esc_html_x( "Show a customizable maintenance page on the frontend while performing a brief maintenance to your site. Logged-in administrators can still view the site as usual.", "Module description", 'wpmastertoolkit' ),
			),
			'WPMastertoolkit_Password_Protection' => array(
				'name' => esc_html_x( "Password Protection", "Module name", 'wpmastertoolkit' ),
				'desc' => esc_html_x( "Password-protect the entire site to hide the content from public view and search engine bots / crawlers. Logged-in administrators can still access the site as usual.", "Module description", 'wpmastertoolkit' ),
			),
			'WPMastertoolkit_Content_Duplication' => array(
				'name' => esc_html_x( "Content Duplication", "Module name", 'wpmastertoolkit' ),
				'desc' => esc_html_x( "Enable one-click duplication of pages, posts and custom posts. The corresponding taxonomy terms and post meta will also be duplicated.", "Module description", 'wpmastertoolkit' ),
			),
			'WPMastertoolkit_Post_Per_Page' => array(
				'name' => esc_html_x( "Post Per Page", "Module name", 'wpmastertoolkit' ),
				'desc' => esc_html_x( "Specifying the number of posts to display per page, for each post type.", "Module description", 'wpmastertoolkit' ),
			),
			'WPMastertoolkit_Content_Order' => array(
				'name' => esc_html_x( "Content Order", "Module name", 'wpmastertoolkit' ),
				'desc' => esc_html_x( "Enable custom ordering of various \"hierarchical\" content types or those supporting \"page attributes\". A new 'Order' sub-menu will appear for enabled content type(s).", "Module description", 'wpmastertoolkit' ),
			),
			'WPMastertoolkit_External_Permalinks' => array(
				'name' => esc_html_x( "External Permalinks", "Module name", 'wpmastertoolkit' ),
				'desc' => esc_html_x( "Enable pages, posts and/or custom post types to have permalinks that point to external URLs. The rel=\"noopener noreferrer nofollow\" attribute will also be added for enhanced security and SEO benefits.", "Module description", 'wpmastertoolkit' ),
			),
			'WPMastertoolkit_Meta_Debugger' => array(
				'name' => esc_html_x( "Meta Debugger", "Module name", 'wpmastertoolkit' ),
				'desc' => esc_html_x( "Display all metadata for a post, user, term, or comment.", "Module description", 'wpmastertoolkit' ),
			),
			'WPMastertoolkit_Clean_Up_Admin_Bar' => array(
				'name' => esc_html_x( "Clean Up Admin Bar", "Module name", 'wpmastertoolkit' ),
				'desc' => esc_html_x( "Remove various elements from the admin bar.", "Module description", 'wpmastertoolkit' ),
			),
			'WPMastertoolkit_Enhance_List_Tables' => array(
				'name' => esc_html_x( "Enhance List Tables", "Module name", 'wpmastertoolkit' ),
				'desc' => esc_html_x( "Improve the usefulness of listing pages for various post types and taxonomies, media, comments and users by adding / removing columns and elements.", "Module description", 'wpmastertoolkit' ),
			),
			'WPMastertoolkit_Login_Logout_Menu' => array(
				'name' => esc_html_x( "Log In/Out Menu", "Module name", 'wpmastertoolkit' ),
				'desc' => esc_html_x( "Enable log in, log out and dynamic log in/out menu item for addition to any menu.", "Module description", 'wpmastertoolkit' ),
			),
			'WPMastertoolkit_Custom_Admin_CSS' => array(
				'name' => esc_html_x( "Custom Admin CSS", "Module name", 'wpmastertoolkit' ),
				'desc' => esc_html_x( "Add custom CSS on all admin pages for all user roles.", "Module description", 'wpmastertoolkit' ),
			),
			'WPMastertoolkit_Custom_Frontend_CSS' => array(
				'name' => esc_html_x( "Custom Frontend CSS", "Module name", 'wpmastertoolkit' ),
				'desc' => esc_html_x( "Add custom CSS on all frontend pages for all user roles.", "Module description", 'wpmastertoolkit' ),
			),
			'WPMastertoolkit_Insert_Head_Body_Footer_Code' => array(
				'name' => esc_html_x( "Insert <head>, <body> and <footer> Code", "Module name", 'wpmastertoolkit' ),
				'desc' => esc_html_x( "Easily insert <meta>, <link>, <script> and <style> tags, Google Analytics, Tag Manager, AdSense, Ads Conversion and Optimize code, Facebook, TikTok and Twitter pixels, etc.", "Module description", 'wpmastertoolkit' ),
			),
			'WPMastertoolkit_Manage_Ads_Txt' => array(
				'name' => esc_html_x( "Manage ads.txt and app-ads.txt", "Module name", 'wpmastertoolkit' ),
				'desc' => esc_html_x( "Easily edit and validate your ads.txt and app-ads.txt content.", "Module description", 'wpmastertoolkit' ),
			),
			'WPMastertoolkit_Manage_Robots_Txt' => array(
				'name' => esc_html_x( "Manage robots.txt", "Module name", 'wpmastertoolkit' ),
				'desc' => esc_html_x( "Easily edit and validate your robots.txt content.", "Module description", 'wpmastertoolkit' ),
			),
			'WPMastertoolkit_Disable_REST_API' => array(
				'name' => esc_html_x( "Disable REST API", "Module name", 'wpmastertoolkit' ),
				'desc' => esc_html_x( "Disable REST API access for non-authenticated users and remove URL traces from <head>, HTTP headers and WP RSD endpoint.", "Module description", 'wpmastertoolkit' ),
			),
			'WPMastertoolkit_Disable_All_Updates' => array(
				'name' => esc_html_x( "Disable All Updates", "Module name", 'wpmastertoolkit' ),
				'desc' => esc_html_x( "Completely disable core, theme and plugin updates and auto-updates. Will also disable update checks, notices and emails.", "Module description", 'wpmastertoolkit' ),
			),
			'WPMastertoolkit_Obfuscate_Author_Slugs' => array(
				'name' => esc_html_x( "Obfuscate Author Slugs", "Module name", 'wpmastertoolkit' ),
				'desc' => esc_html_x( "Obfuscate publicly exposed author page URLs that shows the user slugs / usernames, e.g. sitename.com/author/username1/ into sitename.com/author/a6r5b8ytu9gp34bv/, and output 404 errors for the original URLs. Also obfuscates in /wp-json/wp/v2/users/ REST API endpoint.", "Module description", 'wpmastertoolkit' ),
			),
			'WPMastertoolkit_Obfuscate_Email_Address' => array(
				'name' => esc_html_x( "Obfuscate Email Addresses", "Module name", 'wpmastertoolkit' ),
				'desc' => esc_html_x( "Obfuscate email address to prevent spam bots from harvesting them, but make it readable like a regular email address for human visitors, using shortcode [wpm_obfuscate email=\"example@email.com\" display=\"newline\"]", "Module description", 'wpmastertoolkit' ),
			),
			'WPMastertoolkit_Image_Upload_Control' => array(
				'name' => esc_html_x( "Image Upload Control", "Module name", 'wpmastertoolkit' ),
				'desc' => esc_html_x( "Resize newly uploaded, large images to a smaller dimension and delete originally uploaded files. BMPs and non-transparent PNGs will be converted to JPGs and resized.", "Module description", 'wpmastertoolkit' ),
			),
			'WPMastertoolkit_Heartbeat_Control' => array(
				'name' => esc_html_x( "Heartbeat Control", "Module name", 'wpmastertoolkit' ),
				'desc' => esc_html_x( "Modify the interval of the WordPress heartbeat API or disable it on admin pages, post creation/edit screens and/or the frontend. This will help reduce CPU load on the server.", "Module description", 'wpmastertoolkit' ),
			),
			'WPMastertoolkit_Limit_Login_Attempts' => array(
				'name' => esc_html_x( "Limit Login Attempts", "Module name", 'wpmastertoolkit' ),
				'desc' => esc_html_x( "Prevent brute force attacks by limiting the number of failed login attempts allowed per IP address.", "Module description", 'wpmastertoolkit' ),
			),
			'WPMastertoolkit_Block_User_Registration_From_Disposable_Email' => array(
				'name' => esc_html_x( "Block User Registration from Disposable Email", "Module name", 'wpmastertoolkit' ),
				'desc' => esc_html_x( "Block user registration from disposable email addresses. Disposable email addresses are temporary email addresses that are used to register on websites that require email verification.", "Module description", 'wpmastertoolkit' ),
			),
			'WPMastertoolkit_Ban_Emails' => array(
				'name' => esc_html_x( "Ban Emails", "Module name", 'wpmastertoolkit' ),
				'desc' => esc_html_x( "Ban the chosen emails.", "Module description", 'wpmastertoolkit' ),
			),
			'WPMastertoolkit_SMTP_mailer' => array(
				'name' => esc_html_x( "SMTP Mailer", "Module name", 'wpmastertoolkit' ),
				'desc' => esc_html_x( "Set custom sender name and email. Optionally use external SMTP service to ensure notification and transactional emails from your site are being delivered to inboxes.", "Module description", 'wpmastertoolkit' ),
			),
			'WPMastertoolkit_Protect_Website_Headers' => array(
				'name' => esc_html_x( "Protect Website Headers", "Module name", 'wpmastertoolkit' ),
				'desc' => esc_html_x( "Add security headers quickly to your site to protect it from threats such as phishing attacks, data theft and more.", "Module description", 'wpmastertoolkit' ),
			),
			'WPMasterToolKit_Prevent_User_Enumeration' => array(
				'name' => esc_html_x( "Prevent User Enumeration", "Module name", 'wpmastertoolkit' ),
				'desc' => esc_html_x( "Prevent user enumeration via ?author=X and REST API /users/ endpoints.", "Module description", 'wpmastertoolkit' ),
			),
			'WPMastertoolkit_File_Manager' => array(
				'name' => esc_html_x( "File Manager", "Module name", 'wpmastertoolkit' ),
				'desc' => esc_html_x( "Browser and manage your files efficiently and easily.", "Module description", 'wpmastertoolkit' ),
			),
			'WPMastertoolkit_Disable_jQuery_Migrate' => array(
				'name' => esc_html_x( "Disable jQuery Migrate", "Module name", 'wpmastertoolkit' ),
				'desc' => esc_html_x( "Removes the jQuery Migrate script from the frontend of your site.", "Module description", 'wpmastertoolkit' ),
			),
			'WPMastertoolkit_Plugin_Theme_Rollback' => array(
				'name' => esc_html_x( "Plugin & Theme Rollback", "Module name", 'wpmastertoolkit' ),
				'desc' => esc_html_x( "Revert to previous versions of any theme or plugin from WordPress.org.", "Module description", 'wpmastertoolkit' ),
			),
			'WPMastertoolkit_Multiple_User_Roles' => array(
				'name' => esc_html_x( "Multiple User Roles", "Module name", 'wpmastertoolkit' ),
				'desc' => esc_html_x( "Enable assignment of multiple roles during user account creation and editing. This maybe useful for working with roles not defined in WordPress core, e.g. from e-commerce or LMS plugins.", "Module description", 'wpmastertoolkit' ),
			),
			'WPMastertoolkit_Adminer' => array(
				'name' => esc_html_x( "Adminer", "Module name", 'wpmastertoolkit' ),
				'desc' => esc_html_x( "A full-featured database management tool.", "Module description", 'wpmastertoolkit' ),
			),
			'WPMastertoolkit_Apple_Touch_Icon' => array(
				'name' => esc_html_x( "Apple Touch Icon", "Module name", 'wpmastertoolkit' ),
				'desc' => esc_html_x( "Manage app icon (Apple Touch Icon) individually. Once activated, go to Settings / General for change your Apple Touch icon without impact your favicon.", "Module description", 'wpmastertoolkit' ),
			),
			'WPMastertoolkit_Local_Avatars' => array(
				'name' => esc_html_x( "Local avatars", "Module name", 'wpmastertoolkit' ),
				'desc' => esc_html_x( "Replaces GRAVATAR management with media management.", "Module description", 'wpmastertoolkit' ),
			),
			'WPMastertoolkit_Auto_Clean_Actionscheduler_Actions' => array(
				'name' => esc_html_x( "Auto clean actionscheduler_actions", "Module name", 'wpmastertoolkit' ),
				'desc' => esc_html_x( "Clean actionscheduler_actions database table from actions that have been completed | failed | cancelled.", "Module description", 'wpmastertoolkit' ),
			),
			'WPMastertoolkit_Cron_Manager' => array(
				'name' => esc_html_x( "CRON Manager", "Module name", 'wpmastertoolkit' ),
				'desc' => esc_html_x( "Manage cron events on your website.", "Module description", 'wpmastertoolkit' ),
			),
			'WPMastertoolkit_Hook_Filter_Debugger' => array(
				'name' => esc_html_x( "Hook And Filter Debugger", "Module name", 'wpmastertoolkit' ),
				'desc' => esc_html_x( "Displaying the sequence of action and filter hooks by their origin on a single page.", "Module description", 'wpmastertoolkit' ),
			),
			'WPMastertoolkit_Change_Database_Prefix' => array(
				'name' => esc_html_x( "Change Database Prefix", "Module name", 'wpmastertoolkit' ),
				'desc' => esc_html_x( "Quickly change your WordPress database prefix to save time and enhance security.", "Module description", 'wpmastertoolkit' ),
			),
			'WPMastertoolkit_User_Switching' => array(
				'name' => esc_html_x( "User Switching", "Module name", 'wpmastertoolkit' ),
				'desc' => esc_html_x( "Instant switching between user accounts.", "Module description", 'wpmastertoolkit' ),
			),
			'WPMastertoolkit_Media_Encoder' => array(
				'name' => esc_html_x( "Media Encoder", "Module name", 'wpmastertoolkit' ),
				'desc' => esc_html_x( "Automatically converts images to WebP when they are uploaded to the media library.", "Module description", 'wpmastertoolkit' ),
			),
			'WPMastertoolkit_Media_Cleaner' => array(
				'name' => esc_html_x( "Media Cleaner", "Module name", 'wpmastertoolkit' ),
				'desc' => esc_html_x( "Automatically sanitize uploaded file names by removing special characters, and streamline media management by auto-generating key metadata fields (title, caption, alt text, and description) directly from the cleaned file name.", "Module description", 'wpmastertoolkit' ),
			),
			'WPMastertoolkit_Paste_Image_In_Media' => array(
				'name' => esc_html_x( "Paste Image In Media", "Module name", 'wpmastertoolkit' ),
				'desc' => esc_html_x( "With this feature you can paste directly your picture in WordPress media.", "Module description", 'wpmastertoolkit' ),
			),
			'WPMastertoolkit_Add_Essentials_Shortcodes' => array(
				'name' => esc_html_x( "Add Essentials Shortcodes", "Module name", 'wpmastertoolkit' ),
				'desc' => esc_html_x( "Insert dynamic variables into your titles and content via shortcodes.", "Module description", 'wpmastertoolkit' ),
			),
			'WPMastertoolkit_410_manager' => array(
				'name' => esc_html_x( "410 Manager", "Module name", 'wpmastertoolkit' ),
				'desc' => esc_html_x( "Managing HTTP 410 statuses on your site. The 410 status indicates that the requested resource has been permanently deleted and that this deletion is intentional and final.", "Module description", 'wpmastertoolkit' ),
			),
			'WPMasterToolKit_No_Plugin_Management' => array(
				'name' => esc_html_x( "No Plugin Activation / Deactivation / Deletion", "Module name", 'wpmastertoolkit' ),
				'desc' => esc_html_x( "Prevents plugin activation, deactivation, and deletion for enhanced security.", "Module description", 'wpmastertoolkit' ),
			),
			'WPMastertoolkit_Link_Shortener' => array(
				'name' => esc_html_x( "Link Shortener", "Module name", 'wpmastertoolkit' ),
				'desc' => esc_html_x( "Shorten your links with a custom prefix. You can also track the number of clicks on each link.", "Module description", 'wpmastertoolkit' ),
			),
		);

		return $modules;
	}

	/**
	 * Return modules values
	 */
	public static function modules_values() {

		$normal_values      = self::modules_normal_values();
		$translation_values = self::modules_translation_values();

		$modules = array();
		foreach ( $normal_values as $key => $value ) {

			if ( isset( $translation_values[ $key ] ) ) {
				$translation = $translation_values[ $key ];
				$modules[$key] = array_merge( $value, $translation );
			}
		}

		return $modules;
	}

	/**
	 * Return modules groups
	 */
	public static function modules_groups() {

		$groups = array(
			'all' => array(
				'name'      => esc_html__( 'All modules', 'wpmastertoolkit' ),
				'logo'      => 'asterix.svg',
				'exception' => true,
			),
			'activated' => array(
				'name'      => esc_html__( 'Activated modules', 'wpmastertoolkit' ),
				'logo'      => 'checked.svg',
				'exception' => false,
			),
			'administration' => array(
				'name'      => esc_html__( 'Administration', 'wpmastertoolkit' ),
				'logo'      => 'wordpress.svg',
				'exception' => false,
			),
			'content-media' => array(
				'name'      => esc_html__( 'Contents & Media', 'wpmastertoolkit' ),
				'logo'      => 'content.svg',
				'exception' => false,
			),
			'custom-code' => array(
				'name'      => esc_html__( 'Custom Code', 'wpmastertoolkit' ),
				'logo'      => 'code.svg',
				'exception' => false,
			),
			'disable-features' => array(
				'name'      => esc_html__( 'Disable Features', 'wpmastertoolkit' ),
				'logo'      => 'stop.svg',
				'exception' => false,
			),
			'security' => array(
				'name'      => esc_html__( 'Security', 'wpmastertoolkit' ),
				'logo'      => 'shield.svg',
				'exception' => false,
			),
			'speed-optimizations' => array(
				'name'      => esc_html__( 'Speed Optimizations', 'wpmastertoolkit' ),
				'logo'      => 'rocket.svg',
				'exception' => false,
			),
			'woocommerce' => array(
				'name'      => esc_html__( 'Woocommerce', 'wpmastertoolkit' ),
				'logo'      => 'woocommerce.svg',
				'exception' => false,
			),
			'other-features' => array(
				'name'      => esc_html__( 'Other Features', 'wpmastertoolkit' ),
				'logo'      => 'tools.svg',
				'exception' => false,
			),
			'settings' => array(
				'name'      => esc_html__( 'Settings', 'wpmastertoolkit' ),
				'logo'      => 'gear.svg',
				'exception' => true,
			),
			'credits' => array(
				'name'      => esc_html__( 'Credits', 'wpmastertoolkit' ),
				'logo'      => 'star.svg',
				'exception' => true,
			)
		);
	
		return $groups;
	}
}
