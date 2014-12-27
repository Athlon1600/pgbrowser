<?php

/**
 * PGBrowser - A 'pretty good' mechanize-like php library for managing cookies and submitting forms.
 * Website: https://github.com/monkeysuffrage/pgbrowser
 *
 * <pre>
 * require 'pgbrowser.php';
 *
 * $b = new PGBrowser();
 * $page = $b->get('http://www.google.com/');
 * echo $page->title;
 * </pre>
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @link http://code.nabla.net/doc/gantry4/class-phpQueryObject.html phpQueryObject
 * @link http://simplehtmldom.sourceforge.net/manual_api.htm SimpleHtmlDom
 *
 * @package PGBrowser
 * @author P Guardiario <pguardiario@gmail.com>
 * @version 0.4
 */

/**
 * PGBrowser
 * @package PGBrowser
 */
class PGBrowser{
    /**
     * The curl handle
     * @var mixed
     */
    public $ch;

    /**
     * The parser to use (phpquery/simple-html-dom)
     * @var string
     */
    public $parserType;

    /**
     * If true, requests will be cached in a folder named "cache"
     * @var bool
     */
    public $useCache = false;

    /**
     * Expire items in cache after time in seconds
     * @var int
     */
    public $expireAfter = 0;

    /**
     * If true, relative href and src attributes will be converted to absolute
     * @var bool
     */
    public $convertUrls = false;
    private $lastUrl;
    private $visited;

    /**
     * Return a new PGBrowser object
     * @param string $parserType the type of parser to use (phpquery/simple-html-dom)
     */
    function __construct($parserType = null){
        $this->ch = curl_init();
        curl_setopt($this->ch, CURLOPT_USERAGENT, "PGBrowser/0.0.1 (http://github.com/monkeysuffrage/pgbrowser/)");
        curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($this->ch, CURLOPT_AUTOREFERER, true);
        curl_setopt($this->ch, CURLOPT_MAXREDIRS, 10);
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($this->ch, CURLOPT_ENCODING, 'gzip,deflate,identity');
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, array(
            "Accept-Charset:	ISO-8859-1,utf-8;q=0.7,*;q=0.7",
            "Accept-Language:	en-us,en;q=0.5",
            "Connection: keep-alive",
            "Keep-Alive: 300",
            "Expect:"
        ));
        curl_setopt($this->ch, CURLOPT_COOKIEJAR, '');
        curl_setopt($this->ch, CURLOPT_HEADER, true);
        $this->parserType = $parserType;
        if(function_exists('gc_enable')) gc_enable();
    }

    // private methods

    private function clean($str){
        return preg_replace(array('/&nbsp;/'), array(' '), $str);
    }

    private function cacheFilename($url){
        return 'cache/' . md5($url) . '.cache';
    }

    private function saveCache($url, $response){
        if(!is_dir('cache')) @mkdir('cache', 0777);
        file_put_contents($this->cacheFilename($url), $response);
    }

    public function cacheExpired($url){
        if(!$this->expireAfter) return false;
        $fn = $this->cacheFilename($url);
        if(!file_exists($fn)){
            trigger_error('cache does not exist for: ' . $url, E_USER_WARNING);
            return true;
        }
        $age = microtime(true) - filemtime($fn);
        if($age < $this->expireAfter) return false;
        $this->deleteCache($url);
        return true;
    }

    // public methods

    /**
     * Delete the cached version of an url
     * @param string $url
     */
    public function deleteCache($url){
        unlink($this->cacheFilename(($url)));
    }

    public function isCached($url){
        return file_exists($this->cacheFilename($url));
    }


    /**
     * Clear the cache
     * @param string $url
     */
    public function clearCache(){
        if($files = glob('cache/*.cache')){
            foreach($files as $file){ unlink($file); }
        }
    }

    /**
     * Set a curl option
     * @param int $key
     * @param string $value
     */
    public function setopt($key, $value){
        curl_setopt($this->ch, $key, $value);
    }

    /**
     * Set a proxy
     * @param string $host
     * @param string $port
     * @param string $user
     * @param string $password
     */
    public function setProxy($host, $port, $user = NULL, $password = NULL){
        curl_setopt($this->ch, CURLOPT_PROXY, "http://$host:$port");
        if(!empty($user)) curl_setopt($this->ch, CURLOPT_PROXYUSERPWD, "$user:$password");
    }

    /**
     * Set the user agent
     * @param string $user_agent
     */
    public function setUserAgent($user_agent){
        curl_setopt($this->ch, CURLOPT_USERAGENT, $user_agent);
    }

    /**
     * Set curl timeout in milliseconds
     * @param int $timeout
     */
    public function setTimeout($timeout){
        curl_setopt($this->ch, CURLOPT_TIMEOUT_MS, $timeout);
        curl_setopt($this->ch, CURLOPT_CONNECTTIMEOUT_MS, $timeout);
    }

    /**
     * Todo: fill this out
     */
    public function visited($url){
        if(!isset($this->visited)) $this->visited = array();
        if(array_search($url, $this->visited) !== false) return true;
        $this->visited[] = $url;
        return false;
    }

    /**
     * Create a Page object from an url and a string
     * @param string $url
     * @param string $html
     * @return PGPage
     */
    public function load($url, $html) {
        $page = new PGPage($url, "HTTP/1.1 200 OK\n\n" . $this->clean($html), $this);
        $this->lastUrl = $url;
        return $page;
    }

    /**
     * Pretend to 'get' an url but mock it using a local file.
     * @param string $url
     * @param string $filename
     * @return PGPage
     */
    public function mock($url, $filename) {
        $response = file_get_contents($filename);
        $page = new PGPage($url, $this->clean($response), $this);
        $this->lastUrl = $url;
        return $page;
    }

    /**
     * Set curl headers
     */
    public function setHeaders($headers){
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $headers);
    }

    public $follow_meta_refresh = false;

    /**
     * Get an url
     * @param string $url
     * @return PGPage
     */
    public function get($url) {
        if($this->useCache && file_exists($this->cacheFilename($url))){
            if($this->cacheExpired($url)) return $this->get($url);
            $response = file_get_contents($this->cacheFilename($url));
            $page = new PGPage($url, $this->clean($response), $this);
        } else {
            curl_setopt($this->ch, CURLOPT_URL, $url);
            if(!empty($this->lastUrl)) curl_setopt($this->ch, CURLOPT_REFERER, $this->lastUrl);
            curl_setopt($this->ch, CURLOPT_POST, false);
            $response = curl_exec($this->ch);

            $page = new PGPage($url, $this->clean($response), $this);

            // deal with meta refresh
            if($this->follow_meta_refresh && ($meta = $page->at('meta[http-equiv="refresh"]'))){
                if(!preg_match('/^\d+; url=(.*)$/', $meta->content, $m)){
                    echo "bad redirect meta: " . $meta->content;
                } else {
                    $url = $m[1];
                    curl_setopt($this->ch, CURLOPT_URL, $url);
                    if(!empty($this->lastUrl)) curl_setopt($this->ch, CURLOPT_REFERER, $this->lastUrl);
                    curl_setopt($this->ch, CURLOPT_POST, false);
                    $response = curl_exec($this->ch);
                    $page = new PGPage($url, $this->clean($response), $this);
                }
            }
            if($this->useCache) $this->saveCache($url, $response);
        }
        $this->lastUrl = $url;
        return $page;
    }

    /**
     * Post to an url
     * @param string $url url to post
     * @param string $body post body
     * @param array  $headers http headers
     * @return PGPage
     */
    public function post($url, $body, $headers = array('Content-Type: application/x-www-form-urlencoded')) {
        if($this->useCache && file_exists($this->cacheFilename($url . $body))){
            if($this->cacheExpired($url . $body)) return $this->post($url, $body, $headers);
            $response = file_get_contents($this->cacheFilename($url . $body));
            $page = new PGPage($url, $this->clean($response), $this);
        } else {
            $this->setHeaders($headers);
            curl_setopt($this->ch, CURLOPT_URL, $url);
            if(!empty($this->lastUrl)) curl_setopt($this->ch, CURLOPT_REFERER, $this->lastUrl);
            curl_setopt($this->ch, CURLOPT_POST, true);
            curl_setopt($this->ch, CURLOPT_POSTFIELDS, $body);
            $response = curl_exec($this->ch);
            $page = new PGPage($url, $this->clean($response), $this);
            if($this->useCache) $this->saveCache($url . $body, $response);
            if($headers) $this->setHeaders(preg_replace('/(.*?:).*/','\1', $headers)); // clear headers
        }
        $this->lastUrl = $url;
        return $page;
    }
}


if(!class_exists('phpUri')){
    /**
     * @package phpUri
     */
    class phpUri{
        var $scheme, $authority, $path, $query, $fragment;

        function __construct($string){
            preg_match_all('/^(([^:\/?#]+):)?(\/\/([^\/?#]*))?([^?#]*)(\?([^#]*))?(#(.*))?$/', $string ,$m);
            $this->scheme = $m[2][0];
            $this->authority = $m[4][0];
            $this->path = $m[5][0];
            $this->query = $m[7][0];
            $this->fragment = $m[9][0];
        }

        public static function parse($string){
            $uri = new phpUri($string);
            return $uri;
        }

        function join($string){
            $uri = new phpUri($string);
            switch(true){
                case !empty($uri->scheme): break;
                case !empty($uri->authority): break;
                case empty($uri->path):
                    $uri->path = $this->path;
                    if(empty($uri->query)) $uri->query = $this->query;
                case strpos($uri->path, '/') === 0: break;
                default:
                    $base_path = $this->path;
                    if(strpos($base_path, '/') === false){
                        $base_path = '';
                    } else {
                        $base_path = preg_replace ('/\/[^\/]+$/' ,'/' , $base_path);
                    }
                    if(empty($base_path) && empty($this->authority)) $base_path = '/';
                    $uri->path = $base_path . $uri->path;
            }
            if(empty($uri->scheme)){
                $uri->scheme = $this->scheme;
                if(empty($uri->authority)) $uri->authority = $this->authority;
            }
            return $uri->to_str();
        }

        function normalize_path($path){
            if(empty($path)) return '';
            $normalized_path = $path;
            $normalized_path = preg_replace('`//+`', '/' , $normalized_path, -1, $c0);
            $normalized_path = preg_replace('`^/\\.\\.?/`', '/' , $normalized_path, -1, $c1);
            $normalized_path = preg_replace('`/\\.(/|$)`', '/' , $normalized_path, -1, $c2);
            $normalized_path = preg_replace('`/[^/]*?/\\.\\.(/|$)`', '/' , $normalized_path, -1, $c3);
            $num_matches = $c0 + $c1 + $c2 + $c3;
            return ($num_matches > 0) ? $this->normalize_path($normalized_path) : $normalized_path;
        }

        function to_str(){
            $ret = "";
            if(!empty($this->scheme)) $ret .= "$this->scheme:";
            if(!empty($this->authority)) $ret .= "//$this->authority";
            $ret .= $this->normalize_path($this->path);
            if(!empty($this->query)) $ret .= "?$this->query";
            if(!empty($this->fragment)) $ret .= "#$this->fragment";
            return $ret;
        }
    }

}



?>