<?php
/*
 * Copyright (C) 2019, Daniel Haslinger <creo+oss@mesanova.com>
 * This program is free software licensed under the terms of the GNU General Public License v3 (GPLv3).
 */

class PLUGIN_ECHO
{
    private $object_broker;
    private $classname;
    const ACL_MODE = 'white';    // white, black, none

    public function __construct($object_broker)
    {
        $this->classname = strtolower(static::class);

        $this->object_broker = $object_broker;
        $object_broker->plugins[] = $this->classname;
        debug_log($this->classname . ": starting up");

        $this->object_broker->instance['api_routing']->register("echo", $this->classname, "Repeats the received message body back to the current channel");
        $this->object_broker->instance['api_routing']->register("whisper", $this->classname, "Repeats the received message body to the sender via PM");
        $this->object_broker->instance['api_routing']->helptext("echo", "", "Repeats the received message body back to the current channel");
        $this->object_broker->instance['api_routing']->helptext("whisper", "", "Repeats the received message body to the sender via PM");
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
        debug_log($this->classname . ": processing trigger $trigger");

        $chatid = $GLOBALS['layer7_stanza']['message']['chat']['id'];
        $senderid = $GLOBALS['layer7_stanza']['message']['from']['id'];

        $payload = str_replace('/' . $trigger, '', $GLOBALS['layer7_stanza']['message']['text']);

        switch($trigger)
        {
            case "echo":
                // send a telegram message to the channel
                $this->object_broker->instance['api_telegram']->send_message($chatid, $payload);
                break;

            case "whisper":
                // send a telegram message to the sender, regardless of where the message was received from
                $this->object_broker->instance['api_telegram']->send_message($senderid, $payload);
                break;
        }
    }
}

?>