<?php
/*
* Plugin Name: WP Twitter Autopost
* Plugin URI: http://www.vivacityinfotech.net/
* Description: Auto publish your blog posts to Twitter .
* Version: 1.2
* Author: Vivacity InfoTech Pvt. Ltd.
* Author URI: http://www.vivacityinfotech.net/
* 
*/
/*  Copyright 2016  Vivacity InfoTech Pvt. Ltd.

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
if ( ! defined( 'ABSPATH' ) ) exit;
apply_filters( 'debug', 'WP to Twitter Init' );
global $wp_version;
$wp_content_url = content_url();
$wp_content_dir = str_replace( '/plugins/wp-twitter-autopost','',plugin_dir_path( __FILE__ ) );
if ( defined('WP_CONTENT_URL') ) { $wp_content_url = constant('WP_CONTENT_URL');}
if ( defined('WP_CONTENT_DIR') ) { $wp_content_dir = constant('WP_CONTENT_DIR');}

define( 'WPT_DEBUG', false );
define( 'WPT_DEBUG_ADDRESS', 'support@vivacityinfotech.net' );
define( 'WPT_FROM', "From: \"".get_option('blogname')."\" <".get_option('admin_email').">" );
$wp_plugin_url = plugins_url();
include_once( ABSPATH . 'wp-admin/includes/plugin.php' );

require_once( plugin_dir_path(__FILE__).'/twitter_api_auth.php' );
require_once( plugin_dir_path(__FILE__).'/inc/wptp-url-strippers.php' );
require_once( plugin_dir_path(__FILE__).'/inc/wptp-twitter_config.php' );
require_once( plugin_dir_path(__FILE__).'/inc/wptp-utils.php' );
require_once( plugin_dir_path(__FILE__).'/wptp-twitter_tweets.php' );
require_once( plugin_dir_path(__FILE__).'/inc/wptp_build_widget.php' );
require_once( plugin_dir_path(__FILE__).'/inc/wptp_functions.php' );
global $wptp_plugin_ver;
$wptp_plugin_ver = "2.8.6";
$plugin_dir = basename(dirname(__FILE__));
function wptp_is_allowed_cat( $array ) {
	$allowed_categories =  get_option( 'tweet_categories' );
	if ( is_array( $array ) && is_array( $allowed_categories ) ) {
	$common = @array_intersect( $array,$allowed_categories );
		
		if ( count( $common ) >= 1 ) {
			return true;
		} else {
			return false;
		}
	} else {
		return true;
	}
}

function wptp_get_post_info( $post_ID ) {
	$encoding = get_option('blog_charset'); 
	if ( $encoding == '' ) { $encoding = 'UTF-8'; }
	$post = get_post( $post_ID );
	$category_ids = false;
	$values = array();
	$values['id'] = $post_ID;
	// get post author
	$values['postinfo'] = $post;
	$values['authId'] = $post->post_author;
		$postdate = $post->post_date;
		$altformat = "Y-m-d H:i:s";	
		$dateformat = (get_option('wptp_twitter_date_frmat')=='')?get_option('date_format'):get_option('wptp_twitter_date_frmat');
		$thisdate = mysql2date( $dateformat,$postdate );
		$altdate = mysql2date( $altformat, $postdate );
	$values['_postDate'] = $altdate;
	$values['postDate'] = $thisdate;
		$moddate = $post->post_modified;
	$values['_postModified'] = mysql2date( $altformat,$moddate );
	$values['postModified'] = mysql2date( $dateformat,$moddate );
	// get first category
	$category = $cat_desc = null;
	$categories = get_the_category( $post_ID );
	if ( is_array( $categories ) ) {
		if ( count($categories) > 0 ) {
			$category = $categories[0]->cat_name;
			$cat_desc = $categories[0]->description;
		} 
		foreach ($categories AS $cat) {
			$category_ids[] = $cat->term_id;
		}
	} else {
		$category = '';
		$cat_desc = '';
		$category_ids = array();
	}
	$values['categoryIds'] = $category_ids;
	$values['category'] = html_entity_decode( $category, ENT_COMPAT, $encoding );
	$values['cat_desc'] = html_entity_decode( $cat_desc, ENT_COMPAT, $encoding );
		$excerpt_length = get_option( 'wptp_excerpt_post' );
	$post_excerpt = ( trim( $post->post_excerpt ) == "" )?@mb_substr( strip_tags( strip_shortcodes( $post->post_content ) ), 0, $excerpt_length ):@mb_substr( strip_tags( strip_shortcodes( $post->post_excerpt ) ), 0, $excerpt_length );
	$values['postExcerpt'] = html_entity_decode( $post_excerpt, ENT_COMPAT, $encoding );
	$thisposttitle =  stripcslashes( strip_tags( $post->post_title ) );
	if ( $thisposttitle == "" && isset( $_POST['title'] ) ) {
		$thisposttitle = stripcslashes( strip_tags( $_POST['title'] ) );
	}
	$values['postTitle'] = html_entity_decode( $thisposttitle, ENT_COMPAT, $encoding );
	$values['postLink'] = wptp_twitter_api_link( $post_ID );
	$values['blogTitle'] = get_bloginfo( 'name' );
	$values['shortUrl'] = wptp_do_shorten_url( $post_ID );
	$values['postStatus'] = $post->post_status;
	$values['postType'] = $post->post_type;
	$values = apply_filters( 'wpt_post_info',$values, $post_ID );
	return $values;
}

function wptp_do_shorten_url( $post_id ) {
	global $post_ID;
	if ( !$post_id ) { $post_id = $post_ID; }
	$jd_short = get_post_meta( $post_id, '_wp_jd_bitly', true );
	if ( $jd_short == "" ) {$jd_short = get_post_meta( $post_id, '_wp_jd_goo', true );}
	if ( $jd_short == "" ) {$jd_short = get_post_meta( $post_id, '_wp_jd_supr', true );	}
	if ( $jd_short == "" ) {$jd_short = get_post_meta( $post_id, '_wp_jd_wp', true );	}	
	if ( $jd_short == "" ) {$jd_short = get_post_meta( $post_id, '_wp_jd_ind', true );	}		
	if ( $jd_short == "" ) {$jd_short = get_post_meta( $post_id, '_wp_jd_yourls', true );}
	if ( $jd_short == "" ) {$jd_short = get_post_meta( $post_id, '_wp_jd_url', true );}
	if ( $jd_short == "" ) {$jd_short = get_post_meta( $post_id, '_wp_jd_joturl', true );}	
	if ( $jd_short == "" ) {$jd_short = get_post_meta( $post_id, '_wp_jd_target', true );}
	if ( $jd_short == "" ) {$jd_short = get_post_meta( $post_id, '_wp_jd_clig', true );}	
	return $jd_short;
}

function wptp_post_media_get( $post_ID, $post_info=array() ) {
	$return = false;
	if ( isset( $post_info['wpt_image'] ) && $post_info['wpt_image'] == 1 ) return $return;

		if ( has_post_thumbnail( $post_ID ) || wptp_post_attachment( $post_ID ) ) {
			$return = true;
		}

return apply_filters( 'wpt_upload_media', $return, $post_ID );
	

}

function wptp_cat_limit( $post_type, $post_info, $post_ID ) {
	$post_type_cats = get_object_taxonomies( $post_type );
	$continue = true; 					
	if ( in_array( 'category', $post_type_cats ) ) { 
	// 'category' is assigned to this post type, so apply filters.
		if ( get_option('jd_twit_cats') == '1' ) {
			$continue = ( !wptp_is_allowed_cat( $post_info['categoryIds'] ) )?true:false;
		} else {
			$continue = ( wptp_is_allowed_cat( $post_info['categoryIds'] ) )?true:false;
		}
	}
	
	$continue = ( get_option('limit_categories') == '0' )?true:$continue;
	$args = array( 'type'=>$post_type, 'info'=>$post_info, 'id'=>$post_ID );
	return apply_filters( 'wpt_filter_terms', $continue, $args );
}

function wptp_twitter_tweet( $post_ID, $type='instant' ) {
	
	if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE || wp_is_post_revision($post_ID) ) { return $post_ID; }
	wptp_chk_ver();
	$jd_tweet_this = get_post_meta( $post_ID, '_jd_tweet_this', true );
	$newpost = $oldpost = $is_inline_edit = false;
	$sentence = $template = '';
	if ( get_option('wptp_post_quick_updates') != 1 ) {
		if ( isset($_POST['_inline_edit']) || isset( $_REQUEST['bulk_edit'] ) ) { return; }
	} else {
		if ( isset($_POST['_inline_edit']) || isset( $_REQUEST['bulk_edit'] ) ) { $is_inline_edit = true; }
	}
	
	if ( get_option('wptp_send_tweet_default') == 0 ) { 
		$test = ( $jd_tweet_this != 'no')?true:false;
	} else { 
		$test = ( $jd_tweet_this == 'yes')?true:false;
	}
	if ( $test ) { // test switch: depend on default settings.
		$post_info = wptp_get_post_info( $post_ID );
		$media = wptp_post_media_get( $post_ID, $post_info );
	
		$filter = apply_filters( 'wpt_filter_post_data', false, $_POST );
		if ( $filter ) {
			return false;
		}
		$post_type = $post_info['postType'];
		if ( $type == 'future' || get_post_meta( $post_ID, 'wpt_publishing' ) == 'future' ) { 
			$new = 1; 
			
			delete_post_meta( $post_ID, 'wpt_publishing' );
		} else {
			
			$new = wptp_date_compare( $post_info['_postModified'], $post_info['_postDate'] );
		}
		
		if ( $new == 0 && ( isset( $_POST['edit_date'] ) && $_POST['edit_date'] == 1 && !isset( $_POST['save'] ) ) ) { $new = 1; }
		
		$post_type_settings = get_option('wptp_post_types');
		$post_types = array_keys($post_type_settings);
		if ( in_array( $post_type, $post_types ) ) {
			
			$continue = wptp_cat_limit( $post_type, $post_info, $post_ID );
			if ( $continue == false ) { return; }
			
			$cT = get_post_meta( $post_ID, '_wptp_twitter_data', true );
			if ( isset( $_POST['_wptp_twitter_data'] ) && $_POST['_wptp_twitter_data'] != '' ) { $cT = $_POST['_wptp_twitter_data']; }
			$customTweet = ( $cT != '' )?stripcslashes( trim( $cT ) ):'';
			
			if ( $new == 0 || $is_inline_edit == true ) {
						
				if ( get_option( 'wptp_tweet_edit_action' ) == 1 ) {
					$jd_tweet_this = apply_filters( 'wpt_tweet_this_edit', $jd_tweet_this, $_POST );
					if ( $jd_tweet_this != 'yes' ) {
						
						return;
					} 
				}
				
				if ( $post_type_settings[$post_type]['post-edited-update'] == '1' ) {
					$nptext = stripcslashes( $post_type_settings[$post_type]['post-edited-text'] );
					$oldpost = true;
				}
			} else {
							
				if ( $post_type_settings[$post_type]['post-published-update'] == '1' ) {
					$nptext = stripcslashes( $post_type_settings[$post_type]['post-published-text'] );			
					$newpost = true;
				}
			}
			if ( $newpost || $oldpost ) {
				$template = ( $customTweet != "" ) ? $customTweet : $nptext;
				$sentence = wptp_trim_tweets( $template, $post_info, $post_ID );
				
				
			}
			if ( $sentence != '' ) {
				
					$tweet = wptp_post_to_twtrAPI( $sentence, false, $post_ID, $media );
				
				if ( $tweet == false ) {
					update_option( 'wp_twitter_failure','1' );
				}
			}
		} else {
				
			return $post_ID;
		}
	}
	return $post_ID;
}

// Add Tweets on links in Blogroll
function wptp_twitter_lnk( $link_ID )  {
	wptp_chk_ver();
	global $wptp_plugin_ver;
	$thislinkprivate = $_POST['link_visible'];
	if ($thislinkprivate != 'N') {
		$thislinkname = stripcslashes( $_POST['link_name'] );
		$thispostlink =  $_POST['link_url'] ;
		$thislinkdescription =  stripcslashes( $_POST['link_description'] );
		$sentence = stripcslashes( get_option( 'newlink-published-text' ) );
		$sentence = str_ireplace("#title#",$thislinkname,$sentence);
		$sentence = str_ireplace("#description#",$thislinkdescription,$sentence);		 

		if (wptp_strlen( $sentence ) > 118) {
			$sentence = mb_substr($sentence,0,114) . '...';
		}
		$shrink = apply_filters( 'wptt_shorten_link', $thispostlink, $thislinkname, false, 'link' );
			if ( stripos($sentence,"#url#") === FALSE ) {
				$sentence = $sentence . " " . $shrink;
			} else {
				$sentence = str_ireplace("#url#",$shrink,$sentence);
			}						
			if ( $sentence != '' ) {
				$tweet = wptp_post_to_twtrAPI( $sentence, false, $link_ID );
				if ( $tweet == false ) { update_option('wp_twitter_failure','2'); }
			}
		return $link_ID;
	} else {
		return;
	}
}

function wptp_build_hashtags( $post_ID ) {
	$hashtags = '';
	$term_meta = $t_id = false;
	$max_tags = get_option( 'wptp_max_num_tags' );
	$max_characters = get_option( 'wptp_max_chars_disp' );
	$max_characters = ( $max_characters == 0 || $max_characters == "" )?100:$max_characters + 1;
	if ($max_tags == 0 || $max_tags == "") { $max_tags = 100; }
		$tags = get_the_tags( $post_ID );
		if ( $tags > 0 ) {
		$i = 1;
			foreach ( $tags as $value ) {
				if ( get_option('wptp_tag_usuage') == 'slug' ) {
					$tag = $value->slug;
				} else {
					$tag = $value->name;
				}
				$strip = get_option( 'wptp_remove_noalphnum' );
				$search = "/[^\p{L}\p{N}\s]/u";
				$replace = get_option( 'wptp_replace_chars_disp' );
				$replace = ( $replace == "[ ]" || $replace == "" )?"":$replace;
				$tag = str_ireplace( " ",$replace,trim( $tag ) );
				$tag = preg_replace( '/[\/]/',$replace,$tag ); // remove forward slashes.
				if ($strip == '1') { $tag = preg_replace( $search, $replace, $tag ); }
				switch ( $term_meta ) {
					case 1 : $newtag = "#$tag"; break;
					case 2 : $newtag = "$$tag"; break;
					case 3 : $newtag = ''; break;
					default: $newtag = apply_filters( 'wpt_tag_default', "#", $t_id ).$tag;
				}
				if ( wptp_strlen( $newtag ) > 2 && (wptp_strlen( $newtag ) <= $max_characters) && ($i <= $max_tags) ) {
					$hashtags .= "$newtag ";
					$i++;
				}
			}
		}
	$hashtags = trim( $hashtags );
	if ( wptp_strlen( $hashtags ) <= 1 ) { $hashtags = ""; }
	return $hashtags;	
}

add_action('admin_menu','wptp_twitter_box_add');

function wptp_twitter_box_add() {
	wptp_chk_ver();
	// add Twitter panel to post types where it's enabled.
	$wptp_post_type = get_option('wptp_post_types');
	
	if ( function_exists( 'add_meta_box' )) {
		if ( is_array( $wptp_post_type ) ) {
			foreach ($wptp_post_type as $key=>$value) {
				if ( $value['post-published-update'] == 1 || $value['post-edited-update'] == 1 ) {
					add_meta_box( 'wp2t','WP Twitter Autopost', 'wptp_twitter_box_add_inner', $key, 'side' );
				}
			}
		}
	}
}

function wptp_twitter_box_add_inner( $post ) {
	if ( current_user_can( 'wpt_can_tweet' ) ) {
		$is_pro = 'free';
		echo "<div class='wp-to-twitter $is_pro'>";

		$tweet_status = '';
		$options = get_option('wptp_post_types');
		if ( is_object( $post ) ) {
			$type = $post->post_type;
			$status = $post->post_status;
			$post_id = $post->ID;
		}
		$jd_tweet_this = get_post_meta( $post_id, '_jd_tweet_this', true );
		if ( !$jd_tweet_this ) { 
			$jd_tweet_this = ( get_option( 'wptp_send_tweet_default' ) == '1' ) ? 'no':'yes'; 
		}
		if ( isset( $_GET['action'] ) && $_GET['action'] == 'edit' && get_option( 'wptp_tweet_edit_action' ) == '1' ) { $jd_tweet_this = 'no'; }
		if ( isset( $_REQUEST['message'] ) && $_REQUEST['message'] != 10 ) { // don't display when draft is updated or if no message
			if ( !( ( $_REQUEST['message'] == 1 ) && ( $status == 'publish' && $options[$type]['post-edited-update'] != 1 ) ) ) {
				$log = wptp_logger( 'wptp_status_notifier', $post_id );
				
				$class = ( $log != 'Tweet posted successfully to your linked twitter account.' ) ? 'error' : 'updated' ;
				if ( $log != '' ) {
					echo "<div class='$class'><p>".wptp_logger( 'wptp_status_notifier', $post_id )."</p></div>";
				}
			}
		}
		$previous_tweets = get_post_meta ( $post_id, '_wptp_twitter_api', true );
		$failed_tweets = get_post_meta( $post_id, '_wpt_failed' );
		$tweet = esc_attr( stripcslashes( get_post_meta( $post_id, '_wptp_twitter_data', true ) ) );
		$tweet = apply_filters( 'wpt_user_text', $tweet, $status );
		$jd_template = ( $status == 'publish' )?$options[$type]['post-edited-text']:$options[$type]['post-published-text'];

		if ( $status == 'publish' && $options[$type]['post-edited-update'] != 1 ) {
			$tweet_status = sprintf('Tweeting %s edits is disabled.', $type );
		}
		
		
		if ( $tweet_status != '' ) { echo "<p class='disabled'>$tweet_status</p>"; } 
		if ( current_user_can( 'wpt_twitter_custom' ) || current_user_can('update_core') ) { ?>
			<p class='jtw'>
			<label for="jtw">Custom Twitter Post</label><br /><textarea class="attachmentlinks" name="_wptp_twitter_data" id="jtw" rows="2" cols="60"><?php echo esc_attr( $tweet ); ?></textarea>
			</p>
			<?php
			$jd_expanded = $jd_template;
				
			?>
			<p class='template'>Your template: <code><?php echo stripcslashes( $jd_expanded ); ?></code></p>
			<?php 
			if ( get_option('wptp_keyword_format_config') == 2 ) {
				$custom_keyword = get_post_meta( $post_id, '_yourls_keyword', true );
				echo "<label for='yourls_keyword'>YOURLS Custom Keyword</label> <input type='text' name='_yourls_keyword' id='yourls_keyword' value='$custom_keyword' />";
			}
		} else { ?>
			<input type="hidden" name='_wptp_twitter_data' value='<?php echo esc_attr($tweet); ?>' />
			<?php 
		} 
		if ( current_user_can('update_core') ) {
			$nochecked = ( $jd_tweet_this == 'no' )?' checked="checked"':'';
			$yeschecked = ( $jd_tweet_this == 'yes' )?' checked="checked"':'';
			?>
			<p><input type="radio" name="_jd_tweet_this" value="no" id="jtn"<?php echo $nochecked; ?> /> <label for="jtn">Don't Tweet this post.</label> <input type="radio" name="_jd_tweet_this" value="yes" id="jty"<?php echo $yeschecked; ?> /> <label for="jty">Tweet this post.</label></p>
			<?php 
			} else { 
			?>
			<input type='hidden' name='_jd_tweet_this' value='<?php echo $jd_tweet_this; ?>' />
			<?php 
		} 
		?>
		<div class='wpt-options'>
				
		</div>		
		
		<?php wptp_disp_tweets( $previous_tweets, $failed_tweets ); ?>
		</div>
		<?php
	} else { 
		echo 'Your role does not have the ability to Post Tweets from this site.'; ?> <input type='hidden' name='_jd_tweet_this' value='no' /> <?php
	}
} 

function wptp_disp_tweets( $previous_tweets, $failed_tweets ) {
	if ( !is_array( $previous_tweets ) && $previous_tweets != '' ) { $previous_tweets = array( 0=>$previous_tweets ); }
	if ( ! empty( $previous_tweets ) || ! empty( $failed_tweets ) ) { ?>
		<hr>
		
		<p class='error'><em>Failed Tweets:</em></p>
		<ul>
		<?php
			$list = false;
			if ( is_array( $failed_tweets ) ) {
				foreach ( $failed_tweets as $failed_tweet ) {
					if ( !empty($failed_tweet) ) {
						$ft = $failed_tweet['sentence'];
						$reason = $failed_tweet['code'];
						$error = $failed_tweet['error'];
						$list = true;
						echo "<li> <code>Error: $reason</code> $ft <a href='http://twitter.com/intent/tweet?text=".urlencode($ft)."'>Tweet this</a><br /><em>$error</em></li>";
					}
				}
			}
			if ( !$list ) { echo "<li>No failed tweets yet.</li>"; }
		?>
		</ul>
		<?php
	//	echo "<div>".$hidden_fields."</div>";
		if ($list ) {
			echo "<p><input type='checkbox' name='wpt_clear_history' id='wptch' value='clear' /> <label for='wptch'>Delete Tweet History</label></p>";
		}
	}
}

function wptp_enqueue_admin_scripts( $hook ) {
global $current_screen;
	if ( $current_screen->base == 'post' || $current_screen->id == 'wp-tweets-pro_page_wp-to-twitter-schedule' ) {
		wp_enqueue_script(  'charCount', plugins_url( 'wp-twitter-autopost/js/jquery.charcount.js'), array('jquery') );
	}
	//echo $current_screen->id;
	wp_register_script( 'select.js', plugins_url( 'js/select.js', __FILE__ ), array( 'jquery' ) );
	wp_enqueue_script( 'select.js' );
	if ( $current_screen->id == 'settings_page_wp-to-twitter/wp-to-twitter' || $current_screen->id == 'toplevel_page_wp-tweets-pro'  ) {
		
		wp_register_script( 'wpt.tabs', plugins_url( 'js/tabs.js', __FILE__ ), array( 'jquery' ) );
		wp_enqueue_script( 'wpt.tabs' );
		wp_localize_script( 'wpt.tabs', 'firstItem', 'wpt_post' );
		wp_enqueue_script( 'dashboard' );		
	}
}
add_action( 'admin_enqueue_scripts', 'wptp_enqueue_admin_scripts', 10, 1 );

function wptp_load_admin_script( $hook ) {
global $current_screen;
if ( $current_screen->base == 'post' || $current_screen->id == 'wp-tweets-pro_page_wp-to-twitter-schedule' ) {
	wp_register_style( 'wptp-style_admin', plugins_url('css/style_admin_post.css',__FILE__) );
	wp_enqueue_style('wptp-style_admin');
	if ( $current_screen->base == 'post' ) {
		$allowed = 140;
	} else {
		$allowed = ( wptp_ssl_chk( home_url() ) )?137:138;		
	}
		$first = '#notes'; 
	echo "
<script type='text/javascript'>
	jQuery(document).ready(function(\$){	
		\$('#jtw').charCount( { allowed: $allowed, counterText: '".'Characters left: '."' } );
	});
	jQuery(document).ready(function(\$){
		\$('#side-sortables .tabs a[href=\"$first\"]').addClass('active');
		\$('#side-sortables .wptab').not('$first').hide();
		\$('#side-sortables .tabs a').on('click',function(e) {
			e.preventDefault();
			\$('#side-sortables .tabs a').removeClass('active');
			\$(this).addClass('active');
			var target = $(this).attr('href');
			\$('#side-sortables .wptab').not(target).hide();
			\$(target).show();
		});
	});
</script>
<style type='text/css'>
#wp2t h3 span { padding-left: 30px; background: url(".plugins_url('wp-twitter-autopost/images/twitter-bird-light-bgs.png').") left 50% no-repeat; }
</style>";
	}
}
add_action( 'admin_head', 'wptp_load_admin_script' );

function wptp_post_to_twitter( $id ) {
	if ( empty($_POST) || ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) || wp_is_post_revision($id) || isset($_POST['_inline_edit'] ) || ( defined('DOING_AJAX') && DOING_AJAX ) || !wpt_in_post_type( $id ) ) { return; }
	if ( isset( $_POST['_yourls_keyword'] ) ) {
		$yourls = $_POST[ '_yourls_keyword' ];
		$update = update_post_meta( $id, '_yourls_keyword', $yourls );
	}
	if ( isset( $_POST[ '_wptp_twitter_data' ] ) ) {
		$jd_twitter = $_POST[ '_wptp_twitter_data' ];
		$update = update_post_meta( $id, '_wptp_twitter_data', $jd_twitter );
	} 
	if ( isset( $_POST[ '_wptp_twitter_api' ] ) && $_POST['_wptp_twitter_api'] != '' ) {
		$jd_wp_twitter = $_POST[ '_wptp_twitter_api' ];
		$update = update_post_meta( $id, '_wptp_twitter_api', $jd_wp_twitter );
	}
	if ( isset( $_POST[ '_jd_tweet_this' ] ) ) {
		$jd_tweet_this = ( $_POST[ '_jd_tweet_this' ] == 'no')?'no':'yes';
		$update = update_post_meta( $id, '_jd_tweet_this', $jd_tweet_this );
	} else {
		if ( isset($_POST['_wpnonce'] ) ) {
			$wptp_post_tweet_default = ( get_option( 'wptp_send_tweet_default' ) == 1 )?'no':'yes';
			$update = update_post_meta( $id, '_jd_tweet_this', $wptp_post_tweet_default );
		}
	}
	if ( isset( $_POST['wpt_clear_history'] ) && $_POST['wpt_clear_history'] == 'clear' ) {
		delete_post_meta( $id, '_wpt_failed' );
		delete_post_meta( $id, '_wptp_twitter_api' );
	}
	$update = apply_filters( 'wpt_insert_post', $_POST, $id );
 
		
}

function wptp_twitter_user_data() {
	global $user_ID;
	get_currentuserinfo();
	if ( current_user_can( 'wpt_twitter_oauth' ) || current_user_can('update_core') ) {
		$user_edit = ( isset($_GET['user_id']) )?(int) $_GET['user_id']:$user_ID; 

		$is_enabled = get_user_meta( $user_edit, 'wptp_user_act',true );
		$twitter_username = get_user_meta( $user_edit, 'wptp_twitter_username',true );
		$wpt_remove = get_user_meta( $user_edit, 'wpt-remove', true );
		?>
		<h3>WP Tweets User Settings</h3>
		<?php if ( function_exists('wpt_connect_oauth_message') ) { wpt_connect_oauth_message( $user_edit ); } ?>
		<table class="form-table">
		<tr>
			<th scope="row">Use My Twitter Username</th>
			<td><input type="radio" name="wptp_user_act" id="wptp_user_act-3" value="mainAtTwitter"<?php if ($is_enabled == "mainAtTwitter") { echo " checked='checked'"; } ?> /> <label for="wptp_user_act-3">Tweet my posts with an @ reference to my username.</label><br />
			<input type="radio" name="wptp_user_act" id="wptp_user_act-4" value="mainAtTwitterPlus"<?php if ($is_enabled == "mainAtTwitterPlus") { echo " checked='checked'"; } ?> /> <label for="wptp_user_act-3">Tweet my posts with an @ reference to both my username and to the main site username.</label>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="wptp_twitter_username">Your Twitter Username</label></th>
			<td><input type="text" name="wptp_twitter_username" id="wptp_twitter_username" value="<?php echo esc_attr( $twitter_username ); ?>" />Enter your own Twitter username.</td>
		</tr>
		<tr>
			<th scope="row"><label for="wpt-remove">Hide account name in Tweets</label></th>
			<td><input type="checkbox" name="wpt-remove" id="wpt-remove" value="on"<?php if ( $wpt_remove == 'on' ) { echo ' checked="checked"'; } ?> /> Do not display my account in the #account# template tag.</td>
		</tr>
		<?php if ( !function_exists('wpt_pro_exists') ) { add_filter( 'wpt_twitter_user_fields',create_function('','return;') ); } ?>
		<?php echo apply_filters('wpt_twitter_user_fields',$user_edit ); ?>
		</table>
		<?php 
		if ( function_exists('wpt_schedule_tweet') ) {
			if ( function_exists('wptp_connect_twtr') ) { wptp_connect_twtr( $user_edit ); }
		}
	}
}

function wptp_user_codes( $sentence, $post_ID ) {
	$pattern = '/([([\[\]?)([A-Za-z0-9-_])*(\]\]]?)+/';
	$params = array(0=>"[[",1=>"]]");
	preg_match_all($pattern,$sentence, $matches);
	if ($matches && is_array($matches[0])) {
		foreach ($matches[0] as $value) {
			$shortcode = "$value";
			$field = str_replace($params, "", $shortcode);
			$custom = apply_filters( 'wpt_custom_shortcode',strip_tags(get_post_meta( $post_ID, $field, TRUE )), $post_ID, $field );
			$sentence = str_replace( $shortcode, $custom, $sentence );
		}
		return $sentence;
	} else {
		return $sentence;
	}
}

function wptp_save_twitter_user_data(){
	global $user_ID;
	get_currentuserinfo();
	if ( isset($_POST['user_id']) ) {
		$edit_id = (int) $_POST['user_id']; 
	} else {
		$edit_id = $user_ID;
	}
	$enable = ( isset($_POST['wptp_user_act']) )?$_POST['wptp_user_act']:'';
	$username = ( isset($_POST['wptp_twitter_username']) )?$_POST['wptp_twitter_username']:'';
	$wpt_remove = ( isset($_POST['wpt-remove']) )?'on':'';
	update_user_meta($edit_id ,'wptp_user_act' , $enable );
	update_user_meta($edit_id ,'wptp_twitter_username' , $username );
	update_user_meta($edit_id ,'wpt-remove' , $wpt_remove );
	//WPT PRO
	apply_filters( 'wpt_save_user', $edit_id, $_POST );
}

function jd_addTwitterAdminPages() {
    if ( function_exists( 'add_options_page' ) && !function_exists( 'wpt_pro_functions') ) {
		 $plugin_page = add_menu_page( 'WP Twitter Autopost', 'WP Twitter Autopost', 'manage_options', 'wp-twitter-autopost', 'wptp_config_update' );
		 add_submenu_page( 'wp-twitter-autopost', 'WP Twitter Autopost', 'Status Updates', 'manage_options', 'template_sel', 'templates_sel' );
		 add_submenu_page( 'wp-twitter-autopost', 'WP Twitter Autopost - Url Shortner ', 'Url Shortner Settings', 'manage_options', 'url_shortner_settings', 'wp_url_shortner_settings' );
		 add_submenu_page( 'wp-twitter-autopost', 'WP Twitter Autopost - Advanced Setup', 'Advanced Settings', 'manage_options', 'adv_settings', 'adv_settings' );
    }
}
add_action( 'admin_head', 'jd_addTwitterAdminStyles' );
function jd_addTwitterAdminStyles() {
	if ( isset($_GET['page']) && ( $_GET['page'] == "template_sel" || $_GET['page'] == "wp-twitter-autopost" || $_GET['page'] == "url_shortner_settings" || $_GET['page'] == "adv_settings" || $_GET['page'] == "wp-to-twitter-errors" ) ) {
		wp_enqueue_style( 'wpt-styles', plugins_url( '/wp-twitter-autopost/styles.css' ) );
	}
}

function jd_plugin_action($links, $file) {
	if ( $file == plugin_basename(dirname(__FILE__).'/wp-twitter-autopost.php') ) {
		$admin_url = admin_url('options-general.php?page=wp-twitter-autopost');

		$links[] = "<a href='$admin_url'>Settings</a>";
	}
	return $links;
}
add_filter('plugin_action_links', 'jd_plugin_action', -10, 2);

if ( get_option( 'jd_individual_twitter_users')=='1') {
	add_action( 'show_user_profile', 'wptp_twitter_user_data' );
	add_action( 'edit_user_profile', 'wptp_twitter_user_data' );
	add_action( 'profile_update', 'wptp_save_twitter_user_data');
}

$admin_url = ( is_plugin_active('wp-tweets-pro/wpt-pro-functions.php') )?admin_url('admin.php?page=wp-tweets-pro'):admin_url('options-general.php?page=wp-twitter-autopost');

add_action( 'in_plugin_update_message-wp-twitter-autopost', 'wpt_plugin_update_message' );
function wpt_plugin_update_message() {
	global $wptp_plugin_ver;
	$note = '';
	define('WPT_PLUGIN_README_URL',  'http://svn.wp-plugins.org/wp-twitter-autopost/trunk/readme.txt');
	$response = wp_remote_get( WPT_PLUGIN_README_URL, array ('user-agent' => 'WordPress/WP to Twitter' . $wptp_plugin_ver . '; ' . get_bloginfo( 'url' ) ) );
	if ( ! is_wp_error( $response ) || is_array( $response ) ) {
		$data = $response['body'];
		$bits=explode('== Upgrade Notice ==',$data);
		$note = '<div id="wpt-upgrade"><p><strong style="color:#c22;">Upgrade Notes:</strong> '.nl2br(trim($bits[1])).'</p></div>';
	} else {
		printf('<br /><strong>Note:</strong> Please review the <a class="thickbox" href="%1$s">changelog</a> before upgrading.plugin-install.php?tab=plugin-information&amp;plugin=wp-to-twitter&amp;TB_iframe=true&amp;width=640&amp;height=594');
	}
	echo $note;
}

if ( get_option( 'jd_twit_blogroll' ) == '1' ) {
	add_action( 'add_link', 'wptp_twitter_lnk' );
}

add_action( 'save_post', 'wpt_twit', 16 );
add_action( 'save_post', 'wptp_post_to_twitter', 10 ); 

function wpt_in_post_type( $id ) {
	$post_type_settings = get_option('wptp_post_types');
	$post_types = array_keys($post_type_settings);
	$type = get_post_type( $id );
	if ( in_array( $type, $post_types ) ) {
		if ( $post_type_settings[$type]['post-edited-update'] == '1' || $post_type_settings[$type]['post-published-update'] == '1' ) {
			return true;
		}
	}
	return false;
}

add_action( 'future_to_publish', 'wpt_future_to_publish', 16 );
function wpt_future_to_publish( $post ) {
	$id = $post->ID;
	if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE || wp_is_post_revision( $id ) || !wpt_in_post_type( $id ) ) { return; }
	wpt_twit_future( $id );
}

function wpt_twit( $id ) {
	if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE || wp_is_post_revision( $id ) || !wpt_in_post_type( $id ) ) return;
	$post = get_post( $id );
	if ( $post->post_status != 'publish' ) return;
	wpt_twit_instant( $id );
}
add_action( 'xmlrpc_publish_post', 'wpt_twit_xmlrpc' ); 
add_action( 'publish_phone', 'wpt_twit_xmlrpc' );	

function wpt_twit_future( $id ) {
	set_transient( '_wpt_twit_future', $id, 10 );
	if ( get_transient ( '_wpt_twit_instant' ) && get_transient( '_wpt_twit_instant' ) == $id ) {
		delete_transient( '_wpt_twit_instant' );
		return;
	}	
	wptp_twitter_tweet( $id, 'future' );
}
function wpt_twit_instant( $id ) {
	set_transient( '_wpt_twit_instant', $id, 10 );

	if ( get_transient ( '_wpt_twit_future' ) && get_transient( '_wpt_twit_future' ) == $id ) {
		delete_transient( '_wpt_twit_future' );
		return;
	}
	if ( get_transient ( '_wpt_twit_xmlrpc' ) && get_transient( '_wpt_twit_xmlrpc' ) == $id ) {
		delete_transient( '_wpt_twit_xmlrpc' );
		return;
	}	
	wptp_twitter_tweet( $id, 'instant' );
}
function wpt_twit_xmlrpc( $id ) {
	set_transient( '_wpt_twit_xmlrpc', $id, 10 );
	if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE || wp_is_post_revision( $id ) || !wpt_in_post_type( $id )  ) { return $id; }
	wptp_twitter_tweet( $id, 'xmlrpc' );
}

add_action( 'admin_menu', 'jd_addTwitterAdminPages' );
add_action('wp_enqueue_scripts', 'wpt_stylesheet');
function wpt_stylesheet() {
	$apply = apply_filters( 'wpt_enqueue_feed_styles', true );
	if ( $apply ) {
		$file = apply_filters( 'wpt_feed_stylesheet', plugins_url( 'css/twitter-post.css', __FILE__ ) );
		wp_register_style( 'wpt-twitter-feed', $file );
		wp_enqueue_style( 'wpt-twitter-feed' );
	}
}
add_action('admin_head', 'wpt_css');
function wpt_css() {
?>
<style type="text/css">
th#wpt { width: 60px; } 
.wpt_twitter .authorized { padding: 1px 3px; border-radius: 3px; background: #070; color: #fff; }
</style>
<?php	
}
