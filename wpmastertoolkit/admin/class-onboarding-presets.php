<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Define guided onboarding presets.
 *
 * @since 2.25.0
 */
class WPMastertoolkit_Onboarding_Presets {

	/**
	 * Preset schema version.
	 *
	 * @var int
	 */
	const SCHEMA_VERSION = 1;

	/**
	 * Return validated onboarding presets.
	 *
	 * @return array
	 */
	public static function get_presets() {
		$presets = array(
			'security' => array(
				'name'        => esc_html__( 'Protect my website', 'wpmastertoolkit' ),
				'description' => esc_html__( 'Apply broadly compatible protections without changing login URLs, SSL, REST API, or server rules.', 'wpmastertoolkit' ),
				'icon'        => 'shield.svg',
				'modules'     => array(
					self::module( 'WPMastertoolkit_Blacklisted_Usernames', true ),
					self::module( 'WPMastertoolkit_Block_404_Php_File_Scanning', true ),
					self::module( 'WPMastertoolkit_Two_Factor_Authentication', true ),//PRO //has complex settings.
					self::module( 'WPMastertoolkit_Block_User_Registration_From_Disposable_Email', true ),
					self::module( 'WPMastertoolkit_Vulnerabilities_Scan', true ),//PRO
					self::module( 'WPMastertoolkit_Disallow_Dir_Listing', true ),
					self::module( 'WPMastertoolkit_Disallow_WP_File_Edit', true ),
					self::module( 'WPMastertoolkit_Disallow_Access_WP_Sensible_Files', true ),//PRO
					self::module( 'WPMastertoolkit_Password_Expiration', true ),//PRO
					self::module( 'WPMastertoolkit_Hide_Login_Errors', true ),
					self::module( 'WPMastertoolkit_Hide_WordPress_Version', true ),
					self::module( 'WPMastertoolkit_Lock_Admin_Email', true ),
					self::module( 'WPMastertoolkit_Lock_Site_URL', true ),
					self::module( 'WPMastertoolkit_Obfuscate_Author_Slugs', true ),
					self::module( 'WPMastertoolkit_Obfuscate_Email_Address', true ),
					self::module( 'WPMastertoolkit_Protect_Website_Headers', true ),//has complex settings.
					self::module( 'WPMastertoolkit_Auto_Regenerate_Salt_Keys',
						false,
						esc_html__( 'Some plugins use salt keys to encrypt sensitive data such as API keys. Regenerating salt keys may break these encryptions and cause issues with those plugins. Use this feature with caution.', 'wpmastertoolkit' ),
						array(),
						array(
							'frequently' => array(
								'type'    => 'select',
								'default' => 'never',
								'label'   => esc_html__( 'Regenerate frequently', 'wpmastertoolkit' ),
								'choices' => array(
									'never'      => __( 'Never', 'wpmastertoolkit' ),
									'daily'      => __( 'Daily', 'wpmastertoolkit' ),
									'weekly'     => __( 'Weekly', 'wpmastertoolkit' ),
									'monthly'    => __( 'Monthly', 'wpmastertoolkit' ),
									'quarterly'  => __( 'Quarterly', 'wpmastertoolkit' ),
									'biannually' => __( 'Biannually', 'wpmastertoolkit' ),
								),
							),
						)
					),
					self::module( 'WPMastertoolkit_Better_Password_Hash',//PRO
						false,
						esc_html__( 'Argon2id must be supported by the server and can use more memory than the default algorithm. Otherwise, WordPress keeps its current password hashing algorithm.', 'wpmastertoolkit' )
					),
					self::module( 'WPMastertoolkit_Disable_Xmlrpc',
						false,
						esc_html__( 'May affect Jetpack, mobile apps, and remote publishing tools.', 'wpmastertoolkit' )
					),
					self::module( 'WPMastertoolkit_Disallow_Countries_IP',//PRO //has complex settings.
						false,
						esc_html__( 'Country detection relies on a GeoIP database and may block legitimate visitors using VPNs, proxies, or mobile networks. Review the country rules before enabling them.', 'wpmastertoolkit' )
					),
					self::module( 'WPMastertoolkit_Disallow_Malicious_File_Access_In_Upload',
						false,
						esc_html__( 'Adds web server rules that block listed file extensions in the uploads directory. Review the rules for legitimate file types and custom Apache or Nginx configurations.', 'wpmastertoolkit' )
					),
					self::module( 'WPMastertoolkit_Force_SSL',
						false,
						esc_html__( 'Enable only after HTTPS works with a valid SSL certificate. Incorrect proxy or server configuration can cause redirect loops or prevent access to the site.', 'wpmastertoolkit' )
					),
					self::module( 'WPMastertoolkit_Force_Strong_Password',
						false,
						esc_html__( 'Adds stronger password validation to registration, reset, and profile forms.', 'wpmastertoolkit' )
					),
					self::module( 'WPMastertoolkit_Limit_Login_Attempts',
						false,
						esc_html__( 'May lock out users who share an IP address. The preset allows 15 failed attempts and 3 lockout cycles.', 'wpmastertoolkit' ),
						array(),
						array(
							'fails_allowed' => array(
								'type'    => 'number',
								'default' => 15,
								'label'   => esc_html__( 'Failed attempts allowed', 'wpmastertoolkit' ),
								'min'     => 1,
							),
							'lockout_maxcount' => array(
								'type'    => 'number',
								'default' => 3,
								'label'   => esc_html__( 'Maximum lockout cycles', 'wpmastertoolkit' ),
								'min'     => 1,
							)
						)
					),
					self::module( 'WPMastertoolkit_Move_Login_URL', false, esc_html__( 'Changes the login address and blocks the default login URL for signed-out users. Save the new URL and verify compatibility with login or security plugins to avoid lockouts.', 'wpmastertoolkit' ), array(), array(
						'login_slug' => array(
							'type'     => 'text',
							'label'    => esc_html__( 'Login URL slug', 'wpmastertoolkit' ),
							'default'  => 'login',
							'required' => true,
						),
					) ),
					self::module( 'WPMasterToolKit_Prevent_User_Enumeration', false, esc_html__( 'Also disables public author archive pages.', 'wpmastertoolkit' ) ),
				),
			),
			'performance' => array(
				'name'        => esc_html__( 'Improve performance', 'wpmastertoolkit' ),
				'description' => esc_html__( 'Remove low-value frontend assets and discovery tags with minimal compatibility risk.', 'wpmastertoolkit' ),
				'icon'        => 'rocket.svg',
				'modules'     => array(
					self::module( 'WPMastertoolkit_Disable_Emoji_Support', true ),
					self::module( 'WPMastertoolkit_Disable_Shortlink_Tag', true ),
					self::module( 'WPMastertoolkit_Head_Sorter', true ),//PRO
					self::module( 'WPMastertoolkit_Disable_Really_Simple_Discovery_Tag', true ),
					self::module( 'WPMastertoolkit_Local_Google_Font',//PRO
						true,
						'',
						array(),
						array(
							'auto_load' => array(
								'type'    => 'checkbox',
								'default' => '1',
								'label'   => esc_html__( 'Automatic hosting', 'wpmastertoolkit' ),
							),
							'relative_urls' => array(
								'type'    => 'checkbox',
								'default' => '1',
								'label'   => esc_html__( 'Generated font URLs', 'wpmastertoolkit' ),
							),
						)
					),
					self::module( 'WPMastertoolkit_Heartbeat_Control',
						false,
						esc_html__( 'Reducing or disabling Heartbeat can affect autosaves, post locking, session updates, and plugins that depend on the Heartbeat API.', 'wpmastertoolkit' ),
						array(),
						array(
							'admin_pages_controle' => array(
								'type'    => 'select',
								'label'   => esc_html__( 'Admin pages Heartbeat', 'wpmastertoolkit' ),
								'default' => 'default',
								'choices' => array(
									'default' => esc_html__( 'Keep as is', 'wpmastertoolkit' ),
									'modify'  => esc_html__( 'Modify interval', 'wpmastertoolkit' ),
									'disable' => esc_html__( 'Disable', 'wpmastertoolkit' ),
								),
							),
							'admin_pages_interval' => array(
								'type'    => 'select',
								'label'   => esc_html__( 'Admin pages interval', 'wpmastertoolkit' ),
								'default' => '60',
								'choices' => array(
									'15' => esc_html__( '15 seconds', 'wpmastertoolkit' ),
									'30' => esc_html__( '30 seconds', 'wpmastertoolkit' ),
									'60' => esc_html__( '1 minute', 'wpmastertoolkit' ),
									'120' => esc_html__( '2 minutes', 'wpmastertoolkit' ),
									'180' => esc_html__( '3 minutes', 'wpmastertoolkit' ),
									'300' => esc_html__( '5 minutes', 'wpmastertoolkit' ),
									'600' => esc_html__( '10 minutes', 'wpmastertoolkit' ),
								),
							),
							'post_creation_controle' => array(
								'type'    => 'select',
								'label'   => esc_html__( 'Post editor Heartbeat', 'wpmastertoolkit' ),
								'default' => 'default',
								'choices' => array(
									'default' => esc_html__( 'Keep as is', 'wpmastertoolkit' ),
									'modify'  => esc_html__( 'Modify interval', 'wpmastertoolkit' ),
									'disable' => esc_html__( 'Disable', 'wpmastertoolkit' ),
								),
							),
							'post_creation_interval' => array(
								'type'    => 'select',
								'label'   => esc_html__( 'Post editor interval', 'wpmastertoolkit' ),
								'default' => '60',
								'choices' => array(
									'15' => esc_html__( '15 seconds', 'wpmastertoolkit' ),
									'30' => esc_html__( '30 seconds', 'wpmastertoolkit' ),
									'45' => esc_html__( '45 seconds', 'wpmastertoolkit' ),
									'60' => esc_html__( '60 seconds', 'wpmastertoolkit' ),
									'90' => esc_html__( '90 seconds', 'wpmastertoolkit' ),
									'120' => esc_html__( '120 seconds', 'wpmastertoolkit' ),
								),
							),
							'frontend_controle' => array(
								'type'    => 'select',
								'label'   => esc_html__( 'Frontend Heartbeat', 'wpmastertoolkit' ),
								'default' => 'default',
								'choices' => array(
									'default' => esc_html__( 'Keep as is', 'wpmastertoolkit' ),
									'modify'  => esc_html__( 'Modify interval', 'wpmastertoolkit' ),
									'disable' => esc_html__( 'Disable', 'wpmastertoolkit' ),
								),
							),
							'frontend_interval' => array(
								'type'    => 'select',
								'label'   => esc_html__( 'Frontend interval', 'wpmastertoolkit' ),
								'default' => '60',
								'choices' => array(
									'15' => esc_html__( '15 seconds', 'wpmastertoolkit' ),
									'30' => esc_html__( '30 seconds', 'wpmastertoolkit' ),
									'60' => esc_html__( '1 minute', 'wpmastertoolkit' ),
									'120' => esc_html__( '2 minutes', 'wpmastertoolkit' ),
									'180' => esc_html__( '3 minutes', 'wpmastertoolkit' ),
									'300' => esc_html__( '5 minutes', 'wpmastertoolkit' ),
									'600' => esc_html__( '10 minutes', 'wpmastertoolkit' ),
								),
							),
						)
					),
					self::module( 'WPMastertoolkit_410_manager',//PRO
						false,
						esc_html__( 'A 410 response tells browsers and search engines that a URL was removed permanently. Add only intentionally deleted URLs and review sitemap and feed exclusions.', 'wpmastertoolkit' ),
						array(),
						array(
							'urls' => array(
								'type'        => 'textarea',
								'label'       => esc_html__( 'Permanently removed URLs', 'wpmastertoolkit' ),
								'description' => esc_html__( 'Enter one URL on this site per line. Leave blank to add URLs later.', 'wpmastertoolkit' ),
								'default'     => '',
							),
							'exclude_from_sitemap' => array(
								'type'    => 'checkbox',
								'label'   => esc_html__( 'Exclude listed URLs from XML sitemaps', 'wpmastertoolkit' ),
								'default' => '0',
							),
							'exclude_from_rss' => array(
								'type'    => 'checkbox',
								'label'   => esc_html__( 'Exclude listed URLs from RSS feeds', 'wpmastertoolkit' ),
								'default' => '0',
							),
						)
					),
					self::module( 'WPMastertoolkit_Redirect_404_Home',
						false,
						esc_html__( 'Permanently redirects every missing URL to the homepage. This can hide broken links, make troubleshooting harder, and create poor search engine signals.', 'wpmastertoolkit' )
					),
					self::module( 'WPMastertoolkit_Auto_Clean_Actionscheduler_Actions',//PRO
						false,
						esc_html__( 'Permanently deletes completed, failed, and cancelled Action Scheduler records. The first cleanup may run soon after activation, removing history needed to diagnose background jobs.', 'wpmastertoolkit' ),
						array(),
						array(
							'period' => array(
								'type'    => 'select',
								'label'   => esc_html__( 'Cleanup period', 'wpmastertoolkit' ),
								'default' => '86400',
								'choices' => array(
									'60'      => esc_html__( 'Minute', 'wpmastertoolkit' ),
									'3600'    => esc_html__( 'Hour', 'wpmastertoolkit' ),
									'86400'   => esc_html__( 'Day', 'wpmastertoolkit' ),
									'604800'  => esc_html__( 'Week', 'wpmastertoolkit' ),
									'2592000' => esc_html__( 'Month', 'wpmastertoolkit' ),
								),
							),
							'frequency' => array(
								'type'    => 'select',
								'label'   => esc_html__( 'Number of periods between cleanups', 'wpmastertoolkit' ),
								'default' => '1',
								'choices' => array(
									'1' => '1',
									'2' => '2',
									'3' => '3',
									'4' => '4',
									'5' => '5',
									'6' => '6',
									'7' => '7',
									'8' => '8',
									'9' => '9',
								),
							),
						)
					),
					self::module( 'WPMastertoolkit_Disable_Dashicons_CSS_JS_files',
						false,
						esc_html__( 'Can affect frontend forms or designs that use Dashicons.', 'wpmastertoolkit' )
					),
					self::module( 'WPMastertoolkit_Disable_jQuery_Migrate',
						false,
						esc_html__( 'Can break themes or plugins that still depend on legacy jQuery behavior.', 'wpmastertoolkit' )
					),
				),
			),
			'administration' => array(
				'name'        => esc_html__( 'Simplify administration', 'wpmastertoolkit' ),
				'description' => esc_html__( 'Add practical editorial and administrative tools without hiding existing WordPress features.', 'wpmastertoolkit' ),
				'icon'        => 'wordpress.svg',
				'modules'     => array(
					self::module( 'WPMastertoolkit_Quick_Add_Post', true ),
					self::module( 'WPMastertoolkit_Clean_Up_Admin_Bar',
						true,
						'',
						array(),
						array(
							'logo_menu' => array(
								'type'    => 'checkbox',
								'label'   => esc_html__( 'Remove WordPress logo', 'wpmastertoolkit' ),
								'default' => '1',
							),
							'customize_menu' => array(
								'type'    => 'checkbox',
								'label'   => esc_html__( 'Remove customize menu', 'wpmastertoolkit' ),
								'default' => '1',
							),
							'updates_counter' => array(
								'type'    => 'checkbox',
								'label'   => esc_html__( 'Remove updates counter', 'wpmastertoolkit' ),
								'default' => '1',
							),
							'comments_counter' => array(
								'type'    => 'checkbox',
								'label'   => esc_html__( 'Remove comments counter', 'wpmastertoolkit' ),
								'default' => '1',
							),
							'new_content_menu' => array(
								'type'    => 'checkbox',
								'label'   => esc_html__( 'Remove new content menu', 'wpmastertoolkit' ),
								'default' => '1',
							),
							'howdy' => array(
								'type'    => 'checkbox',
								'label'   => esc_html__( 'Remove Howdy greeting', 'wpmastertoolkit' ),
								'default' => '1',
							),
							'help_drawer' => array(
								'type'    => 'checkbox',
								'label'   => esc_html__( 'Remove the Help drawer', 'wpmastertoolkit' ),
								'default' => '1',
							),
						)
					),
					self::module( 'WPMastertoolkit_Manage_Admin_Emails_Notifications',//PRO
						true,
						'',
						array(),
						array(
							'send_password_change_email' => array(
								'type'    => 'checkbox',
								'label'   => esc_html__( 'Suppress administrator password change emails', 'wpmastertoolkit' ),
								'default' => '0',
							),
							'send_email_change_email' => array(
								'type'    => 'checkbox',
								'label'   => esc_html__( 'Suppress administrator email change emails', 'wpmastertoolkit' ),
								'default' => '0',
							),
							'wp_send_new_user_notification_to_admin' => array(
								'type'    => 'checkbox',
								'label'   => esc_html__( 'Suppress new user registration emails', 'wpmastertoolkit' ),
								'default' => '0',
							),
							'send_core_update_notification_email' => array(
								'type'    => 'checkbox',
								'label'   => esc_html__( 'Suppress new core update emails', 'wpmastertoolkit' ),
								'default' => '0',
							),
							'automatic_updates_send_debug_email' => array(
								'type'    => 'checkbox',
								'label'   => esc_html__( 'Suppress automatic background update debug emails', 'wpmastertoolkit' ),
								'default' => '0',
							),
							'auto_core_update_send_email' => array(
								'type'    => 'checkbox',
								'label'   => esc_html__( 'Suppress automatic core update emails', 'wpmastertoolkit' ),
								'default' => '0',
							),
							'auto_plugin_update_send_email' => array(
								'type'    => 'checkbox',
								'label'   => esc_html__( 'Suppress automatic plugin update emails', 'wpmastertoolkit' ),
								'default' => '0',
							),
							'auto_theme_update_send_email' => array(
								'type'    => 'checkbox',
								'label'   => esc_html__( 'Suppress automatic theme update emails', 'wpmastertoolkit' ),
								'default' => '0',
							),
							'send_network_admin_email_change_email' => array(
								'type'    => 'checkbox',
								'label'   => esc_html__( 'Suppress network admin email change emails', 'wpmastertoolkit' ),
								'default' => '0',
							),
							'comment_notification_recipients' => array(
								'type'    => 'checkbox',
								'label'   => esc_html__( 'Remove administrator from new comment emails', 'wpmastertoolkit' ),
								'default' => '0',
							),
						)
					),
					self::module( 'WPMastertoolkit_Custom_Admin_CSS', true ),//has complex settings.
					self::module( 'WPMastertoolkit_Content_Duplication', true ),
					self::module( 'WPMastertoolkit_Content_Order', true ),//has complex settings.
					self::module( 'WPMastertoolkit_Last_Login_Column', true ),
					self::module( 'WPMastertoolkit_Enhance_List_Tables', true ),//has complex settings.
					self::module( 'WPMastertoolkit_Duplicate_Menu', true ),//has complex settings.
					self::module( 'WPMastertoolkit_Disable_Dashboard_Widgets', true ),//has complex settings.
					self::module( 'WPMastertoolkit_Hide_Admin_Bar',//has complex settings.
						false,
						esc_html__( 'Hides the frontend admin bar for every user role by default, removing quick access to dashboard actions. Review the selected roles after activation.', 'wpmastertoolkit' )
					),
					self::module( 'WPMastertoolkit_Disable_Gutenberg',//has complex settings.
						false,
						esc_html__( 'Disables the Gutenberg editor, reverting to the classic editor.', 'wpmastertoolkit' )
					),
					self::module( 'WPMastertoolkit_Admin_Menu_Organizer',//PRO //has complex settings.
						false,
						esc_html__( 'A PRO module that can reorder, rename, or hide dashboard menu items.', 'wpmastertoolkit' )
					),
					self::module( 'WPMastertoolkit_Hide_Admin_Notices',
						false,
						esc_html__( 'Moves admin notices into a separate popup, which can make important notices less visible.', 'wpmastertoolkit' )
					),
					self::module( 'WPMastertoolkit_Mail_Catcher',//has complex settings.
						false,
						esc_html__( 'Catches outgoing emails for testing purposes.', 'wpmastertoolkit' )
					),
					self::module( 'WPMastertoolkit_Wider_Admin_Menu',
						false,
						esc_html__( 'Expands the admin menu for better visibility and accessibility.', 'wpmastertoolkit' ),
						array(),
						array(
							'width' => array(
								'type'    => 'select',
								'label'   => esc_html__( 'Admin menu width', 'wpmastertoolkit' ),
								'default' => '180',
								'choices' => array(
									'180' => '180px',
									'200' => '200px',
									'220' => '220px',
									'240' => '240px',
									'260' => '260px',
									'280' => '280px',
									'300' => '300px',
								),
							),
						)
					),
					self::module( 'WPMastertoolkit_Maintenance_Mode',//has complex settings.
						false,
						esc_html__( 'Displays a maintenance mode page to visitors while you work on your site.', 'wpmastertoolkit' )
					),
				),
			),
			'media' => array(
				'name'        => esc_html__( 'Organize media', 'wpmastertoolkit' ),
				'description' => esc_html__( 'Improve media organization while keeping file conversion and upload changes optional.', 'wpmastertoolkit' ),
				'icon'        => 'content.svg',
				'modules'     => array(
					self::module( 'WPMastertoolkit_Media_Encoder', true ),//has complex settings.
					self::module( 'WPMastertoolkit_Media_Library_Post_Folders', true ),
					self::module( 'WPMastertoolkit_Media_Trash',//PRO
						true,
						'',
						array(),
						array(
							'retention_days' => array(
								'type'        => 'number',
								'label'       => esc_html__( 'Media trash retention (days)', 'wpmastertoolkit' ),
								'description' => esc_html__( 'Trashed media is permanently deleted after this many days.', 'wpmastertoolkit' ),
								'default'     => 90,
								'required'    => true,
								'min'         => 1,
								'max'         => 365,
							),
						)
					),
					self::module( 'WPMastertoolkit_Generate_Alt_Text_With_AI', true ),//PRO //has complex settings.
					self::module( 'WPMastertoolkit_Svg_Upload', true ),
					self::module( 'WPMastertoolkit_Media_Cleaner',
						true,
						'',
						array(),
						array(
							'remove_special_char' => array(
								'type'    => 'checkbox',
								'label'   => esc_html__( 'Remove special characters from uploaded filenames', 'wpmastertoolkit' ),
								'default' => '1',
							),
							'title_completion' => array(
								'type'    => 'checkbox',
								'label'   => esc_html__( 'Set media title from filename', 'wpmastertoolkit' ),
								'default' => '1',
							),
							'alt_completion' => array(
								'type'    => 'checkbox',
								'label'   => esc_html__( 'Set image alt text from filename', 'wpmastertoolkit' ),
								'default' => '1',
							),
							'caption_completion' => array(
								'type'    => 'checkbox',
								'label'   => esc_html__( 'Set media caption from filename', 'wpmastertoolkit' ),
								'default' => '0',
							),
							'description_completion' => array(
								'type'    => 'checkbox',
								'label'   => esc_html__( 'Set media description from filename', 'wpmastertoolkit' ),
								'default' => '0',
							),
						)
					),
					self::module( 'WPMastertoolkit_Paste_Image_In_Media', true ),//PRO
					self::module( 'WPMastertoolkit_Media_Replacement',//PRO
						false,
						esc_html__( 'Replacing a media file updates every page and post that uses it. Back up the original first, and use a compatible file type and dimensions to avoid broken layouts or unexpected results.', 'wpmastertoolkit' )
					),
					self::module( 'WPMastertoolkit_Download_Medias_As_Zip',//PRO
						false,
						esc_html__( 'Requires the PHP ZipArchive extension and writable upload storage. Large media libraries may need significant disk space, memory, and processing time while the ZIP archive is generated.', 'wpmastertoolkit' )
					),
					self::module( 'WPMastertoolkit_Image_Upload_Control',
						false,
						esc_html__( 'Applies only to newly uploaded images. Large images may be resized, BMP and non-transparent PNG files may be converted to JPEG, and the original file may be permanently removed. Confirm the configured dimensions and keep backups when originals are important.', 'wpmastertoolkit' ),
						array(),
						array(
							'max_width' => array(
								'type'     => 'number',
								'label'    => esc_html__( 'Maximum image width (pixels)', 'wpmastertoolkit' ),
								'default'  => 1920,
								'required' => true,
								'min'      => 1,
							),
							'max_height' => array(
								'type'     => 'number',
								'label'    => esc_html__( 'Maximum image height (pixels)', 'wpmastertoolkit' ),
								'default'  => 1920,
								'required' => true,
								'min'      => 1,
							),
						)
					),
				),
			),
		);

		/**
		 * Filter the onboarding presets before module metadata is attached.
		 *
		 * @since 2.25.0
		 * @param array $presets Onboarding preset definitions.
		 */
		$presets = apply_filters( 'wpmastertoolkit_onboarding_presets', $presets );

		return self::enrich_presets( is_array( $presets ) ? $presets : array() );
	}

	/**
	 * Return all module IDs exposed by onboarding.
	 *
	 * @return array
	 */
	public static function get_allowed_module_ids() {
		$module_ids = array();

		foreach ( self::get_presets() as $preset ) {
			foreach ( $preset['modules'] as $module ) {
				$module_ids[] = $module['id'];
			}
		}

		return array_values( array_unique( $module_ids ) );
	}

	/**
	 * Create a module preset definition.
	 *
	 * @param string $module_id   Module class name.
	 * @param bool   $recommended Whether the module is recommended by default.
	 * @param string $warning     Optional compatibility warning.
	 * @param array  $options     Optional fixed module preset settings.
	 * @param array  $fields      Optional fields keyed by module setting name. Each field accepts type, label, default, required, description, choices (select), min and max (number).
	 * @return array
	 */
	private static function module( $module_id, $recommended, $warning = '', $options = array(), $fields = array() ) {
		return array(
			'id'          => $module_id,
			'recommended' => (bool) $recommended,
			'warning'     => $warning,
			'options'     => $options,
			'fields'      => $fields,
		);
	}

	/**
	 * Validate definitions and attach current module metadata.
	 *
	 * @param array $presets Raw preset definitions.
	 * @return array
	 */
	private static function enrich_presets( $presets ) {
		$registry = wpmastertoolkit_options();
		$result   = array();
		$schemas  = array();

		foreach ( $presets as $preset_id => $preset ) {
			if ( ! is_array( $preset ) || empty( $preset['modules'] ) || ! is_array( $preset['modules'] ) ) {
				continue;
			}

			$modules = array();
			foreach ( $preset['modules'] as $module ) {
				$module_id = $module['id'] ?? '';
				if ( ! is_string( $module_id ) || '' === $module_id || ! isset( $registry[ $module_id ] ) ) {
					continue;
				}

				if ( ! isset( $schemas[ $module_id ] ) ) {
					$schemas[ $module_id ] = self::normalize_fields( $module['fields'] ?? array() );
				}

				$module_data          = $registry[ $module_id ];
				$module['name']        = $module_data['name'] ?? $module_data['original_name'];
				$module['description'] = $module_data['desc'] ?? '';
				$module['pro']         = ! empty( $module_data['pro'] );
				$module['available']   = self::is_module_available( $module_data );
				$module['fields']      = $schemas[ $module_id ];
				$modules[]             = $module;
			}

			if ( empty( $modules ) ) {
				continue;
			}

			$preset['id']      = sanitize_key( $preset_id );
			$preset['modules'] = $modules;
			$result[ $preset['id'] ] = $preset;
		}

		return $result;
	}

	/**
	 * Allow only supported scalar onboarding controls in filtered definitions.
	 *
	 * @param mixed $fields Field definitions.
	 * @return array
	 */
	private static function normalize_fields( $fields ) {
		if ( ! is_array( $fields ) ) {
			return array();
		}

		$result = array();
		foreach ( $fields as $key => $field ) {
			if ( ! is_string( $key ) || '' === $key || sanitize_key( $key ) !== $key || ! is_array( $field ) || ! is_string( $field['label'] ?? null ) || '' === $field['label'] ) {
				continue;
			}

			$type = $field['type'] ?? 'text';
			if ( ! in_array( $type, array( 'text', 'number', 'select', 'checkbox', 'textarea' ), true ) || ( isset( $field['description'] ) && ! is_string( $field['description'] ) ) ) {
				continue;
			}

			$field['type']     = $type;
			$field['required'] = ! empty( $field['required'] );
			$field['default']  = $field['default'] ?? ( 'checkbox' === $type ? '0' : '' );
			if ( ! is_scalar( $field['default'] ) ) {
				continue;
			}

			if ( 'select' === $type ) {
				if ( ! isset( $field['choices'] ) || ! is_array( $field['choices'] ) || ! $field['choices'] ) {
					continue;
				}
				if ( ! array_key_exists( (string) $field['default'], $field['choices'] ) ) {
					continue;
				}
				foreach ( $field['choices'] as $choice => $label ) {
					if ( ! is_scalar( $choice ) || ! is_string( $label ) ) {
						continue 2;
					}
				}
			}

			if ( 'number' === $type ) {
				if ( ( isset( $field['min'] ) && ! is_numeric( $field['min'] ) ) || ( isset( $field['max'] ) && ! is_numeric( $field['max'] ) ) ) {
					continue;
				}
			}

			$result[ $key ] = $field;
		}

		return $result;
	}

	/**
	 * Check whether a registered module file is available.
	 *
	 * @param array $module Module registry data.
	 * @return bool
	 */
	private static function is_module_available( $module ) {
		$module_path = $module['path'] ?? '';

		if ( strpos( $module_path, 'core/' ) === 0 || strpos( $module_path, 'pro/' ) === 0 ) {
			$module_path = WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/modules/' . $module_path;
		}

		return is_file( $module_path );
	}
}