<?php
/*
 * Copyright (C) 2019, Daniel Haslinger <creo+oss@mesanova.com>
 * This program is free software licensed under the terms of the GNU General Public License v3 (GPLv3).
 */

class PLUGIN_PROCEDURE
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

        $this->object_broker->instance['api_routing']->register("teardown", $this->classname, "Procedure to shutdown the space");
        $this->object_broker->instance['api_routing']->helptext("teardown", "", "Procedure to shutdown the space");
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

    private function delete_msg($msgId)
    {
        $senderid = $GLOBALS['layer7_stanza']['message']['from']['id'];
        $this->object_broker->instance['api_telegram']->delete_message($senderid, $msgId);
    }

    public function process($trigger)
    {
        debug_log($this->classname . ": processing trigger $trigger");

        $chatid = $GLOBALS['layer7_stanza']['message']['chat']['id'];
        $senderid = $GLOBALS['layer7_stanza']['message']['from']['id'];

        $payload = str_replace('/' . $trigger, '', $GLOBALS['layer7_stanza']['message']['text']);
        $payload = trim($payload);
        debug_log($this->classname . ": payload \"$payload\"");

        $herald_ok = $this->object_broker->instance['api_routing']->acl_check_list($senderid, "plugin_heralding", "white");


        $_tok = NULL;
        $_use_tok = false;
        if(array_key_exists('plugin_token' , $this->object_broker->instance))
        {
            $_tok = $this->object_broker->instance['plugin_token']->generate_token();
            $_use_tok = true;
        }
        $tok_string = ($_use_tok ? " ".$_tok : "");

        switch(strtolower($trigger))
        {
            case "bootup":
                break;

            case "teardown":
                if( empty($payload) )
                {
                    $payload = "start";
                }
                $payload_arr = preg_split("/\s+/", $payload);
                if( count($payload_arr) <= 0 )
                {
                    return false;
                }
                if($_use_tok && $payload !== "start")
                {
                    $last_elem = array_slice($payload_arr, -1)[0];
                    debug_log("last_elem=".$last_elem);
                    $res = $this->object_broker->instance['plugin_token']->consume_token($last_elem);
                    if(!$res)
                    {
                        $this->send_to_user("Replay detected - ignoring input!");
                        return;
                    }
                }
                switch($payload_arr[0])
                {
                    case "start":
                        $spacestate = $this->object_broker->instance['core_persist']->retrieve('heralding.state');
                        if($spacestate)
                        {
                             if($spacestate != 'closed')
                             {
                                  if($herald_ok)
                                  {
                                      $this->send_to_user("<b>Optional</b> do you want to change the state?", [ [ "yes: memberonly" => "/membersonly after teardown completion" ] , [ "yes: closed" => "/shutdown" ] ] );
                                  }else{
                                      $spaceownergecos = $this->object_broker->instance['core_persist']->retrieve('heralding.lastchange.gecos');
                                      if($spaceownergecos)
                                      {
                                          $this->send_to_user("Ask ".$spaceownergecos." if a change in the spacestate should be performed!");
                                      }else{
                                          $this->send_to_user("Usually at this point the keymember is asked by the bot if the space state should be changed...\nit seems you are not a keymember...");
                                      }
                                  }
                             }
                        }
                        $this->send_to_user("Close the windows\nand the door to the <i>Loggia</i>", [ [ "closed" => "/teardown windows".$tok_string ] ] );
                        break;
                    case "windows":
                        $this->send_to_user("Clear Tables", [ [ "all clear" => "/teardown tables_clear".$tok_string ] ]);
                        break;
                    case "tables_clear":
                        $last_swipe_dry = $this->object_broker->instance['core_persist']->retrieve('procedure.swipes.dry.last');
                        $last_swipe_wet = $this->object_broker->instance['core_persist']->retrieve('procedure.swipes.wet.last');

                        $info_str = "";
                        if($last_swipe_dry || $last_swipe_wet)
                        {
                            $tz = new DateTimeZone('Europe/Vienna');
                            $_now = new DateTime(null, $tz);
                            if($last_swipe_dry)
                            {
                                $_parsed = new DateTime($last_swipe_dry, $tz);
                                $_d = (int) $_parsed->diff($_now)->format('%r%a');
                                $info_str .= "days since last sweep: ".$_d."\n";
                            }
                            if($last_swipe_wet)
                            {
                                $_parsed = new DateTime($last_swipe_wet, $tz);
                                $_d = (int) $_parsed->diff($_now)->format('%r%a');
                                $info_str .= "days since last wash up: ".$_d."\n";
                            }
                        }

                        $this->send_to_user("Swipe Floor if needed".(empty($info_str) ? "" : "\n\n".$info_str ), 
                        [
                            ["SKIP" => "/teardown not_swiped".$tok_string],
                            [ "Swiped Dry" => "/teardown swiped_dry".$tok_string , "Washed up" => "/teardown swiped_wet".$tok_string ] 
                        ]);
                        break;
                    case "swiped_dry":
                        $swipes = $this->object_broker->instance['core_persist']->retrieve('procedure.swipes.dry');
                        if(!$swipes)
                        {
                            $swipes = 1;
                        }else
                        {
                            $swipes += 1;
                        }
                        $this->send_to_user("Thank you!\n\nSwipes (dry) so far: ".$swipes);
                        $this->object_broker->instance['core_persist']->store('procedure.swipes.dry', $swipes);

                        $tz = new DateTimeZone('Europe/Vienna');
                        $_now = new DateTime(null, $tz);
                        $_time = $_now->format(DATE_ATOM);
                        $this->object_broker->instance['core_persist']->store('procedure.swipes.dry.last', $_time);


                        $this->send_to_user("Align the chairs", [ [ "aligned" => "/teardown chairs_aligned".$tok_string ] ]);
                        break;
                    case "swiped_wet":
                        $swipes = $this->object_broker->instance['core_persist']->retrieve('procedure.swipes.wet');
                        if(!$swipes)
                        {
                            $swipes = 1;
                        }else
                        {
                            $swipes += 1;
                        }
                        $this->send_to_user("Thank you!\n\nSwipes (wet) so far: ".$swipes);
                        $this->object_broker->instance['core_persist']->store('procedure.swipes.wet', $swipes);

                        $tz = new DateTimeZone('Europe/Vienna');
                        $_now = new DateTime(null, $tz);
                        $_time = $_now->format(DATE_ATOM);
                        $this->object_broker->instance['core_persist']->store('procedure.swipes.wet.last', $_time);

                        $this->send_to_user("Align the chairs", [ [ "aligned" => "/teardown chairs_aligned".$tok_string ] ]);
                        break;

                    case "not_swiped":
                        $this->send_to_user("Align the chairs", [ [ "aligned" => "/teardown chairs_aligned".$tok_string ] ]);
                        break;
                    case "chairs_aligned":
                        $this->send_to_user("Refill fridges", [ [ "refilled" => "/teardown refilled".$tok_string ] ]);
                        break;
                    case "refilled":
                        $this->send_to_user("Are enough bottles in stock?\nif not: notify in keymembers group and tag @creolis\n\nSee: http://segvault.space/wiki/doku.php?id=internal:prozesse#getraenkelieferung \n\nRequirements according to documentation (by 2019-11-05):\n\n- 2 Kisten Club Mate\n- 2 Kisten Bier\n- 1 Kiste Mate Granat\n- 1 Kiste Mate Winter\n- 2 Kisten Tirola Kola\n- 1 Kiste Makava\n- 1/2 Karton ChariTea", [ [ "checked or notified" => "/teardown stock_checked".$tok_string ] ]);
                        break;
                    case "stock_checked":
                        $this->send_to_user("Check Fridge for soon spoiled products", [ [ "Checked" => "/teardown fridge_checked".$tok_string ] ]);
                        break;
                    case "fridge_checked":
                        $this->send_to_user("Collect trash", [ [ "collected" => "/teardown trashed".$tok_string ] ]);
                        break;
                    case "trashed":
                        $this->send_to_user("Wash dishes if needed", [ [ "dishes clean" => "/teardown dishwashed".$tok_string ] ]);
                        break;
                    case "dishwashed":
                        $this->send_to_user("Tidy kitchen\n(stow away dishes, empty dishwasher, ...)", [ [ "kitchen tidy" => "/teardown kitchen_tidy".$tok_string ] ]);
                        break;
                    case "kitchen_tidy":
                        $this->send_to_user("Turn off heated equipment\n\n- solder station\n- 3d printer (if not in use)\n- laser cutter\n- ...", [ [ "all checked" => "/teardown checked_heated".$tok_string ] ]);
                        break;
                    case "checked_heated":
                        $this->send_to_user("Shutdown Lounge-Entertainment\n\n1. Turn off PC\n2. Shutdown projector\n3. curl up screen (remote is next to space time)", [ [ "ok" => "/teardown beamered".$tok_string ] ]);
                        break;
                    case "beamered":
                        $this->send_to_user("Turn off Mac at the <i>Bar</i>", [ [ "is off" => "/teardown mac_off".$tok_string ] ]);
                        break;
                    case "mac_off":
                        $this->send_to_user("Turn off the amplifier of the PA system\n(Computer power supply under the amplifier)", [ [ "is off" => "/teardown amplifier_off".$tok_string ] ]);
                        break;
                    case "amplifier_off":
                        $this->send_to_user("Turn off stand-alone-lamps, lightstrips and the lava lamp", [ [ "lamps off" => "/teardown lamps_off".$tok_string ] ]);
                        break;
                    case "lamps_off":
                        $this->send_to_user("Turn off the light in storage room, book shelf, ...", [ [ "lights are out" => "/teardown lights_out".$tok_string ] ]);
                        break;
                    case "lights_out":
                        $this->send_to_user("Turn off stuff with remote\n(the one at the fuse panel)", [ [ "stuff off" => "/teardown stuff_off".$tok_string ] ]);
                        break;
                    case "stuff_off":
                        $this->send_to_user("Turn off lights\n\nFuses <b>except</b> <i>Eingang</i>(23)\n\nplease also check the keymember group if there is cleaning of the toilets scheduled!\n\nReminder: take the trash with you", [ [ "it's dark" => "/teardown rooms_dark".$tok_string ] ]);
                        break;
                    case "rooms_dark":
                        global $config;
                        if($herald_ok)
                        {
                            if(isset($config['keysafe']))
                            {
                                $msg_id = $this->send_to_user("Reminder: ".$config['keysafe'], null);
                                $this->send_to_user("Lock inner space door!", [ [ "locked" => "/teardown locked ".$msg_id.$tok_string ] ]);
                                $this->object_broker->instance['core_persist']->store('procedure.msg_id', $msg_id);
                            }else{
                                $this->send_to_user("Lock inner space door!", [ [ "locked" => "/teardown locked".$tok_string] ]);
                            }
                        }else{
                            $spaceownergecos = $this->object_broker->instance['core_persist']->retrieve('heralding.lastchange.gecos');
                            if($spaceownergecos)
                            {
                                $this->send_to_user("Ask ".$spaceownergecos." to lock the inner door!");
                            }else{
                                $this->send_to_user("Usually at this point the keymember is asked to close the inner door\nit seems you are not a keymember...");
                            }
                        }
                        break;
                    case "locked":
                        $msg_id = $this->object_broker->instance['core_persist']->retrieve('procedure.msg_id');                          
                        if( count($payload_arr) >= 2 )
                        {
                            if(is_numeric($payload_arr[1]))
                            {
                                $this->delete_msg($payload_arr[1]);
                                if($msg_id)
                                {
                                    if( ( (string) $msg_id) != ( (string) $payload_arr[1]) )
                                    {
                                        delete_msg($msg_id);
                                    }
                                }
                            }else{
                                if($msg_id)
                                {
                                    delete_msg($msg_id);
                                }
                            }
                        }else if($msg_id)
                        {
                            delete_msg($msg_id);
                        }
                        $spacestate = $this->object_broker->instance['core_persist']->retrieve('heralding.state');
                        if($spacestate)
                        {
                             if($spacestate != 'closed')
                             {
                                 if(!$herald_ok)
                                 {
                                      $spaceownergecos = $this->object_broker->instance['core_persist']->retrieve('heralding.lastchange.gecos');
                                      if($spaceownergecos)
                                      {
                                          $this->send_to_user("Ask ".$spaceownergecos." if a change in the space state should be performed!");
                                      }else{
                                          $this->send_to_user("Usually at this point the keymember is asked by the bot if the space state should be changed...\nit seems you are not a keymember...");
                                      }
                                      $GLOBALS['layer7_stanza']['message']['text'] = "/teardown closed".$tok_string;
                                      $this->object_broker->instance['api_routing']->route_text();
                                 }else{
                                     $GLOBALS['layer7_stanza']['message']['text'] = "/shutdown";
                                     $this->object_broker->instance['api_routing']->route_text();

                                     $spacestate = $this->object_broker->instance['core_persist']->retrieve('heralding.state');
                                     if(!$spacestate || $spacestate != 'closed')
                                     {
                                         $this->send_to_user("Sending /shutdown ...\n\ndid it work?", [ ["NO" => "/shutdown" ], ["YES" => "/teardown closed".$tok_string ] ] );
                                     }
                                 }
                             }
                        }
                        $GLOBALS['layer7_stanza']['message']['text'] = "/teardown closed".$tok_string;
                        $this->object_broker->instance['api_routing']->route_text();
                        break;
                    case "closed":
                        $this->send_to_user("Lock outer door and check door\n\nbonus points for waiting for the traffic light to change (should be within 1 minute)", [ [ "locked and checked" => "/teardown locked_and_checked".$tok_string ] ]);
                        break;
                    case "locked_and_checked":
                        $this->send_to_user("Thank you - <b>shutdown complete</b>\n\nGet rid of the trash you have with you. If you don't wanna use the public trash bins you can go to this location:", null);
                        $this->object_broker->instance['api_telegram']->send_coordinate($senderid, 48.205827, 15.625760);
                        break;

                }
                break;
        }
    }
}

?>
