<?php
if ( ! defined( 'ABSPATH' ) ) exit; 

if ( !function_exists( 'wptp_do_link_shorten' ) ) { 
	add_filter( 'wptt_shorten_link','wptp_do_link_shorten', 10, 4 );

	function wptp_do_link_shorten( $url, $thisposttitle, $post_ID, $testmode=false ) {
			$url = apply_filters('wpt_shorten_link',$url,$post_ID );
			if ($testmode == false ) {
						$campaign = get_option('twitter-analytics-campaign');
					if ( strpos( $url,"%3F" ) === FALSE && strpos( $url,"?" ) === FALSE ) {
						$ct = "?";
					} else {
						$ct = "&";
					}
					$medium = apply_filters( 'wpt_utm_medium', 'twitter' );
					$source = apply_filters( 'wpt_utm_source', 'twitter' );
					$ga = "utm_campaign=$campaign&utm_medium=$medium&utm_source=$source";
					$url .= $ct .= $ga;
				$url = urldecode(trim($url));
				$encoded = urlencode($url);
			} else {
				$url = urldecode(trim($url));
				$encoded = urlencode($url);
			}

			$keyword_format = ( get_option( 'wptp_keyword_format_config' ) == '1' )?$post_ID:false;
			$keyword_format = ( get_option( 'wptp_keyword_format_config' ) == '2' )?get_post_meta( $post_ID,'_yourls_keyword',true ):$keyword_format;
			$error = '';
			switch ( get_option( 'wptp_url_stripper' ) ) {
				case 0:
				case 1:
				case 3:
					$shrink = $url;
					break;
				case 4:
					if ( function_exists('wp_get_shortlink') ) {
						$shrink = ( $post_ID != false ) ? wp_get_shortlink( $post_ID, 'post' ) : $url;
					}
					if ( !$shrink ) { $shrink = $url; }
					break;
				case 2:
					$bitlyapi = trim ( get_option( 'bitlyapi' ) );
					$bitlylogin = trim ( strtolower( get_option( 'bitlylogin' ) ) );				
					$decoded = wptp_json_data( "https://api-ssl.bitly.com/v3/shorten?longUrl=".$encoded."&login=".$bitlylogin."&apiKey=".$bitlyapi."&format=json" );
					if ($decoded) {
						if ($decoded['status_code'] != 200) {
							$shrink = $decoded;
							$error = $decoded['status_txt'];
						} else {
							$shrink = $decoded['data']['url'];		
						}
					} else {
						$shrink = false;
					}	
					if ( !wptp_url_validator($shrink) ) { $shrink = false; }
					break;
				case 8:
					$target = "https://www.googleapis.com/urlshortener/v1/url?key=AIzaSyBSnqQOg3vX1gwR7y2l-40yEG9SZiaYPUQ";					
					$body = "{'longUrl':'$url'}";
					$json = wptp_get_url( $target, 'POST', $body, 'Content-Type: application/json' );
					$decoded = json_decode($json);
					$shrink = $decoded->id;
					if ( !wptp_url_validator($shrink) ) { $shrink = false; }
					break;
				case 9:
					$shrink = $url;
					if ( function_exists( 'twitter_link' ) ) {
						$shrink = twitter_link( $post_ID );
					}
					break;
				case 10:		
					$joturlapi = trim(get_option('joturlapi'));
					$joturllogin = trim(get_option('joturllogin'));				
					$joturl_longurl_params = trim( get_option('joturl_longurl_params') );
					if ($joturl_longurl_params != '') {
					   if (strpos($url, "%3F") === FALSE && strpos($url, "?") === FALSE) {
						  $ct = "?";
					   } else {
						  $ct = "&";
					   }
					   $url .= $ct . $joturl_longurl_params;
					   $encoded = urlencode(urldecode(trim($url)));
					}
					$decoded = wptp_get_url("https://api.joturl.com/a/v1/shorten?url=" . $encoded . "&login=" . $joturllogin . "&key=" . $joturlapi . "&format=plain");
					if ($decoded !== false) {
					   $shrink = $decoded;
					   $joturl_shorturl_params = trim( get_option('joturl_shorturl_params') );
					   if ($joturl_shorturl_params != '') {
						  if (strpos($shrink, "%3F") === FALSE && strpos($shrink, "?") === FALSE) {
							 $ct = "?";
						  } else {
							 $ct = "&";
						  }
						  $shrink .= $ct . $joturl_shorturl_params;
					   }
					} else {
					   $error = $decoded;
					   $shrink = false;
					}
					if (!wptp_url_validator($shrink)) {
					   $shrink = false;
					}
				break;
				update_option( 'wptp_url_packed_status', "$shrink : $error" );
			}
			if ( !$testmode ) {
				if ( $shrink === false || ( filter_var($shrink, FILTER_VALIDATE_URL) === false ) ) {
				update_option( 'wp_url_failure','1' );
				$shrink = urldecode( $url );
				} else {
					update_option( 'wp_url_failure','0' );
				}
			}
			wptp_save_link( $post_ID, $shrink );
		return $shrink;
	}

	function wptp_save_link($post_ID, $url) {
		if ( function_exists('wptp_do_link_shorten') ) {
			$shortener = get_option( 'wptp_url_stripper' );
			switch ($shortener) {
				case 0:	case 1:	case 4: $ext = '_wp';break;
				case 2:	$ext = '_bitly';break;
				case 3:	$ext = '_url';break;
				case 5:	case 6:	$ext = '_yourls';break;
				case 7:	$ext = '_supr';	break;
				case 8:	$ext = '_goo';	break;
				case 9: $ext = '_tfl'; break;
				case 10:$ext = '_joturl'; break;				
				default:$ext = '_ind';
			}
			if ( get_post_meta ( $post_ID, "_wp_jd$ext", TRUE ) != $url ) {
				update_post_meta ( $post_ID, "_wp_jd$ext", $url );
			}
			switch ( $shortener ) {
				case 0: case 1: case 2: case 7: case 8: $target = wptp_url_unpack( $url );break;
				case 5: case 6: $target = wptp_yourl_unpack( $url, $shortener );break;
				case 9: $target = $url; 
				default: $target = $url;
			}
		} else {
			$target = $url;
		}
		update_post_meta( $post_ID, '_wp_jd_target', $target );
	}	
	
	function wptp_url_unpack( $short_url ) {
		$short_url = urlencode( $short_url );
		$decoded = wptp_json_data("http://api.longurl.org/v2/expand?format=json&url=" . $short_url );
		if ( isset( $decoded['long-url'] ) ) {
			$url = $decoded['long-url'];
		} else {
			$url = $short_url;
		}
		return $url;
		//return $short_url;
	}
	function wptp_yourl_unpack( $short_url, $remote ) {
		if ( $remote == 6 ) {
			$short_url = urlencode( $short_url );
			$yourl_api = get_option( 'yourlsurl' );
			$user = get_option( 'yourlslogin' );
			$pass = stripcslashes( get_option( 'yourlsapi' ) );
			$decoded = wptp_json_data( $yourl_api . "?action=expand&shorturl=$short_url&format=json&username=$user&password=$pass" );
			$url = $decoded['longurl'];
			return $url;
		} else {
			global $yourls_reserved_URL;
			define('YOURLS_INSTALLING', true);
			define('YOURLS_FLOOD_DELAY_SECONDS', 0);
			if ( file_exists( dirname( get_option( 'yourlspath' ) ).'/load-yourls.php' ) ) {
				global $ydb;
				require_once( dirname( get_option( 'yourlspath' ) ).'/load-yourls.php' ); 
				$yourls_result = yourls_api_expand( $short_url );
			} else { // YOURLS 1.3
				if ( file_exists( get_option( 'yourlspath' ) ) ) {
					require_once( get_option( 'yourlspath' ) ); 
					$yourls_db = new wpdb( YOURLS_DB_USER, YOURLS_DB_PASS, YOURLS_DB_NAME, YOURLS_DB_HOST );
					$yourls_result = yourls_api_expand( $short_url );
				}
			}	
			$url = $yourls_result['longurl'];
			return $url;
		}
	}
	
	
	function wptp_url_pack_options() {
	?>
	<style>
	
	</style>
<div class="ui-sortable meta-box-sortables">
<div class="postbox">
	
	<h3 class='hndle'><span><abbr title="Uniform Resource Locator">URL</abbr> Shortener Account Settings</span></h3>
	<div class="inside">
	<?php wptp_get_url_packer();?>
		
	<div class="panel <?php if ( get_option('wptp_url_stripper') != 2 ) { echo 'hidden';}?>">
	<h4 class="bitly"><span>Your Bit.ly account details</span></h4>
		
		<div><input type='hidden' name='wpt_shortener_update' value='true' /></div>
		<div>
			<p>
			<label for="bitlylogin">Your Bit.ly username:</label>
			<input type="text" name="bitlylogin" id="bitlylogin" value="<?php echo ( esc_attr( get_option( 'bitlylogin' ) ) ) ?>" />
			</p>	
			<p>
			<label for="bitlyapi">Your Bit.ly <abbr title='application programming interface'>API</abbr> Key:</label>
			<input type="text" name="bitlyapi" id="bitlyapi" size="40" value="<?php echo ( esc_attr( get_option( 'bitlyapi' ) ) ) ?>" />
			</p>
			<p><a href="http://bitly.com/a/your_api_key">View your Bit.ly username and API key</a></p>
			<div>
			<input type="hidden" name="submit-type" value="bitlyapi" />
			</div>
		<?php $nonce = wp_nonce_field('wp-twitter-autopost-nonce', '_wpnonce', true, false).wp_referer_field(false);  echo "<div>$nonce</div>"; ?>	
			<p><input type="submit" name="submit" value="Save Bit.ly API Key" class="green_btn " /> <input type="submit" name="clear" value="Clear Bit.ly API Key" class="alert-message danger"/><br /><small>A Bit.ly API key and username is required to shorten URLs via the Bit.ly API and WP Twitter Autopost.</small></p>
		</div>
		
	</div>
	<?php if ( get_option('wptp_url_stripper') != 2 ) { ?>
	<?php echo '<div class="not_req">Your shortener does not require any account settings.</div>'; ?>
	<?php } ?>
		</div>
</div>
</div>
	<?php
	}
	
	function wptp_url_pack_update( $post ) {
		if ( isset($post['submit-type']) && $post['submit-type'] == 'yourlsapi' ) {
			if ( $post['yourlsapi'] != '' && isset( $post['submit'] ) ) {
				update_option( 'yourlsapi', trim($post['yourlsapi']) );
				$message = "YOURLS password updated. ";
			} else if ( isset( $post['clear'] ) ) {
				update_option( 'yourlsapi','' );
				$message = "YOURLS password deleted. You will be unable to use your remote YOURLS account to create short URLS.";
			} else {
				$message = "Failed to save your YOURLS password! ";
			}
			if ( $post['yourlslogin'] != '' ) {
				update_option( 'yourlslogin', trim($post['yourlslogin']) );
				$message .=  "YOURLS username added. "; 
			}
			if ( $post['yourlsurl'] != '' ) {
				update_option( 'yourlsurl', trim($post['yourlsurl']) );
				$message .= "YOURLS API url added. "; 
			} else {
				update_option('yourlsurl','');
				$message .= "YOURLS API url removed. "; 			
			}
			if ( $post['yourlspath'] != '' ) {
				update_option( 'yourlspath', trim($post['yourlspath']) );	
				if ( file_exists( $post['yourlspath'] ) ) {
				$message .="YOURLS local server path added. "; 
				} else {
				$message .= "The path to your YOURLS installation is not correct. ";
				}
			} else {
				update_option( 'yourlspath','' );
				$message .= "YOURLS local server path removed. ";
			}
			if ( $post['wptp_keyword_format_config'] != '' ) {
				update_option( 'wptp_keyword_format_config', $post['wptp_keyword_format_config'] );
				if ( $post['wptp_keyword_format_config'] == 1 ) {
				$message .=  "YOURLS will use Post ID for short URL slug.";
				} else {
				$message .=  "YOURLS will use your custom keyword for short URL slug.";
				}
			} else {
				update_option( 'wptp_keyword_format_config','' );
				$message .= "YOURLS will not use Post ID for the short URL slug.";
			}
		} 
		
		if ( isset($post['submit-type']) && $post['submit-type'] == 'suprapi' ) {
			if ( $post['suprapi'] != '' && isset( $post['submit'] ) ) {
				update_option( 'suprapi', trim($post['suprapi']) );
				update_option( 'suprlogin', trim($post['suprlogin']) );
				$message = "Su.pr API Key and Username Updated";
			} else if ( isset( $post['clear'] ) ) {
				update_option( 'suprapi','' );
				update_option( 'suprlogin','' );
				$message = "Su.pr API Key and username deleted. Su.pr URLs created by WP Twitter Autopost will no longer be associated with your account. ";
			} else {
				$message = "Su.pr API Key not added - <a href='http://su.pr/'>get one here</a>! ";
			}
		} 
		if ( isset($post['submit-type']) && $post['submit-type'] == 'bitlyapi' ) {
			if ( $post['bitlyapi'] != '' && isset( $post['submit'] ) ) {
				update_option( 'bitlyapi', trim($post['bitlyapi']) );
				$message = "Bit.ly API Key Updated.";
			} else if ( isset( $post['clear'] ) ) {
				update_option( 'bitlyapi','' );
				$message = "Bit.ly API Key deleted. You cannot use the Bit.ly API without an API key. ";
			} else {
				$message = "Bit.ly API Key not added - <a href='http://bit.ly/account/'>get one here</a>! An API key is required to use the Bit.ly URL shortening service.";
			}
			if ( $post['bitlylogin'] != '' && isset( $post['submit'] ) ) {
				update_option( 'bitlylogin', trim($post['bitlylogin']) );
				$message .=" Bit.ly User Login Updated.";
			} else if ( isset( $post['clear'] ) ) {
				update_option( 'bitlylogin','' );
				$message = "Bit.ly User Login deleted. You cannot use the Bit.ly API without providing your username. ";
			} else {
				$message = "Bit.ly Login not added - <a href='http://bit.ly/account/'>get one here</a>! ";
			}
		}
		if (isset($post['submit-type']) && $post['submit-type'] == 'joturlapi') {
			if ($post['joturlapi'] != '' && isset($post['submit'])) {
				update_option('joturlapi', trim($post['joturlapi']));
				$message ="jotURL private API Key Updated. ";
			} else if (isset($post['clear'])) {
				update_option('joturlapi', '');
				$message = "jotURL private API Key deleted. You cannot use the jotURL API without a private API key. ";
			} else {
				$message = "jotURL private API Key not added - <a href='https://www.joturl.com/reserved/api.html'>get one here</a>! A private API key is required to use the jotURL URL shortening service. ";
			}
			if ($post['joturllogin'] != '' && isset($post['submit'])) {
				update_option('joturllogin', trim($post['joturllogin']));
				$message .= "jotURL public API Key Updated. ";
			} else if (isset($post['clear'])) {
				update_option('joturllogin', '');
				$message = "jotURL public API Key deleted. You cannot use the jotURL API without providing your public API Key. ";
			} else {
				$message = "jotURL public API Key not added - <a href='https://www.joturl.com/reserved/api.html'>get one here</a>! ";
			}
			if ($post['joturl_longurl_params'] != '' && isset($post['submit'])) {
				$v = trim($post['joturl_longurl_params']);
				if (substr($v, 0, 1) == '&' || substr($v, 0, 1) == '?') { $v = substr($v, 1); }
				update_option('joturl_longurl_params', $v);
				$message .= "Long URL parameters added. ";
			} else if (isset($post['clear'])) {
				update_option('joturl_longurl_params', '');
				$message ="Long URL parameters deleted. ";
			}
			if ($post['joturl_shorturl_params'] != '' && isset($post['submit'])) { 
				$v = trim($post['joturl_shorturl_params']);
				if (substr($v, 0, 1) == '&' || substr($v, 0, 1) == '?') {$v = substr($v, 1);}
				update_option('joturl_shorturl_params', $v);
				$message .= "Short URL parameters added. ";
			} else if (isset($post['clear'])) {
				update_option('joturl_shorturl_params', '');
				$message = "Short URL parameters deleted. ";
			}			
		}	
		return $message;
	}
	
	function wptp_select_url_packer( $post ) {
		update_option( 'wptp_url_stripper', $post['wptp_url_stripper'] );
		if ( $post['wptp_url_stripper'] == get_option('wptp_url_stripper') ) return; // no message if no change.
		if ( get_option( 'wptp_url_stripper' ) == 2 && ( get_option( 'bitlylogin' ) == "" || get_option( 'bitlyapi' ) == "" ) ) {
			$message .= 'You must add your Bit.ly login and API key in order to shorten URLs with Bit.ly.';
			$message .= "<br />";
		}
		if (get_option('wptp_url_stripper') == 10 && (get_option('joturllogin') == "" || get_option('joturlapi') == "")) {
			$message .= 'You must add your jotURL public and private API key in order to shorten URLs with jotURL.';
			$message .= "<br />";
		}		
		if ( get_option( 'wptp_url_stripper' ) == 6 && ( get_option( 'yourlslogin' ) == "" || get_option( 'yourlsapi' ) == "" || get_option( 'yourlsurl' ) == "" ) ) {
			$message .= 'You must add your YOURLS remote URL, login, and password in order to shorten URLs with a remote installation of YOURLS.';
			$message .= "<br />";
		}
		if ( get_option( 'wptp_url_stripper' ) == 5 && ( get_option( 'yourlspath' ) == "" ) ) {
			$message .=  'You must add your YOURLS server path in order to shorten URLs with a remote installation of YOURLS.';
			$message .= "<br />";
		}
		return $message;
	}
	
	
	function wptp_get_url_packer() {
		?>
			<p>	
			<label>Select a URL Shortner service</label>
			<select name="wptp_url_stripper" id="wptp_url_stripper">
				<option value="3" <?php echo check_url_shortner_opt('wptp_url_stripper','3'); ?>>Don't shorten URLs</option>
				<option value="2" <?php echo check_url_shortner_opt('wptp_url_stripper','2'); ?>>Bit.ly</option>
				<option value="8" <?php echo check_url_shortner_opt('wptp_url_stripper','8'); ?>>Goo.gl</option> 				
				<option value="4" <?php echo check_url_shortner_opt('wptp_url_stripper','4'); ?>>WordPress</option>
				<?php if ( function_exists( 'twitter_link' ) ) { ?><option value="9" <?php echo check_url_shortner_opt('wptp_url_stripper','9'); ?>>Use Twitter Friendly Links.</option><?php } ?>
			</select>
			</p>
		<?php
	}
}