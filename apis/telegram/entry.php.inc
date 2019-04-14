<?php
/*
 * Copyright (C) 2019, Daniel Haslinger <creo+oss@mesanova.com>
 * This program is free software licensed under the terms of the GNU General Public License v3 (GPLv3).
 */

class API_TELEGRAM
{
    private $object_broker;
    private $classname;


    public function __construct($object_broker)
    {
        $this->classname = strtolower(static::class);

        $this->object_broker = $object_broker;
        $object_broker->apis[] = 'api_telegram';
        error_log($this->classname . ": starting up");
    }


    public function __destruct()
    {

    }


    public function send_message($target, $message)
    {
        global $config;

        // set parameters
        $params = [
            'chat_id'=>$target,
            'text'=>$message,
            'parse_mode'=>'HTML'
        ];

        // send request
        $ch = curl_init($config['api_endpoint'] . "sendMessage");
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, ($params));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        $result = curl_exec($ch);
        curl_close($ch);

        error_log($this->classname . ":sendMessage: $result");
    }


    public function delete_message($chat_id, $msg_id)
    {
        global $config;

        // make sure that we're technically able to delete a message
        if($chat_id > 0)
        {
            error_log($this->classname . ":deleteMessage: can not delete $msg_id @ $chat_id (not a channel)");
            return;
        }

        // set parameters
        $params = [
            'chat_id'=>$chat_id,
            'message_id'=>$msg_id
        ];

        // send request
        $ch = curl_init($config['api_endpoint'] . "deleteMessage");
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, ($params));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        $result = curl_exec($ch);
        curl_close($ch);

        error_log($this->classname . ":deleteMessage: $result");
    }


    public function download_resource($file_id, $destination)
    {
        global $config;

        // set parameters
        $params = [
            'file_id'=>$file_id
        ];

        // send request
        $ch = curl_init($config['api_endpoint'] . "getFile");
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, ($params));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        $result = curl_exec($ch);
        curl_close($ch);

        error_log($this->classname . ":getFile: $result");
    }
}

?>