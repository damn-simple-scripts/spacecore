<?php
/*
 * Copyright (C) 2019, Daniel Haslinger <creo+oss@mesanova.com>
 * This program is free software licensed under the terms of the GNU General Public License v3 (GPLv3).
 */

class PLUGIN_SANITIZE
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
    }


    public function __destruct()
    {

    }


    public function get_acl_mode()
    {
        return self::ACL_MODE;
    }


    public function router_preprocess()
    {

        $current_update_id = $GLOBALS['layer7_stanza']['update_id'];
        $last_update_id = $this->object_broker->instance['core_persist']->retrieve('update_id');
        error_log($this->classname . ":dedup: $last_update_id vs $current_update_id");
        if(!$last_update_id)
        {
            error_log($this->classname . ":dedup: no update id in persistent store -> bootstrapping");
            $this->object_broker->instance['core_persist']->store('update_id', $current_update_id);
        }
        elseif($last_update_id >= $current_update_id)
        {
            error_log($this->classname . ":dedup: possible duplicate -> ignoring");
            exit;
        }
        else
        {
            $this->object_broker->instance['core_persist']->store('update_id', $current_update_id);
        }

    }
}

?>