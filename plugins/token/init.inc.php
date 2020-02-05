<?php
/*
 * Copyright (C) 2019, 
 * This program is free software licensed under the terms of the GNU General Public License v3 (GPLv3).
 */

class PLUGIN_TOKEN
{
    private $object_broker;
    private $classname;
    const ACL_MODE = 'none';    // white, black, none

    private $random_characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ-';
    private $random_length = 10;
    private $kill_tokens_sec = 60*60*24; // 1 day
    private $token_cache = NULL;
    private $token_fast_lookup = NULL;
    private $token_cache_needs_push = false;
    private $time_zone = NULL;

    public function __construct($object_broker)
    {
        $this->classname = strtolower(static::class);

        $this->object_broker = $object_broker;
        $object_broker->plugins[] = $this->classname;
        debug_log($this->classname . ": starting up");
    }


    public function __destruct()
    {

    }

    private function token_cache_init(){
        if( is_null($this->token_cache) )
        {
            $tokens = $this->object_broker->instance['core_persist']->retrieve('procedure.tokens');
            if($tokens)
            {
                $tokens_decoded = json_decode($tokens, TRUE, 3);
                if( is_null($tokens_decoded) )
                {
                   error_log("token_cache_init: could not decode JSON");
                   $this->token_cache = array();
                   $this->token_fast_lookup = array();
                   return;
                }
                $this->token_cache = $tokens_decoded;
                $this->build_lookup();
            }else{
                $this->token_cache = array();
                $this->token_fast_lookup = array();
                return;
            }
        }
    }

    private function build_lookup(){
        if(is_null($this->token_fast_lookup))
        {
            $this->token_fast_lookup = array();
        }else{
            unset($this->token_fast_lookup);
            $this->token_fast_lookup = array();
        }
        foreach(array_values($this->token_cache) as $tok){
            array_push($this->token_fast_lookup, $tok["tok"]);
        }
    }

    private function token_lookup($val){
        if(is_null($this->token_fast_lookup))
        {
            $this->build_lookup();
        }
        return in_array($val, $this->token_fast_lookup);
    }

    private function push_cache(){
        $json = json_encode($this->token_cache);
        $this->object_broker->instance['core_persist']->store('procedure.tokens', $json);
        $this->token_cache_needs_push = false;
    }

    private function subit_new_cache($c){
        $this->token_cache = $c;
        $this->token_cache_needs_push = true;
    }

    private function push_token($tok){
        array_push($this->token_cache, $tok);
        array_push($this->token_fast_lookup, $tok["tok"]);
        $this->push_cache();
    }

    private function kill_invalid_tokens(){
        $this->token_cache_init();
        $new_cache = array();
        $changed_something = false;
        foreach(array_values($this->token_cache) as $tok){
            if(array_key_exists("tok", $tok) && array_key_exists("ts", $tok))
            {
                array_push($new_cache, $tok);
            }else{
                $changed_something = true;
            }
        }
        if($changed_something)
        {
            $this->subit_new_cache($new_cache);
        }
    }

    private function get_timezone(){
        if(is_null($this->time_zone))
        {
            $this->time_zone = new DateTimeZone('Europe/Vienna');
        }
        return $this->time_zone;
    }

    private function now_ts(){
        $date = new DateTime(null, $this->get_timezone());
        $ts = date_timestamp_get($date);
        return $ts;
    }

    private function kill_outdated_tokens(){
        $this->token_cache_init();
        $new_cache = array();
        $changed_something = false;
        $now = $this->now_ts();
        foreach(array_values($this->token_cache) as $tok){
           if(($now - $tok["ts"]) < $this->kill_tokens_sec)
           {
                array_push($new_cache, $tok);
           }else{
                $changed_something = true;
           }
        }
        if($changed_something)
        {
            $this->subit_new_cache($new_cache);
        }
    }

    public function router_housekeeping(){
        $this->token_cache_init();
        debug_log("token cache consists of ".count($this->token_cache)." elements");
        $this->kill_invalid_tokens();
        $this->kill_outdated_tokens();

        if($this->token_cache_needs_push)
        {
            $this->push_cache();
        }
    }

    private function generateRandomString($length = NULL) {
        if(is_null($length))
        {
            $length = $this->random_length;
        }
        $charactersLength = strlen($this->random_characters);
        $randomString = NULL;
        $attempts = 0;

        do{
            $randomString = '';
            $attempts += 1;
            if($attempts > 100) // should never happen
            {
                $attempts = 1;
                $length += 1;
            }

            for ($i = 0; $i < $length; $i++)
            {
                $randomString .= $this->random_characters[rand(0, $charactersLength - 1)];
            }
        }while($this->token_lookup($randomString));
        return $randomString;
    }

    public function generate_token()
    {
        $this->token_cache_init();
        $rnd = $this->generateRandomString();

        $tok = ["tok" => $rnd, "ts" => $this->now_ts()];
        $this->push_token($tok);

        return $rnd;
    }

    private function checkRandomString($rnd_string, $length = NULL){
        if(is_null($length))
        {
            $length = $this->random_length;
        }
        if(strlen($rnd_string) != $length)
        {
            return false;
        }
        $chars=$this->random_characters;
        if(preg_match("/^[".$chars."]+\$/", $rnd_string) == 0)
        {
            return false;
        }
        return true;
    }

    public function consume_token($tok){ // true = was valid
        if(! $this->checkRandomString($tok)){
            return false;
        }
        $this->token_cache_init();
        if(!$this->token_lookup($tok)) {
            return false;
        }

        $now = $this->now_ts();
        $keys = array_keys($this->token_cache);
        foreach($keys as $k){
            $t = $this->token_cache[$k];
            if(array_key_exists("tok", $t) && $t["tok"] == $tok){
                $was_valid = array_key_exists("ts", $t) && (($now - $t["ts"]) < $this->kill_tokens_sec);

                unset($this->token_cache[$k]);
                $this->push_cache();
                unset($this->token_fast_lookup[array_search($tok, $this->token_fast_lookup)]);

                return $was_valid;
            }
        }
        return false;
    }
}
?>
