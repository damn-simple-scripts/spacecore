<?php
/*
 * Copyright (C) 2019, Daniel Haslinger <creo+oss@mesanova.com>
 * This program is free software licensed under the terms of the GNU General Public License v3 (GPLv3).
 */

global $config;
$config['bot_token'] = '';
$config['api_endpoint'] = 'https://api.telegram.org/bot' . $config['bot_token'] . '/';
$config['download_endpoint'] = 'https://api.telegram.org/file/bot' . $config['bot_token'] . '/';
$config['allowed_photo_extensions'] = array('jpg', 'png', 'gif', 'jpeg', 'tiff');

$config['publicChannelID'] = "";
$config['privateChannelID'] = "";
$config['keymemberChannelID'] = "";

$config['admins'] = array('999999999' => 'admin');

$config['error_log'] = "debug.log";
$config['webapi_level'] = 2;
$config['webapi_token'] = '';

?>