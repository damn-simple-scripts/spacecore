<?php
/*
 * Copyright (C) 2019, Daniel Haslinger <creo+oss@mesanova.com>
 * This program is free software licensed under the terms of the GNU General Public License v3 (GPLv3).
 */

class PLUGIN_SYSTEM
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

        $this->object_broker->instance['api_routing']->register("system", $this->classname, "System related commands");
        $this->object_broker->instance['api_routing']->helptext("system", "", "System related commands");
        $this->object_broker->instance['api_routing']->helptext("system", "commands", "List all commands currently available");
        $this->object_broker->instance['api_routing']->helptext("system", "whoami", "Show user-related metadata");
        $this->object_broker->instance['api_routing']->helptext("system", "permissions", "List all permissions associated with the user");
        $this->object_broker->instance['api_routing']->helptext("system", "whitelist (register/remove) UID PLUGIN_NAME", "Add/remove a user to/from a plugin in whitelist mode");
        $this->object_broker->instance['api_routing']->helptext("system", "blacklist (register/remove) UID PLUGIN_NAME", "Add/remove a user to/from a plugin in blacklist mode");
        $this->object_broker->instance['api_routing']->helptext("system", "help", "The information you're reading at the moment");
    }


    public function __destruct()
    {

    }


    public function get_acl_mode()
    {
        return self::ACL_MODE;
    }


    public function process($trigger)
    {
        error_log($this->classname . ": processing trigger $trigger");
        global $config;

        $senderid = $GLOBALS['layer7_stanza']['message']['from']['id'];
        $sendergecos = $GLOBALS['layer7_stanza']['message']['from']['first_name'];
        $senderlocale = $GLOBALS['layer7_stanza']['message']['from']['language_code'];
        $senderusername = $GLOBALS['layer7_stanza']['message']['from']['username'];

        $payload = trim(str_replace('/' . $trigger, '', $GLOBALS['layer7_stanza']['message']['text']));

        switch(strtok($payload, ' '))
        {
            case "permissions":
                // tell the user about his current permission set
                $message = "";
                foreach($this->object_broker->plugins as $registered_plugin_name)
                {
                    $message .= "<b>$registered_plugin_name:</b>\n";

                    if(method_exists($this->object_broker->instance[$registered_plugin_name], 'get_acl_mode'))
                    {
                        $message .= " ACL mode: " . $this->object_broker->instance[$registered_plugin_name]->get_acl_mode() . "\n";
                    }
                    else
                    {
                        $message .= " ACL mode: none\n";
                    }

                    $check_blacklist = $this->object_broker->instance['api_routing']->acl_check_list($senderid, $registered_plugin_name, 'black');
                    if($check_blacklist)
                    {
                        $check_blacklist = date("d.m.Y H:i", $check_blacklist);
                        $message .= " Blacklisted: yes (since $check_blacklist)\n";
                    }
                    else
                    {
                        $message .= " Blacklisted: no\n";
                    }

                    $check_whitelist = $this->object_broker->instance['api_routing']->acl_check_list($senderid, $registered_plugin_name, 'white');
                    if($check_whitelist)
                    {
                        $check_whitelist = date("d.m.Y H:i", $check_whitelist);
                        $message .= " Whitelisted: yes (since $check_whitelist)\n";
                    }
                    else
                    {
                        $message .= " Whitelisted: no\n";
                    }

                    $message .= "\n";
                }

                $this->object_broker->instance['api_telegram']->send_message($senderid, $message);
                break;

            case "commands":
                // tell the user what commands are available at the moment
                $message = "";
                foreach($this->object_broker->instance['api_routing']->get_hooks_map() as $hook_classname => $hook_triggers)
                {
                    $message .= "<b>$hook_classname:</b>\n";
                    foreach($hook_triggers as $trigger)
                    {
                        $message .= " /$trigger,";
                    }
                    $message = rtrim($message, ',') . "\n\n";
                }
                $this->object_broker->instance['api_telegram']->send_message($senderid, $message);
                break;

            case "help":
                // give hints on features and usage
                $message = "";
                foreach($this->object_broker->instance['api_routing']->get_help_map() as $hook_trigger => $help_arr)
                {
                    foreach($help_arr as $modifier => $helptext)
                    {
                        if(!$modifier)
                        {
                            $message .= "<b>/$hook_trigger</b>\n<i>$helptext</i>\n\n";
                        }
                        else
                        {
                            $message .= "    <b>&#746; $modifier</b>\n    <i>$helptext</i>\n\n";
                        }
                    }

                }
                $message .= "\n\n";

                error_log("SENDING: " . $message);
                $this->object_broker->instance['api_telegram']->send_message($senderid, $message);
                break;

            case "whoami":
                // tell the user who he is from the bots perspective
                $message = "<b>ID:</b>\n $senderid\n\n" .
                            "<b>Username:</b>\n $senderusername\n\n" .
                            "<b>Gecos:</b>\n $sendergecos\n\n" .
                            "<b>Locale:</b>\n $senderlocale\n";

                $this->object_broker->instance['api_telegram']->send_message($senderid, $message);
                break;

            case "whitelist":
                // add a user to a whitelist
                if(isset($config['admins'][$senderid]))
                {
                    // Expected format: /system whitelist register <uid> <class>
                    error_log($this->classname . ": ---$payload---");

                    $payload_args = explode(' ', $payload);
                    $whitelist_command = $payload_args[1];
                    $whitelist_user = $payload_args[2];
                    $whitelist_class = $payload_args[3];

                    if($whitelist_class == "")
                    {
                        $message = "<b>Error</b> you tried to whitelist the user for empty-string!";
                        $this->object_broker->instance['api_telegram']->send_message($senderid, $message);
                        return false;
                    }

                    $valid_whitelist_commands = array('register', 'unregister');
                    if(!in_array($whitelist_command, $valid_whitelist_commands))
                    {
                        $message = "<b>Error</b> Action '".$whitelist_command."' is not allowed by this plugin.\nAllowed are: ".join(", ", $valid_whitelist_commands);
                        $this->object_broker->instance['api_telegram']->send_message($senderid, $message);
                        return false;
                    }

                    if($this->object_broker->instance['api_routing']->acl_modify_list($whitelist_user, $whitelist_class, 'white', $whitelist_command))
                    {
                        $message = "<b>Success!</b>\n User $whitelist_user: $whitelist_class:white -> $whitelist_command";
                        $this->object_broker->instance['api_telegram']->send_message($senderid, $message);

                        $message = "<b>UPGRADES BABY!</b>\n You are now enrolled to $whitelist_class:white";
                        $this->object_broker->instance['api_telegram']->send_message($whitelist_user, $message);
                    }
                    else
                    {
                        $message = "<b>Failed!</b>\n $whitelist_command user $whitelist_user @ $whitelist_class:white failed (check logs)";
                        $this->object_broker->instance['api_telegram']->send_message($senderid, $message);
                    }
                }
                else
                {
                    $message = "<b>Failed!</b>\n You are not allowed to modify ACLs";
                    $this->object_broker->instance['api_telegram']->send_message($senderid, $message);
                }
                break;

            case "blacklist":
                // add a user to a blacklist
                if(isset($config['admins'][$senderid]))
                {
                    // Expected format: /system blacklist register <uid> <class>
                    $payload_args = explode(' ', $payload);
                    $blacklist_command = $payload_args[1];
                    $blacklist_user = $payload_args[2];
                    $blacklist_class = $payload_args[3];
                    
                    if($blacklist_class == "")
                    {
                        $message = "<b>Error</b> you tried to blacklist the user for empty-string!";
                        $this->object_broker->instance['api_telegram']->send_message($senderid, $message);
                        return false;
                    }

                    $valid_blacklist_commands = array('register', 'unregister');
                    if(!in_array($blacklist_command, $valid_blacklist_commands))
                    {
                        $message = "<b>Error</b> Action '".$blacklist_command."' is not allowed by this plugin.\nAllowed are: ".join(", ", $valid_blacklist_commands);
                        $this->object_broker->instance['api_telegram']->send_message($senderid, $message);
                        return false;
                    }


                    if($this->object_broker->instance['api_routing']->acl_modify_list($blacklist_user, $blacklist_class, 'black', $blacklist_command))
                    {
                        $message = "<b>Success!</b>\n User $blacklist_user: $blacklist_class:black -> $blacklist_command";
                    }
                    else
                    {
                        $message = "<b>Failed!</b>\n $blacklist_command user $blacklist_user @ $blacklist_class:black failed (check logs)";
                    }
                    $this->object_broker->instance['api_telegram']->send_message($senderid, $message);
                }
                else
                {
                    $message = "<b>Failed!</b>\n You are not allowed to modify ACLs";
                    $this->object_broker->instance['api_telegram']->send_message($senderid, $message);
                }
                break;
        }
    }
}

?>
