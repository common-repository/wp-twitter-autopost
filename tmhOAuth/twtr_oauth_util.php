<?php
class twitter_utils {
  const VERSION = '0.5.0';
  public static function entify($tweet, &$replacements=array()) {
    return twitter_utils::entify_with_options($tweet, array(), $replacements);
  }
  public static function entify_with_options($tweet, $options=array(), &$replacements=array()) {
    $default_opts = array(
      'encoding' => 'UTF-8',
      'target'   => '',
    );
    $opts = array_merge($default_opts, $options);
    $encoding = mb_internal_encoding();
    mb_internal_encoding($opts['encoding']);

    $keys = array();
    $is_retweet = false;

    if (isset($tweet['retweeted_status'])) {
      $tweet = $tweet['retweeted_status'];
      $is_retweet = true;
    }

    if (!isset($tweet['entities'])) {
      return $tweet['text'];
    }

    $target = (!empty($opts['target'])) ? ' target="'.$opts['target'].'"' : '';

    // prepare the entities
    foreach ($tweet['entities'] as $type => $things) {
      foreach ($things as $entity => $value) {
        $tweet_link = "<a href=\"https://twitter.com/{$tweet['user']['screen_name']}/statuses/{$tweet['id']}\"{$target}>{$tweet['created_at']}</a>";

        switch ($type) {
          case 'hashtags':
            $href = "<a href=\"https://twitter.com/search?q=%23{$value['text']}\"{$target}>#{$value['text']}</a>";
            break;
          case 'user_mentions':
            $href = "@<a href=\"https://twitter.com/{$value['screen_name']}\" title=\"{$value['name']}\"{$target}>{$value['screen_name']}</a>";
            break;
          case 'urls':
          case 'media':
            $url = empty($value['expanded_url']) ? $value['url'] : $value['expanded_url'];
            $display = isset($value['display_url']) ? $value['display_url'] : str_replace('http://', '', $url);
            $display = urldecode(str_replace('%E2%80%A6', '&hellip;', urlencode($display)));
            $href = "<a href=\"{$value['url']}\"{$target}>{$display}</a>";
            break;
        }
        $keys[$value['indices']['0']] = mb_substr(
          $tweet['text'],
          $value['indices']['0'],
          $value['indices']['1'] - $value['indices']['0']
        );
        $replacements[$value['indices']['0']] = $href;
      }
    }

    ksort($replacements);
    $replacements = array_reverse($replacements, true);
    $entified_tweet = $tweet['text'];
    foreach ($replacements as $k => $v) {
      $entified_tweet = mb_substr($entified_tweet, 0, $k).$v.mb_substr($entified_tweet, $k + strlen($keys[$k]));
    }
    $replacements = array(
      'replacements' => $replacements,
      'keys' => $keys
    );

    mb_internal_encoding($encoding);
    return $entified_tweet;
  }
  public static function php_self($dropqs=true) {
    $protocol = 'http';
    if (isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on') {
      $protocol = 'https';
    } elseif (isset($_SERVER['SERVER_PORT']) && ($_SERVER['SERVER_PORT'] == '443')) {
      $protocol = 'https';
    }

    $url = sprintf('%s://%s%s',
      $protocol,
      $_SERVER['SERVER_NAME'],
      $_SERVER['REQUEST_URI']
    );

    $parts = parse_url($url);

    $port = $_SERVER['SERVER_PORT'];
    $scheme = $parts['scheme'];
    $host = $parts['host'];
    $path = @$parts['path'];
    $qs   = @$parts['query'];

    $port or $port = ($scheme == 'https') ? '443' : '80';

    if (($scheme == 'https' && $port != '443')
        || ($scheme == 'http' && $port != '80')) {
      $host = "$host:$port";
    }
    $url = "$scheme://$host$path";
    if ( ! $dropqs)
      return "{$url}?{$qs}";
    else
      return $url;
  }

  public static function is_cli() {
    return (PHP_SAPI == 'cli' && empty($_SERVER['REMOTE_ADDR']));
  }
  public static function pr($obj) {

    if (!self::is_cli())
      echo '<pre style="word-wrap: break-word">';
    if ( is_object($obj) )
      print_r($obj);
    elseif ( is_array($obj) )
      print_r($obj);
    else
      echo $obj;
    if (!self::is_cli())
      echo '</pre>';
  }
  public static function auto_fix_time_request($tmhOAuth, $method, $url, $params=array(), $useauth=true, $multipart=false) {
    $tmhOAuth->request($method, $url, $params, $useauth, $multipart);
    if ( ! $useauth)
      return;
    if ($tmhOAuth->response['code'] != 401)
      return;
    if (stripos($tmhOAuth->response['response'], 'password') !== false)
     return;
    $tmhOAuth->auto_fixed_time = true;
    $tmhOAuth->config['force_timestamp'] = true;
    $tmhOAuth->config['timestamp'] = strtotime($tmhOAuth->response['headers']['date']);
    return $tmhOAuth->request($method, $url, $params, $useauth, $multipart);
  }
  public static function read_input($prompt) {
    echo $prompt;
    $handle = fopen("php://stdin","r");
    $data = fgets($handle);
    return trim($data);
  }
  public static function read_password($prompt, $stars=false) {
    echo $prompt;
    $style = shell_exec('stty -g');

    if ($stars === false) {
      shell_exec('stty -echo');
      $password = rtrim(fgets(STDIN), "\n");
    } else {
      shell_exec('stty -icanon -echo min 1 time 0');
      $password = '';
      while (true) :
        $char = fgetc(STDIN);
        if ($char === "\n") :
          break;
        elseif (ord($char) === 127) :
          if (strlen($password) > 0) {
            fwrite(STDOUT, "\x08 \x08");
            $password = substr($password, 0, -1);
          }
        else
          fwrite(STDOUT, "*");
          $password .= $char;
        endif;
      endwhile;
    }
    shell_exec('stty ' . $style);
    echo PHP_EOL;
    return $password;
  }
  public static function endswith($haystack, $needle) {
    $haylen  = strlen($haystack);
    $needlelen = strlen($needle);
    if ($needlelen > $haylen)
      return false;

    return substr_compare($haystack, $needle, -$needlelen) === 0;
  }
}