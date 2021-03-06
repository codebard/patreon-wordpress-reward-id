<?php

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

class Patreon_Routing {

	function __construct() {
		add_action( 'generate_rewrite_rules', array($this, 'add_rewrite_rules') );
		add_filter( 'query_vars', array($this, 'query_vars') );
		add_action( 'parse_request', array($this, 'parse_request') );
		add_action( 'init', array($this, 'force_rewrite_rules') );
		add_action( 'init', array($this, 'set_patreon_redirect_cookie') );
		add_action( 'init', array($this,'set_patreon_nonce'), 1);
	}

	public static function activate() {
		global $wp_rewrite;
		$wp_rewrite->flush_rules();
	}

	public static function deactivate() {
		remove_action( 'generate_rewrite_rules','add_rewrite_rules' );
		global $wp_rewrite;
		$wp_rewrite->flush_rules();
	}

	function force_rewrite_rules() {
		global $wp_rewrite;
		if(get_option('patreon-rewrite-rules-flushed', false) == false) {
			$wp_rewrite->flush_rules();
			update_option( 'patreon-rewrite-rules-flushed', true );
		}
	}

	function add_rewrite_rules($wp_rewrite) {

		$rules = array(
			'patreon-authorization\/?$' => 'index.php?patreon-oauth=true',
		);

		$wp_rewrite->rules = $rules + (array)$wp_rewrite->rules;

	}

	function query_vars($public_query_vars) {
		array_push($public_query_vars, 'patreon-oauth');
		array_push($public_query_vars, 'code');
		array_push($public_query_vars, 'state');
		array_push($public_query_vars, 'patreon-redirect');
		return $public_query_vars;
	}

	function set_patreon_redirect_cookie() {
		if (isset($_REQUEST['patreon-redirect']) && is_numeric($_REQUEST['patreon-redirect'])) {
			setcookie('ptrn_dst',$_REQUEST['patreon-redirect']);
			$_COOKIE['ptrn_dst'] = $_REQUEST['patreon-redirect'];
		} else {
			unset($_COOKIE['ptrn_dst']);
		}
	}

	function set_patreon_nonce() {

		if(isset($_COOKIE['ptrn_nonce']) == false) {
			$state = md5(bin2hex(openssl_random_pseudo_bytes(32) . md5(time()) . openssl_random_pseudo_bytes(32)));
			setcookie('ptrn_nonce',$state, 0, COOKIEPATH, COOKIE_DOMAIN );
 		}

	}

	function parse_request( &$wp ) {

		if (array_key_exists( 'patreon-oauth', $wp->query_vars )) {

			if( array_key_exists( 'code', $wp->query_vars ) && array_key_exists( 'state', $wp->query_vars ) && isset($_COOKIE['ptrn_nonce']) && $wp->query_vars['state'] == $_COOKIE['ptrn_nonce']) {

				unset($_COOKIE['ptrn_nonce']);

				if(get_option('patreon-client-id', false) == false || get_option('patreon-client-secret', false) == false) {

					/* redirect to homepage because of oauth client_id or secure_key error #HANDLE_ERROR */
					wp_redirect( home_url() );
					exit;

				} else {
					$oauth_client = new Patreon_Oauth;
				}

				$tokens = $oauth_client->get_tokens($wp->query_vars['code'], site_url().'/patreon-authorization/');

				if(array_key_exists('error', $tokens)) {

					/* redirect to homepage because of some error #HANDLE_ERROR */
					wp_redirect( home_url() );
					exit;

				} else {

					$redirect = false;
					if(get_option('patreon-enable-redirect-to-page-after-login', false)) {
						$redirect = get_option('patreon-enable-redirect-to-page-id', get_option('page_on_front') );
					} else if(isset($_COOKIE['ptrn_dst']) && is_numeric($_COOKIE['ptrn_dst'])) {
						$redirect = get_post($_COOKIE['ptrn_dst']);
						unset($_COOKIE['ptrn_dst']);
					}

					$redirect = apply_filters('ptrn/redirect', $redirect);

					/* redirect to homepage successfully #HANDLE_SUCCESS */
					$api_client = new Patreon_API($tokens['access_token']);
					$user_response = $api_client->fetch_user();

					if(get_option('patreon-enable-strict-oauth', true)) {
						$user = Patreon_Login::updateLoggedInUser($user_response, $tokens, $redirect);
					} else {
						$user = Patreon_Login::createUserFromPatreon($user_response, $tokens, $redirect);
					}

					//shouldn't get here
					wp_redirect( home_url(), 302 );
					exit;

				}


			} else {

				wp_redirect( home_url() );
				exit;

			}


		}

	}

}


?>
