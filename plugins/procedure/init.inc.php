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
        error_log($this->classname . ": starting up");

        //$this->object_broker->instance['api_routing']->register("bootup", $this->classname, "Procedure to start the space");
        $this->object_broker->instance['api_routing']->register("teardown", $this->classname, "Procedure to shutdown the space");
        //$this->object_broker->instance['api_routing']->helptext("bootup", "", "Procedure to start the space");
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
        error_log($this->classname . ": processing trigger $trigger");

        $chatid = $GLOBALS['layer7_stanza']['message']['chat']['id'];
        $senderid = $GLOBALS['layer7_stanza']['message']['from']['id'];

        $payload = str_replace('/' . $trigger, '', $GLOBALS['layer7_stanza']['message']['text']);
        $payload = trim($payload);
        error_log($this->classname . ": payload \"$payload\"");

        $herald_ok = $this->object_broker->instance['api_routing']->acl_check_list($senderid, "plugin_heralding", "white");

        switch($trigger)
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
                                      $this->send_to_user("<b>Optional</b> do you want to change the state?", [ [ "yes: memberonly" => "/membersonly" ] , [ "yes: closed" => "/shutdown" ] ] );
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
                        $this->send_to_user("Close the windows\nand the door to the <i>Loggia</i>", [ [ "closed" => "/teardown windows" ] ] );
                        break;
                    case "windows":
                        $this->send_to_user("Clear Tables", [ [ "all clear" => "/teardown tables_clear" ] ]);
                        break;
                    case "tables_clear":
                        $this->send_to_user("Swipe Floor if needed", [ [ "Swiped" => "/teardown swiped" , "SKIP" => "/teardown not_swiped" ] ]);
                        break;
                    case "swiped":
                        $swipes = $this->object_broker->instance['core_persist']->retrieve('procedure.swipes');
                        if(!$swipes)
                        {
                            $swipes = 1;
                        }else
                        {
                            $swipes += 1;
                        }
                        $this->send_to_user("Thank you!\n\nSwipes so far: ".$swipes);
                        $this->object_broker->instance['core_persist']->store('procedure.swipe', $swipes);
                        // FALL THROUGH
                    case "not_swiped":
                        $this->send_to_user("Align the chairs", [ [ "aligned" => "/teardown chairs_aligned" ] ]);
                        break;
                    case "chairs_aligned":
                        $this->send_to_user("Refill fridges", [ [ "refilled" => "/teardown refilled" ] ]);
                        break;
                    case "refilled":
                        $this->send_to_user("Are enough bottles in stock?\nif not notify: notify in keymembers group and tag @creolis\n\nSee: http://segvault.space/wiki/doku.php?id=internal:prozesse#getraenkelieferung \n\nRequirements according to documentation (by 2019-11-05):\n\n- 2 Kisten Club Mate\n- 2 Kisten Bier\n- 1 Kiste Mate Granat\n- 1 Kiste Mate Winter\n- 2 Kisten Tirola Kola\n- 1 Kiste Makava\n- 1/2 Karton ChariTea", [ [ "checked or notified" => "/teardown stock_checked" ] ]);
                        break;
                    case "stock_checked":
                        $this->send_to_user("Check Fridge for soon spoiled products", [ [ "Checked" => "/teardown fridge_checked" ] ]);
                        break;
                    case "fridge_checked":
                        $this->send_to_user("Collect trash", [ [ "collected" => "/teardown trashed" ] ]);
                        break;
                    case "trashed":
                        $this->send_to_user("Wash dishes if needed", [ [ "dishes clean" => "/teardown dishwashed" ] ]);
                        break;
                    case "dishwashed":
                        $this->send_to_user("Tidy kitchen\n(stow away dishes, empty dishwasher, ...)", [ [ "kitchen tidy" => "/teardown kitchen_tidy" ] ]);
                        break;
                    case "kitchen_tidy":
                        $this->send_to_user("Turn off heated equipment\n\n- solder station\n- 3d printer (if not in use)\n- laser cutter\n- ...", [ [ "all checked" => "/teardown checked_heated" ] ]);
                        break;
                    case "checked_heated":
                        $this->send_to_user("Shutdown Lounge-Entertainment\n\n1. Turn off PC\n2. Shutdown projector\n3. curl up screen (remote is next to space time)", [ [ "ok" => "/teardown beamered" ] ]);
                        break;
                    case "beamered":
                        $this->send_to_user("Turn off Mac at the <i>Bar</i>", [ [ "is off" => "/teardown mac_off" ] ]);
                        break;
                    case "mac_off":
                        $this->send_to_user("Turn off the amplifier of the PA system\n(Computer power supply under the amplifier)", [ [ "is off" => "/teardown amplifier_off" ] ]);
                        break;
                    case "amplifier_off":
                        $this->send_to_user("Turn off stand-allown-lamps and chain of lights", [ [ "lamps off" => "/teardown lamps_off" ] ]);
                        break;
                    case "lamps_off":
                        $this->send_to_user("Turn off the light in storage room, book shelf, ...", [ [ "lights are out" => "/teardown lights_out" ] ]);
                        break;
                    case "lights_out":
                        $this->send_to_user("Turn off stuff with remote\n(the one at the fuse panel)", [ [ "stuff off" => "/teardown stuff_off" ] ]);
                        break;
                    case "stuff_off":
                        $this->send_to_user("Turn off lights\n\nFuses <b>except</b> <i>Eingang</i>(23)\n\nplease also check the keymember group if there is cleaning of the toilets scheduled!\n\nReminder: take the trash with you", [ [ "it's dark" => "/teardown rooms_dark" ] ]);
                        break;
                    case "rooms_dark":
                        global $config;
                        if( isset($config['keysafe']) && $herald_ok)
                        {
                            $msg_id = $this->send_to_user("Reminder: ".$config['keysafe'], null);
                            $this->send_to_user("Lock inner space door!", [ [ "locked" => "/teardown locked ".$msg_id ] ]);
                            $this->object_broker->instance['core_persist']->store('procedure.msg_id', $msg_id);
                        }
                        $this->send_to_user("Lock inner space door!", [ [ "locked" => "/teardown locked"] ]);

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
                                      $GLOBALS['layer7_stanza']['message']['text'] = "/teardown closed";
                                      $this->object_broker->instance['api_routing']->route_text();
                                 }else{
                                     $GLOBALS['layer7_stanza']['message']['text'] = "/shutdown";
                                     $this->object_broker->instance['api_routing']->route_text();

                                     $spacestate = $this->object_broker->instance['core_persist']->retrieve('heralding.state');
                                     if(!$spacestate || $spacestate != 'closed')
                                     {
                                         $this->send_to_user("Sending /shutdown ...\n\ndid it work?", [ ["NO" => "/shutdown" ], ["YES" => "/teardown closed" ] ] );
                                     }
                                 }
                             }
                        }
                        $GLOBALS['layer7_stanza']['message']['text'] = "/teardown closed";
                        $this->object_broker->instance['api_routing']->route_text();
                        break;
                    case "closed":
                        $this->send_to_user("Lock outer door and check door\n\nbonus points for waiting for the traffic light to change (should be within 1 minute)", [ [ "locked and checked" => "/teardown locked_and_checked" ] ]);
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
