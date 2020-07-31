<?php
/*
 * Copyright (C) 2019, Daniel Haslinger <creo+oss@mesanova.com>
 * This program is free software licensed under the terms of the GNU General Public License v3 (GPLv3).
 */

class API_ROUTING
{
    private $object_broker;
    private $hooks;
    private $helptexts;
    private $classname;

    public function __construct($object_broker)
    {
        $this->classname = strtolower(static::class);

        $this->object_broker = $object_broker;
        $object_broker->apis[] = $this->classname;
        debug_log($this->classname .  ": starting up");

        $this->hooks = [];
        $this->helptexts = [];
    }


    public function __destruct()
    {

    }


    public function route_text()
    {
        $message = null;
        $senderid = null;
        if(isset($GLOBALS['layer7_stanza']['message']['text']))
        {
            $message = $GLOBALS['layer7_stanza']['message']['text'];
            $senderid = $GLOBALS['layer7_stanza']['message']['from']['id'];
        }else if(isset($GLOBALS['layer7_stanza']['callback_query']['data'])){
            debug_log($this->classname . ": convert from inline");
            $message = $GLOBALS['layer7_stanza']['callback_query']['data'];
            $senderid = $GLOBALS['layer7_stanza']['callback_query']['from']['id'];
            $GLOBALS['layer7_stanza']['message']['text'] = $message;
            $GLOBALS['layer7_stanza']['message']['chat']['id'] = $senderid;
            $GLOBALS['layer7_stanza']['message']['from'] = $GLOBALS['layer7_stanza']['callback_query']['from'];
        }else{
            error_log($this->classname . ": MESSAGE COULD NOT BE PARSED");
            return;
        }

        if($message[0] == '/')
        {
            debug_log($this->classname . ": command prefix detected");

            $trigger = ltrim(explode(' ', trim($message))[0], '/');
            debug_log($this->classname . ": command trigger detected: $trigger");

            $classname = $this->resolve($trigger);
						if ( ! $classname )
						{
							$_chr_pos = strpos($trigger, '@');
							if ( $_chr_pos !== false )
							{
								$_sub_trigger = substr($trigger, 0, $_chr_pos);
								$classname = $this->resolve($_sub_trigger);
								if($classname)
								{
									$trigger = $_sub_trigger;
								}
							}
						}

            if ($classname)
            {
                $access_permitted = false;

                // check if the class supports ACLs and actually uses them
                if(!method_exists($this->object_broker->instance[$classname], 'get_acl_mode') || $this->object_broker->instance[$classname]->get_acl_mode() != 'none')
                {
                    $list = $this->object_broker->instance[$classname]->get_acl_mode();

                    if($this->acl_check_list($senderid, $classname, $list))
                    {
                        // The user was found on the list. Let's see how this translates to permissions...
                        if ($list == 'black')
                        {
                            // The user is blacklisted
                            error_log($this->classname . ": routing request to $classname for user $senderid refused (blacklisted!)");
                            $access_permitted = false;
                        }
                        elseif ($list == 'white')
                        {
                            // The user is whitelisted
                            error_log($this->classname . ": routing request to $classname for user $senderid accepted (whitelisted!)");
                            $access_permitted = true;
                        }
                    }
                    else
                    {
                        // The user was NOT found on the list. Let's see what how THAT translates to permissions...
                        if ($list == 'black')
                        {
                            error_log($this->classname . ": routing request to $classname for user $senderid accepted (not blacklisted!)");
                            $access_permitted = true;
                        }
                        elseif ($list == 'white')
                        {
                            error_log($this->classname . ": routing request to $classname for user $senderid accepted (not whitelisted!)");
                            $access_permitted = false;
                        }
                    }
                }
                else
                {
                        // the class either does not support ACLs or it does not make use of them -> Free For All.
                        $access_permitted = true;
                }

                if($access_permitted === true)
                {
                    if (method_exists($this->object_broker->instance[$classname], 'process'))
                    {
                        debug_log($this->classname . ": routing request to $classname");
                        $this->object_broker->instance[$classname]->process($trigger);
                    }
                    else
                    {
                        error_log($this->classname . ": the class registered to trigger $trigger is unsupported");
                    }
                }
                else
                {
                    // Feedback to the user
                    $message = "<b>Denied!</b>\n You are not authorized to access this command.\n Ask a moderator for permissions to use $classname and/or use \"/system permissions\" to check your privileges!";
                    $this->object_broker->instance['api_telegram']->send_message($senderid, $message);
                }
            }
            else
            {
                $payload = "Unknown command. Use '/system help' to get more information.";
                $this->object_broker->instance['api_telegram']->send_message($senderid, $payload);
                error_log($this->classname . ": no class registered to trigger: $trigger --> sending help text");
            }
        }
        else
        {
            // that's no command. This branch could facilitate some chatbot feature.
            debug_log($this->classname . ": no chatmode available yet --> ignoring");
        }
    }

		private function to_lower($string)
		{
			if(function_exists('mb_strtolower')){
				return mb_strtolower($string);
			}else{
				return strtolower($string);
			}
		}

    public function register($trigger, $classname, $description = NULL)
    {
        $this->hooks[$trigger] = $classname;
				$trigger_lower = $this->to_lower($trigger);
				if($trigger_lower !== $trigger){
					$this->hooks[$trigger_lower] = $classname;
				}
        $this->hooks[$trigger] = $classname;
        debug_log($this->classname . ": registered class $classname on trigger $trigger");
    }

    public function helptext($trigger, $modifier, $description = NULL)
    {
        $this->helptexts[$trigger][$modifier] = $description;
        debug_log($this->classname . ": registered helptext for trigger $trigger");
    }

    public function resolve($trigger)
    {
        if(isset($this->hooks[$trigger]))
        {
            $classname = $this->hooks[$trigger];
            debug_log($this->classname . ": resolved class $classname from trigger $trigger");
            return $classname;
        }
        else
        {
						$trigger_lower = $this->to_lower($trigger);
						if($trigger_lower !== $trigger){
							$res = $this->resolve($trigger_lower);
							if(! $res ){
								error_log($this->classname . ": could not resolve trigger $trigger");
							}
							return $res;
						}
						error_log($this->classname . ": could not resolve trigger $trigger");
            return false;
        }
    }


    public function get_hooks_map()
    {
        // create a map of all hooks organized by classname as associative array
        $map = [];
        foreach($this->hooks as $trigger => $classname)
        {
            $map[$classname][] = $trigger;
        }

        return $map;
    }

    public function get_help_map()
    {
        // return a map of all help texts organized by classname as associative array
        return $this->helptexts;
    }


    public function acl_check_list($userid, $classname, $list)
    {
        // retrieve the desired list for this class
        $acl = $this->object_broker->instance['core_persist']->retrieve('acl_' . $list . "_$classname");
        if(!$acl)
        {
            error_log($this->classname . ": no list $list for class $classname");
            return false;
        }
        else
        {
            // decode the list and check if the user is registered to it
            $acl_assoc = json_decode($acl, true);
            if(isset($acl_assoc[$userid]))
            {
                debug_log($this->classname . ": user $userid is listed on $list for class $classname since EPOCH:" . $acl_assoc[$userid]);
                return $acl_assoc[$userid];
            }
            else
            {
                debug_log($this->classname . ": user $userid is not listed on $list for class $classname");
                return false;
            }
        }
    }


    public function acl_modify_list($userid, $classname, $list, $action)
    {
        if(!ctype_digit($userid))
        {
            error_log($this->classname . ": userid $userid is invalid and can not be registered to $classname:$list");
            return false;
        }

        // retrieve the desired list for this class
        $acl = $this->object_broker->instance['core_persist']->retrieve('acl_' . $list . "_$classname");

        if(!$acl)
        {
            error_log($this->classname . ": no list $list for class $classname");
            $acl_assoc = [];
        }
        else
        {
            // decode the list and check if the user is registered to it
            $acl_assoc = json_decode($acl, true);
        }

        if($action == 'register')
        {
            if(isset($acl_assoc[$userid]))
            {
                error_log($this->classname . ": registration failed - user $userid was already registered to $list for class $classname since EPOCH:" . $acl_assoc[$userid]);
                return false;
            }
            else
            {
                $acl_assoc[$userid] = time();
                $acl = json_encode($acl_assoc);
                $this->object_broker->instance['core_persist']->store('acl_' . $list . "_$classname", $acl);
                error_log($this->classname . ": registration successful - user $userid is now registered to $list for class $classname");
                return true;
            }
        }
        elseif($action == 'unregister')
        {
            if(isset($acl_assoc[$userid]))
            {
                unset($acl_assoc[$userid]);
                $acl = json_encode($acl_assoc);
                $this->object_broker->instance['core_persist']->store('acl_' . $list . "_$classname", $acl);
                error_log($this->classname . ": unregister successful - user $userid successfully removed from $list for class $classname");
                return false;
            }
            else
            {
                error_log($this->classname . ": unregister failed - user $userid not found on list $list for class $classname");
            }
        }
    }

}

?>
