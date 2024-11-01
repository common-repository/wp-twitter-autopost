<?php
function wtt_oauth_test( $auth=false, $context='' ) {
	if ( !$auth ) {
		return ( wptp_usr_cred_hash() == get_option('wtt_oauth_hash') );
	} else {
		$return = ( wptp_usr_cred_hash( $auth ) == wptp_do_user_verif( $auth ) );
		if ( !$return && $context != 'verify' ) {
			return ( wptp_usr_cred_hash() == get_option('wtt_oauth_hash') );
		} else {
			return $return;
		}
	}
}
function wptp_do_user_verif( $auth ) {
	if ( get_option( 'wptp_twitter_per_user_api' ) != '1' ) {
		return false; 
	} else {
		$auth = get_user_meta( $auth,'wtt_oauth_hash',true );
		return $auth;
	}
	return false;
}
function wptp_connection_twtr_oauth( $auth=false ) {
if ( !$auth ) {
	$ack = get_option('app_consumer_key');
	$acs = get_option('app_consumer_secret');
	$ot = get_option('oauth_token');
	$ots = get_option('oauth_token_secret');
} else {
	$ack = get_user_meta( $auth,'app_consumer_key',true);
	$acs = get_user_meta( $auth,'app_consumer_secret',true);
	$ot = get_user_meta( $auth,'oauth_token',true);
	$ots = get_user_meta( $auth,'oauth_token_secret',true);
}
	if ( !empty( $ack ) && !empty( $acs ) && !empty( $ot ) && !empty( $ots ) ) {	
		require_once( plugin_dir_path(__FILE__).'api_twitter_oauth.php' );
		$connection = new wptp_twitterOAuth_class( $ack,$acs,$ot,$ots );
		$connection->useragent = 'WP Twitter Autopost http://www.joedolson.com/articles/wp-to-twitter';
		return $connection;
	} else {
		return false;
	}
}
function wptp_usr_cred_hash( $auth=false ) {
	if ( !$auth ) {
		$hash = md5(get_option('app_consumer_key').get_option('app_consumer_secret').get_option('oauth_token').get_option('oauth_token_secret'));
	} else {
		$hash = md5( get_user_meta( $auth,'app_consumer_key',true ). get_user_meta( $auth,'app_consumer_secret',true ). get_user_meta( $auth,'oauth_token',true ). get_user_meta( $auth,'oauth_token_secret',true ) );
	}
	return $hash;		
}
function wptp_oauth_settings_update( $auth=false, $post=false ) {
if ( isset($post['oauth_settings'] ) ) {
switch ( $post['oauth_settings'] ) {
	case 'wtt_oauth_test':
			if ( !wp_verify_nonce( $post['_wpnonce'], 'wp-twitter-autopost-nonce' ) && !$auth ) {
				wp_die('Oops, please try again.');
			}
			$auth_test = false;
			if ( !empty($post['wptp_twitter_api_consumer_key'])
				&& !empty($post['wptp_twitter_api_consumer_secret'])
				&& !empty($post['wptp_twitter_api_token'])
				&& !empty($post['wptp_twitter_oauth_token_secret'])
			) {
				$acs = trim($post['wptp_twitter_api_consumer_secret']);
				$ot = trim($post['wptp_twitter_api_token']);
				$ack = trim($post['wptp_twitter_api_consumer_key']);
				$ots =trim($post['wptp_twitter_oauth_token_secret']);
				if ( !$auth ) {
					update_option('app_consumer_key',$ack);
					update_option('app_consumer_secret',$acs);
					update_option('oauth_token',$ot);
					update_option('oauth_token_secret',$ots);
				} else {
					update_user_meta( $auth,'app_consumer_key',$ack);
					update_user_meta( $auth,'app_consumer_secret',$acs);
					update_user_meta( $auth,'oauth_token',$ot);
					update_user_meta( $auth,'oauth_token_secret',$ots);				
				}
				$message = 'failed';
				if ( $connection = wptp_connection_twtr_oauth( $auth ) ) {
					$data = $connection->get('https://api.twitter.com/1.1/account/verify_credentials.json');
					if ( $connection->http_code != '200' ) {
						$data = json_decode( $data );
						update_option( 'wpt_error', $data->errors[0]->message );
					} else {
						delete_option( 'wpt_error' );
					}
					if ($connection->http_code == '200') {
						$error_information = '';
						$decode = json_decode($data);
						if ( !$auth ) { 
							update_option( 'wptp_api_twitter_user_name', stripslashes( $decode->screen_name ) );
						} else {
							update_user_meta( $auth,'wptp_api_twitter_user_name', stripslashes( $decode->screen_name ) );
						}
						$oauth_hash = wptp_usr_cred_hash( $auth );
						if ( !$auth ) {
							update_option( 'wtt_oauth_hash', $oauth_hash );
						} else {
							update_user_meta( $auth,'wtt_oauth_hash',$oauth_hash );
						}
						$message = 'success';
						delete_option( 'wpt_curl_error' );
					} else if ( $connection->http_code == 0 ) {
						$error_information = "WP Twitter Autopost was unable to establish a connection to Twitter."; 
						update_option( 'wpt_curl_error',"$error_information" );
					} else {
						$error_information = array("http_code"=>$connection->http_code,"status"=>$connection->http_header['status']);
						$error_code ="Twitter response: http_code $error_information[http_code] - $error_information[status]";
						update_option( 'wpt_curl_error',$error_code );
					}
				}
			} else {
				$message = "nodata";
			}
			if ( $message == 'failed' && ( time() < strtotime( $connection->http_header['date'] )-300 || time() > strtotime( $connection->http_header['date'] )+300 ) ) {
				$message = 'nosync';
			}
			return $message;
		break;
		case 'wptp_disconnect_twitter':
			if ( !wp_verify_nonce($post['_wpnonce'], 'wp-twitter-autopost-nonce') && !$auth ) {
				wp_die('Oops, please try again.');
			}
			if ( !$auth ) {
				update_option( 'app_consumer_key', '' );
				update_option( 'app_consumer_secret', '' );
				update_option( 'oauth_token', '' );
				update_option( 'oauth_token_secret', '' );
				update_option( 'wptp_api_twitter_user_name', '' );
			} else {
				delete_user_meta( $auth, 'app_consumer_key' );
				delete_user_meta( $auth, 'app_consumer_secret' );
				delete_user_meta( $auth, 'oauth_token' );
				delete_user_meta( $auth, 'oauth_token_secret' );
				delete_user_meta( $auth, 'wptp_api_twitter_user_name' );
			}
			$message = "cleared";
			return $message;
		break;
	}
	return "Nothing";
}
}
function wptp_connect_twtr( $auth=false ) {
if ( !$auth ) {
	echo '<div class="ui-sortable meta-box-sortables">';
	echo '<div class="postbox">';
}
$server_time = date( DATE_COOKIE );
$response = wp_remote_get( "https://twitter.com/", array( 'timeout'=>1, 'redirection'=>1 ) );
if ( is_wp_error( $response ) ) {
	$warning = '';
	$error = $response->errors;
	if ( is_array( $error ) ) {
		$warning = "<ul>";
		foreach ( $error as $k=>$e ) {
			foreach ( $e as $v ) {
				$warning .= "<li>".$v."</li>";
			}
		}
		$warning .= "</ul>";
	}
	$ssl = "";
	$date = "There was an error querying Twitter's servers";
	$errors = "";
} else {
	$date = date( DATE_COOKIE, strtotime($response['headers']['date']) );
	$errors = '';
}
$class = ( $auth )?'wpt-profile':'wpt-settings';
$form = ( !$auth )?'<form action="" method="post">':'';
$nonce = ( !$auth )?wp_nonce_field('wp-twitter-autopost-nonce', '_wpnonce', true, false).wp_referer_field(false).'</form>':'';
	if ( !wtt_oauth_test( $auth,'verify' ) ) {
		$ack = ( !$auth )?esc_attr( get_option('app_consumer_key') ):esc_attr( get_user_meta( $auth,'app_consumer_key', true ) );
		$acs = ( !$auth )?esc_attr( get_option('app_consumer_secret') ):esc_attr( get_user_meta( $auth,'app_consumer_secret', true ) );
		$ot = ( !$auth )?esc_attr( get_option('oauth_token') ):esc_attr( get_user_meta( $auth,'oauth_token', true ) );
		$ots = ( !$auth )?esc_attr( get_option('oauth_token_secret') ):esc_attr( get_user_meta( $auth,'oauth_token_secret', true ) );
	
		$submit = ( !$auth )?'<p class="submit"><input type="submit" name="submit" class="blue_btn" value="Connect to Twitter" /></p>':'';
		print('	
			<div class="handlediv"><span class="screen-reader-text">Click to toggle</span></div>
			<h3 class="hndle"><span>Connect to Twitter</span></h3>
			<div class="inside '.$class.'">
			<div class="notes">
			<h4>WP Twitter Autopost Set-up</h4>
			<p>Your server time: <code>'.$server_time.'</code><br/><br/>Twitter\'s time: <code>'.$date.'</code>.<br/><br/>If these timestamps are not within 5 minutes of each other, your server will not connect to Twitter.</p>
			'.$errors.'
			</div>	
			'.$form.'
				<fieldset class="options">	
				<h4>Enter your Consumer Key and Access Consumer Secret into the fields below</h4>					
					<div class="tokens">
					<p>
						<label for="wptp_twitter_api_consumer_key">API Key</label>
						<input type="text" size="45" name="wptp_twitter_api_consumer_key" id="wptp_twitter_api_consumer_key" value="'.$ack.'" />
					</p>
					<p>
						<label for="wptp_twitter_api_consumer_secret">API Secret</label>
						<input type="text" size="45" name="wptp_twitter_api_consumer_secret" id="wptp_twitter_api_consumer_secret" value="'.$acs.'" />
					</p>
					</div>
					<h4>Enter your Access Token and Access Token Secret into the fields below</h4>
					<p>You must keep Access level for your App to "<em>Read and write</em>", Otherwise the plugin would not work as it should.</p>
					<div class="tokens">
					<p>
						<label for="wptp_twitter_api_token">Access Token</label>
						<input type="text" size="45" name="wptp_twitter_api_token" id="wptp_twitter_api_token" value="'.$ot.'" />
					</p>
					<p>
						<label for="wptp_twitter_oauth_token_secret">Access Token Secret</label>
						<input type="text" size="45" name="wptp_twitter_oauth_token_secret" id="wptp_twitter_oauth_token_secret" value="'.$ots.'" />
					</p>
					</div>
				</fieldset>
				'.$submit.'
				<input type="hidden" name="oauth_settings" value="wtt_oauth_test" class="hidden" style="display: none;" />
				'.$nonce.'
			</div>	
				');
	} else if ( wtt_oauth_test( $auth ) ) {
		$ack = ( !$auth )?esc_attr( get_option('app_consumer_key') ):esc_attr( get_user_meta( $auth,'app_consumer_key', true ) );
		$acs = ( !$auth )?esc_attr( get_option('app_consumer_secret') ):esc_attr( get_user_meta( $auth,'app_consumer_secret', true ) );
		$ot = ( !$auth )?esc_attr( get_option('oauth_token') ):esc_attr( get_user_meta( $auth,'oauth_token', true ) );
		$ots = ( !$auth )?esc_attr( get_option('oauth_token_secret') ):esc_attr( get_user_meta( $auth,'oauth_token_secret', true ) );
		$uname = ( !$auth )?esc_attr( get_option('wptp_api_twitter_user_name') ):esc_attr( get_user_meta( $auth,'wptp_api_twitter_user_name', true ) );
		$nonce = ( !$auth )?wp_nonce_field('wp-twitter-autopost-nonce', '_wpnonce', true, false).wp_referer_field(false).'</form>':'';
		if ( !$auth ) {
			$submit = '
					<input type="submit" name="submit" class="blue_btn" value="Disconnect from Twitter" />
					<input type="hidden" name="oauth_settings" value="wptp_disconnect_twitter" class="hidden" />
				';					
		} else {
			$submit = '<input type="checkbox" name="oauth_settings" value="wptp_disconnect_twitter" id="disconnect" /> <label for="disconnect">Disconnect your WordPress and Twitter Account</label>';
		}
		$warning =  ( get_option('wptp_auth_missing') )?'<p><strong>Troubleshooting tip:</strong> Connected, but getting a error that your Authentication credentials are missing or incorrect? Check that your Access token has read and write permission. If not, you\'ll need to create a new token. </p>':'';
		if ( !is_wp_error( $response ) ) { 
			$diff = ( abs( time() - strtotime($response['headers']['date']) ) > 300 )?'<p> Your time stamps are more than 5 minutes apart. Your server could lose its connection with Twitter.</p>':''; 
		} else { 
			//$diff = 'WP Twitter Autopost could not contact Twitter\'s remote server. Here is the error triggered: '.$errors;
		}

		print('
			<div class="handlediv"><span class="screen-reader-text">Click to toggle</span></div>
			<h3 class="hndle"><span>Disconnect from Twitter</span></h3>
			<div class="inside '.$class.'">
			'.$form.'
				<div id="wtt_authentication_display">
					<fieldset class="options">
					<ul class="twitter_info">
						<li><strong class="auth_label">Twitter Username</strong> <code class="auth_code">'.$uname.'</code></li>
						<li><strong class="auth_label">Consumer Key </strong> <code class="auth_code">'.$ack.'</code></li>
						<li><strong class="auth_label">Consumer Secret</strong> <code class="auth_code">'.$acs.'</code></li>
						<li><strong class="auth_label">Access Token</strong> <code class="auth_code">'.$ot.'</code></li>
						<li><strong class="auth_label">Access Token Secret</strong> <code class="auth_code">'.$ots.'</code></li>
					</ul>
					</fieldset>
					<div>
					'.$submit.'
					</div>
				</div>		
				'.$nonce.'
			<!--<p>Your server time: <code>'.$server_time.'</code>.<br />Twitter\'s server time: <code>'.$date.'</code>.</p>--!>
			'.$errors.$diff.'</div>' );
			global $wpt_server_string;
			$wpt_server_string = 
			'Your server time: <code>'.$server_time.'</code>
			Twitter\'s server time: <code>'.$date.'</code>
			'.$errors.$diff;
	}
	if ( !$auth ) {
		echo "</div>";
		echo "</div>";
	}
}