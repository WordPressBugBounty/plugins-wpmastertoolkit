<?php

global $error, $password_protected_errors, $is_iphone;

/**
 * WP Shake JS
 */
if ( ! function_exists( 'wp_shake_js' ) ) {
	function wp_shake_js() {
		if ( isset( $is_iphone ) ) {
			if ( $is_iphone ) {
				return;			
			}
		}
		?>
		<script>
		addLoadEvent = function(func){if(typeof jQuery!="undefined")jQuery(document).ready(func);else if(typeof wpOnload!='function'){wpOnload=func;}else{var oldonload=wpOnload;wpOnload=function(){oldonload();func();}}};
		function s(id,pos){g(id).left=pos+'px';}
		function g(id){return document.getElementById(id).style;}
		function shake(id,a,d){c=a.shift();s(id,c);if(a.length>0){setTimeout(function(){shake(id,a,d);},d);}else{try{g(id).position='static';wp_attempt_focus();}catch(e){}}}
		addLoadEvent(function(){ var p=new Array(15,30,15,0,-15,-30,-15,0);p=p.concat(p.concat(p));var i=document.forms[0].id;g(i).position='relative';shake(i,p,20);});
		</script>
		<?php
	}
}

nocache_headers();
header( 'Content-Type: ' . get_bloginfo( 'html_type' ) . '; charset=' . get_bloginfo( 'charset' ) );

$shake_error_codes = array( 'empty_password', 'incorrect_password' );
if ( $password_protected_errors && $password_protected_errors->get_error_code() && in_array( $password_protected_errors->get_error_code(), $shake_error_codes ) ) {
	add_action( WPMastertoolkit_Password_Protection::LOGIN_HEAD_ACTION, 'wp_shake_js', 12 );
}

?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" <?php language_attributes(); ?>>
<head>
	<meta http-equiv="Content-Type" content="<?php bloginfo( 'html_type' ); ?>; charset=<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width" />
	<title><?php bloginfo( 'name' ); ?></title>
	<?php
	wp_admin_css( 'login', true );
	do_action( WPMastertoolkit_Password_Protection::LOGIN_HEAD_ACTION );
	?>
</head>
<body class="login protected-page-login wp-core-ui">

<div id="login">
	<?php do_action( WPMastertoolkit_Password_Protection::ERROR_MESSAGES_ACTION ); ?>
	<form name="loginform" id="loginform" action="<?php echo esc_url( add_query_arg( 'protected-page', 'view', home_url() ) ); ?>" method="post">
		<label for="protected_page_pwd" ><?php esc_html_e( 'Password', 'wpmastertoolkit' ); ?></label>
		<input type="password" name="protected_page_pwd" id="protected_page_pwd" class="input" value="" size="20" />
		<p class="submit">
			<input type="submit" name="wp-submit" id="wp-submit" class="button button-primary button-large" value="<?php esc_attr_e( 'View Content', 'wpmastertoolkit' ); ?>" />
			<input type="hidden" name="protected-page" value="view" />
			<input type="hidden" name="source" value="<?php echo esc_attr( ! empty( $_REQUEST['source'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['source'] ) ) : '' ); //phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>" />
		</p>
	</form>
</div>

<?php do_action( 'login_footer' ); ?>

</body>
</html>