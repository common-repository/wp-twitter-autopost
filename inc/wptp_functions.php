<?php
function wptp_commments_removed() {
	if ( isset($_GET['dismiss']) ) {
		update_option( 'wptp_removed', 'true' );
	}

}
function wptp_check_twtr_conn( $auth=false ) {
	if ( !function_exists('wtt_oauth_test') ) {
		$oauth = false;
	} else {
		$oauth = wtt_oauth_test( $auth );
	}
	return $oauth;
}
function wptp_chk_ver() {
	global $wptp_plugin_ver;
	$prev_version = get_option( 'wp_autopost_ver' );
	if ( version_compare( $prev_version,$wptp_plugin_ver,"<" ) ) {
		wptp_plug_activate();
	}
}
function wptp_twitter_api_link( $post_ID ) {
	$ex_link = false;
	$tweet_link = get_option('wptp_tweet_custom_link');
	$permalink = get_permalink( $post_ID );
	if ( $tweet_link != '' ) {
		$ex_link = get_post_meta( $post_ID, $tweet_link, true );
	}
	return ( $ex_link ) ? $ex_link : $permalink;
}
function wptp_plug_activate() {
	global $wptp_plugin_ver;
	$prev_version = get_option( 'wp_autopost_ver' );
	update_option( 'wp_autopost_ver',$wptp_plugin_ver );
}

function wptp_log_errors( $id, $auth, $twit, $error, $http_code, $ts ) {
	$http_code = (int) $http_code;
	if ( $http_code != 200 ) {
		add_post_meta( $id, '_wpt_failed', array( 'author'=>$auth, 'sentence'=>$twit, 'error'=>$error,'code'=>$http_code, 'timestamp'=>$ts ) );
	}
}
function wptp_post_to_twtrAPI( $twit, $auth=false, $id=false, $media=false ) {
	if ( !wptp_check_twtr_conn( $auth ) ) {
		$error ='This account is not authorized to post to Twitter.';
		wptp_log_errors( $id, $auth, $twit, $error, '401', time() );
		wptp_log_msg( 'wptp_status_notifier', $id, $error );
		return true;
	} 
	$check = ( !$auth )?get_option('wptp_last_tweet_msg'):get_user_meta( $auth, 'wpt_last_tweet', true );
	if ( $check == $twit ) {

		$error =  'Yo already tweeted this. Twitter requires all Tweets to be unique.';
		wptp_log_errors( $id, $auth, $twit, $error, '403', time() );
		wptp_log_msg( 'wptp_status_notifier', $id, $error );
		return true;
	} else if ( $twit == '' || !$twit ) {

		$error = 'The tweet was blank and could not be sent to Twitter.';
		wptp_log_errors( $id, $auth, $twit, $error, '403', time() );
		wptp_log_msg( 'wptp_status_notifier', $id, $error );
		return true;
	} else {
		$attachment = ( $media ) ? wptp_post_attachment( $id ) : false;
		if ( $attachment ) {
			$meta = wp_get_attachment_metadata($attachment);
			if ( !isset( $meta['width'], $meta['height'] ) ) {

				$attachment = false;
			}
		}
		$api = ( $media && $attachment )?"https://api.twitter.com/1.1/statuses/update_with_media.json":"https://api.twitter.com/1.1/statuses/update.json";
		if ( wtt_oauth_test( $auth ) && ( $connection = wptp_connection_twtr_oauth( $auth ) ) ) {
			if ( $media && $attachment ) {
				$connection->media( $api, array( 'status' => $twit, 'source' => 'wp-to-twitter', 'include_entities' => 'true', 'id'=>$id, 'auth'=>$auth ) );
			} else {
				$connection->post( $api, array( 'status' => $twit, 'source' => 'wp-to-twitter', 'include_entities' => 'true' ) );
			}
			$http_code = ($connection)?$connection->http_code:'failed';
		} else if ( wtt_oauth_test( false ) && ( $connection = wptp_connection_twtr_oauth( false ) ) ) {
			if ( $media ) {
				$connection->media( $api, array( 'status' => $twit, 'source' => 'wp-to-twitter', 'include_entities' => 'true', 'id'=>$id, 'auth'=>$auth ) );
			} else {
				$connection->post( $api, array( 'status' => $twit, 'source' => 'wp-to-twitter', 'include_entities' => 'true'	) );
			}
			$http_code = ($connection)?$connection->http_code:'failed';
		}

		if ( $connection ) {
			if ( isset($connection->http_header['x-access-level']) && $connection->http_header['x-access-level'] == 'read' ) {
				$supplement = sprintf('Your Twitter application does not have read and write permissions. Go to <a href="%s">your Twitter apps</a> to modify these settings.', 'https://dev.twitter.com/apps/' );
			} else { $supplement = '';
			}
			$return = false;
			switch ($http_code) {
				case '200':
					$return = true;
					$error = "200 OK: Success!";
					update_option('wptp_auth_missing', false );
					break;
				case '304':
					$error ="304 Not Modified: There was no new data to return";
					break;
				case '400':
					$error = "400 Bad Request: The request was invalid. This is the status code returned during rate limiting.";
					break;
				case '401':
					$error = "401 Unauthorized: Authentication credentials were missing or incorrect.";
					update_option( 'wptp_auth_missing',"$auth");
					break;
				case '403':
					$error = "403 Forbidden: The request is understood, but has been refused by Twitter. Possible reasons: too many Tweets, same Tweet submitted twice, Tweet longer than 140 characters.";
					break;
				case '404':
					$error = "404 Not Found: The URI requested is invalid or the resource requested does not exist.";
					break;
				case '406':
					$error = "406 Not Acceptable: Invalid Format Specified.";
					break;
				case '422':
					$error = "422 Unprocessable Entity: The image uploaded could not be processed..";
					break;
				case '429':
					$error = "429 Too Many Requests: You have exceeded your rate limits.";
					break;
				case '500':
					$error = "500 Internal Server Error: Something is broken at Twitter.";
					break;
				case '502':
					$error = "502 Bad Gateway: Twitter is down or being upgraded.";
					break;
				case '503':
					$error = "503 Service Unavailable: The Twitter servers are up, but overloaded with requests - Please try again later.";
					break;
				case '504':
					$error = "504 Gateway Timeout: The Twitter servers are up, but the request couldn't be serviced due to some failure within our stack. Try again later.";
					break;
				default:
					$error = "<strong>Code $http_code</strong>: Twitter did not return a recognized response code.";
					break;
			}
			$error .= ($supplement != '')?" $supplement":'';
			$update = ( !$auth )?update_option( 'wptp_last_tweet_msg',$twit ):update_user_meta( $auth, 'wpt_last_tweet',$twit );
			wptp_log_errors( $id, $auth, $twit, $error, $http_code, time() );
			if ( $http_code == '200' ) {
				$jwt = get_post_meta( $id, '_wptp_twitter_api', true );
				if ( !is_array( $jwt ) ){
					$jwt=array();
				}
				$jwt[] = urldecode( $twit );
				if ( empty($_POST) ) {
					$_POST = array();
				}
				$_POST['_wptp_twitter_api'] = $jwt;
				update_post_meta( $id,'_wptp_twitter_api', $jwt );

			}
			if ( !$return ) {
				wptp_log_msg( 'wptp_status_notifier', $id, $error );
			} else {
				wptp_log_msg( 'wptp_status_notifier', $id, 'Tweet posted successfully to your linked twitter account.');
			}
			return $return;
		} else {
			wptp_log_msg( 'wptp_status_notifier', $id,'No Twitter OAuth connection found.');
			return false;
		}
	}
}

function wptp_sanatize( $string ) {
	if ( version_compare( PHP_VERSION, '5.0.0', '>=' ) && function_exists('normalizer_normalize') && 1==2 ) {
		if ( normalizer_is_normalized( $string ) ) {
			return $string;
		}
		return normalizer_normalize( $string );
	} else {
		return preg_replace( '~&([a-z]{1,2})(acute|cedil|circ|grave|lig|orn|ring|slash|th|tilde|uml|mp);~i', '$1', htmlentities( $string, ENT_NOQUOTES, 'UTF-8' ) );
	}
}

function wptp_ssl_chk( $url ) {
	if ( stripos( $url, 'https' ) ) {
		return true;
	} else { return false;
	}
}

function wptp_trim_tweets( $tweet, $post, $post_ID, $retweet=false, $ref=false ) {
	$tweet_length = ( wptp_post_media_get( $post_ID ) ) ? 117 : 139;
	$tweet = trim(wptp_user_codes( $tweet, $post_ID ));
	$shrink = ( $post['shortUrl'] != '' )?$post['shortUrl']:apply_filters( 'wptt_shorten_link', $post['postLink'], $post['postTitle'], $post_ID, false );
	$auth = $post['authId'];
	$title = trim( apply_filters( 'wpt_status', $post['postTitle'], $post_ID, 'title' ) );
	$blogname = trim($post['blogTitle']);
	$excerpt = trim( apply_filters( 'wpt_status', $post['postExcerpt'], $post_ID, 'post' ) );
	$thisposturl = trim($shrink);
	$category = trim($post['category']);
	$cat_desc = trim($post['cat_desc']);
	$user_account = get_user_meta( $auth,'wptp_api_twitter_user_name', true ) ;
	$tags = wptp_build_hashtags( $post_ID );
	$account = get_option('wptp_api_twitter_user_name');
	$date = trim($post['postDate']);
	$modified = trim($post['postModified']);
	if ( get_option( 'wptp_twitter_per_user_api' ) == 1 ) {
		if ( $user_account == '' ) {
			if ( get_user_meta( $auth, 'wptp_user_act',true ) == 'mainAtTwitter' ) {
				$account = stripcslashes(get_user_meta( $auth, 'wptp_twitter_username',true ));
			} else if ( get_user_meta( $auth, 'wptp_user_act',true ) == 'mainAtTwitterPlus' ) {
				$account = stripcslashes(get_user_meta( $auth, 'wptp_twitter_username',true ) . ' @' . get_option( 'wptp_api_twitter_user_name' ));
			}
		} else {
			$account = "$user_account";
		}
	}
	$display_name = get_the_author_meta( 'display_name', $auth );
	$author = ( $account != '' )?"@$account":$display_name;
	$account = ( $account != '' )?"@$account":'';
	$uaccount = ( $user_account != '' )?"@$user_account":"$account";
	$account = str_ireplace( '@@','@',$account );
	$uaccount = str_ireplace( '@@', '@', $uaccount );
	$author = str_ireplace( '@@', '@', $author );

	if ( get_user_meta( $auth, 'wptp_delete_twitter', true ) == 'on' ) {
		$account = '';
	}
	
	$encoding = get_option('blog_charset');
	if ( $encoding == '' ) {
		$encoding = 'UTF-8';
	}

	if ( strpos( $tweet, '#url#' ) === false
			&& strpos( $tweet, '#category#' ) === false
			&& strpos( $tweet, '#date#' ) === false
			&& strpos( $tweet, '#author#' ) === false
			&& strpos( $tweet, '#displayname#' ) === false
			&& strpos( $tweet, '#tags#' ) === false
			&& strpos( $tweet, '#title#' ) === false
			&& strpos( $tweet, '#blog#' ) === false
			&& strpos( $tweet, '#post#' ) === false
			&& strpos( $tweet, '#modified#' ) === false
			&& strpos( $tweet, '#reference#' ) === false
			&& strpos( $tweet, '#account#' ) === false
			&& strpos( $tweet, '#@#' ) === false
			&& strpos( $tweet, '#cat_desc' ) === false
	) {
		
		$post_tweet = mb_substr( $tweet, 0, $tweet_length, $encoding );
		return $post_tweet;
	}

	
	$replace = '' ;
	$search = array( '#account#', '#@#', '#reference#', '#url#', '#title#', '#blog#', '#post#', '#category#', '#cat_desc#', '#date#', '#author#', '#displayname#', '#tags#', '#modified#' );
	$replace = array( $account, $uaccount, $replace, $thisposturl, $title, $blogname, $excerpt, $category, $cat_desc, $date, $author, $display_name, $tags, $modified );
	$post_tweet = str_ireplace( $search, $replace, $tweet );
	$url_strlen = wptp_strlen( urldecode( wptp_sanatize( $thisposturl ) ), $encoding );
	$str_length = wptp_strlen( urldecode( wptp_sanatize( $post_tweet ) ), $encoding );
	if ( $str_length < $tweet_length+1 ) {
		if ( wptp_strlen( wptp_sanatize ( $post_tweet ) ) > $tweet_length+1 ) {
			$post_tweet = mb_substr( $post_tweet,0,$tweet_length,$encoding );
		}
		return $post_tweet; 
	} else {
	
		$length = get_option( 'wptp_excerpt_post' );
		$excerpt_post = array();
		$default_order = array(
				'excerpt'=>0,
				'title'=>1,
				'date'=>2,
				'category'=>3,
				'blogname'=>4,
				'author'=>5,
				'account'=>6,
				'tags'=>7,
				'modified'=>8,
				'@'=>9,
				'cat_desc'=>10
		);
		$excerpt_post['excerpt'] = wptp_strlen( wptp_sanatize( $excerpt ),$encoding );
		$excerpt_post['title'] = wptp_strlen( wptp_sanatize( $title ),$encoding );
		$excerpt_post['category'] = wptp_strlen( wptp_sanatize( $category ),$encoding );
		$excerpt_post['cat_desc'] = wptp_strlen( wptp_sanatize( $cat_desc ),$encoding );
		$excerpt_post['@'] = wptp_strlen( wptp_sanatize( $uaccount ),$encoding );
		$excerpt_post['blogname'] = wptp_strlen( wptp_sanatize( $blogname ),$encoding );
		$excerpt_post['date'] = wptp_strlen( wptp_sanatize( $date ),$encoding );
		$excerpt_post['author'] = wptp_strlen( wptp_sanatize( $author ),$encoding );
		$excerpt_post['account'] = wptp_strlen( wptp_sanatize( $account ),$encoding );
		$excerpt_post['tags'] = wptp_strlen( wptp_sanatize( $tags ),$encoding );
		$excerpt_post['modified'] = wptp_strlen( wptp_sanatize( $modified ),$encoding );
		$tco = ( wptp_ssl_chk( $thisposturl ) )?23:22;
		$order = get_option( 'wptp_tweets_trim_seq',$default_order );
		if ( is_array( $order ) ) {
			asort($order);
			$preferred = array();
			foreach ( $order as $k=>$v ) {
				$preferred[$k] = $excerpt_post[$k];
			}
		} else {
			$preferred = $excerpt_post;
		}
		$diff = ( ($url_strlen - $tco) > 0 )?$url_strlen-$tco:0;
		if ( $str_length > ( $tweet_length+ 1 + $diff ) ) {
			foreach ( $preferred AS $key=>$value ) {
				$str_length = wptp_strlen( urldecode( wptp_sanatize( trim( $post_tweet ) ) ),$encoding );
				if ( $str_length > ( $tweet_length + 1 + $diff ) ) {
					$trim = $str_length - ( $tweet_length + 1 + $diff );
					$old_value = ${
						$key};
						$post_tweet = str_ireplace( $thisposturl, '#url#', $post_tweet );
						if ( $key == 'account' || $key == 'author' || $key == 'category' || $key == 'date' || $key == 'modified' || $key == 'reference' || $key == '@' ) {
							$new_value = '';
						} else if ( $key == 'tags' ) {
							if (wptp_strlen($old_value)-$trim <= 2) {
								$new_value = '';
							} else {
								$new_value = $old_value;
								while ((wptp_strlen($old_value)-$trim) < wptp_strlen($new_value)) {
									$new_value = trim(mb_substr($new_value,0,wptp_strrpos($new_value,'#',$encoding)-1));
								}
							}
						} else {
							$new_value = mb_substr( $old_value,0,-( $trim ),$encoding );
						}
						$post_tweet = str_ireplace( $old_value,$new_value,$post_tweet );
						$post_tweet = str_ireplace( '#url#', $thisposturl, $post_tweet );
				} else {
					if ( wptp_strlen( wptp_sanatize ( $post_tweet ),$encoding ) > ( $tweet_length + 1 + $diff ) ) {
						$post_tweet = mb_substr( $post_tweet,0,( $tweet_length + $diff ),$encoding );
					}
				}
			}
		}
		if ( wptp_strlen( wptp_sanatize( $post_tweet ) ) > $tweet_length + 1 ) {
			$temp = str_ireplace( $thisposturl, '#url#', $post_tweet );
			if ( wptp_strlen( wptp_sanatize( $temp ) ) > ( ( $tweet_length + 1 ) - $tco) && $temp != $post_tweet ) {
				$post_tweet = trim(mb_substr( $temp,0,( ( $tweet_length + 1 ) -$tco),$encoding ));
				$sub_sentence = (strpos( $tweet, '#url#' )===false )?$post_tweet:$post_tweet .' '. $thisposturl;
				$post_tweet = ( strpos( $post_tweet,'#url#' ) === false )?$sub_sentence:str_ireplace( '#url#',$thisposturl,$post_tweet );
			}
		}
	}
	return $post_tweet;
}
?>