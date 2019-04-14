<?php

require_once("config.php.inc");


// Instantiate plugins - things we might wanna load or
// might not wanna have _right now_ ... who knows.

$plugins = array_filter(glob('plugins/*'), 'is_dir');
foreach($plugins as $plugin_src)
{
    $plug_dir = basename($plug_src);
    include_once('plugins/' . $plug_dir .'/entry.php.inc');
    $plug_classname = 'PLUGIN_'.strtoupper($plug_src);
    $object_broker->instance['plugin_' . $plug_src] = new $plugin_classname($object_broker);
}

























// read incoming info and grab the chatID

$content = file_get_contents("php://input");
$update = json_decode($content, true);








// CALLBACK QUERIES

if(!isset($update['message']) && isset($update['callback_query']))
{
	file_put_contents("php-error.log", print_r($update, true));

	$chatID = $update['callback_query']['message']['chat']['id'];
	$cb_query = $update['callback_query']['data'];

	switch($cb_query)
	{

		case 'shutdown-routine:initiate':

			// prepare keyboard
			$text = "<b>FRIDGE (1/3): Refill the fridges</b>";
			$inline_keyboard = prepare_single_keyboard_button('Refilled', 'shutdown-routine:step-1');
			$params = [
				'chat_id'=>$chatID,
				'text'=>$text,
				'parse_mode'=>'HTML',
				'reply_markup'=>$inline_keyboard
			];
			send_post_message($params);
		break;

		case 'shutdown-routine:step-1':

			// prepare keyboard
			$text = "<b>FRIDGE (2/3): Check temperature setting (Left:4, Right:2)</b>";
			$inline_keyboard = prepare_single_keyboard_button('Checked', 'shutdown-routine:step-2');
			$params = [
				'chat_id'=>$chatID,
				'text'=>$text,
				'parse_mode'=>'HTML',
				'reply_markup'=>$inline_keyboard
			];
			send_post_message($params);
		break;

		case 'shutdown-routine:step-2':

			// prepare keyboard
			$text = "<b>FRIDGE (3/3): Check for (soon) spoiled products</b>";
			$inline_keyboard = prepare_single_keyboard_button('Checked', 'shutdown-routine:step-3');
			$params = [
				'chat_id'=>$chatID,
				'text'=>$text,
				'parse_mode'=>'HTML',
				'reply_markup'=>$inline_keyboard
			];
			send_post_message($params);
		break;

		case 'shutdown-routine:step-3':

			// prepare keyboard
			$text = "<b>TRAFFIC LIGHT (1/1): Set traffic lights to RED</b>";
			$inline_keyboard = prepare_single_keyboard_button('OK', 'shutdown-routine:step-4');
			$params = [
				'chat_id'=>$chatID,
				'text'=>$text,
				'parse_mode'=>'HTML',
				'reply_markup'=>$inline_keyboard
			];
			send_post_message($params);
		break;

		case 'shutdown-routine:step-4':

			// prepare keyboard
			$text = "<b>HOUSEKEEPING (1/4): Sweep the floor</b>";
			$inline_keyboard = prepare_single_keyboard_button('Swept', 'shutdown-routine:step-5');
			$params = [
				'chat_id'=>$chatID,
				'text'=>$text,
				'parse_mode'=>'HTML',
				'reply_markup'=>$inline_keyboard
			];
			send_post_message($params);
		break;

		case 'shutdown-routine:step-5':

			// prepare keyboard
			$text = "<b>HOUSEKEEPING (2/4): Clean the tables</b>";
			$inline_keyboard = prepare_single_keyboard_button('Cleaned', 'shutdown-routine:step-5.5');
			$params = [
				'chat_id'=>$chatID,
				'text'=>$text,
				'parse_mode'=>'HTML',
				'reply_markup'=>$inline_keyboard
			];
			send_post_message($params);
		break;

		case 'shutdown-routine:step-5.5':

			// prepare keyboard
			$text = "<b>HOUSEKEEPING (3/4): Drain water containers (coffee, etc.)</b>";
			$inline_keyboard = prepare_single_keyboard_button('Drained', 'shutdown-routine:step-6');
			$params = [
				'chat_id'=>$chatID,
				'text'=>$text,
				'parse_mode'=>'HTML',
				'reply_markup'=>$inline_keyboard
			];
			send_post_message($params);
		break;

		case 'shutdown-routine:step-6':

			// prepare keyboard
			$text = "<b>HOUSEKEEPING (4/4): Bring out the papers and the trash</b>";
			$inline_keyboard = prepare_single_keyboard_button('Done', 'shutdown-routine:step-7');
			$params = [
				'chat_id'=>$chatID,
				'text'=>$text,
				'parse_mode'=>'HTML',
				'reply_markup'=>$inline_keyboard
			];
			send_post_message($params);
		break;

		case 'shutdown-routine:step-7':

			// prepare keyboard
			$text = "<b>POWER DOWN (1/3): Heated equipment (Soldering Iron, 3D Printer, Lasercutter, ...)</b>";
			$inline_keyboard = prepare_single_keyboard_button('Turned off', 'shutdown-routine:step-8');
			$params = [
				'chat_id'=>$chatID,
				'text'=>$text,
				'parse_mode'=>'HTML',
				'reply_markup'=>$inline_keyboard
			];
			send_post_message($params);
		break;

		case 'shutdown-routine:step-8':

			// prepare keyboard
			$text = "<b>POWER DOWN (2/3): Computers, Screens and Beamers</b>";
			$inline_keyboard = prepare_single_keyboard_button('Turned off', 'shutdown-routine:step-9');
			$params = [
				'chat_id'=>$chatID,
				'text'=>$text,
				'parse_mode'=>'HTML',
				'reply_markup'=>$inline_keyboard
			];
			send_post_message($params);
		break;

		case 'shutdown-routine:step-9':

			// prepare keyboard
			$text = "<b>POWER DOWN (3/3): Music, Fancy Lighting, Stuff</b>";
			$inline_keyboard = prepare_single_keyboard_button('All off', 'shutdown-routine:step-10');
			$params = [
				'chat_id'=>$chatID,
				'text'=>$text,
				'parse_mode'=>'HTML',
				'reply_markup'=>$inline_keyboard
			];
			send_post_message($params);

		break;

		case 'shutdown-routine:step-10':

			// prepare keyboard
			$text = "<b>HERD THE CATTLE (1/1): Align the chairs</b>";
			$inline_keyboard = prepare_single_keyboard_button('Aligned', 'shutdown-routine:step-11');
			$params = [
				'chat_id'=>$chatID,
				'text'=>$text,
				'parse_mode'=>'HTML',
				'reply_markup'=>$inline_keyboard
			];
			send_post_message($params);

		break;

		case 'shutdown-routine:step-11':

			// prepare keyboard
			$text = "<b>SAFETY (1/1): Check smoke detectors for obstructions</b>";
			$inline_keyboard = prepare_single_keyboard_button('OK', 'shutdown-routine:step-12');
			$params = [
				'chat_id'=>$chatID,
				'text'=>$text,
				'parse_mode'=>'HTML',
				'reply_markup'=>$inline_keyboard
			];
			send_post_message($params);

		break;

		case 'shutdown-routine:step-12':

			// prepare keyboard
			$text = "<b>LOCKDOWN (1/5): Close the windows</b>";
			$inline_keyboard = prepare_single_keyboard_button('Closed', 'shutdown-routine:step-12.1');
			$params = [
				'chat_id'=>$chatID,
				'text'=>$text,
				'parse_mode'=>'HTML',
				'reply_markup'=>$inline_keyboard
			];
			send_post_message($params);

		break;

		case 'shutdown-routine:step-12.1':

			// prepare keyboard
			$text = "<b>LOCKDOWN (2/5): Close the fucking (persian) blinds!</b>";
			$inline_keyboard = prepare_single_keyboard_button('SunBlock activated!', 'shutdown-routine:step-13');
			$params = [
				'chat_id'=>$chatID,
				'text'=>$text,
				'parse_mode'=>'HTML',
				'reply_markup'=>$inline_keyboard
			];
			send_post_message($params);

		break;

		case 'shutdown-routine:step-13':

			// prepare keyboard
			$text = "<b>LOCKDOWN (3/5): Turn off main lighting</b>";
			$inline_keyboard = prepare_single_keyboard_button('Turned off', 'shutdown-routine:step-14');
			$params = [
				'chat_id'=>$chatID,
				'text'=>$text,
				'parse_mode'=>'HTML',
				'reply_markup'=>$inline_keyboard
			];
			send_post_message($params);

		break;

		case 'shutdown-routine:step-14':

			// prepare keyboard
			$text = "<b>LOCKDOWN (4/5): Lock the inner (space-) door</b>";
			$inline_keyboard = prepare_single_keyboard_button('Locked', 'shutdown-routine:step-15');
			$params = [
				'chat_id'=>$chatID,
				'text'=>$text,
				'parse_mode'=>'HTML',
				'reply_markup'=>$inline_keyboard
			];
			send_post_message($params);

		break;

		case 'shutdown-routine:step-15':

			// prepare keyboard
			$text = "<b>LOCKDOWN (5/5): Lock the outer (BIZ) door</b>";
			$inline_keyboard = prepare_single_keyboard_button('Locked', 'shutdown-routine:finalize');
			$params = [
				'chat_id'=>$chatID,
				'text'=>$text,
				'parse_mode'=>'HTML',
				'reply_markup'=>$inline_keyboard
			];
			send_post_message($params);

		break;

		case 'shutdown-routine:finalize':

			$text = "<b>SHUTDOWN PROCEDURE FINISHED</b>\nShutdown acknoledged successfully. Sanx!";
			$params = [
				'chat_id'=>$chatID,
				'text'=>$text,
				'parse_mode'=>'HTML'
			];
			send_post_message($params);

			sleep(1);

			file_put_contents($statefile, "0");
			unlink("current_segvault_host.txt");
			if(file_exists("current_segvault_handover.txt"))
			{
				unlink("current_segvault_handover.txt");
			}

			$text = "<b>Space shutdown completed. Vault state: CLOSED!</b>";
			$params = [
				'chat_id'=>$publicChannelID,
				'text'=>$text,
				'parse_mode'=>'HTML'
			];
			send_post_message($params);

		break;
	}

	exit();
}

$chatID = $update['message']['chat']['id'];
$command = $update['message']['text'];

if(isset($update['message']['from']['username']))	$userID = $update['message']['from']['username'];	else	$userID = NULL;
$datestamp = $update['message']['date'];
$uniqueUserID = $update['message']['from']['id'];
$niceUserID = $update['message']['from']['first_name'];
$allowed['153204854'] = 'Paul';
$allowed['120046325'] = '/)/)';
$allowed['105532307'] = 'Strassi';
$allowed['77012710'] = 'Cahira';
$allowed['76831489'] = 'flocom';
$allowed['67183607'] = 'cryptoflow';
$allowed['28868756'] = 'xoh';
$allowed['410027601'] = 'cybercow';
$allowed['195406840'] = 'Mattheo';
$allowed['279254129'] = 'Cookie';
$allowed['82615721'] = 'Guido';
$allowed['104426465'] = 'Clemoo';
$allowed['133064155'] = 'PrivateShorty';


file_put_contents($logfile, "$datestamp:$uniqueUserID:$chatID:$niceUserID: $command\n", FILE_APPEND | LOCK_EX);

if(array_key_exists($uniqueUserID, $allowed))
{
	if(strtolower($command) == '/silentshutdown')
	{
		if($chatID == $uniqueUserID)
		{
			// post reply to closed channel
                        $text = "<b>Space shutdown completed. Vault state: CLOSED!</b>";
                        $params = [
                                'chat_id'=>$closedChannelID,
                                'text'=>$text,
                                'parse_mode'=>'HTML'
                        ];
                        send_post_message($params);


			// post reply to interacting user
			$reply = API_URL . "sendmessage?chat_id=$chatID&parse_mode=HTML";
			$text = urlencode("Space shutdown completed. Vault state: CLOSED!");
			$reply .= "&text=$text";

			file_put_contents($statefile, "0");

		}
	}
	elseif(strtolower($command) == '/shutdown')
	{
		if($chatID == $uniqueUserID)
		{

				$inline_keyboard = prepare_single_keyboard_button('CONFIRM', 'shutdown-routine:initiate');
				$text = "<b>SHUTDOWN PROCEDURE INITIATED:</b> Please confirm";

				$params = [
					'chat_id'=>$chatID,
					'text'=>$text,
					'parse_mode'=>'HTML',
					'reply_markup'=>$inline_keyboard
				];

				send_post_message($params);
			exit();

		}
	}
	elseif(substr_count(strtolower($command), '/silentboot') > 0)
	{
		if($chatID == $uniqueUserID)
		{

			$cmdexp = explode(' ', $command);
			if(count($cmdexp) == 1)
			{
				// prepare reply statement
				$reply = API_URL . "sendmessage?chat_id=$chatID&parse_mode=HTML";
				$text = urlencode("Space initialization completed. Vault state: OPEN!");
				file_put_contents($statefile, "1");
			}
			else
			{
				$newcmd = str_replace('/silentboot ', '', strtolower($command));
				if(preg_match("/(2[0-3]|[01][0-9]):([0-5][0-9])/", $newcmd))
				{
					// prepare reply statement
					$reply = API_URL . "sendmessage?chat_id=$chatID&parse_mode=HTML";
					$text = urlencode("Space initialization completed. Vault state OPEN until approx. $newcmd CET.");
					file_put_contents($statefile, "1");
				}
				else
				{
					// prepare reply statement
					$reply = API_URL . "sendmessage?chat_id=$chatID&parse_mode=HTML";
					$text = urlencode("Space initialization failed. Argument needs to be in 24hr format 'HH:MM'.");
				}
			}
			$reply .= "&text=$text";
		}
	}
	elseif(strtolower($command) == '/help')
	{
		if($chatID == $uniqueUserID)
		{
				$text = "/boot [hh:mm] ... Boot up the space [until hh:mm].\n/shutdown ... Shut down the space\n/silentboot ... Boot the space, but do not tell anybody\n/silentshutdown ... Shut down the space, but do not tell anybody\n/handover ... Initiate handover of the currently open space\n/takeover [hh:mm] ... Accept an offered handover of the currently open space [...and extend the open time until hh:mm]";
				$params = [
					'chat_id'=>$uniqueUserID,
					'text'=>$text,
					'parse_mode'=>'HTML'
				];
				send_post_message($params);
				exit();
		}
	}
	elseif(substr_count(strtolower($command), '/boot') > 0)
	{
		if($chatID == $uniqueUserID)
		{
			if(file_exists("current_segvault_host.txt"))
			{
				$current_host = trim(file_get_contents("current_segvault_host.txt"));
				// prepare reply statement
				$reply = API_URL . "sendmessage?chat_id=$chatID&parse_mode=HTML";
				$text = urlencode("Space initialization failed. The space is currently initialized by " . $allowed[$current_host] . " !");
			}
			else
			{
				$cmdexp = explode(' ', $command);
				if(count($cmdexp) == 1)
				{
				        $text = "Acknowledged. You are now hosting the space.";
					$params = [
						'chat_id'=>$chatID,
						'text'=>$text,
						'parse_mode'=>'HTML'
					];
					send_post_message($params);

					// prepare reply statement
					$reply = API_URL . "sendmessage?chat_id=$publicChannelID&parse_mode=HTML";
					$text = urlencode("Space initialization completed. Vault state: OPEN! Hosted by: $niceUserID");
					file_put_contents($statefile, "1");
					file_put_contents("current_segvault_host.txt", $uniqueUserID);
				}
				else
				{
					$newcmd = str_replace('/boot ', '', strtolower($command));
					if(preg_match("/(2[0-3]|[01][0-9]):([0-5][0-9])/", $newcmd))
					{
						$text = "Acknowledged. You are now hosting the space.";
						$params = [
							'chat_id'=>$chatID,
							'text'=>$text,
							'parse_mode'=>'HTML'
						];
						send_post_message($params);

						// prepare reply statement
						$reply = API_URL . "sendmessage?chat_id=$publicChannelID&parse_mode=HTML";
						$text = urlencode("Space initialization completed. Vault state OPEN until approx. $newcmd CET. Hosted by: $niceUserID");
						file_put_contents($statefile, "1");
						file_put_contents("current_segvault_host.txt", $uniqueUserID);
					}
					else
					{
						// prepare reply statement
						$reply = API_URL . "sendmessage?chat_id=$chatID&parse_mode=HTML";
						$text = urlencode("Space initialization failed. Argument needs to be in 24hr format 'HH:MM'.");
					}
				}
			}
			$reply .= "&text=$text";
		}
	}
	elseif(strtolower($command) == '/permcheck')
	{
		if($chatID == $uniqueUserID)
		{
			// prepare reply statement
			$reply = API_URL . "sendmessage?chat_id=$chatID&parse_mode=HTML";

			$text = urlencode("You are permitted to talk to me, $niceUserID!");
			$reply .= "&text=$text";
		}
	}
	elseif(strtolower($command) == '/takeover')
	{
		if($chatID == $uniqueUserID)
		{
				if(file_exists("current_segvault_handover.txt"))
				{
					$current_host = trim(file_get_contents("current_segvault_host.txt"));

					if($current_host == $uniqueUserID)
					{
						// prepare reply statement
						$reply = API_URL . "sendmessage?chat_id=$chatID&parse_mode=HTML";

						$text = urlencode("Takeover impossible. You already ARE the hosting keymember!");
						$reply .= "&text=$text";
					}
					else
					{

						$cmdexp = explode(' ', $command);
						if(count($cmdexp) == 1)
						{
							$text = "<b>Handover:</b> " . $allowed[$uniqueUserID] . " now hosts the current session!";
							$params= [
								'chat_id'=>$publicChannelID,
								'text'=>$text,
								'parse_mode'=>'HTML'
							];
							send_post_message($params);

							sleep(1);

							// prepare reply statement
							$reply = API_URL . "sendmessage?chat_id=$chatID&parse_mode=HTML";

							$text = urlencode("Takeover successful. You replaced " . $allowed[$current_host] . " and took over all duties!");
							$reply .= "&text=$text";
							unlink("current_segvault_handover.txt");
							file_put_contents("current_segvault_host.txt", $uniqueUserID);
						}
						else
						{
							$newcmd = str_replace('/takeover ', '', strtolower($command));
							if(preg_match("/(2[0-3]|[01][0-9]):([0-5][0-9])/", $newcmd))
							{
								$text = "<b>Handover:</b> " . $allowed[$uniqueUserID] . " now hosts the current session and extended the opening hours until $newcmd CET!";
								$params= [
									'chat_id'=>$publicChannelID,
									'text'=>$text,
									'parse_mode'=>'HTML'
								];

								send_post_message($params);

								sleep(1);

								// prepare reply statement
								$reply = API_URL . "sendmessage?chat_id=$chatID&parse_mode=HTML";

								$text = urlencode("Takeover successful. You replaced " . $allowed[$current_host] . " and took over all duties!");
								$reply .= "&text=$text";
								unlink("current_segvault_handover.txt");
								file_put_contents("current_segvault_host.txt", $uniqueUserID);
							}
							else
							{
								$text = urlencode("Takeover unsuccessful. Expected time format: hh:mm !");
								$reply .= "&text=$text";
							}
						}

					}
				}
				else
				{
					// prepare reply statement
					$reply = API_URL . "sendmessage?chat_id=$chatID&parse_mode=HTML";

					$text = urlencode("Takeover is not possible. No handover in progress.");
					$reply .= "&text=$text";
				}
		}
	}
	elseif(strtolower($command) == '/handover')
	{
		if($chatID == $uniqueUserID)
		{
			if(file_exists("current_segvault_host.txt"))
                        {
                                $current_host = trim(file_get_contents("current_segvault_host.txt"));

				if($current_host == $uniqueUserID)
				{
					// prepare reply statement
					$reply = API_URL . "sendmessage?chat_id=$chatID&parse_mode=HTML";

					$text = urlencode("Handover initialized. Waiting for potential keymember to take over!");
					$reply .= "&text=$text";
					file_put_contents("current_segvault_handover.txt", 1);
				}
				else
				{
					// prepare reply statement
					$reply = API_URL . "sendmessage?chat_id=$chatID&parse_mode=HTML";

					$text = urlencode("Initializing the handover is not possible. Only the hosting keymember is allowed to do that.");
					$reply .= "&text=$text";
				}
			}
			else
			{
				// prepare reply statement
				$reply = API_URL . "sendmessage?chat_id=$chatID&parse_mode=HTML";

				$text = urlencode("Initializing the handover is not possible. The space is not initialized right now.");
				$reply .= "&text=$text";
			}
		}
	}
	elseif(substr_count(strtolower($command), '/simonsays') > 0)
	{
		if($chatID == $uniqueUserID)
		{
			// prepare reply statement
			$reply = API_URL . "sendmessage?chat_id=$publicChannelID&parse_mode=HTML";

			$revoice = str_replace('/simonsays ', '', strtolower($command));
			$text = urlencode("$revoice");
			$reply .= "&text=$text";
		}
	}


	if(isset($text) && $text)
	{
		// send reply
		file_get_contents($reply);
	}

	exit;
}

if(strtolower($command) == '/debug')
{
		if($chatID == $uniqueUserID)
		{
			// prepare reply statement
			$reply = API_URL . "sendmessage?chat_id=$chatID&parse_mode=HTML";

			$text = urlencode("[DEBUG: $uniqueUserID:$chatID:$datestamp:$niceUserID:$chatID]");
			$reply .= "&text=$text";
		}
}
elseif(strtolower($command) == '/permcheck')
{
	if($chatID == $uniqueUserID)
	{
		// prepare reply statement
		$reply = API_URL . "sendmessage?chat_id=$chatID&parse_mode=HTML";

		$text = urlencode("Sorry, $niceUserID. My dad told me not to talk to strangers online!");
		$reply .= "&text=$text";
	}
}
elseif($command[0] == '/')
{
	if($chatID == $uniqueUserID)
	{
		$items[] = "Nope, not going to do that.";
		$items[] = "Nope, not going to do that either :)";
		$items[] = "Nah, I still don't know you.";
		$items[] = "Have we met before?";
		$items[] = "You should buy me a coffee first :)";
		$items[] = "Uh, okay. Want some cheese along with your whine?";
		$items[] = "My mum says, life's like a box of chocolates ...";
		$items[] = "Is that so?";
		$items[] = "Let me think about it ...... nnnnnnno.";
		$items[] = "Aren't you that guy I told to get lost already?";
		$items[] = "Yeah ... no!";

		$template = $items[rand(0, count($items) - 1)];
		// prepare reply statement
		$reply = API_URL . "sendmessage?chat_id=$chatID&parse_mode=HTML";

		$text = urlencode($template);
		$reply .= "&text=$text";
	}
}
else
{
	if($chatID == $uniqueUserID)
	{
		$arr_cmd = array("hi", "hello", "hej", "hallo", "hoi");
	}
}


if(isset($text) && $text)
{
	// send reply
	file_get_contents($reply);
}

function prepare_single_keyboard_button($label, $data)
{
	$inline_keyboard = [
        	'inline_keyboard' => [
        		[
         		    ['text' => $label, 'callback_data' => $data]
                        ]
                ]
        ];
	return json_encode($inline_keyboard);
}

function send_post_message($params)
{
  $ch = curl_init(API_URL . "sendMessage");
  curl_setopt($ch, CURLOPT_HEADER, false);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_POST, 1);
  curl_setopt($ch, CURLOPT_POSTFIELDS, ($params));
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
  $result = curl_exec($ch);
  file_put_contents("php-error.log", "$result", FILE_APPEND);

  curl_close($ch);
}

?>
