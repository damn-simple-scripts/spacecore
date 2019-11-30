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
    $path = 'core/' . $class_dir . '/init.inc.php';
    if(file_exists($path))
    {
        include_once($path);
        $core_classname = 'CORE_'.strtoupper($class_dir);
        $object_broker->instance['core_' . $class_dir] = new $core_classname($object_broker);
        error_log("class $core_classname loaded");
    }else{
        error_log("Directory for core $plugin_classname exists, but no 'init.inc.php' file was found");
    }
}


// Instantiate apis
$apis = array_filter(glob('apis/*'), 'is_dir');
foreach($apis as $api_src)
{
    $api_dir = basename($api_src);
    $path = 'apis/' . $api_dir . '/init.inc.php';
    if(file_exists($path))
    {
        include_once($path);
        $api_classname = 'API_'.strtoupper($api_dir);
        $object_broker->instance['api_' . $api_dir] = new $api_classname($object_broker);
        error_log("class $api_classname loaded");
    }
    else
    {
        error_log("Directory for class $api_classname exists, but no 'init.inc.php' file was found");
    }
}


// Instantiate plugins
$plugins = array_filter(glob('plugins/*'), 'is_dir');
foreach($plugins as $plugin_src)
{
    $plugin_dir = basename($plugin_src);
    $path = 'plugins/' . $plugin_dir . '/init.inc.php';
    if(file_exists($path))
    {
        include_once($path);
        $plugin_classname = 'PLUGIN_'.strtoupper($plugin_dir);
        $object_broker->instance['plugin_' . $plugin_dir] = new $plugin_classname($object_broker);
        error_log("class $plugin_classname loaded");
    }else{
        error_log("Directory for plugin $plugin_classname exists, but no 'init.inc.php' file was found");
    }
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
        // Invalid JSON encountered (for whatever reason, we don't care).
        // Treat this as plain GET/POST requests

        error_log("getpost:receivePostBody: '$layer6_stanza'");

        /* FIXME: Right now we're tapping into the data from the persistence module, but this is shitty in too many ways.
        // The next step for this section will be to refactor this to ASK all registered plugins for public / private
        // data to be exposed via plain GET/POST requests.
        */

        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');

        // -- collect information that is available to unauthenticated sessions
        $spacestate = $object_broker->instance['core_persist']->retrieve('heralding.state');
        $spacestate_msg = $object_broker->instance['core_persist']->retrieve('heralding.msg');
        $spacestate_lastchange_ts = $object_broker->instance['core_persist']->retrieve('heralding.lastchange.ts');
        $spacestate_lastchange_gecos = $object_broker->instance['core_persist']->retrieve('heralding.lastchange.gecos');

        if($spacestate == 'open') {
            $spaceapi_state_message = "Public. $spacestate_msg";
            $spaceapi_state_icon_open = "http://www.segvault.space/segvault_logo_green.png";
            $spaceapi_state_icon_closed = "http://www.segvault.space/segvault_logo_red.png";
            $traffic_light = 'green';
        }
        elseif($spacestate == 'membersonly') {
            $spaceapi_state_message = "Members only. $spacestate_msg";
            $spaceapi_state_icon_open = "http://www.segvault.space/segvault_logo_yellow.png";
            $spaceapi_state_icon_closed = "http://www.segvault.space/segvault_logo_red.png";
            $traffic_light = 'yellow';
        }
        elseif($spacestate == 'closed') {
            $spaceapi_state_message = "See you soon";
            $spaceapi_state_icon_open = "http://www.segvault.space/segvault_logo_green.png";
            $spaceapi_state_icon_closed = "http://www.segvault.space/segvault_logo_red.png";
            $traffic_light = 'red';
        }
        else {
            $spaceapi_state_message = "Could not retrieve space state. Halp!";
            $spaceapi_state_icon_open = "http://www.segvault.space/segvault_logo_yellow.png";
            $spaceapi_state_icon_closed = "http://www.segvault.space/segvault_logo_yellow.png";
            $traffic_light = 'blinking';
        }

        $mqtt_data = file_get_contents("mqtt.data");
        $mqtt_json = json_decode($mqtt_data, TRUE);

        // FIXME: HARDCODED MADNESS!
        // FIXME: We should find a way to interpret that stuff dynamically

        if(isset($mqtt_json['climate_serverroom'])) {
            $server_humidity = str_replace('.', ',', $mqtt_json['climate_serverroom']['humidity'][0]['value']);
            $server_pressure = str_replace('.', ',', $mqtt_json['climate_serverroom']['pressure'][0]['value'] / 100);
            $server_temperature = str_replace('.', ',', $mqtt_json['climate_serverroom']['temperature'][0]['value']);
        }
        else {
            $server_humidity = $server_pressure = $server_temperature = 0;
        }

        if(isset($mqtt_json['climate_lounge'])) {
            $lounge_humidity = str_replace('.', ',', $mqtt_json['climate_lounge']['humidity'][0]['value']);
            $lounge_pressure = str_replace('.', ',', $mqtt_json['climate_lounge']['pressure'][0]['value'] / 100);
            $lounge_temperature = str_replace('.', ',', $mqtt_json['climate_lounge']['temperature'][0]['value']);
        }
        else {
            $lounge_humidity = $lounge_pressure = $lounge_temperature = 0;
        }

        if(isset($mqtt_json['climate_ballpit'])) {
            $ballpit_humidity = str_replace('.', ',', $mqtt_json['climate_ballpit']['humidity'][0]['value']);
            $ballpit_pressure = str_replace('.', ',', $mqtt_json['climate_ballpit']['pressure'][0]['value'] / 100);
            $ballpit_temperature = str_replace('.', ',', $mqtt_json['climate_ballpit']['temperature'][0]['value']);
        }
        else {
            $ballpit_humidity = $ballpit_temperature = $ballpit_pressure = 0;
        }

        if(isset($mqtt_json['radiation'])) {
            $lounge_radiation_cpm = $mqtt_json['radiation']['cpm'][0]['value'];
            $lounge_radiation_usv = $mqtt_json['radiation']['usv'][0]['value'];
        }
        else {
            $lounge_radiation_cpm = $lounge_radiation_usv = 0;
        }

        // FIXME: MORE HARDCODED MADNESS!
        // FIXME: we should get that done somewhere else

        $spaceapi_data = [
            'api'        => "0.13",
            'space'      => "Segmentation Vault",
            'logo'       => "https://segvault.space/logo.png",
            'url'        => "https://segvault.space",
            'location'   => [
                'address'           => "Segmentation Vault, Kremser Gasse 11, 3100 St. Poelten, Austria",
                'lat'               => 48.2050255,
                'lon'               => 15.6221177
                ],
            'spacefed' => [
                'spacenet'          => false,
                'spacesaml'         => false,
                'spacephone'        => false
            ],
            'state' => [
                'open'              => ($spacestate == 'closed' ? false : true),
                'lastchange'        => (int) $spacestate_lastchange_ts,
                'trigger_person'    => $spacestate_lastchange_gecos,
                'message'           => $spaceapi_state_message,
                'icon'              => [
                    'open'          => $spaceapi_state_icon_open,
                    'closed'        => $spaceapi_state_icon_closed
                ],
            ],
            'contact' => [
                'facebook'          => "https://www.facebook.com/segvault/",
                'twitter'           => "@segvaultspace",
                'email'             => "info@segvault.space"
            ],
            'issue_report_channels' => [
                'email'
            ],
            'ext_traffic_light'     => $traffic_light,
            'cache' => [
                'schedule'          => "m.30"
            ],
            'projects' => [
                'https://segvault.space/wiki/'
            ],
            'sensors' => [
                'temperature' => [
                  0 => [
                      'value'           => (float) str_replace(',', '.', $server_temperature),
                      'unit'            => '°C',
                      'location'        => 'Serverroom'
                  ],
                  1 => [
                      'value'           => (float) str_replace(',', '.', $ballpit_temperature),
                      'unit'            => '°C',
                      'location'        => 'Ballpit'
                  ],
                  2 => [
                      'value'           => (float) str_replace(',', '.', $lounge_temperature),
                      'unit'            => '°C',
                      'location'        => 'Lounge'
                  ],
                ],
                'humidity' => [
                    0 => [
                        'value'           => (int) $server_humidity,
                        'unit'            => '%',
                        'location'        => 'Serverroom'
                    ],
                    1 => [
                        'value'           => (int) $ballpit_humidity,
                        'unit'            => '%',
                        'location'        => 'Ballpit'
                    ],
                    2 => [
                        'value'           => (int) $lounge_humidity,
                        'unit'            => '%',
                        'location'        => 'Lounge'
                    ],
                ],
                'pressure' => [
                    0 => [
                        'value'           => (int) $server_pressure,
                        'unit'            => 'hPA',
                        'location'        => 'Serverroom'
                    ],
                    1 => [
                        'value'           => (int) $ballpit_pressure,
                        'unit'            => 'hPA',
                        'location'        => 'Ballpit'
                    ],
                    2 => [
                        'value'           => (int) $lounge_pressure,
                        'unit'            => 'hPA',
                        'location'        => 'Lounge'
                    ],
                ],
                'radiation' => [
                    'beta_gamma' => [
                        0 => [
                            'value'             => 0 + $lounge_radiation_cpm,
                            'unit'              => "cpm",
                            'conversion_factor' => 0.0057,
                            'location'          => "Lounge",
                            'name'              => "Primary Radiation Sensor",
                            'description'       => "Tube: SBM-20, Logic: MightyOhm v1.0"
                        ]
                    ]
                ]
            ]
        ];

        // Let's deliver some data ...
        if(isset($_GET['token']) && strtolower($_GET['token']) == 'spaceapi') {
            if(isset($_GET['filter']) && $_GET['filter'] != "") {
                // -- that's the proper way of retrieving data, using SpaceAPI (https://spaceapi.io)
                $spaceapi_data_filter['api'] = $spaceapi_data['api'];
                $spaceapi_data_filter['space'] = $spaceapi_data['space'];
                if( array_key_exists( $_GET['filter'] , $spaceapi_data ) )
                {
                    $spaceapi_data_filter[$_GET['filter']] = $spaceapi_data[$_GET['filter']];
                }else{
                    $spaceapi_data_filter[$_GET['filter']] = "THIS KEY DOES NOT EXIST!";
                }
                print json_encode($spaceapi_data_filter);
            }
            else {
                // -- that's the proper way of retrieving data, using SpaceAPI (https://spaceapi.io)
                print json_encode($spaceapi_data);
            }

        }
        else {
            // -- that's the deprecated way, which I left here for Clemens in order to let him migrate his code
            $private_array = [];
            $public_array = [];

            $public_array['spacecore_api'] = $config['webapi_level'];
            $public_array['spacestate'] = ($spacestate == 'open' ? 'open' : 'closed');

            if (!isset($_GET['token']) || $_GET['token'] != $config['webapi_token']) {
                $return_array = ['ERROR' => 'This API was migrated to SpaceAPI format'];
            } else {
                $private_array['spacestate'] = $spacestate;
                $private_array['warning'] = 'This API is deprecated. Please migrate to SpaceAPI.';
                $return_array = ['public' => $public_array, 'private' => $private_array];
            }
            print json_encode($return_array);
        }
    }
}

?>
