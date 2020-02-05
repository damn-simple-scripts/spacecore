<?php
/*
 * Copyright (C) 2019, Daniel Haslinger <creo+oss@mesanova.com>
 * This program is free software licensed under the terms of the GNU General Public License v3 (GPLv3).
 */

class PLUGIN_EVENTS
{
    private $object_broker;
    private $classname;
    const ACL_MODE = 'black';    // white, black, none


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


    public function get_acl_mode()
    {
        return self::ACL_MODE;
    }


    public function router_preprocess_photo()
    {
        //error_log(print_r($GLOBALS['layer7_stanza'], true));

        // the photo array is ordered by filesize (ascending)
        // so the largest file is at the end of the array
        $photo_data = end($GLOBALS['layer7_stanza']['message']['photo']);
        debug_log($this->classname . ": found image locator" . $photo_data['file_id'] . " (size:" . $photo_data['file_size'] . ")");

        // we need an area to dump the files
        if(!is_dir('photos')) mkdir('photos');

        // if an index exists, fine. If not, create one to prohibit leaks due to directory listings.
        touch('photos/index.html');

        $this->object_broker->instance['api_telegram']->download_resource($photo_data['file_id'], 'photos/' . time() . '_' . md5($photo_data['file_id']));
    }

}

?>
