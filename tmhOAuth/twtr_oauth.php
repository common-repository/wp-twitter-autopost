<?php
class tmhOAuth {
  const VERSION = '0.7.5';
  var $response = array();
  public function __construct($config=array()) {
    $this->params = array();
    $this->headers = array();
    $this->auto_fixed_time = false;
    $this->buffer = null;
    $this->config = array_merge(
      array(
        'user_agent'                 => '',
        'timezone'                   => 'UTC',
        'use_ssl'                    => true,
        'host'                       => 'api.twitter.com',
        'consumer_key'               => '',
        'consumer_secret'            => '',
        'user_token'                 => '',
        'user_secret'                => '',
        'force_nonce'                => false,
        'nonce'                      => false,
        'force_timestamp'            => false,
        'timestamp'                  => false,
        'oauth_version'              => '1.0',
        'oauth_signature_method'     => 'HMAC-SHA1',
        'curl_connecttimeout'        => 30,
        'curl_timeout'               => 10,
        'curl_ssl_verifyhost'        => 2,
        'curl_ssl_verifypeer'        => true,
        'curl_cainfo'                => dirname(__FILE__) . DIRECTORY_SEPARATOR . 'cacert.pem',
        'curl_capath'                => dirname(__FILE__),
        'curl_followlocation'        => false,
        'curl_proxy'                 => false, 
        'curl_proxyuserpwd'          => false,
        'curl_encoding'              => '',
        'is_streaming'               => false,
        'streaming_eol'              => "\r\n",
        'streaming_metrics_interval' => 60,
        'as_header'                  => true,
        'debug'                      => false,
      ),
      $config
    );
    $this->set_user_agent();
    date_default_timezone_set($this->config['timezone']);
  }
  private function set_user_agent() {
    if (!empty($this->config['user_agent']))
      return;

    if ($this->config['curl_ssl_verifyhost'] && $this->config['curl_ssl_verifypeer']) {
      $ssl = '+SSL';
    } else {
      $ssl = '-SSL';
    }

    $ua = 'tmhOAuth ' . self::VERSION . $ssl . ' - //github.com/themattharris/tmhOAuth';
    $this->config['user_agent'] = $ua;
  }
  private function create_nonce($length=12, $include_time=true) {
    if ($this->config['force_nonce'] == false) {
      $sequence = array_merge(range(0,9), range('A','Z'), range('a','z'));
      $length = $length > count($sequence) ? count($sequence) : $length;
      shuffle($sequence);

      $prefix = $include_time ? microtime() : '';
      $this->config['nonce'] = md5(substr($prefix . implode('', $sequence), 0, $length));
    }
  }
  private function create_timestamp() {
    $this->config['timestamp'] = ($this->config['force_timestamp'] == false ? time() : $this->config['timestamp']);
  }
  private function safe_encode($data) {
    if (is_array($data)) {
      return array_map(array($this, 'safe_encode'), $data);
    } else if (is_scalar($data)) {
      return str_ireplace(
        array('+', '%7E'),
        array(' ', '~'),
        rawurlencode($data)
      );
    } else {
      return '';
    }
  }
  private function safe_decode($data) {
    if (is_array($data)) {
      return array_map(array($this, 'safe_decode'), $data);
    } else if (is_scalar($data)) {
      return rawurldecode($data);
    } else {
      return '';
    }
  }
  private function get_defaults() {
    $defaults = array(
      'oauth_version'          => $this->config['oauth_version'],
      'oauth_nonce'            => $this->config['nonce'],
      'oauth_timestamp'        => $this->config['timestamp'],
      'oauth_consumer_key'     => $this->config['consumer_key'],
      'oauth_signature_method' => $this->config['oauth_signature_method'],
    );
    if ( $this->config['user_token'] )
      $defaults['oauth_token'] = $this->config['user_token'];
    foreach ($defaults as $k => $v) {
      $_defaults[$this->safe_encode($k)] = $this->safe_encode($v);
    }

    return $_defaults;
  }
  public function extract_params($body) {
    $kvs = explode('&', $body);
    $decoded = array();
    foreach ($kvs as $kv) {
      $kv = explode('=', $kv, 2);
      $kv[0] = $this->safe_decode($kv[0]);
      $kv[1] = $this->safe_decode($kv[1]);
      $decoded[$kv[0]] = $kv[1];
    }
    return $decoded;
  }
  private function prepare_method($method) {
    $this->method = strtoupper($method);
  }

  private function prepare_url($url) {
    $parts = parse_url($url);

    $port   = isset($parts['port']) ? $parts['port'] : false;
    $scheme = $parts['scheme'];
    $host   = $parts['host'];
    $path   = isset($parts['path']) ? $parts['path'] : false;

    $port or $port = ($scheme == 'https') ? '443' : '80';

    if (($scheme == 'https' && $port != '443')
        || ($scheme == 'http' && $port != '80')) {
      $host = "$host:$port";
    }
    $this->url = strtolower("$scheme://$host");
    $this->url .= $path;
  }

  private function prepare_params($params) {
    if ($this->config['multipart']) {
      $this->request_params = $params;
      $params = array();
    }

    $this->signing_params = array_merge($this->get_defaults(), (array)$params);
    if (isset($this->signing_params['oauth_signature'])) {
      unset($this->signing_params['oauth_signature']);
    }
    uksort($this->signing_params, 'strcmp');
    foreach ($this->signing_params as $k => $v) {
      $k = $this->safe_encode($k);

      if (is_array($v))
        $v = implode(',', $v);

      $v = $this->safe_encode($v);
      $_signing_params[$k] = $v;
      $kv[] = "{$k}={$v}";
    }
    $this->auth_params = array_intersect_key($this->get_defaults(), $_signing_params);
    if (isset($_signing_params['oauth_callback'])) {
      $this->auth_params['oauth_callback'] = $_signing_params['oauth_callback'];
      unset($_signing_params['oauth_callback']);
    }

    if (isset($_signing_params['oauth_verifier'])) {
      $this->auth_params['oauth_verifier'] = $_signing_params['oauth_verifier'];
      unset($_signing_params['oauth_verifier']);
    }
    if ( ! $this->config['multipart'])
      $this->request_params = array_diff_key($_signing_params, $this->get_defaults());
    $this->signing_params = implode('&', $kv);
  }
  private function prepare_signing_key() {
    $this->signing_key = $this->safe_encode($this->config['consumer_secret']) . '&' . $this->safe_encode($this->config['user_secret']);
  }
  private function prepare_base_string() {
    $url = $this->url;
    if (!empty($this->custom_headers['Host'])) {
      $url = str_ireplace(
        $this->config['host'],
        $this->custom_headers['Host'],
        $url
      );
    }

    $base = array(
      $this->method,
      $url,
      $this->signing_params
    );
    $this->base_string = implode('&', $this->safe_encode($base));
  }
  private function prepare_auth_header() {
    unset($this->headers['Authorization']);

    uksort($this->auth_params, 'strcmp');
    if (!$this->config['as_header']) :
      $this->request_params = array_merge($this->request_params, $this->auth_params);
      return;
    endif;

    foreach ($this->auth_params as $k => $v) {
      $kv[] = "{$k}=\"{$v}\"";
    }
    $this->auth_header = 'OAuth ' . implode(', ', $kv);
    $this->headers['Authorization'] = $this->auth_header;
  }
  private function sign($method, $url, $params, $useauth) {
    $this->prepare_method($method);
    $this->prepare_url($url);
    $this->prepare_params($params);
    if ($useauth) {
      $this->prepare_base_string();
      $this->prepare_signing_key();

      $this->auth_params['oauth_signature'] = $this->safe_encode(
        base64_encode(
          hash_hmac(
            'sha1', $this->base_string, $this->signing_key, true
      )));

      $this->prepare_auth_header();
    }
  }
  public function request($method, $url, $params=array(), $useauth=true, $multipart=false, $headers=array()) {
    $this->headers = array();
    $this->custom_headers = $headers;
    $this->config['multipart'] = $multipart;
    $this->create_nonce();
    $this->create_timestamp();
    $this->sign($method, $url, $params, $useauth);
    if (!empty($this->custom_headers))
      $this->headers = array_merge((array)$this->headers, (array)$this->custom_headers);
    return $this->curlit();
  }
  public function streaming_request($method, $url, $params=array(), $callback='') {
    if ( ! empty($callback) ) {
      if ( ! is_callable($callback) ) {
        return false;
      }
      $this->config['streaming_callback'] = $callback;
    }
    $this->metrics['start']          = time();
    $this->metrics['interval_start'] = $this->metrics['start'];
    $this->metrics['tweets']         = 0;
    $this->metrics['last_tweets']    = 0;
    $this->metrics['bytes']          = 0;
    $this->metrics['last_bytes']     = 0;
    $this->config['is_streaming']    = true;
    $this->request($method, $url, $params);
  }
  private function update_metrics() {
    $now = time();
    if (($this->metrics['interval_start'] + $this->config['streaming_metrics_interval']) > $now)
      return false;
    $this->metrics['tps'] = round( ($this->metrics['tweets'] - $this->metrics['last_tweets']) / $this->config['streaming_metrics_interval'], 2);
    $this->metrics['bps'] = round( ($this->metrics['bytes'] - $this->metrics['last_bytes']) / $this->config['streaming_metrics_interval'], 2);
    $this->metrics['last_bytes'] = $this->metrics['bytes'];
    $this->metrics['last_tweets'] = $this->metrics['tweets'];
    $this->metrics['interval_start'] = $now;
    return $this->metrics;
  }
  public function url($request, $format='json') {
    $format = strlen($format) > 0 ? ".$format" : '';
    $proto  = $this->config['use_ssl'] ? 'https:/' : 'http:/';
    if (isset($this->config['v']))
      $this->config['host'] = $this->config['host'] . '/' . $this->config['v'];
    $request = ltrim($request, '/');
    $pos = strlen($request) - strlen($format);
    if (substr($request, $pos) === $format)
      $request = substr_replace($request, '', $pos);
    return implode('/', array(
      $proto,
      $this->config['host'],
      $request . $format
    ));
  }
  public function transformText($text, $mode='encode') {
    return $this->{"safe_$mode"}($text);
  }
  private function curlHeader($ch, $header) {
    $this->response['raw'] .= $header;

    list($key, $value) = array_pad(explode(':', $header, 2), 2, null);

    $key = trim($key);
    $value = trim($value);

    if ( ! isset($this->response['headers'][$key])) {
      $this->response['headers'][$key] = $value;
    } else {
      if (!is_array($this->response['headers'][$key])) {
        $this->response['headers'][$key] = array($this->response['headers'][$key]);
      }
      $this->response['headers'][$key][] = $value;
    }

    return strlen($header);
  }
  private function curlWrite($ch, $data) {
    $l = strlen($data);
    if (strpos($data, $this->config['streaming_eol']) === false) {
      $this->buffer .= $data;
      return $l;
    }
    $buffered = explode($this->config['streaming_eol'], $data);
    $content = $this->buffer . $buffered[0];
    $this->metrics['tweets']++;
    $this->metrics['bytes'] += strlen($content);
    if ( ! is_callable($this->config['streaming_callback']))
      return 0;
    $metrics = $this->update_metrics();
    $stop = call_user_func(
      $this->config['streaming_callback'],
      $content,
      strlen($content),
      $metrics
    );
    $this->buffer = $buffered[1];
    if ($stop)
      return 0;

    return $l;
  }
  private function curlit() {
    $this->response['raw'] = '';
    switch ($this->method) {
      case 'POST':
        break;
      default:
        if ( ! empty($this->request_params)) {
          foreach ($this->request_params as $k => $v) {
            if ($this->config['multipart']) {
              $params[] = $this->safe_encode($k) . '=' . $this->safe_encode($v);
            } else {
              $params[] = $k . '=' . $v;
            }
          }
          $qs = implode('&', $params);
          $this->url = strlen($qs) > 0 ? $this->url . '?' . $qs : $this->url;
          $this->request_params = array();
        }
        break;
    }
    $c = curl_init();
    curl_setopt_array($c, array(
      CURLOPT_USERAGENT      => $this->config['user_agent'],
      CURLOPT_CONNECTTIMEOUT => $this->config['curl_connecttimeout'],
      CURLOPT_TIMEOUT        => $this->config['curl_timeout'],
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_SSL_VERIFYPEER => $this->config['curl_ssl_verifypeer'],
      CURLOPT_SSL_VERIFYHOST => $this->config['curl_ssl_verifyhost'],
      CURLOPT_FOLLOWLOCATION => $this->config['curl_followlocation'],
      CURLOPT_PROXY          => $this->config['curl_proxy'],
      CURLOPT_ENCODING       => $this->config['curl_encoding'],
      CURLOPT_URL            => $this->url,
      CURLOPT_HEADERFUNCTION => array($this, 'curlHeader'),
      CURLOPT_HEADER         => false,
      CURLINFO_HEADER_OUT    => true,
    ));

    if ($this->config['curl_cainfo'] !== false)
      curl_setopt($c, CURLOPT_CAINFO, $this->config['curl_cainfo']);
    if ($this->config['curl_capath'] !== false)
      curl_setopt($c, CURLOPT_CAPATH, $this->config['curl_capath']);
    if ($this->config['curl_proxyuserpwd'] !== false)
      curl_setopt($c, CURLOPT_PROXYUSERPWD, $this->config['curl_proxyuserpwd']);
    if ($this->config['is_streaming']) {
      $this->response['content-length'] = 0;
      curl_setopt($c, CURLOPT_TIMEOUT, 0);
      curl_setopt($c, CURLOPT_WRITEFUNCTION, array($this, 'curlWrite'));
    }
    switch ($this->method) {
      case 'GET':
        break;
      case 'POST':
        curl_setopt($c, CURLOPT_POST, true);
        curl_setopt($c, CURLOPT_POSTFIELDS, $this->request_params);
        break;
      default:
        curl_setopt($c, CURLOPT_CUSTOMREQUEST, $this->method);
    }
    if ( ! empty($this->request_params) ) {
      if ( ! $this->config['multipart'] ) {
        foreach ($this->request_params as $k => $v) {
          $ps[] = "{$k}={$v}";
        }
        $this->request_params = implode('&', $ps);
      }
      curl_setopt($c, CURLOPT_POSTFIELDS, $this->request_params);
    }

    if ( ! empty($this->headers)) {
      foreach ($this->headers as $k => $v) {
        $headers[] = trim($k . ': ' . $v);
      }
      curl_setopt($c, CURLOPT_HTTPHEADER, $headers);
    }

    if (isset($this->config['prevent_request']) && (true == $this->config['prevent_request']))
      return 0;
    $response = curl_exec($c);
    $code = curl_getinfo($c, CURLINFO_HTTP_CODE);
    $info = curl_getinfo($c);
    $error = curl_error($c);
    $errno = curl_errno($c);
    curl_close($c);
    $this->response['code'] = $code;
    $this->response['response'] = $response;
    $this->response['info'] = $info;
    $this->response['error'] = $error;
    $this->response['errno'] = $errno;
    if (!isset($this->response['raw'])) {
      $this->response['raw'] = '';
    }
    $this->response['raw'] .= $response;
    return $code;
  }
}