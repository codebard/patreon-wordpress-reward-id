<?php


// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

class Patreon_Frontend {

	function __construct() {

		add_action( 'login_enqueue_scripts', array($this,'patreonEnqueueCss'), 10 );
		add_action( 'login_enqueue_scripts', array($this,'patreonEnqueueJs'), 1 );
		add_action( 'wp_enqueue_scripts', array($this,'patreonEnqueueCss') );
		add_action( 'wp_enqueue_scripts', array($this,'patreonEnqueueJs') );

		if(get_option('patreon-enable-register-with-patreon', false)) {
			add_action( 'register_form', array($this, 'showPatreonButton' ) );
			add_action( 'woocommerce_register_form_end', array($this, 'showPatreonButton') );
		}
		if(get_option('patreon-enable-login-with-patreon', false)) {
			add_action( 'login_form', array($this, 'showPatreonButton' ) );
			add_action( 'woocommerce_login_form_end', array($this, 'showPatreonButton' ) );
		}

		add_filter( 'the_content', array($this, 'protectContentFromUsers'), PHP_INT_MAX );

	
	}

	public function showPatreonButton() {

		global $wp;

		$client_id = get_option('patreon-client-id', false);

		$login_with_patreon = get_option('patreon-enable-login-with-patreon', false);
		$admins_editors_login_with_patreon = get_option('patreon-enable-allow-admins-login-with-patreon', false);

		if($client_id == false) {
			return '';
		}

		$redirect_uri = site_url().'/patreon-authorization/';

		$href = 'https://www.patreon.com/oauth2/authorize?response_type=code&client_id='.$client_id.'&redirect_uri='.$redirect_uri;

		if($login_with_patreon == true && $admins_editors_login_with_patreon == false && isset($_REQUEST['patreon-msg']) && $_REQUEST['patreon-msg'] == 'login_with_wordpress') {
			echo '<p class="patreon-msg">You can now login with your wordpress username/password.</p>';
		} else if( $login_with_patreon == false && isset($_REQUEST['patreon-msg']) && $_REQUEST['patreon-msg'] == 'login_with_patreon') {
			echo '<p class="patreon-msg">You can now login with your wordpress username/password.</p>';
		} else {
			echo '<div class="patreon-login-refresh-button">'.self::patreonMakeLoginButton().'</div>';
		}

	}

	function patreonEnqueueJs() {
		wp_register_script( 'patreon-wordpress-js', PATREON_PLUGIN_ASSETS.'/js/app.js', array( 'jquery' ) );
		wp_enqueue_script( 'patreon-wordpress-js', PATREON_PLUGIN_ASSETS.'/js/app.js', false );
	}

	function patreonEnqueueCss() {
		wp_register_style( 'patreon-wordpress-css', PATREON_PLUGIN_ASSETS.'/css/app.css', false );
		wp_enqueue_style('patreon-wordpress-css', PATREON_PLUGIN_ASSETS.'/css/app.css' );
	}

	public static function displayPatreonCampaignBanner($patreon_level = false) {

		global $wp;
		global $post;

		$login_with_patreon = get_option('patreon-enable-login-with-patreon', false);

		$client_id = get_option('patreon-client-id', false);
	
		// Check existence of a custom patreon banners as saved in plugin options
		$custom_universal_banner = get_option('patreon-custom-universal-banner',false);

        $contribution_required = PATREON_TEXT_LOCKED_POST;
		
        if($custom_universal_banner AND $custom_universal_banner!='') {
			// Custom banner exists and it is not empty. Override the message
			$contribution_required = $custom_universal_banner;	
		}
		
        if($patreon_level != false) {
			
        	$contribution_required = str_replace('%%pledgelevel%%',$patreon_level,$contribution_required);
        	$contribution_required = apply_filters('ptrn/contribution_required',$contribution_required,$patreon_level);
		}
		
        if ($client_id) {
		
			// Wrap message and buttons in divs for responsive interface mechanics
			
			$contribution_required = '<div class="patreon-locked-content-message">'.$contribution_required.'</div>';
			
			$universal_button = self::patreonMakeUniversalButton();
	
			$universal_button = '<div class="patreon-universal-button">'.$universal_button.'</div>';
			
			if(isset($login_with_patreon_button))
			{
				$login_with_patreon_button = '<div class="patreon-login-refresh-button">'.$login_with_patreon_button.'</div>';
			}
			
			$text_over_universal_button = self::getLabelOverUniversalButton($patreon_level);
			$text_under_universal_button = self::getLabelUnderUniversalButton($patreon_level);
			
			// Wrap all of them in a responsive div
			
			$campaign_banner = '<div class="patreon-campaign-banner">'.
									$contribution_required.
									'<div class="patreon-patron-button-wrapper">'.
										'<div class="patreon-text-over-button">'.
											$text_over_universal_button.
										'</div>'.
										$universal_button.
										'<div class="patreon-text-under-button">'.
											$text_under_universal_button.
										'</div>'.
									'</div>'.
								'</div>';
			
			// This extra button is solely here to test whether new wrappers cause any design issues in different themes. For easy comparison with existing unwrapped button. Remove when confirmed.
			

        	$campaign_banner = apply_filters('ptrn/campaign_banner', $campaign_banner, $patreon_level);

            return $campaign_banner;
        }
		

	}
	
	function getLabelOverUniversalButton($patreon_level) {
		
		$label = PATREON_TEXT_OVER_BUTTON_1;
		
		$user_logged_into_patreon = self::isUserLoggedInPatreon();

		$is_patron = Patreon_Wordpress::isPatron();
		
		$is_patron = Patreon_Wordpress::isPatron();
		
		if(!$user_logged_into_patreon)
		{
			// Patron logged in and patron, but we are still showing the banner. This means pledge level is not enough.
			
			return str_replace('%%pledgelevel%%',$patreon_level,PATREON_TEXT_OVER_BUTTON_1);
		
		}
		if(!$is_patron)
		{
			// Patron logged in and not patron
			
			return str_replace('%%pledgelevel%%',$patreon_level,PATREON_TEXT_OVER_BUTTON_1);
		
		}
		
		$user_patronage = Patreon_Wordpress::getUserPatronage();
		
		if($user_patronage < ($patreon_level*100) AND $user_patronage>0)
		{
			// Patron logged in and not patron
			
			$label = str_replace('%%pledgelevel%%',$patreon_level,PATREON_TEXT_OVER_BUTTON_2);
			
			// Get creator full name:
			
			$creator_full_name = get_option('patreon-creator-full-name', false);
			
			if(!$creator_full_name OR $creator_full_name=='')
			{
				$creator_full_name = 'this creator';
			}
			
			$label = str_replace('%%creator%%',$creator_full_name,$label);
			
			// REVISIT - calculating user patronage value by dividing patronage var may be bad.
			return str_replace('%%currentpledgelevel%%',($user_patronage/100),$label);
			
		
		}
	
		
		return $label;
		
	}
	function getLabelUnderUniversalButton($patreon_level) {
		
		$label = PATREON_TEXT_UNDER_BUTTON_1;
	
		$user_logged_into_patreon = self::isUserLoggedInPatreon();

		$is_patron = Patreon_Wordpress::isPatron();
		
		if(!$user_logged_into_patreon)
		{
			// Patron logged in and patron, but we are still showing the banner. This means pledge level is not enough.
			
			return PATREON_TEXT_UNDER_BUTTON_1;
		
		}
		if(!$is_patron)
		{
			// Patron logged in and not patron
			
			return str_replace('%%pledgelevel%%',$patreon_level,PATREON_TEXT_UNDER_BUTTON_2);
		
		}
	 
		$user_patronage = Patreon_Wordpress::getUserPatronage();
		
		if($user_patronage < ($patreon_level*100) AND $user_patronage>0)
		{
			// Patron logged in and not patron
			
			$label = str_replace('%%pledgelevel%%',$patreon_level,PATREON_TEXT_UNDER_BUTTON_2);
			$refresh_link = '<a href="'.self::patreonMakeLoginLink().'">Refresh</a>';
			return str_replace('%%flowlink%%',$refresh_link,$label);
			
		
		}
		
		return $label;
		
	}
	function patreonMakeUniversalButton($mincents=false,$state=false,$post=false,$client_id=false) {
		
		// This very customizable function takes numerous parameters to customize universal flow links and creates the desired link

		// If no post is given, get the active post:
		
		if(!$post)
		{
			global $post;
		}
				
		// If no post object given, 
		
		$send_pledge_level=0;
		
		if($mincents)
		{
			$send_pledge_level = $mincents;
		}
		
		if(!$client_id)
		{
			$client_id = get_option('patreon-client-id', false);
		}
		
		// If we werent given any state vars to send, initialize the array
		if(!$state)
		{
			$state=array();
		}

		// Get the address of the current page, and save it as final redirect uri.		
		// Start with home url for redirect. If post is valid, get permalink. 
		
		$final_redirect = home_url();
		
		
		if($post)
		{
			$final_redirect = get_permalink($post->ID);
		}
		
		$state['final_redirect_uri'] = $final_redirect;
		
		$href = self::MakeUniversalFlowLink($send_pledge_level,$state,$client_id);
			
		$label_text = self::patreonMakeUniversalButtonLabel();
		
		$paywall_img = get_option('patreon-paywall-img-url', false);
		
        if ($paywall_img == false) {
        	$paywall_img = '<div class="patreon-responsive-button-wrapper"><div class="patreon-responsive-button"><img class="patreon_logo" src="'.PATREON_PLUGIN_ASSETS.'/img/patreon-logomark-on-coral.svg" alt=""> '.$label_text.'</div></div>';
        } else {
        	$paywall_img = '<img src="'.$paywall_img.'" />';
        }
		
		return apply_filters('ptrn/patron_button', '<a href="'.$href.'">'.$paywall_img.'</a>');		
		
	}
	function MakeUniversalFlowLink($pledge_level,$state,$client_id = false) {
		
		if(!$client_id)
		{
			$client_id = get_option('patreon-client-id', false);
		}	
		
		$redirect_uri = site_url().'/patreon-authorization/';
		
		$href = 'https://www.patreon.com/check-auth-route?min_cents='.$pledge_level.'&client_id='.$client_id.'&redirect_uri='.$redirect_uri.'&state='.base64_encode(serialize($state));

		// 3rd party dev goodie! Apply custom filters so they can manipulate the url:
		
		return apply_filters('ptrn/patron_link', $href);			
		
	}
	function patreonMakeUniversalButtonLabel() {
		
		// Default label:
		
		$label = PATREON_TEXT_UNLOCK_WITH_PATREON;
		
		$user_logged_into_patreon = self::isUserLoggedInPatreon();

		$is_patron = Patreon_Wordpress::isPatron();
		
		// Change this after getting info about which value confirms user's payment is declined. The only different button label is for that condition.
		
		return $label;		
		
		
	}
	function isUserLoggedInPatreon() {
		 
		$user_logged_into_patreon = false;
		
		if(is_user_logged_in()){
			// User is logged into WP. Check if user has valid patreon data :
			
			$user = wp_get_current_user();

			if($user) {
				
				$user_response = Patreon_Wordpress::getPatreonUser($user);
				// ^ REVISIT - whats above may be a concern - it connects to API to check for valid user for every generation of button. If we could cache it it would be better

				if($user_response) {
					// This is a user logged into Patreon. 
					$user_logged_into_patreon = true;
				}					
			}
			
		}		
		return $user_logged_into_patreon;
	}
	function patreonMakeLoginLink($client_id=false) {
		
		if(!$client_id)
		{
			$client_id = get_option('patreon-client-id', false);
		}
		
		
		$redirect_uri = site_url().'/patreon-authorization/';

		$href = 'https://www.patreon.com/oauth2/authorize?response_type=code&client_id='.$client_id.'&redirect_uri='.$redirect_uri;
	
		return apply_filters('ptrn/login_link', $href);

	}
	function patreonMakeLoginButton($client_id=false) {
		
		if(!$client_id)
		{
			$client_id = get_option('patreon-client-id', false);
		}
		
		// Check if user is logged in to WP, for determination of label text
		
		// Set login label to default
		$login_label = PATREON_TEXT_CONNECT;
		
		if(is_user_logged_in()){
			// User is logged into WP. Check if user has valid patreon data :
			
			$user = wp_get_current_user();

			if($user) {
				
				$user_response = Patreon_Wordpress::getPatreonUser($user);
				// ^ REVISIT - whats above may be a concern - it connects to API to check for valid user for every generation of button. If we could cache it it would be better

				if($user_response) {
					// This is a user logged into Patreon. use refresh text
					$login_label = PATREON_TEXT_REFRESH;
				}					
			}
			
			
		}
		
		$redirect_uri = site_url().'/patreon-authorization/';

		$href = 'https://www.patreon.com/oauth2/authorize?response_type=code&client_id='.$client_id.'&redirect_uri='.$redirect_uri;
	
		return apply_filters('ptrn/login_button', '<a href="'.$href.'" class="ptrn-login" data-ptrn_nonce="' . wp_create_nonce( 'patreon-nonce' ).'"><div class="patreon-responsive-button-wrapper"><div class="patreon-responsive-button"><img class="patreon_logo" src="'.PATREON_PLUGIN_ASSETS.'/img/patreon-logomark-on-coral.svg" alt=""> '.$login_label.'</div></div></a>', $href);

	}	
	function protectContentFromUsers($content) {

		global $post;

		$post_types = get_post_types(array('public'=>true),'names');
	
			
		if(in_array(get_post_type(),$post_types)) {

			if(current_user_can('manage_options')) {
				return $content;
			}
		
			// Below define can be defined in any plugin to bypass core locking function and use a custom one from plugin
			// It is independent of the plugin load order since it checks if it is defined.
			// It can be defined by any plugin until right before the_content filter is run.
			
			if(defined('PATREON_BYPASS_FILTERING')) {
                return $content;
            }
			
			// First check if entire site is locked, get the level for locking.
			
			$patreon_level = get_option('patreon-lock-entire-site',false);
			
			// Check if specific level is given for this post:
			
			$post_level = get_post_meta( $post->ID, 'patreon-level', true );
			
			// get post meta returns empty if no value is found. If so, set the value to 0.
			
			if($post_level == '')
			{
				$post_level = 0;				
			}

			// Check if both post level and site lock level are set to 0 or nonexistent. If so return normal content.
			
			if($post_level == 0 
				&& (!get_option('patreon-lock-entire-site',false) 
					|| get_option('patreon-lock-entire-site',false)==0)
			) {
				return $content;
			}
			
			// Passed checks. If post level is not 0, override patreon level and hence site locking value with post's. This will allow Creators to lock entire site and then set a different value for individual posts for access. Ie, site locking is $5, but one particular post can be $10, and it will require $10 to see. 
			
			if($post_level!=0)
			{
				$patreon_level = $post_level;
			}
			
			$user_patronage = Patreon_Wordpress::getUserPatronage();
			
			if($user_patronage == false 
				|| $user_patronage < ($patreon_level*100)
			) {

				//protect content from user
			
				// Get client id
				
				$client_id = get_option('patreon-client-id', false);
				
				// // If client id exists. Do the banner. If not, no point in protecting since we wont be able to send people to patronage. If so dont modify normal content.
				
				if($client_id) {
					
					$content = self::displayPatreonCampaignBanner($patreon_level);

					$content = apply_filters('ptrn/post_content', $content, $user_patronage);				
					
				}
				
			}

		}

		return $content;

	}


}

?>
