<?php
/*
 * Copyright (C) 2019, Daniel Haslinger <creo+oss@mesanova.com>
 * This program is free software licensed under the terms of the GNU General Public License v3 (GPLv3).
 */

class PLUGIN_INFORM
{
    private $object_broker;
    private $classname;
    const ACL_MODE = 'none';    // white, black, none

    public function __construct($object_broker)
    {
        $this->classname = strtolower(static::class);

        $this->object_broker = $object_broker;
        $object_broker->plugins[] = $this->classname;
        debug_log($this->classname . ": starting up");

        $this->object_broker->instance['api_routing']->register("inform", $this->classname, "Bot will inform user about public openings of the segvault (use again to unsubscribe)");
        $this->object_broker->instance['api_routing']->helptext("inform", "", "Bot will inform user only about the next opening");
        $this->object_broker->instance['api_routing']->helptext("inform", "next (default if unset)", "Bot will inform user only about the next opening");
        $this->object_broker->instance['api_routing']->helptext("inform", "nonext (default if set)", "Bot will NOT inform user only about the next opening");
        $this->object_broker->instance['api_routing']->helptext("inform", "all", "Bot will inform user about the next openings");
        $this->object_broker->instance['api_routing']->helptext("inform", "noall", "Bot will NOT inform user about the next openings");
        $this->object_broker->instance['api_routing']->helptext("inform", "status", "Bot will inform you about your notifications");
    }


    public function __destruct()
    {

    }


    public function get_acl_mode()
    {
        return self::ACL_MODE;
    }

    private function send_to_user($text, $inlineKB=null)
    {
         $senderid = $GLOBALS['layer7_stanza']['message']['from']['id'];
         return $this->object_broker->instance['api_telegram']->send_message($senderid, $text, $inlineKB);
    }

		private function check_state($sender, $subtrigger){
			$info_state = $this->object_broker->instance['core_persist']->retrieve("inform.state.$sender");
			if($info_state){
				$_res = json_decode($info_state, true);
				if(! $_res){
					return false;
				}
				if (array_key_exists($subtrigger, $_res)){
					if($_res[$subtrigger] === true){ // check if is true and indeed a boolean
						return true;
					}else{
						return false;
					}
				}else{
					return false;
				}
			}else{
				return false;
			}
		}


		public function broadcast_next($message){
			$uids = $this->seen_user_ids();
			foreach($uids['next'] as $uid){
				$this->process_next($uid, $message);
			}
		}

		public function broadcast_all($message){
			$uids = $this->seen_user_ids();
			foreach($uids['all'] as $uid){
				$this->process_all($uid, $message);
			}
		}

		public function process_next($senderid, $message=null){
			if($this->check_state($senderid, 'next')){
				$key = $this->get_persist_key($senderid);
				$arr = $this->build_array($key);
				$arr['next'] = false;

				$msgid =  $GLOBALS['layer7_stanza']['message']['message_id'];
				if(array_key_exists('next_msg', $arr)){
					if($arr['next_msg'] == $msgid){
						return; // nothing to do
					}
				}
				$arr['next_msg'] = $msgid;

				if(! $message){
					$message = "Space is opening, check the <a href=\"https://telegram.me/joinchat/BAEj90A5QJ6WknEsNZWf4g\">usergroup</a>";
				}
				$message = "[inform next] $message";
				if($this->array_persist($arr, $key)){
					$kb = array();
					if($this->check_state($senderid, 'all')){
						array_push($kb, [ "NOT inform on all openings" => "/inform noall" ] );
					}else{
						array_push($kb, [ "inform on next opening" => "/inform next" ] );
						array_push($kb, [ "inform on all openings" => "/inform all" ] );
					}
					$this->send_to_user($message, $kb);
					$uids = $this->seen_user_ids();
					if( in_array($senderid, $uids['next']) ){
						$uids['next'] = array_diff($uids['next'], [ $senderid ]);
						$this->store_user_ids($uids);
					}
				}else{
					debug_log($this->classname . ": tried to persist array for $senderid but failed!");
					$message = "$message \n\n\nINFO: Internal error, bot will not remember that you have been aleady informed about this opening!";
					$kb = array();
					if($this->check_state($senderid, 'all')){
						array_push($kb, [ "NOT inform on all openings" => "/inform noall" ] );
					}else{
						array_push($kb, [ "inform on all openings" => "/inform all" ] );
					}
					$this->send_to_user($message, $kb);
				}
			}
		}
		
		public function process_all($senderid, $message=null){
			if($this->check_state($senderid, 'all')){
				$key = $this->get_persist_key($senderid);
				$arr = $this->build_array($key);
			
				if(array_key_exists('next_msg', $arr)){
					$msgid =  $GLOBALS['layer7_stanza']['message']['message_id'];
					if($arr['next_msg'] == $msgid){
						return; // nothing to do
					}
				}
				$arr['next_msg'] = $msgid;

				
				if(! $message){
					$message = "Space is opening, check the <a href=\"https://telegram.me/joinchat/BAEj90A5QJ6WknEsNZWf4g\">usergroup</a>";
				}
				$message = "[inform all] $message";
				$this->send_to_user($message, [["unsubscrbe from ALL" => "/inform noall"]]);
			}
		}

		private function get_persist_key($sender){
			return "inform.state.$sender";
		}

		private function init_info_state(){
			$info_state = array();
			$info_state['next'] = false;
			$info_state['all'] = false;
			return $info_state;
		}

		private function array_persist($array, $key){
			$_text = json_encode($array);
			if( ! $_text ){
				send_to_user("Sorry, there was an error during processing - could not handle your /inform command!\n\nSerializing array to json failed - therefore no state could be persisted!");
				return false;
			}
			$this->object_broker->instance['core_persist']->store($key, $_text); 
			return true;
		}

		private function seen_user_ids(){
			$uids = $this->object_broker->instance['core_persist']->retrieve("inform.user_ids");
			$_t = json_decode($uids, true);
			if($_t){
				$uids = $_t;
			}else{
				$uids = false;
			}
			if(!$uids){
				$uids = array();
			}
			if(! array_key_exists('next', $uids)){
				$uids['next'] = array();
			}
			if(! array_key_exists('all', $uids)){
				$uids['all'] = array();
			}
			return $uids;
		}

		private function store_user_ids($arr){
			$val = json_encode($arr);
			if($val){
				$this->object_broker->instance['core_persist']->store("inform.user_ids", $val);
			}
		}

		private function build_array($key){
			$info_state = $this->object_broker->instance['core_persist']->retrieve($key);
			if($info_state){
				$_res = json_decode($info_state, true);
				if($_res){
					if(! array_key_exists('next', $_res)){
						$_res['next'] = false;
					}
					if(! array_key_exists('all', $_res) ){
						$_res['all'] = false;
					}
					$info_state = $_res;
				}else{
					$info_state = $this->init_info_state();
				}
			}else{
				$info_state = $this->init_info_state();
			}
			return $info_state;
		}

    public function process($trigger)
    {
        debug_log($this->classname . ": processing trigger $trigger");

        $chatid = $GLOBALS['layer7_stanza']['message']['chat']['id'];
        $senderid = $GLOBALS['layer7_stanza']['message']['from']['id'];

        $payload = trim(str_replace('/' . $trigger, '', $GLOBALS['layer7_stanza']['message']['text']));
				$payload = strtolower($payload);

				debug_log($this->classname . ": senderid=$senderid payload='$payload'");

				if($trigger !== "inform"){
					debug_log($this->classname . ": WRONG TRIGGER FOR THIS PLUGIN");
					return false;
				}
				if($chatid < 0){
					$this->send_to_user("The /inform command won't interact with groups!");
			
					// attempt to delete input message from group
					$msgid =  $GLOBALS['layer7_stanza']['message']['message_id'];
					$this->object_broker->instance['api_telegram']->delete_message($chatid, $msgid);
					return;
				}
				
				$uids = $this->seen_user_ids();
				$persist_key = $this->get_persist_key($senderid);
				$info_state = $this->build_array($persist_key); 
				if($payload === ""){
					if($info_state['next']){
						$payload = "nonext";
					}else{
						$payload = "next";
					}
				}
				if($payload === "next"){
					$info_state['next'] = true;
					if(! $this->array_persist($info_state, $persist_key)) { return false; }
					$this->send_to_user("You <b>will be informed</b> on the <b>next</b> opening of the segvault!");
					if(! in_array($senderid, $uids['next']) ){
						array_push($uids['next'], $senderid);
						$this->store_user_ids($uids);
					}
				}else if ($payload === "nonext"){
					$info_state['next'] = false;
					if(! $this->array_persist($info_state, $persist_key)) { return false; }
					$this->send_to_user("You <b>will NOT</b> be <b>informed</b> on the <b>next</b> opening of the segvault!");

					if( in_array($senderid, $uids['next']) ){
						$uids['next'] = array_diff($uids['next'], [ $senderid ]);
						$this->store_user_ids($uids);
					}
				}else if($payload === "all"){
					$info_state['all'] = true;
					if(! $this->array_persist($info_state, $persist_key)) { return false; }
					$this->send_to_user("You <b>will be informed</b> on <b>all</b> openings of the segvault!");	
					if(! in_array($senderid, $uids['all']) ){
						array_push($uids['all'], $senderid);
						$this->store_user_ids($uids);
					}
				}else if($payload === "noall"){
					$info_state['all'] = false;
					if(! $this->array_persist($info_state, $persist_key)) { return false; }
					$this->send_to_user("You <b>will NOT</b> be <b>informed</b> on <b>all</b> openings of the segvault!");
					if( in_array($senderid, $uids['all']) ){
						$uids['all'] = array_diff($uids['all'], [ $senderid ]);
						$this->store_user_ids($uids);
					}
				}
    }
}

?>
