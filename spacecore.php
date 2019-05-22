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
        if (!isset($_GET['token']) || $_GET['token'] != $config['bot_token']) {
            error_log("telegram:SenderAuthentication: Invalid token: " . $_GET['token']);
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

    } else {
        // Invalid JSON encountered (for whatever reason, we don't care).
        // Treat this as plain GET/POST requests

        error_log("getpost:receivePostBody: '$layer6_stanza'");

        /* TODO: Right now we're tapping into the data from the persistence module, but this is shitty in too many ways.
        // The next step for this section will be to refactor this to ASK all registered plugins for public / private
        // data to be exposed via plain GET/POST requests.
        */

        // -- collect information that is available to unauthenticated sessions
        $spacestate = $object_broker->instance['core_persist']->retrieve('heralding.state');
        $public_array = [];
        $public_array['spacecore_api'] = $config['webapi_level'];
        $public_array['spacestate'] = ($spacestate == 'open' ? 'open' : 'closed');

        // -- collect information that is available to authenticated sessions only
        $private_array = [];

        // TODO: Move WebAPI tokens to the persistence module and generate them on demand
        if (!isset($_GET['token']) || $_GET['token'] != $config['webapi_token']) {
            $private_array = ['unauthenticated'];
        } else {
            $private_array['spacestate'] = $spacestate;
        }

        // -- here we go:
        $return_array = ['public' => $public_array, 'private' => $private_array];
        print json_encode($return_array);
    }
}

?>
