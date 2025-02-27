<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Module Name: Blacklisted Usernames
 * Description: Blacklist some usernames for better security
 * @since 1.0.0
 * @updated 1.3.0
 */
class WPMastertoolkit_Blacklisted_Usernames {

    const ACTION    = 'wpmastertoolkit-blacklisted-usernames-action';
    const NONCE     = 'wpmastertoolkit-blacklisted-usernames-nonce';
    const BLACKLIST = array(
        '!','@','#','$','%','^','&','*','(',')','-','_','+','=','{','}','[',']','|','\\',':',';','"',"'",'<','>',',','.','?','/','`','~','0','1','2','3','4','5','6','7','8','9',
        'a', 'about', 'access', 'account', 'accounts', 'ad', 'address', 'adm', 'admin', 'adminaccount', 'adminaccounts', 'adminapi', 'adminarea', 'admindb', 'adminftp', 'administration', 'administrator', 'adminmail', 'adminnetwork', 'adminpage', 'adminpanel', 'adminroot', 'adminserver', 'adminservice', 'adminsite', 'adminssh', 'adminsys', 'adminuser', 'adminusers', 'adminweb', 'adult', 'advertising', 'affiliate', 'affiliates', 'ajax', 'analytics', 'android', 'anon', 'anonymous', 'api', 'apiadmin', 'apis', 'apiuser', 'app', 'apps', 'archive', 'atom', 'auth', 'authentication', 'avatar',
        'b', 'backup', 'banner', 'banners', 'billing', 'bin', 'blog', 'blogadmin', 'blogs', 'board', 'bot', 'bots', 'business',
        'c', 'cache', 'cadastro', 'calendar', 'campaign', 'careers', 'cdn', 'cgi', 'chat', 'client', 'cliente', 'code', 'comercial', 'compare', 'compras', 'config', 'connect', 'contact', 'contest', 'controlpanel', 'create', 'css', 'customer', 'customers',
        'd', 'dashboard', 'data', 'database', 'db', 'dbadmin', 'dbuser', 'default', 'delete', 'demo', 'design', 'designer', 'dev', 'devel', 'dir', 'directory', 'doc', 'docs', 'documentation', 'domain', 'download', 'downloads',
        'e', 'ecommerce', 'edit', 'editor', 'email',
        'f', 'faq', 'favorite', 'feed', 'feedback', 'file', 'files', 'flog', 'follow', 'forum', 'forums', 'free', 'ftp', 'ftpadmin', 'ftpuser',
        'g', 'gadget', 'gadgets', 'games', 'group', 'groups', 'guest', 'guests',
        'h', 'help', 'home', 'homepage', 'host', 'hosting', 'hostname', 'hpg', 'htm', 'html', 'http', 'httpd', 'https',
        'i', 'image', 'images', 'imap', 'img', 'index', 'indice', 'info', 'information', 'intranet', 'invite', 'ipad', 'iphone', 'irc',
        'j', 'java', 'javascript', 'job', 'jobs', 'js',
        'k', 'kb', 'knowledgebase',
        'l', 'list', 'lists', 'log', 'login', 'logout', 'logs',
        'm', 'mail', 'mail1', 'mail2', 'mail3', 'mail4', 'mail5', 'mailadmin', 'mailer', 'mailing', 'mailuser', 'main', 'manager', 'marketing', 'master', 'me', 'media', 'member', 'memberarea', 'members', 'message', 'messenger', 'microblog', 'microblogs', 'mine', 'mob', 'mobile', 'moderator', 'moderators', 'movie', 'movies', 'mp3', 'msg', 'msn', 'music', 'musicas', 'mx', 'my', 'mysql',
        'n', 'name', 'named', 'net', 'network', 'networkadmin', 'new', 'news', 'newsletter', 'nick', 'nickname', 'notes', 'noticias', 'ns', 'ns1', 'ns2', 'ns3', 'ns4', 'ns5', 'ns6', 'ns7', 'ns8', 'ns9',
        'o', 'old', 'online', 'operator', 'order', 'orders',
        'p', 'page', 'pager', 'pages', 'panel', 'password', 'perl', 'photo', 'photoalbum', 'photos', 'php', 'pic', 'pics', 'plugin', 'plugins', 'pop', 'pop3', 'post', 'postfix', 'postmaster', 'posts', 'private', 'profile', 'project', 'projects', 'promo', 'pub', 'public', 'python',
        'q', 'query',
        'r', 'random', 'register', 'registration', 'root', 'rootadmin', 'rootuser', 'rss', 'ruby',
        's', 'sale', 'sales', 'sample', 'samples', 'script', 'scripts', 'search', 'secure', 'security', 'send', 'server', 'serveradmin', 'service', 'services', 'setting', 'settings', 'setup', 'sex', 'shop', 'signin', 'signup', 'site', 'sitemap', 'sites', 'smtp', 'soporte', 'sql', 'ssh', 'sshadmin', 'sshuser', 'staff', 'stage', 'staging', 'start', 'stat', 'static', 'stats', 'status', 'store', 'stores', 'subdomain', 'subscribe', 'superadmin', 'superuser', 'suporte', 'support', 'sys', 'sysadmin', 'system', 'systemadmin', 'sysuser',
        't', 'tablet', 'tablets', 'talk', 'task', 'tasks', 'tech', 'telnet', 'test', 'test1', 'test2', 'test3', 'teste', 'tests', 'theme', 'themes', 'tmp', 'todo', 'tools', 'tv',
        'u', 'update', 'upload', 'url', 'usage', 'user', 'useradmin', 'userapi', 'userdb', 'userftp', 'usermail', 'username', 'usernetwork', 'userpage', 'userroot', 'users', 'userserver', 'userservice', 'usersite', 'userssh', 'usersys', 'userweb', 'usr', 'usuario',
        'v', 'vendas', 'video', 'videos', 'visitor',
        'w', 'web', 'webadmin', 'webmail', 'webmaster', 'webpage', 'webpages', 'webserver', 'webservices', 'website', 'websites', 'webuser', 'win', 'workshop', 'ww', 'wws', 'www', 'www1', 'www2', 'www3', 'www4', 'www5', 'www6', 'www7', 'www9', 'wwws', 'wwww',
        'x', 'xpg', 'xxx',
        'y', 'you',
        'z', 'zzz',
    );

    private $last_username = '';

    /**
     * Invoke Wp Hooks
	 *
     * @since    1.0.0
	 */
    public function __construct() {
        add_filter( 'illegal_user_logins', array( $this, 'blacklist_logins_illegal_user_logins' ) );
        add_action( 'admin_init', array( $this, 'check_the_current_username' ) );
        add_action( 'wp_ajax_' . self::ACTION, array( $this, 'change_admin_name' ) );
    }
    
    /**
     * blacklist_logins_illegal_user_logins
     *
     * @param  mixed $usernames
     * @return void
     */
    public function blacklist_logins_illegal_user_logins( $usernames ) {
        return array_merge( $usernames, self::BLACKLIST );
    }

    /**
     * Check the current username
     */
    public function check_the_current_username() {

        if ( defined( 'DOING_AJAX' ) && DOING_AJAX ){
            return;
        }

        foreach ( self::BLACKLIST as $username ) {

            if ( empty( $username ) || $username === '<' ) {
                continue;
            }

            $user = get_user_by( 'login', $username );
            
            if ( ! empty( $user ) ) {
                $user_roles = $user->roles ?? array();
                $is_admin   = in_array( 'administrator', $user_roles );

                if ( $is_admin ) {
                    $this->last_username = $username;
                    add_action( 'admin_notices', array( $this, 'show_notice_to_change_username' ) );
                    break;
                }
            }
        }
    }
    
    /**
     * Show notice to change the admin username
     */
    public function show_notice_to_change_username() {

        $assets = include( WPMASTERTOOLKIT_PLUGIN_PATH . 'admin/assets/build/core/blacklisted-usernames.asset.php' );
        wp_enqueue_style( 'wpmastertoolkit-blacklisted-usernames-admin', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/blacklisted-usernames.css', array(), $assets['version'], 'all' );
        wp_enqueue_script( 'wpmastertoolkit-blacklisted-usernames-admin', WPMASTERTOOLKIT_PLUGIN_URL . 'admin/assets/build/core/blacklisted-usernames.js', $assets['dependencies'], $assets['version'], true );
        wp_localize_script( 'wpmastertoolkit-blacklisted-usernames-admin', 'wpmastertoolkitBlacklistedUsernames', array(
            'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
            'action'   => self::ACTION,
            'nonce'    => wp_create_nonce( self::NONCE ),
            'username' => $this->last_username,
        ));

        ?>
            <div class="notice notice-error" id="wpmastertoolkit-blacklisted-usernames-notice">
                <div id="wpmastertoolkit-blacklisted-usernames-text">
                    <p>
                        <strong>WPMastertoolkit: </strong>
                        <?php 
                        echo esc_html( sprintf(
                            /* translators: %s: username */
                            __( 'You can\'t use "%s" as your username. It\'s not safe. Please pick another username for better security.', 'wpmastertoolkit' ), 
                            $this->last_username 
                        ) ); 
                        ?>
                    </p>
                    <p><?php esc_html_e( 'After changing the username you\'ll need to login again.', 'wpmastertoolkit' ) ?></p>
                </div>
                <input type="text" id="wpmastertoolkit-blacklisted-usernames-input" placeholder="<?php esc_attr_e( 'Enter new username', 'wpmastertoolkit' ); ?>">
                <input type="submit" id="wpmastertoolkit-blacklisted-usernames-button" class="button button-primary" value="<?php esc_attr_e( 'Change', 'wpmastertoolkit'); ?>">
                <p id="wpmastertoolkit-blacklisted-usernames-message"></p>
            </div>
        <?php
    }

    /**
     * Change the admin username
     */
    public function change_admin_name() {

        $nonce    = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
        $username = sanitize_text_field( wp_unslash( $_POST['username'] ?? '' ) );
        if ( ! wp_verify_nonce( $nonce, self::NONCE ) || empty( $username ) ) {
            wp_send_json_error( array( 'message' => __( 'Refresh the page and try again.', 'wpmastertoolkit' ) ) );
        }

        $newusername = sanitize_text_field( wp_unslash( $_POST['newusername'] ?? '' ) );
        $newusername = sanitize_title( trim( $newusername ) );
        if ( empty( $newusername ) ) {
            wp_send_json_error( array( 'message' => __( 'Username can\'t be empty.', 'wpmastertoolkit' ) ) );
        }

        if ( in_array( $newusername, self::BLACKLIST ) ) {
            wp_send_json_error( array( 'message' => __( 'Sorry, that username is not allowed.', 'wpmastertoolkit' ) ) );
        }

        if ( username_exists( $newusername ) ) {
            wp_send_json_error( 
                array(
                 'message' => sprintf(
                        /* translators: %s: username */
                        __( 'The username "%s" already exists. Please choose a different one.', 'wpmastertoolkit' ), 
                        $newusername 
                    ) 
                ) 
            );
        }

        $admin_user = get_user_by( 'login', $username );
        if ( ! $admin_user ) {
            wp_send_json_error( 
                array( 'message' => sprintf( 
                        /* translators: %s: username */
                        __( 'No user has the username "%s". Nothing to update.', 'wpmastertoolkit' ), 
                        $username
                    ) 
                ) 
            );
        }

        global $wpdb;
		//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update( $wpdb->users, array( 'user_login' => $newusername ), array( 'ID' => $admin_user->ID ) );

        if ( get_user_by( 'login', $newusername ) ) {
            wp_send_json_success( array( 'message' => __( 'Username changed successfully. Please logout and login with the new username.', 'wpmastertoolkit' ) ) );
        }

        wp_send_json_error( array( 'message' => __( 'Username change failed.', 'wpmastertoolkit' ) ) );
    }
}
