<?php
if (!class_exists('WPOAuthException')) {

class WPOAuthException extends Exception {
 
}
class WPOAuthToken {

  public $key;
  public $secret;
  function __construct($key, $secret) {
    $this->key = $key;
    $this->secret = $secret;
  }

  function to_string() {
    return "oauth_token=" .
           WPOAuthUtil::urlencode_rfc3986($this->key) .
           "&oauth_token_secret=" .
           WPOAuthUtil::urlencode_rfc3986($this->secret);
  }

  function __toString() {
    return $this->to_string();
  }
}
class WPOAuthConsumer {
	public $key;
	public $secret;

	function __construct($key, $secret, $callback_url=NULL) {
		$this->key = $key;
		$this->secret = $secret;
		$this->callback_url = $callback_url;
	}

	function __toString() {
		return "OAuthConsumer[key=$this->key,secret=$this->secret]";
	}
	}
abstract class WPOAuthSignatureMethod {
 
  abstract public function get_name();

  abstract public function build_signature($request, $consumer, $token);

  public function check_signature($request, $consumer, $token, $signature) {
    $built = $this->build_signature($request, $consumer, $token);
    return $built == $signature;
  }
}

class WPOAuthSignatureMethod_HMAC_SHA1 extends WPOAuthSignatureMethod {
  function get_name() {
    return "HMAC-SHA1";
  }

  public function build_signature($request, $consumer, $token) {
    $base_string = $request->get_signature_base_string();
    $request->base_string = $base_string;

    $key_parts = array(
      $consumer->secret,
      ($token) ? $token->secret : ""
    );

    $key_parts = WPOAuthUtil::urlencode_rfc3986($key_parts);
    $key = implode('&', $key_parts);

    return base64_encode(hash_hmac('sha1', $base_string, $key, true));
  }
}


class WPOAuthSignatureMethod_PLAINTEXT extends WPOAuthSignatureMethod {
  public function get_name() {
    return "PLAINTEXT";
  }

  public function build_signature($request, $consumer, $token) {
    $key_parts = array(
      $consumer->secret,
      ($token) ? $token->secret : ""
    );
    $key_parts = WPOAuthUtil::urlencode_rfc3986($key_parts);
    $key = implode('&', $key_parts);
    $request->base_string = $key;

    return $key;
  }
}

abstract class WPOAuthSignatureMethod_RSA_SHA1 extends WPOAuthSignatureMethod {
  public function get_name() {
    return "RSA-SHA1";
  }

  protected abstract function fetch_public_cert(&$request);


  protected abstract function fetch_private_cert(&$request);

  public function build_signature($request, $consumer, $token) {
    $base_string = $request->get_signature_base_string();
    $request->base_string = $base_string;

    $cert = $this->fetch_private_cert($request);

    $privatekeyid = openssl_get_privatekey($cert);

    $ok = openssl_sign($base_string, $signature, $privatekeyid);

    openssl_free_key($privatekeyid);

    return base64_encode($signature);
  }

  public function check_signature($request, $consumer, $token, $signature) {
    $decoded_sig = base64_decode($signature);

    $base_string = $request->get_signature_base_string();

    $cert = $this->fetch_public_cert($request);

    $publickeyid = openssl_get_publickey($cert);

    $ok = openssl_verify($base_string, $decoded_sig, $publickeyid);

    openssl_free_key($publickeyid);

    return $ok == 1;
  }
}

class WPOAuthRequest {
  private $parameters;
  private $http_method;
  private $http_url;
  public $base_string;
  public static $version = '1.0';
  public static $POST_INPUT = 'php://input';

  function __construct($http_method, $http_url, $parameters=NULL) {
    @$parameters or $parameters = array();
    $parameters = array_merge( WPOAuthUtil::parse_parameters(parse_url($http_url, PHP_URL_QUERY)), $parameters);
    $this->parameters = $parameters;
    $this->http_method = $http_method;
    $this->http_url = $http_url;
  }
  public static function from_request($http_method=NULL, $http_url=NULL, $parameters=NULL) {
    $scheme = (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] != "on")
              ? 'http'
              : 'https';
    @$http_url or $http_url = $scheme .
                              '://' . $_SERVER['HTTP_HOST'] .
                              ':' .
                              $_SERVER['SERVER_PORT'] .
                              $_SERVER['REQUEST_URI'];
    @$http_method or $http_method = $_SERVER['REQUEST_METHOD'];

   
    if (!$parameters) {
      $request_headers = WPOAuthUtil::get_headers();
      $parameters = WPOAuthUtil::parse_parameters($_SERVER['QUERY_STRING']);
      if ($http_method == "POST"
          && @strstr($request_headers["Content-Type"],
                     "application/x-www-form-urlencoded")
          ) {
        $post_data = WPOAuthUtil::parse_parameters(
          file_get_contents(self::$POST_INPUT)
        );
        $parameters = array_merge($parameters, $post_data);
      }
      if (@substr($request_headers['Authorization'], 0, 6) == "OAuth ") {
        $header_parameters = WPOAuthUtil::split_header(
          $request_headers['Authorization']
        );
        $parameters = array_merge($parameters, $header_parameters);
      }

    }

    return new WPOAuthRequest($http_method, $http_url, $parameters);
  }
  public static function from_consumer_and_token($consumer, $token, $http_method, $http_url, $parameters=NULL) {
    @$parameters or $parameters = array();
    $defaults = array("oauth_version" => WPOAuthRequest::$version,
                      "oauth_nonce" => WPOAuthRequest::generate_nonce(),
                      "oauth_timestamp" => WPOAuthRequest::generate_timestamp(),
                      "oauth_consumer_key" => $consumer->key);
    if ($token)
      $defaults['oauth_token'] = $token->key;

    $parameters = array_merge($defaults, $parameters);

    return new WPOAuthRequest($http_method, $http_url, $parameters);
  }

  public function set_parameter($name, $value, $allow_duplicates = true) {
    if ($allow_duplicates && isset($this->parameters[$name])) {
      if (is_scalar($this->parameters[$name])) {
        $this->parameters[$name] = array($this->parameters[$name]);
      }

      $this->parameters[$name][] = $value;
    } else {
      $this->parameters[$name] = $value;
    }
  }

  public function get_parameter($name) {
    return isset($this->parameters[$name]) ? $this->parameters[$name] : null;
  }

  public function get_parameters() {
    return $this->parameters;
  }

  public function unset_parameter($name) {
    unset($this->parameters[$name]);
  }
  public function get_signable_parameters() {
    $params = $this->parameters;
    if (isset($params['oauth_signature'])) {
      unset($params['oauth_signature']);
    }

    return WPOAuthUtil::build_http_query($params);
  }
  public function get_signature_base_string() {
    $parts = array(
      $this->get_normalized_http_method(),
      $this->get_normalized_http_url(),
      $this->get_signable_parameters()
    );

    $parts = WPOAuthUtil::urlencode_rfc3986($parts);

    return implode('&', $parts);
  }
  public function get_normalized_http_method() {
    return strtoupper($this->http_method);
  }
  public function get_normalized_http_url() {
    $parts = parse_url($this->http_url);

    $port   = isset($parts['port']) ? $parts['port'] : false;
    $scheme = @$parts['scheme'];
    $host = @$parts['host'];
    $path = @$parts['path'];

    $port or $port = ($scheme == 'https') ? '443' : '80';

    if (($scheme == 'https' && $port != '443')
        || ($scheme == 'http' && $port != '80')) {
      $host = "$host:$port";
    }
    return "$scheme://$host$path";
  }

  public function to_url() {
    $post_data = $this->to_postdata();
    $out = $this->get_normalized_http_url();
    if ($post_data) {
      $out .= '?'.$post_data;
    }
    return $out;
  }

  public function to_postdata() {
    return WPOAuthUtil::build_http_query($this->parameters);
  }

  public function to_header($realm=null) {
    $first = true;
	if($realm) {
      $out = 'Authorization: OAuth realm="' . WPOAuthUtil::urlencode_rfc3986($realm) . '"';
      $first = false;
    } else
      $out = 'Authorization: OAuth';

    $total = array();
    foreach ($this->parameters as $k => $v) {
      if (substr($k, 0, 5) != "oauth") continue;
      if (is_array($v)) {
        throw new WPOAuthException('Arrays not supported in headers');
      }
      $out .= ($first) ? ' ' : ',';
      $out .= WPOAuthUtil::urlencode_rfc3986($k) .
              '="' .
              WPOAuthUtil::urlencode_rfc3986($v) .
              '"';
      $first = false;
    }
    return $out;
  }

  public function __toString() {
    return $this->to_url();
  }


  public function sign_request($signature_method, $consumer, $token) {
    $this->set_parameter(
      "oauth_signature_method",
      $signature_method->get_name(),
      false
    );
    $signature = $this->build_signature($signature_method, $consumer, $token);
    $this->set_parameter("oauth_signature", $signature, false);
  }

  public function build_signature($signature_method, $consumer, $token) {
    $signature = $signature_method->build_signature($this, $consumer, $token);
    return $signature;
  }

  private static function generate_timestamp() {

	date_default_timezone_set('UTC');
    return time();
  }

  private static function generate_nonce() {
    $mt = microtime();
    $rand = mt_rand();

    return md5($mt . $rand);
  }
}

class WPOAuthServer {
  protected $timestamp_threshold = 300; 
  protected $version = '1.0';             
  protected $signature_methods = array();

  protected $data_store;

  function __construct($data_store) {
    $this->data_store = $data_store;
  }

  public function add_signature_method($signature_method) {
    $this->signature_methods[$signature_method->get_name()] =
      $signature_method;
  }

  public function fetch_request_token(&$request) {
    $this->get_version($request);

    $consumer = $this->get_consumer($request);

    $token = NULL;

    $this->check_signature($request, $consumer, $token);

    $callback = $request->get_parameter('oauth_callback');
    $new_token = $this->data_store->new_request_token($consumer, $callback);

    return $new_token;
  }

  public function fetch_access_token(&$request) {
    $this->get_version($request);

    $consumer = $this->get_consumer($request);

    $token = $this->get_token($request, $consumer, "request");

    $this->check_signature($request, $consumer, $token);

    $verifier = $request->get_parameter('oauth_verifier');
    $new_token = $this->data_store->new_access_token($token, $consumer, $verifier);

    return $new_token;
  }

  public function verify_request(&$request) {
    $this->get_version($request);
    $consumer = $this->get_consumer($request);
    $token = $this->get_token($request, $consumer, "access");
    $this->check_signature($request, $consumer, $token);
    return array($consumer, $token);
  }

  private function get_version(&$request) {
    $version = $request->get_parameter("oauth_version");
    if (!$version) {

      $version = '1.0';
    }
    if ($version !== $this->version) {
      throw new WPOAuthException("OAuth version '$version' not supported");
    }
    return $version;
  }

  private function get_signature_method(&$request) {
    $signature_method =
        @$request->get_parameter("oauth_signature_method");

    if (!$signature_method) {

      throw new WPOAuthException('No signature method parameter. This parameter is required');
    }

    if (!in_array($signature_method,
                  array_keys($this->signature_methods))) {
      throw new WPOAuthException(
        "Signature method '$signature_method' not supported " .
        "try one of the following: " .
        implode(", ", array_keys($this->signature_methods))
      );
    }
    return $this->signature_methods[$signature_method];
  }

  private function get_consumer(&$request) {
    $consumer_key = @$request->get_parameter("oauth_consumer_key");
    if (!$consumer_key) {
      throw new WPOAuthException("Invalid consumer key");
    }

    $consumer = $this->data_store->lookup_consumer($consumer_key);
    if (!$consumer) {
      throw new WPOAuthException("Invalid consumer");
    }

    return $consumer;
  }

  private function get_token(&$request, $consumer, $token_type="access") {
    $token_field = @$request->get_parameter('oauth_token');
    $token = $this->data_store->lookup_token(
      $consumer, $token_type, $token_field
    );
    if (!$token) {
      throw new WPOAuthException("Invalid $token_type token: $token_field");
    }
    return $token;
  }

  private function check_signature(&$request, $consumer, $token) {
    // this should probably be in a different method
    $timestamp = @$request->get_parameter('oauth_timestamp');
    $nonce = @$request->get_parameter('oauth_nonce');

    $this->check_timestamp($timestamp);
    $this->check_nonce($consumer, $token, $nonce, $timestamp);

    $signature_method = $this->get_signature_method($request);

    $signature = $request->get_parameter('oauth_signature');
    $valid_sig = $signature_method->check_signature(
      $request,
      $consumer,
      $token,
      $signature
    );

    if (!$valid_sig) {
      throw new WPOAuthException("Invalid signature");
    }
  }

  private function check_timestamp($timestamp) {
    if( ! $timestamp )
      throw new WPOAuthException(
        'Missing timestamp parameter. The parameter is required'
      );
    
    // verify that timestamp is recentish
    $now = time();
    if (abs($now - $timestamp) > $this->timestamp_threshold) {
      throw new WPOAuthException(
        "Expired timestamp, yours $timestamp, ours $now"
      );
    }
  }

  private function check_nonce($consumer, $token, $nonce, $timestamp) {
    if( ! $nonce )
      throw new WPOAuthException(
        'Missing nonce parameter. The parameter is required'
      );
    $found = $this->data_store->lookup_nonce(
      $consumer,
      $token,
      $nonce,
      $timestamp
    );
    if ($found) {
      throw new WPOAuthException("Nonce already used: $nonce");
    }
  }

}

class WPOAuthDataStore {
  function lookup_consumer($consumer_key) {

  }

  function lookup_token($consumer, $token_type, $token) {

  }

  function lookup_nonce($consumer, $token, $nonce, $timestamp) {

  }

  function new_request_token($consumer, $callback = null) {

  }

  function new_access_token($token, $consumer, $verifier = null) {
 
  }

}

class WPOAuthUtil {
  public static function urlencode_rfc3986($input) {
  if (is_array($input)) {
    return array_map(array('WPOAuthUtil', 'urlencode_rfc3986'), $input);
  } else if (is_scalar($input)) {
    return str_replace(
      '+',
      ' ',
      str_replace('%7E', '~', rawurlencode($input))
    );
  } else {
    return '';
  }
}

  public static function urldecode_rfc3986($string) {
    return urldecode($string);
  }

  public static function split_header($header, $only_allow_oauth_parameters = true) {
    $pattern = '/(([-_a-z]*)=("([^"]*)"|([^,]*)),?)/';
    $offset = 0;
    $params = array();
    while (preg_match($pattern, $header, $matches, PREG_OFFSET_CAPTURE, $offset) > 0) {
      $match = $matches[0];
      $header_name = $matches[2][0];
      $header_content = (isset($matches[5])) ? $matches[5][0] : $matches[4][0];
      if (preg_match('/^oauth_/', $header_name) || !$only_allow_oauth_parameters) {
        $params[$header_name] = WPOAuthUtil::urldecode_rfc3986($header_content);
      }
      $offset = $match[1] + strlen($match[0]);
    }

    if (isset($params['realm'])) {
      unset($params['realm']);
    }

    return $params;
  }

  public static function get_headers() {
    if (function_exists('apache_request_headers')) {

      $headers = apache_request_headers();

      $out = array();
      foreach( $headers AS $key => $value ) {
        $key = str_replace(
            " ",
            "-",
            ucwords(strtolower(str_replace("-", " ", $key)))
          );
        $out[$key] = $value;
      }
    } else {
      $out = array();
      if( isset($_SERVER['CONTENT_TYPE']) )
        $out['Content-Type'] = $_SERVER['CONTENT_TYPE'];
      if( isset($_ENV['CONTENT_TYPE']) )
        $out['Content-Type'] = $_ENV['CONTENT_TYPE'];

      foreach ($_SERVER as $key => $value) {
        if (substr($key, 0, 5) == "HTTP_") {
          $key = str_replace(
            " ",
            "-",
            ucwords(strtolower(str_replace("_", " ", substr($key, 5))))
          );
          $out[$key] = $value;
        }
      }
    }
    return $out;
  }

  public static function parse_parameters( $input ) {
    if (!isset($input) || !$input) return array();

    $pairs = explode('&', $input);

    $parsed_parameters = array();
    foreach ($pairs as $pair) {
      $split = explode('=', $pair, 2);
      $parameter = WPOAuthUtil::urldecode_rfc3986($split[0]);
      $value = isset($split[1]) ? WPOAuthUtil::urldecode_rfc3986($split[1]) : '';

      if (isset($parsed_parameters[$parameter])) {


        if (is_scalar($parsed_parameters[$parameter])) {

          $parsed_parameters[$parameter] = array($parsed_parameters[$parameter]);
        }

        $parsed_parameters[$parameter][] = $value;
      } else {
        $parsed_parameters[$parameter] = $value;
      }
    }
    return $parsed_parameters;
  }

  public static function build_http_query($params) {
    if (!$params) return '';

    $keys = WPOAuthUtil::urlencode_rfc3986(array_keys($params));
    $values = WPOAuthUtil::urlencode_rfc3986(array_values($params));
    $params = array_combine($keys, $values);

    uksort($params, 'strcmp');

    $pairs = array();
    foreach ($params as $parameter => $value) {
      if (is_array($value)) {

        natsort($value);
        foreach ($value as $duplicate_value) {
          $pairs[] = $parameter . '=' . $duplicate_value;
        }
      } else {
        $pairs[] = $parameter . '=' . $value;
      }
    }

    return implode('&', $pairs);
  }
}

}
