<?php
/*
 * Copyright (C) 2019, Daniel Haslinger <creo+oss@mesanova.com>
 * This program is free software licensed under the terms of the GNU General Public License v3 (GPLv3).
 */

require("config.inc.php");

error_reporting(E_ALL);
ini_set('display_errors', 'on');
ini_set("error_log", $config['error_log']);

error_log("");
error_log("WAKEUP");

include_once('object_broker.inc.php');
$object_broker = new OBJECT_BROKER();


// Instantiate core classes first
$classes = array_filter(glob('core/*'), 'is_dir');
foreach($classes as $class_src)
{
    $class_dir = basename($class_src);
    include_once('core/' . $class_dir . '/init.inc.php');
    $core_classname = 'CORE_'.strtoupper($class_dir);
    $object_broker->instance['core_' . $class_dir] = new $core_classname($object_broker);
    error_log("class $core_classname loaded");
}


// Instantiate apis
$apis = array_filter(glob('apis/*'), 'is_dir');
foreach($apis as $api_src)
{
    $api_dir = basename($api_src);
    include_once('apis/' . $api_dir . '/init.inc.php');
    $api_classname = 'API_'.strtoupper($api_dir);
    $object_broker->instance['api_' . $api_dir] = new $api_classname($object_broker);
    error_log("class $api_classname loaded");
}


// Instantiate plugins
$plugins = array_filter(glob('plugins/*'), 'is_dir');
foreach($plugins as $plugin_src)
{
    $plugin_dir = basename($plugin_src);
    include_once('plugins/' . $plugin_dir . '/init.inc.php');
    $plugin_classname = 'PLUGIN_'.strtoupper($plugin_dir);
    $object_broker->instance['plugin_' . $plugin_dir] = new $plugin_classname($object_broker);
    error_log("class $plugin_classname loaded");
}

// determine invocation method: CLI or Serverbased?
if(php_sapi_name() == 'cli')
{
    // fired up via command line. It's cron time.
    error_log("CLI MODE: assuming scheduled invocation");

    // Run through all plugins and execute any housekeeping steps
    foreach ($object_broker->plugins as $registered_plugin) {
        if (method_exists($object_broker->instance[$registered_plugin], 'router_housekeeping')) {
            // if available, do housekeeping (scheduled tasks, etc.)
            $object_broker->instance[$registered_plugin]->router_housekeeping();
        }
    }
}
else
{
    // read incoming layer6 stanza (L6 = HTTP(s) in this particular case)
    $layer6_stanza = file_get_contents("php://input");

    // decode the layer 6 stanza and extract the layer 7 information (the actual Telegram protocol) into an assoc. array)
    $GLOBALS['layer7_stanza'] = json_decode($layer6_stanza, true);

    // right now we are not sure if the stuff we received was valid JSON..
    if (json_last_error() === JSON_ERROR_NONE && $layer6_stanza != NULL) {
        // Valid JSON encountered. Treat this as a telegram message
        error_log("telegram:receiveMessage: VALID JSON DECODED: '$layer6_stanza'");

        // Is the sender legit?
        if (!isset($_GET['token']) || (isset($_GET['token']) && $_GET['token'] != $config['bot_token'])) {
            error_log("telegram:SenderAuthentication: Invalid token: " . ( isset($_GET['token']) ? $_GET['token'] : 'NONE' ) );
            exit;
        }

        // Run through all plugins and execute any preprocessing steps
        foreach ($object_broker->plugins as $registered_plugin) {
            if (isset($GLOBALS['layer7_stanza']['message']['text'])) {
                // if available, use text preprocessor
                if (method_exists($object_broker->instance[$registered_plugin], 'router_preprocess_text')) {
                    $object_broker->instance[$registered_plugin]->router_preprocess_text();
                }
            }

            if (isset($GLOBALS['layer7_stanza']['message']['photo'])) {
                // if available, use photo preprocessor
                if (method_exists($object_broker->instance[$registered_plugin], 'router_preprocess_photo')) {
                    $object_broker->instance[$registered_plugin]->router_preprocess_photo();
                }
            }

            if (method_exists($object_broker->instance[$registered_plugin], 'router_preprocess')) {
                // if available, use generic preprocessor
                $object_broker->instance[$registered_plugin]->router_preprocess();
            }
        }

        if (isset($GLOBALS['layer7_stanza']['message']['text'])) {
            // Interpret text commands and route them to their registered classes
            $object_broker->instance['api_routing']->route_text();
        }
        if (isset($GLOBALS['layer7_stanza']['callback_query']['data'])) {
            // Interpret text commands and route them to their registered classes
            $object_broker->instance['api_routing']->route_text();
        }

    } else {
				$object_broker->instance['api_spaceapi']->process_requests();

        // Invalid JSON encountered (for whatever reason, we don't care).
        // Treat this as plain GET/POST requests

        error_log("getpost:receivePostBody: '$layer6_stanza'");
    }
}

?>
