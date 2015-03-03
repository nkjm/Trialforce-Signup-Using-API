<?php
/*
 * You can freely use, embed, modify and re-distribute this program. No warranty.
 * Copyright Kazuki Nakajima <nkjm.kzk@gmail.com>
 */

require_once "MemcacheSASL.php";

class oauth {
    public $client_id;
    public $client_secret;
    public $login_url;
    public $token_url;
    public $callback_url;
    public $access_token;
    public $refresh_token;
    public $instance_url;
    public $memcache;
    public $error = FALSE;
    public $error_msg = array();

    public function __construct($client_id, $client_secret, $callback_url, $login_url = 'https://login.salesforce.com'){
        $this->client_id = $client_id;
        $this->client_secret = $client_secret;
        $this->callback_url = $callback_url;
        $this->login_url = $login_url;
        $this->token_url = $login_url . "/services/oauth2/token";

        // initiate memcache
        $this->memcache = new MemcacheSASL;
        $this->memcache->addServer($_ENV["MEMCACHIER_SERVERS"], '11211');
        $this->memcache->setSaslAuthData($_ENV["MEMCACHIER_USERNAME"], $_ENV["MEMCACHIER_PASSWORD"]);
    }

    public function auth_with_code($lifetime = 60){
        $this->read_cache_from_memcache();
        $this->refresh_cache_on_memcache($lifetime);

        if ($this->error){
            return(FALSE);
        }

        if (empty($this->access_token) || empty($this->instance_url) || empty($this->refresh_token)){
            //get access code
            if (!isset($_GET['code'])){
                $this->redirect_to_get_access_code();
            }

            //set user display depending on user agent
            if ($this->get_user_agent() == 'iPhone' || $this->get_user_agent() == 'iPad'){
                $display = 'touch';
            } else {
                $display = 'page';
            }

            //get access token
            $fragment = "grant_type=authorization_code"
            . "&code=" . $_GET['code']
            . "&display=" . $display
            . "&client_id=" . $this->client_id
            . "&client_secret=" . $this->client_secret
            . "&redirect_uri=" . urlencode($this->callback_url);
            $response = $this->send($fragment);
            if ($this->error){
                if (array_pop($this->error_msg) == 'new code required'){
                    $this->redirect_to_get_access_code();
                } else {
                    return(FALSE);
                }
            }
            $this->access_token = $response['access_token'];
            $this->refresh_token = $response['refresh_token'];
            $this->instance_url = $response['instance_url'];
            $this->save_to_memcache();
        }
        return(TRUE);
    }

    public function auth_with_password($username, $password, $lifetime = 60){
        $this->refresh_cache_on_memcache($lifetime);
        if ($this->error){
            return(FALSE);
        }
        $this->read_cache_from_memcache();
        if ($this->error){
            return(FALSE);
        }
        if (empty($this->access_token) || empty($this->instance_url)){
            $fragment = "grant_type=password"
            . "&client_id=" . $this->client_id
            . "&client_secret=" . $this->client_secret
            . "&username=" . $username
            . "&password=" . $password;
            $response = $this->send($fragment);
            if ($this->error){
                return(FALSE);
            }
            $this->access_token = $response['access_token'];
            $this->refresh_token = '';
            $this->instance_url = $response['instance_url'];
            $this->save_to_memcache();
            if ($this->error){
                return(FALSE);
            }
        }
        return(TRUE);
    }

    public function auth_with_refresh_token(){
        $fragment = "grant_type=refresh_token"
        . "&client_id=" . $this->client_id
        . "&client_secret=" . $this->client_secret
        . "&refresh_token=" . $this->refresh_token;
        $response = $this->send($fragment);
        if ($this->error){
            return(FALSE);
        }
        $this->access_token = $response['access_token'];
        $this->save_to_memcache();
        return(TRUE);
    }

    public function logout(){
        $this->memcache->delete('oauth');
        return(TRUE);
    }

    private function get_user_agent(){
        $f = explode(';', $_SERVER['HTTP_USER_AGENT']);
        $ff = explode('(', $f[0]);
        return(trim($ff[1]));
    }

    private function redirect_to_get_access_code(){
        $auth_url = $this->login_url . "/services/oauth2/authorize?response_type=code&client_id=" . $this->client_id . "&redirect_uri=" . urlencode($this->callback_url);
        header('Location: ' . $auth_url);
    }

    private function redirect_to_get_access_token(){
        $auth_url = $this->login_url . "/services/oauth2/authorize?response_type=token&client_id=" . $this->client_id . "&redirect_uri=" . urlencode($this->callback_url);
        header('Location: ' . $auth_url);
    }

    private function refresh_cache_on_memcache($lifetime){
        $oauth = json_decode($this->memcache->get("oauth"), true);
        if (is_null($oauth)){
            return false;
        }
        if (isset($oauth['created_at'])){
            $current_time = time();
            if (($current_time - $oauth['created_at']) > $lifetime * 60){
                $this->auth_with_refresh_token();
            }
        }
    }

    private function read_cache_from_memcache(){
        $oauth = json_decode($this->memcache->get("oauth"), true);
        if (is_null($oauth)){
            return FALSE;
        }
        $array_cache = array("access_token", "refresh_token", "instance_url");
        foreach($array_cache as $k => $v){
            if (isset($oauth[$v])){
                $this->$v = $oauth[$v];
            }
        }
    }

    private function send($fragment){
        $curl = curl_init($this->token_url);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $fragment);
        $response = json_decode(curl_exec($curl), true);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($status == 400 && $response['error_description'] == 'expired authorization code') {
            //access code has been expired
            $this->set_error('new code required');
        } elseif ( $status != 200 ) {
            $this->set_error("<h1>Curl Error</h1><p>URL : $this->token_url </p><p>Status : $status</p><p>response : error = " . $response['error'] . ", error_description = " . $response['error_description'] . "</p><p>curl_error : " . curl_error($curl) . "</p><p>curl_errno : " . curl_errno($curl) . "</p>");
        }
        curl_close($curl);
        return($response);
    }

    private function save_to_memcache(){
        $oauth = array();
        $array_cache = array("access_token", "refresh_token", "instance_url");
        foreach($array_cache as $k => $v){
            $oauth[$v] = $this->$v;
        }
        $oauth['created_at'] = time();
        $this->memcache->add("oauth", json_encode($oauth));
    }

    private function set_error($error_msg){
        $this->error = TRUE;
        array_push($this->error_msg, $error_msg);
    }

    public function trace_error(){
        if ($this->error){
            foreach ($this->error_msg as $k => $v){
                print '<p>' . $v . '</p>';
            }
        }
    }
}
?>
