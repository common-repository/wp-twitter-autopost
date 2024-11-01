<?php
function wptp_get_user( $twitter_ID=false ) {
	if ( !$twitter_ID ) return;
    $options = array('screen_name' => $twitter_ID );
	$key = get_option('app_consumer_key');
	$secret = get_option('app_consumer_secret');
	$token = get_option('oauth_token');
	$token_secret = get_option('oauth_token_secret');	
    $connection = new wptp_twitterOAuth_class($key, $secret, $token, $token_secret);
    $result = $connection->get( "https://api.twitter.com/1.1/users/show.json?screen_name=$twitter_ID", $options);
	return json_decode($result);
}

add_shortcode( 'get_tweets', 'wptp_get_twitter_feed' );
function wptp_get_twitter_feed( $atts, $content ) {
	extract( shortcode_atts( array( 
			'id' => false,
			'num' => 10,
			'duration' => 3600,
			'replies' => 0,
			'rts' => 1,
			'links' => 1,
			'mentions' => 1,
			'hashtags' => 0,
			'intents' => 1,
			'source' => 0
		), $atts, 'get_tweets' ) );
		$instance = array( 
			'twitter_id' => $id,
			'twitter_num' => $num,
			'twitter_duration' => $duration,
			'twitter_hide_replice' => $replies,
			'twitter_include_rts' => $rts,
			'link_links' => $links,
			'link_mentions' => $mentions,
			'link_hashtags' => $hashtags,
			'intents' => $intents,
			'source' => $source );
	return wptp_twitter_feed( $instance );
}

function wptp_twitter_feed( $instance ) {
	$return = '<div class="wpt-header">';
		$user = wptp_get_user( $instance['twitter_id'] );		
		if ( isset($user->errors) && $user->errors[0]->message ) {
			return "Error: ". $user->errors[0]->message;
		}
		$avatar = $user->profile_image_url_https;
		$name = $user->name;
		$verified = $user->verified;
		$img_alignment = ( is_rtl() )?'wpt-right':'wpt-left';
		$follow_alignment = ( is_rtl() )?'wpt-left':'wpt-right';
		$follow_url = esc_url( 'https://twitter.com/'.$instance['twitter_id'] );
		$follow_button = apply_filters ( 'wpt_follow_button', "<a href='$follow_url' class='twitter-follow-button $follow_alignment' data-width='30px' data-show-screen-name='false' data-size='large' data-show-count='false' data-lang='en'>Follow @$instance[twitter_id]</a>" );
		$return .= "<p>
			$follow_button
			<img src='$avatar' alt='' class='wpt-twitter-avatar $img_alignment' />
			<span class='wpt-twitter-name'>$name</span><br />
			<span class='wpt-twitter-id'><a href='$follow_url'>@$instance[twitter_id]</a></span>
			</p>";
	$return .= '</div>';
	$return .= '<ul>' . "\n";

	$options['exclude_replies'] = ( isset( $instance['twitter_hide_replies'] ) ) ? $instance['twitter_hide_replies'] : false;
	$options['include_rts'] = $instance['twitter_include_rts'];
	$opts['links'] = $instance['link_links'];
	$opts['mentions'] = $instance['link_mentions'];
	$opts['hashtags'] = $instance['link_hashtags'];
	$rawtweets = WPTP_getTweets($instance['twitter_num'], $instance['twitter_id'], $options);

	if ( isset( $rawtweets['error'] ) ) {
		$return .= "<li>".$rawtweets['error']."</li>";
	} else {
		$tweets = array();
		foreach ( $rawtweets as $tweet ) {

		if ( is_object( $tweet ) ) {
			$tweet = json_decode( json_encode( $tweet ), true );
		}
		if ( $instance['source'] ) {
			$source = $tweet['source'];
			$timetweet = sprintf( '<a href="%3$s">about %1$s ago</a> via %2$s', human_time_diff( strtotime( $tweet['created_at'] ) ), $source, "http://twitter.com/$instance[twitter_id]/status/$tweet[id_str]" );
		} else {
			$timetweet = sprintf( '<a href="%2$s">about %1$s ago</a>', human_time_diff( strtotime( $tweet['created_at'] ) ), "http://twitter.com/$instance[twitter_id]/status/$tweet[id_str]" );
		}
		$tweet_classes = wptp_generate_classes( $tweet );
		
		$intents = ( $instance['intents'] )?"<div class='wpt-intents-border'></div><div class='wpt-intents'><a class='wpt-reply' href='https://twitter.com/intent/tweet?in_reply_to=$tweet[id_str]'><span></span>Reply</a> <a class='wpt-retweet' href='https://twitter.com/intent/retweet?tweet_id=$tweet[id_str]'><span></span>Retweet</a> <a class='wpt-favorite' href='https://twitter.com/intent/favorite?tweet_id=$tweet[id_str]'><span></span>Favorite</a></div>":'';
		/** Add tweet to array */
		$tweets[] = '<li class="'.$tweet_classes.'">' . WPTP_tweet_linkify( $tweet['text'], $opts ) . "<br /><span class='wpt-tweet-time'>$timetweet</span> $intents</li>\n";
		}
	}
	if ( is_array( $tweets ) ) {
		foreach( $tweets as $tweet ) {
			$return .= $tweet;
		}
	}
	$return .= '</ul>' . "\n";
	return $return;
}

function WPTP_tweet_linkify( $text, $opts ) {
	$text = ( $opts['links'] == true )?preg_replace( "#(^|[\n ])([\w]+?://[\w]+[^ \"\n\r\t< ]*)#", '\\1<a href="\\2" rel="nofollow">\\2</a>', $text ):$text;
	$text = ( $opts['links'] == true )?preg_replace( "#(^|[\n ])((www|ftp)\.[^ \"\t\n\r< ]*)#", '\\1<a href="http://\\2" rel="nofollow">\\2</a>', $text ):$text;
	$text = ( $opts['mentions'] == true )?preg_replace( '/@(\w+)/', '<a href="http://www.twitter.com/\\1" rel="nofollow">@\\1</a>', $text ):$text;
	$text = ( $opts['hashtags'] == true )?preg_replace( '/#(\w+)/', '<a href="http://search.twitter.com/search?q=\\1" rel="nofollow">#\\1</a>', $text ):$text;
	return $text;
}
function WPTP_getTweets($count = 20, $username = false, $options = false) {

  $config['key'] = get_option('app_consumer_key');
  $config['secret'] = get_option('app_consumer_secret');
  $config['token'] = get_option('oauth_token');
  $config['token_secret'] = get_option('oauth_token_secret');
  $config['screenname'] = get_option('wptp_api_twitter_user_name');
  $config['cache_expire'] = intval( apply_filters( 'wpt_cache_expire', 3600 ) );
  if ($config['cache_expire'] < 1) $config['cache_expire'] = 3600;
  $config['directory'] = plugin_dir_path(__FILE__);
  
  $obj = new wptp_Post_Twitter($config);
  $res = $obj->getTweets($count, $username, $options);
  update_option('wpt_tdf_last_error',$obj->st_last_error);
  return $res;
  
}

function wptp_generate_classes( $tweet ) {
	$classes[] = ( $tweet['favorited'] )?'favorited':'';
	$clasees[] = ( $tweet['retweeted'] )?'retweeted':'';
	$classes[] = ( isset( $tweet['possibly_sensitive'] ) )?'sensitive':'';
	$classes[] = 'lang-'.$tweet['lang'];
	$class = trim( implode( ' ', $classes ) );
	return $class;
}