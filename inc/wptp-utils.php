<?php 
if ( ! defined( 'ABSPATH' ) ) exit; 
function wptp_mailer( $subject, $body ) {
	$use_email = true;
	if ( $use_email ) {
		wp_mail( WPT_DEBUG_ADDRESS, $subject, $body, WPT_FROM );
	} else {
		$debug = get_option( 'wpt_debug' );
		$debug[date( 'Y-m-d H:i:s' )] = array( $subject, $body );
		update_option( 'wpt_debug', $debug );
	}
}

function wptp_json_data( $url, $array=true ) {
	$input = wptp_get_url( $url );
	$obj = json_decode( $input, $array );
	if ( function_exists( 'json_last_error' ) ) {
		try {
			if ( is_null( $obj ) ) {
				switch ( json_last_error() ) {
					case JSON_ERROR_DEPTH :
						$msg = ' - Maximum stack depth exceeded';
						break;
					case JSON_ERROR_STATE_MISMATCH :
						$msg = ' - Underflow or the modes mismatch';
						break;
					case JSON_ERROR_CTRL_CHAR :
						$msg = ' - Unexpected control character found';
						break;
					case JSON_ERROR_SYNTAX :
						$msg = ' - Syntax error, malformed JSON';
						break;
					case JSON_ERROR_UTF8 :
						$msg = ' - Malformed UTF-8 characters, possibly incorrectly encoded';
						break;
					default :
						$msg = ' - Unknown error';
						break;
				}
				throw new Exception($msg);
			}
		} catch ( Exception $e ) {
			return $e -> getMessage();
		}
	}
	return $obj;
}			
function wptp_post_attachment($post_ID) {
	if ( has_post_thumbnail( $post_ID ) ) {
		$attachment = get_post_thumbnail_id( $post_ID );
		return $attachment;
	} else {
		$args = array(
				'post_type' => 'attachment',
				'numberposts' => 1,
				'post_status' => 'published',
				'post_parent' => $post_ID,
				'post_mime_type'=>'image'
		);
		$attachments = get_posts($args);
		if ($attachments) {
			return $attachments[0]->ID;
		} else {
			return false;
		}
	}
	return false;
}
function wptp_chk_file_write( $file ) {
	$is_writable = false;
	if ( function_exists( 'wp_is_writable' ) ) {
		$is_writable = wp_is_writable( $file );
	} else {
		$is_writable = is_writeable( $file );
	}
	return $is_writable;
}
function wptp_url_validator( $url ) {
    if ( is_string( $url ) ) {
		$url = urldecode( $url );
		return preg_match( '|^http(s)?://[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(/.*)?$|i', $url );
	} else {
		return false;
	}
}

if (!function_exists('mb_substr')) {
	function mb_substr( $str, $start, $count = 'end' ) {
		if ( $start != 0 ) {
			$split = self::mb_substr_split_unicode( $str, intval( $start ) );
			$str = substr( $str, $split );
		}

		if ( $count !== 'end' ) {
			$split = self::mb_substr_split_unicode( $str, intval( $count ) );
			$str = substr( $str, 0, $split );
		}
		return $str;
    }
}
if ( !function_exists( 'filter_var' ) ) {
	function filter_var( $url ) {
		return ( stripos( $url, 'https:' ) !== false || stripos( $url, 'http:' ) !== false )?true:false;
	}
}

if ( !function_exists( 'wptp_strrpos' ) ) {
	function wptp_strrpos( $haystack, $needle, $offset = 0, $encoding = '' ) {
		$needle = preg_quote( $needle, '/' );

		$ar = array();
		preg_match_all( '/' . $needle . '/u', $haystack, $ar, PREG_OFFSET_CAPTURE, $offset );

		if( isset( $ar[0] ) && count( $ar[0] ) > 0 &&
			isset( $ar[0][count( $ar[0] ) - 1][1] ) ) {
			return $ar[0][count( $ar[0] ) - 1][1];
		} else {
			return false;
		}
	}
}
if ( !function_exists( 'str_ireplace' ) ) {
	function str_ireplace( $needle, $str, $haystack ) {
		$needle = preg_quote( $needle, '/' );
		return preg_replace( "/$needle/i", $str, $haystack );
	}
}
if( !function_exists( 'str_split' ) ) {
    function str_split( $string,$string_length=1 ) {
        if( strlen( $string )>$string_length || !$string_length ) {
            do {
                $c = strlen($string);
                $parts[] = substr($string,0,$string_length);
                $string = substr($string,$string_length);
            } while($string !== false);
        } else {
            $parts = array($string);
        }
        return $parts;
    }
}
if ( !function_exists( 'wptp_substr_replace' ) ) {
    function wptp_substr_replace( $string, $replacement, $start, $length = null, $encoding = null ) {
        if ( extension_loaded( 'mbstring' ) === true ) {
            $string_length = (is_null($encoding) === true) ? wptp_strlen($string) : wptp_strlen($string, $encoding);   
            if ( $start < 0 ) {
                $start = max(0, $string_length + $start);
            } else if ( $start > $string_length ) {
                $start = $string_length;
            }
            if ( $length < 0 ) {
                $length = max( 0, $string_length - $start + $length );
            } else if ( ( is_null( $length ) === true ) || ( $length > $string_length ) ) {
                $length = $string_length;
            }
            if ( ( $start + $length ) > $string_length) {
                $length = $string_length - $start;
            }
            if ( is_null( $encoding ) === true) {
                return mb_substr( $string, 0, $start ) . $replacement . mb_substr( $string, $start + $length, $string_length - $start - $length );
            }
		return mb_substr( $string, 0, $start, $encoding ) . $replacement . mb_substr( $string, $start + $length, $string_length - $start - $length, $encoding );
        }
	return ( is_null( $length ) === true ) ? substr_replace( $string, $replacement, $start ) : substr_replace( $string, $replacement, $start, $length );
    }
}
function wptp_get_url( $url, $method='GET', $body='', $headers='', $return='body' ) {
	$request = new WP_Http;
	$result = $request->request( $url , array( 'method'=>$method, 'body'=>$body, 'headers'=>$headers, 'sslverify'=>false, 'user-agent'=>'WP Twitter Autopost/http://www.joedolson.com/articles/wp-to-twitter/' ) );
	if ( !is_wp_error( $result ) && isset( $result['body'] ) ) {
		if ( $result['response']['code'] == 200 ) {
			if ( $return == 'body' ) {
				return $result['body'];
			} else {
				return $result;
			}
		} else {
			return $result['response']['code'];
		}
	} else {
		return false;
	}
}

if (!function_exists('wptp_strlen')) {
	function wptp_strlen( $str, $enc = '' ) {
		$counts = count_chars( $str );
		$total = 0;
		for( $i = 0; $i < 0x80; $i++ ) {
			$total += $counts[$i];
		}
		for( $i = 0xc0; $i < 0xff; $i++ ) {
			$total += $counts[$i];
		}
		return $total;
	}
}

function wtt_option_selected($field,$value,$type='checkbox') {
	switch ($type) {
		case 'radio':		
		case 'checkbox':
		$result = ' checked="checked"';
		break;
		case 'option':
		$result = ' selected="selected"';
		break;
	}	
	if ($field == $value) {
		$output = $result;
	} else {
		$output = '';
	}
	return $output;
}

function wptp_date_compare( $early,$late ) {
	$modifier = apply_filters( 'wpt_edit_sensitivity', 0 );
	$firstdate = strtotime($early)+$modifier;
	$lastdate = strtotime($late);
	if ($firstdate <= $lastdate ) {
		return 1;
	} else {
		return 0;
	}	
}
