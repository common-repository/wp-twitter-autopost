<?php
if ( ! defined( 'ABSPATH' ) ) 
	exit; 
function wptp_logger( $data, $id ) {
	if ( $id == 'test' ) {
		$log = get_option( $data );
	} else if ( $id == 'last' ) {
		$log = get_option( $data.'_last' );
	} else {
		$log = get_post_meta( $id, '_'.$data, true );
	}
	return $log;
}

function wptp_fn_chk() {
	$message = "<div class='update'><ul>";
	$testurl =  get_bloginfo( 'url' );
	$shortener = get_option( 'wptp_url_stripper' );
	$title = urlencode( 'Your blog home' );
	$shrink = apply_filters( 'wptt_shorten_link', $testurl, $title, false, true );
	if ( $shrink == false ) {
		$error = htmlentities( get_option('wptp_url_packed_status') );
		$message .= "<li class=\"error\"><strong>WP Twitter Autopost was unable to contact your selected URL shortening service.</strong></li>";
		if ( $error != '' ) {
			$message .= "<li><code>$error</code></li>";
		} else {
			$message .= "<li><code>No error message was returned.</code></li>";
		}
	} else {
		$message .= "<li><strong>WP Twitter Autopost successfully contacted your selected URL shortening service.</strong>  The following link should point to your blog homepage:";
		$message .= " <a href='$shrink'>$shrink</a></li>";
	}
	if ( wtt_oauth_test() ) {
		$rand = rand( 1000000,9999999 );
		$testpost = wptp_post_to_twtrAPI( "This is a test of WP Twitter Autopost. $shrink ($rand)" );
		if ( $testpost ) {
			$message .= "<li><strong>WP Twitter Autopost successfully submitted a status update to Twitter.</strong></li>";
		} else {
			$error = wptp_logger( 'wptp_status_notifier', 'test' );
			$message .=	"<li class=\"error\"><strong>WP Twitter Autopost failed to submit an update to Twitter.</strong></li>";
			$message .= "<li class=\"error\">$error</li>";
		}
	} else {
		$message .= "<strong>You have not connected WordPress to Twitter.</strong> ";
	}
	if ($testpost == FALSE && $shrink == FALSE  ) {
		$message .="<li class=\"error\"><strong>Your server does not appear to support the required methods for WP Twitter Autopost to function.</strong> You can try it anyway - these tests aren't perfect.</li>";
	} else {
	}
	if ( $testpost && $shrink ) {
		$message .= "<li><strong>Your server should run WP Twitter Autopost successfully.</strong></li>";
	}
	$message .= "</ul>
	</div>";
	return $message;
}

function check_url_shortner_opt( $field, $value, $type='select' ) {
	if( get_option( $field ) == $value ) {
		return ( $type == 'select' )?'selected="selected"':'checked="checked"';
	}
}

function wptp_log_msg( $data, $id, $message ) {
	if ( $id == 'test' ) {
		$log = update_option( $data, $message );
	} else {
		$log = update_post_meta( $id,'_'.$data, $message );
	}
	$last = update_option( $data.'_last', array( $id, $message ) );
}

function wptp_config_update() {
	wptp_chk_ver();

	if ( !empty($_POST) ) {
		$nonce=$_REQUEST['_wpnonce'];
		if (! wp_verify_nonce($nonce,'wp-twitter-autopost-nonce') ) die("Security check failed");  
	}

	if ( isset($_POST['oauth_settings'] ) ) {
		$oauth_message = wptp_oauth_settings_update( false, $_POST );
	}

	$wp_twitter_error = FALSE;
	$wp_shortener_error = FALSE;
	$message = "";
	if ( get_option( 'twitterconnected') != '1' ) {
		$initial_settings = array( 
			'post'=> array( 
					'post-published-update'=>1,
					'post-published-text'=>'Newly added: #title# #url#',
					'post-edited-update'=>1,
					'post-edited-text'=>'Edited Post: #title# #url#'
					),
			'page'=> array( 
					'post-published-update'=>0,
					'post-published-text'=>'New page added: #title# #url#',
					'post-edited-update'=>0,
					'post-edited-text'=>'Page edited: #title# #url#'
					)
			);
		update_option( 'wptp_post_types', $initial_settings );
		update_option( 'jd_twit_blogroll', '1');
		update_option( 'newlink-published-text', 'New link: #title# #url#' );
		update_option( 'wptp_url_stripper', '1' );
		update_option( 'wptp_remove_noalphnum', '0' );
		update_option('wptp_max_num_tags',3);
		update_option('wptp_max_chars_disp',15);	
		update_option('wptp_replace_chars_disp','');
		update_option('wtt_user_permissions','administrator');
		$administrator = get_role('administrator');
		$administrator->add_cap('wpt_twitter_oauth');
		$administrator->add_cap('wpt_twitter_custom');
		$administrator->add_cap('wpt_twitter_switch');
		$administrator->add_cap('wpt_can_tweet');
		$editor = get_role('editor');
		if ( is_object( $editor ) ) { $editor->add_cap('wpt_can_tweet'); }
		$author = get_role('author');
		if ( is_object( $author ) ) { $author->add_cap('wpt_can_tweet'); }
		$contributor = get_role('contributor');
		if ( is_object( $contributor ) ) { $contributor->add_cap('wpt_can_tweet'); }
		update_option('wpt_can_tweet','contributor');
		update_option('wtt_show_custom_tweet','administrator');
		update_option( 'wptp_tweet_remote', '0' );
		update_option( 'wptp_excerpt_post', 30 );
		update_option( 'twitter-analytics-campaign', 'twitter' );
		update_option( 'use-twitter-analytics', '0' );
		update_option( 'wptp_dynamic_analytics','0' );
		update_option( 'no-analytics', 1 );
		update_option( 'wptp_use_dynamic_analytics','category' );			
		update_option( 'wptp_tweet_custom_link', 'tweet_link' );	
		update_option( 'wp_twitter_failure','0' );
		update_option( 'wp_url_failure','0' );
		update_option( 'wptp_send_tweet_default', '0' );
		update_option( 'wptp_tweet_edit_action','0' );
		update_option( 'wptp_post_quick_updates', '0' );
		update_option( 'twitterconnected', '1' );	
		update_option( 'wptp_keyword_format_config', '0' );
	}
	if ( get_option( 'twitterconnected') == '1' && get_option( 'wptp_excerpt_post' ) == "" ) { 
		update_option( 'wptp_excerpt_post', 30 );
	}
		
    if ( isset( $_POST['oauth_settings'] ) ) {
		if ( $oauth_message == "success" ) {
			print('
				<div id="message" class="updated fade">
					<p>WP Twitter Autopost is now <strong>connected</strong> with <strong>Twitter</strong>.</p>
				</div>
			');
		} else if ( $oauth_message == "failed" ) {
			print('
				<div id="message" class="updated fade">
					<p>WP Twitter Autopost failed to connect to Twitter Servers.</p>
					<p>Error:'.get_option('wpt_error').'</p>
				</div>
			');
		} else if ( $oauth_message == "cleared" ) {
			print('
				<div id="message" class="updated fade">
					<p>Authentication Keys Cleared.</p>
				</div>
			');		
		} else  if ( $oauth_message == 'nosync' ) {
			print('
				<div id="message" class="error fade">
					<p>Your server time is not in sync with the Twitter servers -- <strong>OAuth Authentication Failed</strong></p>
				</div>
			');
		} else {
			print('
				<div id="message" class="error fade">
					<p>OAuth Authentication response cannot be processed.</p>
				</div>			
			');
		}
	}	
	if (  isset($_POST['submit-type']) && $_POST['submit-type'] == 'check-support' ) {
		$message = wptp_fn_chk();
	}
?>
<div class="wrap" id="wp-to-twitter">
<?php wptp_commments_removed(); ?>
<?php if ( $message ) { ?>
<div id="message" class="updated fade"><p><?php echo $message; ?></p></div>
<?php }
	$log = wptp_logger( 'wptp_status_notifier', 'last' );
	if ( !empty( $log ) && is_array( $log ) ) {
		$post_ID = $log[0];
		$post = get_post( $post_ID );
		$title = $post->post_title;
		$notice = $log[1];
		echo "<div class='updated fade'><p><strong>Last Tweet</strong>: <a href='".get_edit_post_link( $post_ID )."'>$title</a> &raquo; $notice</p></div>";
	}
	if ( get_option( 'wp_twitter_failure' ) != '0' || get_option( 'wp_url_failure' ) == '1' ) { ?>
		<div class="error">
		<?php if ( get_option( 'wp_twitter_failure' ) == '1' ) {
			?><p>One or more of your last posts has failed to send a status update to Twitter. The Tweet has been saved, and you can re-Tweet it at your leisure.</p><?php 
		}
		if ( get_option( 'wp_twitter_failure' ) == '2') {
			echo "<p>Sorry! I couldn't get in touch with the Twitter servers to post your <strong>new link</strong>! You'll have to post it manually.</p>";
		}		
		if ( get_option( 'wp_url_failure' ) == '1' ) {
			echo "<p>The query to the URL shortener API failed, and your URL was not shrunk. The full post URL was attached to your Tweet. Check with your URL shortening provider to see if there are any known issues.</p>";
		} 
		$admin_url = admin_url('options-general.php?page=wp-twitter-autopost'); ?>
		<form method="post" action="<?php echo $admin_url; ?>">
		<div><input type="hidden" name="submit-type" value="clear-error" /></div>
		<?php $nonce = wp_nonce_field('wp-twitter-autopost-nonce', '_wpnonce', true, false).wp_referer_field(false);  echo "<div>$nonce</div>"; ?>	
		<p><input type="submit" name="submit" value="Clear 'WP Twitter Autopost' Error Messages" class="button-primary" /></p>
		</form>		
		</div>
<?php
}
?>	
<h2>WP Twitter Autopost Options</h2>
<div id="wpt_settings_page" class="postbox-container jcd-wide" >

<?php 
	if ( isset($_GET['debug']) && $_GET['debug'] == 'true' ) {
		$debug = get_option( 'wpt_debug' );
		echo "<pre>";
			print_r( $debug );
		echo "</pre>";
	}
	if ( isset($_GET['debug']) && $_GET['debug'] == 'delete' ) {
		delete_option( 'wpt_debug' );
	}
	$wp_to_twitter_directory = get_bloginfo( 'wpurl' ) . '/' . WP_PLUGIN_DIR . '/' . dirname( plugin_basename(__FILE__) ); ?>
		
<div class="metabox-holder">

<?php if (function_exists('wptp_connect_twtr') ) { wptp_connect_twtr(); } ?>
</div>
</div>
<?php wptp_get_sidebar(); ?>
</div>
</div>
<?php global $wp_version; }

function wptp_get_sidebar() {
?>
<div id="fb-root"></div>
<script>(function(d, s, id) {
  var js, fjs = d.getElementsByTagName(s)[0];
  if (d.getElementById(id)) return;
  js = d.createElement(s); js.id = id;
  js.src = "//connect.facebook.net/en_IN/all.js#xfbml=1&appId=533043140136429";
  fjs.parentNode.insertBefore(js, fjs);
}(document, 'script', 'facebook-jssdk'));</script>
<div class="postbox-container jcd-narrow">
<div class="metabox-holder">
	<div class="ui-sortable meta-box-sortables">
		<div class="postbox">
			<div class="handlediv"><span class="screen-reader-text">Click to toggle</span></div>
			<h3 class='hndle'><span><strong>WP Twitter Autopost Support</strong></span></h3>			
			<div class="inside resources">
			<p>
			<a href="https://twitter.com/intent/follow?screen_name=vivacityIT" class="twitter-follow-button" data-size="small" >Follow @vivacityIT</a>
			<script>!function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0];if(!d.getElementById(id)){js=d.createElement(s);js.id=id;js.src="https://platform.twitter.com/widgets.js";fjs.parentNode.insertBefore(js,fjs);}}(document,"script","twitter-wjs");</script>
			</p>
			
			<a href="#get-support">Get Support</a>
			
			<p><a href="http://tinyurl.com/owxtkmt">Make a donation today!</a><br />Each donation matters - donate today!</p>
			<div class='donations'>
			 <form action="https://www.paypal.com/cgi-bin/webscr" method="post" target="_top">
<input type="hidden" name="cmd" value="_donations">
<input type="hidden" name="business" value="vivacityinfotech@gmail.com">
<input type="hidden" name="lc" value="US">
<input type="hidden" name="item_name" value="Plugin & Theme Development">
<input type="hidden" name="no_note" value="0">
<input type="hidden" name="currency_code" value="USD">
<input type="hidden" name="bn" value="PP-DonationsBF:btn_donateCC_LG.gif:NonHostedGuest">
<input type="image" src="https://www.paypalobjects.com/en_US/i/btn/btn_donateCC_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
<img alt="" border="0" src="https://www.paypalobjects.com/en_US/i/scr/pixel.gif" width="1" height="1">
</form>
			<div class="fb-like" data-href="https://www.facebook.com/vivacityinfotech" data-width="50" data-layout="box_count" data-action="like" data-show-faces="true" data-share="false" style="overflow:hidden;"></div>
			</div>
		
			</div>
		</div>
	</div>
	
</div>
<?php
}
function wptwtr_chkbox_processing( $field,$sub1=false,$sub2='' ) {
	if ( $sub1 ) {
		$setting = get_option($field);
		if ( isset( $setting[$sub1] ) ) {
			$value = ( $sub2 != '' )?$setting[$sub1][$sub2]:$setting[$sub1];
		} else {
			$value = 0;
		}
		if ( $value == 1 ) {
			return 'checked="checked"';
		}
	}
	if( get_option( $field ) == '1'){
		return 'checked="checked"';
	}
}



function wp_url_shortner_settings(){
	if ( isset($_POST['wpt_shortener_update']) && $_POST['wpt_shortener_update'] == 'true' ) {
		update_option( 'wptp_url_stripper', $_POST['wptp_url_stripper']);
		$message = wptp_url_pack_update( $_POST );
	}
	echo '<div id="wpt_settings_page" class="postbox-container jcd-wide" style="width:60%;">
<div class="metabox-holder"><form action="" method="post">';
	wptp_url_pack_options();
	echo '<input type="hidden" name="wpt_shortener_update" value="true"><input type="submit" name="submit" value="Save URL Shortner Options" class="button-side green_btn"></form></div></div>';
}
function templates_sel()
{

	if ( isset($_POST['submit-type']) && $_POST['submit-type'] == 'options' ) {
		//print_r($_POST);exit;
		// UPDATE OPTIONS
		$wpt_settings = get_option('wptp_post_types');
		foreach($_POST['wptp_post_types'] as $key=>$value) {
			$array = array(
					'post-published-update'=>( isset( $value["post-published-update"] ) )?$value["post-published-update"]:"",
					'post-published-text'=>$value["post-published-text"],
					'post-edited-update'=>( isset( $value["post-edited-update"] ) )?$value["post-edited-update"]:"",
					'post-edited-text'=>$value["post-edited-text"]
			);
			$wpt_settings[$key] = $array;
		}
		update_option( 'wptp_post_types', $wpt_settings );
		update_option( 'newlink-published-text', $_POST['newlink-published-text'] );
		update_option( 'jd_twit_blogroll',(isset($_POST['jd_twit_blogroll']) )?$_POST['jd_twit_blogroll']:"" );
		$message = wptp_select_url_packer( $_POST );
		$message .= 'WP Twitter Autopost Options Updated';
		$message = apply_filters( 'wpt_settings', $message, $_POST );
	}
	require_once('render_templates_settings.php');

}
function adv_settings()
{
	if ( isset( $_POST['submit-type'] ) && $_POST['submit-type'] == 'advanced' ) {
		update_option( 'wptp_excerpt_post', $_POST['wptp_excerpt_post'] );
		update_option( 'wptp_max_num_tags',$_POST['wptp_max_num_tags']);
		update_option( 'wptp_tag_usuage', ( ( isset($_POST['wptp_tag_usuage']) && $_POST['wptp_tag_usuage'] == 'slug' )?'slug':'' ) );
		update_option( 'wptp_max_chars_disp',$_POST['wptp_max_chars_disp']);
		update_option( 'wptp_replace_chars_disp',$_POST['wptp_replace_chars_disp']);
		update_option( 'wptp_twitter_date_frmat',$_POST['wptp_twitter_date_frmat'] );
		update_option( 'wptp_dynamic_analytics',$_POST['jd-dynamic-analytics'] );
		update_option( 'wptp_send_tweet_default', ( isset( $_POST['wptp_send_tweet_default'] ) )?$_POST['wptp_send_tweet_default']:0 );
		update_option( 'wptp_tweet_edit_action', ( isset( $_POST['wptp_tweet_edit_action'] ) )?$_POST['wptp_tweet_edit_action']:0 );
		update_option( 'wptp_post_quick_updates', ( isset( $_POST['wptp_post_quick_updates'] ) )?$_POST['wptp_post_quick_updates']:0 );
		update_option( 'wptp_tweet_remote',( isset( $_POST['wptp_tweet_remote'] ) )?$_POST['wptp_tweet_remote']:0 );
		update_option( 'wptp_tweet_custom_link', $_POST['wptp_tweet_custom_link'] );
		update_option( 'wptp_remove_noalphnum', ( isset( $_POST['wptp_remove_noalphnum'] ) )?$_POST['wptp_remove_noalphnum']:0 );
		$twitter_analytics = ( isset($_POST['twitter-analytics']) )?$_POST['twitter-analytics']:0;
		if ( $twitter_analytics == 1 ) {
			update_option( 'wptp_use_dynamic_analytics', 0 );
			update_option( 'use-twitter-analytics', 1 );
			update_option( 'no-analytics', 0 );
		} else if ( $twitter_analytics == 2 ) {
			update_option( 'wptp_use_dynamic_analytics', 1 );
			update_option( 'use-twitter-analytics', 0 );
			update_option( 'no-analytics', 0 );
		} else {
			update_option( 'wptp_use_dynamic_analytics', 0 );
			update_option( 'use-twitter-analytics', 0 );
			update_option( 'no-analytics', 1 );
		}
		update_option( 'wptp_twitter_per_user_api', ( isset( $_POST['wptp_twitter_per_user_api']  )? $_POST['wptp_twitter_per_user_api']:0 ) );
		$message .= 'Advanced Options Updated Successfully';
	}
	include('twitter_config_content.php');	
	
}

?>