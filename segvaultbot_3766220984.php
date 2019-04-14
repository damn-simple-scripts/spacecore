<?php
/*
 * Copyright (C) 2019, Daniel Haslinger <creo+oss@mesanova.com>
 * This program is free software licensed under the terms of the GNU General Public License v3 (GPLv3).
 */

require("config.php.inc");

error_reporting(E_ALL);
ini_set('display_errors', 'on');
ini_set("error_log", $config['error_log']);

error_log("");
error_log("WAKEUP");

//error_log(print_r($_SERVER, true));

include_once('object_broker.php.inc');
$object_broker = new OBJECT_BROKER();


// Instantiate core classes first
$classes = array_filter(glob('core/*'), 'is_dir');
foreach($classes as $class_src)
{
    $class_dir = basename($class_src);
    include_once('core/' . $class_dir . '/entry.php.inc');
    $core_classname = 'CORE_'.strtoupper($class_dir);
    $object_broker->instance['core_' . $class_dir] = new $core_classname($object_broker);
    error_log("class $core_classname loaded");
}


// Instantiate apis
$apis = array_filter(glob('apis/*'), 'is_dir');
foreach($apis as $api_src)
{
    $api_dir = basename($api_src);
    include_once('apis/' . $api_dir . '/entry.php.inc');
    $api_classname = 'API_'.strtoupper($api_dir);
    $object_broker->instance['api_' . $api_dir] = new $api_classname($object_broker);
    error_log("class $api_classname loaded");
}


// Instantiate plugins
$plugins = array_filter(glob('plugins/*'), 'is_dir');
foreach($plugins as $plugin_src)
{
    $plugin_dir = basename($plugin_src);
    include_once('plugins/' . $plugin_dir . '/entry.php.inc');
    $plugin_classname = 'PLUGIN_'.strtoupper($plugin_dir);
    $object_broker->instance['plugin_' . $plugin_dir] = new $plugin_classname($object_broker);
    error_log("class $plugin_classname loaded");
}


// read incoming layer6 stanza (L6 = HTTP(s) in this particular case)
$layer6_stanza = file_get_contents("php://input");

// decode the layer 6 stanza and extract the layer 7 information (the actual Telegram protocol) into an assoc. array)
$GLOBALS['layer7_stanza'] = json_decode($layer6_stanza, true);


// write to debug log
error_log("telegram:receiveMessage: $layer6_stanza");


// Run through all plugins and execute any preprocessing steps
foreach($object_broker->plugins as $registered_plugin)
{
    if(isset($GLOBALS['layer7_stanza']['message']['text']))
    {
        // if available, use text preprocessor
        if(method_exists($object_broker->instance[$registered_plugin], 'router_preprocess_text'))
        {
            $object_broker->instance[$registered_plugin]->router_preprocess_text();
        }
    }

    if(isset($GLOBALS['layer7_stanza']['message']['photo']))
    {
        // if available, use photo preprocessor
        if(method_exists($object_broker->instance[$registered_plugin], 'router_preprocess_photo'))
        {
            $object_broker->instance[$registered_plugin]->router_preprocess_photo();
        }
    }

    if(method_exists($object_broker->instance[$registered_plugin], 'router_preprocess'))
    {
        // if available, use generic preprocessor
        $object_broker->instance[$registered_plugin]->router_preprocess();
    }
}

if(isset($GLOBALS['layer7_stanza']['message']['text']))
{
    // Interpret text commands and route them to their registered classes
    $object_broker->instance['api_routing']->route_text();
}

?>
