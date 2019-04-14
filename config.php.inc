<?php
/*
 * Copyright (C) 2019, Daniel Haslinger <creo+oss@mesanova.com>
 * This program is free software licensed under the terms of the GNU General Public License v3 (GPLv3).
 */

//ini_set("log_errors", 1);
//ini_set("error_log", "php-error.log");

// HERE BE DRAGONS - most of this file will be moved to the database,
// this includes sensitive information that should not go to source control
// therefore this is just a placeholder for now

global $config;
$config['bot_token'] = '';
$config['api_endpoint'] = 'https://api.telegram.org/bot' . $config['bot_token'] . '/';
$config['download_endpoint'] = 'https://api.telegram.org/file/bot' . $config['bot_token'] . '/';

$config['publicChannelID'] = "";
$config['privateChannelID'] = "";
$config['keymemberChannelID'] = "";

$config['admins'] = array('0' => 'foo');

$config['error_log'] = "spacecore.log";

?>
