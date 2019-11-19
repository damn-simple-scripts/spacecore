<?php
/*
 * Copyright (C) 2019, Clemens Jung
 * This program is free software licensed under the terms of the GNU General Public License v3 (GPLv3).
 */

class API_SPACEAPI
{
    private $object_broker;
    private $classname;

    private $sensor_config = [
        'temperature' => [
            0 => [ 'id' => 'climate_serverroom', 'location' => 'Serverroom', 'unit' => '°C'],
            1 => [ 'id' => 'climate_ballpit', 'location' => 'Ballpit', 'unit' => '°C'],
            2 => [ 'id' => 'climate_lounge', 'location' => 'Lounge' , 'unit' => '°C']
        ],
        'humidity' => [
            0 => [ 'id' => 'climate_serverroom', 'location' => 'Serverroom', 'unit' => '%', 'convert' => 'int'],
            1 => [ 'id' => 'climate_ballpit', 'location' => 'Ballpit', 'unit' => '%', 'convert' => 'int'],
            2 => [ 'id' => 'climate_lounge', 'location' => 'Lounge', 'unit' => '%', 'convert' => 'int']
        ],
        'pressure' => [
            0 => [ 'id' => 'climate_serverroom', 'location' => 'Serverroom', 'unit' => 'hPA', 
                   'multiply' => (1.0 / 100.0), 'convert' => 'int'],
            1 => [ 'id' => 'climate_ballpit', 'location' => 'Ballpit', 'unit' => 'hPA', 
                   'multiply' => (1.0 / 100.0), 'convert' => 'int'],
            2 => [ 'id' => 'climate_lounge', 'location' => 'Lounge', 'unit' => 'hPA', 
                   'multiply' => (1.0 / 100.0), 'convert' => 'int']
        ],
        'radiation' => [
            'beta_gamma' => [ 'id' => 'radiation', 'property' => 'cpm', 'location' => 'Lounge', 'unit' => 'cpm', 
                   'extra' => [ 'conversion_factor' =>  0.0057 , 'name' => 'Primary Radiation Sensor', 
                                'description' => 'Tube: SBM-20, Logic: MightyOhm v1.0'
                   ]
            ]
        ]
    ];

    public function __construct($object_broker)
    {
        $this->classname = strtolower(static::class);

        $this->object_broker = $object_broker;
        $object_broker->apis[] = 'api_spaceapi';
        error_log($this->classname . ": starting up");
    }


    public function __destruct()
    {

    }

    private function read_mqtt_file($file_name="mqtt.data")
    {
        if(!file_exists($file_name))
        {
            return NULL;
        }
        $mqtt_data = file_get_contents($file_name);
        $mqtt_json = json_decode($mqtt_data, TRUE);
        if($mqtt_json == NULL)
        {
            error_log("JSON ERROR: ".$file_name." was not valid JSON");
            return NULL;
        }

        $config = $this->sensor_config;
        $result = array();

        foreach(array_keys($config) as $category)
        {
            $cat_result = array();
            foreach(array_keys($config[$category]) as $numbering)
            {
                $elem = $config[$category][$numbering];
                if(isset($mqtt_json[$elem['id']]))
                {
                    $mqtt_elem = $mqtt_json[$elem['id']];
                    $prop = ( isset($elem['property']) ? $elem['property'] : $category );
                    if( array_key_exists($prop, $mqtt_elem) )
                    {
                        $sens_data = $mqtt_elem[$prop][0]['value'];
                        $sens_data = floatval($sens_data);
                        if( isset($elem['multiply']) )
                        {
                            $sens_data *= $elem['multiply'];
                        }
                        if( isset($elem['convert']) )
                        {
                            switch($elem['convert']) {
                                case "int":
                                    $sens_data = intval($sens_data);
                                    break;
                                case "str":
                                    $sens_data = strval($sens_data);
                                    break;
                                case "bool":
                                    $sens_data = boolval($sens_data);
                                    break;
                                case "float":
                                default:
                                    $sens_data = floatval($sens_data);
                                    break;
                            }
                        }
                        $measurement = array();
                        $measurement['value'] = $sens_data;
                        if( isset($elem['unit']) ){ $measurement['unit'] = $elem['unit']; }
                        if( isset($elem['location']) ){ $measurement['location'] = $elem['location']; }
                        if( isset($elem['extra']) )
                        {
                            foreach($elem['extra'] as $key => $value)
                            {
                                $measurement[$key] = $value;
                            }
                        }
                        if(!is_numeric($numbering))
                        {
                            $measurement = [ 0 => $measurement ];
                        }
                        $cat_result[$numbering] = $measurement;
                    }
                }
            }
            if( count($cat_result) > 0 )
            {
                $result[$category] = $cat_result;
            }
        }
        if( count($result) > 0 )
        {
            return $result;
        }else{
            return NULL;
        }
    }

    private function build_spaceapi_object()
    {
        // -- collect information that is available to unauthenticated sessions
        $spacestate = $this->object_broker->instance['core_persist']->retrieve('heralding.state');
        $spacestate_msg = $this->object_broker->instance['core_persist']->retrieve('heralding.msg');
        $spacestate_lastchange_ts = $this->object_broker->instance['core_persist']->retrieve('heralding.lastchange.ts');
        $spacestate_lastchange_gecos = $this->object_broker->instance['core_persist']->retrieve('heralding.lastchange.gecos');

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
            ]
        ];

        $sensors = $this->read_mqtt_file();
        if($sensors != NULL)
        {
            $spaceapi_data['sensors'] = $sensors;
        }
        return $spaceapi_data;
    }


    public function process_requests()
    {
        /* FIXME: Right now we're tapping into the data from the persistence module, but this is shitty in too many ways.
        // The next step for this section will be to refactor this to ASK all registered plugins for public / private
        // data to be exposed via plain GET/POST requests.
        */

        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');

        $spaceapi_data = $this->build_spaceapi_object();

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
                    $spaceapi_data_filter[$_GET['filter']] = "THIS KEY DOES NOT EXISTS!";
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
