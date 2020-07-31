<?php
/*
 * Copyright (C) 2019, Daniel Haslinger <creo+oss@mesanova.com>
 * This program is free software licensed under the terms of the GNU General Public License v3 (GPLv3).
 */

class PLUGIN_HERALDING
{
    private $object_broker;
    private $classname;
    const ACL_MODE = 'white';    // white, black, none

    public function __construct($object_broker)
    {
        $this->classname = strtolower(static::class);

        $this->object_broker = $object_broker;
        $object_broker->plugins[] = $this->classname;
        debug_log($this->classname . ": starting up");

        $this->object_broker->instance['api_routing']->register("startup", $this->classname, "Boot the space for all audiences");
        $this->object_broker->instance['api_routing']->register("membersonly", $this->classname, "Boot the space for members only");
        $this->object_broker->instance['api_routing']->register("shutdown", $this->classname, "Shut down the space");
        $this->object_broker->instance['api_routing']->register("handover", $this->classname, "Offer to handover the space to another Keymember");
        $this->object_broker->instance['api_routing']->register("takeover", $this->classname, "Take over the space from another Keymember");
        $this->object_broker->instance['api_routing']->register("abort", $this->classname, "Abort an open Handover");
    }


    public function __destruct()
    {

    }


    public function get_acl_mode()
    {
        return self::ACL_MODE;
    }


    public function process($trigger)
    {
        global $config;

        debug_log($this->classname . ": processing trigger $trigger");

        $chatid = $GLOBALS['layer7_stanza']['message']['chat']['id'];
        $senderid = $GLOBALS['layer7_stanza']['message']['from']['id'];
        $sendergecos = $GLOBALS['layer7_stanza']['message']['from']['first_name'];

        $payload = str_replace('/' . $trigger, '', $GLOBALS['layer7_stanza']['message']['text']);

        $spacestate = $this->object_broker->instance['core_persist']->retrieve('heralding.state');
        $spaceowner = $this->object_broker->instance['core_persist']->retrieve('heralding.owner');
        $spaceownergecos = $this->object_broker->instance['core_persist']->retrieve('heralding.owner.gecos');
        $spacetransfer = $this->object_broker->instance['core_persist']->retrieve('heralding.transfer');

        $publicChannel = $config['publicChannelID'];
        $privateChannel = $config['privateChannelID'];
        $keymemberChannel = $config['keymemberChannelID'];

        if($chatid < 0)
        {
						debug_log($this->classname . ": WAS GROUP MESSAGE");
            
						if($chatid !== $keymemberChannel)
						{
							// only reply if we're asked directly. Do not reply to channel messages.
							// except keymembers group
							return;
						}
        }

        if(!$spacestate)
        {
            // This is either the first time that this plugin is being used or
            // the data base is uninitialized or still corrupted. Fall back to defaults.
            $spacestate = 'closed';
            $spaceowner = NULL;
            $spaceownergecos = NULL;
            $spacetransfer = '';

            $this->object_broker->instance['core_persist']->store('heralding.state', $spacestate);
            $this->object_broker->instance['core_persist']->store('heralding.msg', '');
            $this->object_broker->instance['core_persist']->store('heralding.owner', '');
            $this->object_broker->instance['core_persist']->store('heralding.owner.gecos', '');
            $this->object_broker->instance['core_persist']->store('heralding.transfer', '');
            $this->object_broker->instance['core_persist']->store('heralding.lastchange.ts', '');
            $this->object_broker->instance['core_persist']->store('heralding.lastchange.gecos', '');

        }

        switch(strtolower($trigger))
        {
            case "startup":

                // STARTING UP THE SPACE ================================================================
                // ======================================================================================
                if($spacestate == 'closed')
                {
                    // Feedback to the user
                    $message = "<b>Success!</b> The space state is now <b>open</b>!";
                    $this->object_broker->instance['api_telegram']->send_message($senderid, $message);

                    if($payload)
                    {
                        // Heralding to the public channel
                        $message = "<b>The Vault is now open</b>\n Hosted by: $sendergecos, estimated shutdown: $payload";
                        $this->object_broker->instance['api_telegram']->send_message($publicChannel, $message);
                        $this->object_broker->instance['core_persist']->store('heralding.msg', 'Estimated shutdown: ' . $payload);
                    }
                    else
                    {
                        // Heralding to the public channel
                        $message = "<b>The Vault is now open</b>\n Hosted by: $sendergecos";
                        $this->object_broker->instance['api_telegram']->send_message($publicChannel, $message);
                        $this->object_broker->instance['core_persist']->store('heralding.msg', 'No estimated shutdown time');
                    }

                    // Persist the state
                    $this->object_broker->instance['core_persist']->store('heralding.state', 'open');
                    $this->object_broker->instance['core_persist']->store('heralding.owner', $senderid);
                    $this->object_broker->instance['core_persist']->store('heralding.owner.gecos', $sendergecos);
                    $this->object_broker->instance['core_persist']->store('heralding.lastchange.ts', time());
                    $this->object_broker->instance['core_persist']->store('heralding.lastchange.gecos', $sendergecos);
                }
                elseif($spacestate == 'membersonly')
                {
                    if($spaceowner == $senderid)
                    {
                        // Feedback to the user
                        $message = "Changing state from <b>Members Only</b> to <b>Open</b>";
                        $this->object_broker->instance['api_telegram']->send_message($senderid, $message);

                        // Heralding to the public channel
                        $message = "<b>The Vault is now open</b>\n Hosted by: $sendergecos";
                        $this->object_broker->instance['api_telegram']->send_message($publicChannel, $message);

                        // Heralding to the private channel
                        $message = "<b>The Vault is now open to the public</b>\n Hosted by: $sendergecos";
                        $this->object_broker->instance['api_telegram']->send_message($privateChannel, $message);

                        // Persist the state
                        $this->object_broker->instance['core_persist']->store('heralding.state', 'open');
                        $this->object_broker->instance['core_persist']->store('heralding.owner', $senderid);
                        $this->object_broker->instance['core_persist']->store('heralding.lastchange.ts', time());
                        $this->object_broker->instance['core_persist']->store('heralding.lastchange.gecos', $sendergecos);
                    }
                    else
                    {
                        // Feedback to the user: Space blocked by semaphore!
                        $message = "<b>Failed!</b>\n The space is currently owned by $spaceownergecos";
                        $this->object_broker->instance['api_telegram']->send_message($senderid, $message);
                    }
                }
                elseif($spacestate == 'open')
                {
                    // Feedback to the user: Space is already open
                    $message = "<b>Failed!</b>\n The space is already hosted by $spaceownergecos";
                    $this->object_broker->instance['api_telegram']->send_message($senderid, $message);
                }
                else
                {
                    // Command not applicable
                    $message = "<b>Sorry!</b>\n You can't take the space, since it's currently flagged as $spacestate";
                    $this->object_broker->instance['api_telegram']->send_message($senderid, $message);
                }
                break;

            case "membersonly":

                // STARTING UP THE SPACE FOR MEMBERS ONLY ===============================================
                // ======================================================================================
                if($spacestate == 'closed')
                {
                    // Feedback to the user
                    $message = "<b>Success!</b>\n The space state is now <b>open for members only</b>!";
                    $this->object_broker->instance['api_telegram']->send_message($senderid, $message);

                    if($payload)
                    {
                        // Heralding to the private channel
                        $message = "<b>The Vault is now open to members only</b>\n Hosted by: $sendergecos, estimated shutdown: $payload";
                        $this->object_broker->instance['core_persist']->store('heralding.msg', 'Estimated shutdown: ' . $payload);
                    }
                    else
                    {
                        // Heralding to the private channel
                        $message = "<b>The Vault is now open to members only</b>\n Hosted by: $sendergecos";
                        $this->object_broker->instance['core_persist']->store('heralding.msg', 'No estimated shutdown time');
                    }
                    $this->object_broker->instance['api_telegram']->send_message($privateChannel, $message);

                    // Persist the state
                    $this->object_broker->instance['core_persist']->store('heralding.state', 'membersonly');
                    $this->object_broker->instance['core_persist']->store('heralding.owner', $senderid);
                    $this->object_broker->instance['core_persist']->store('heralding.owner.gecos', $sendergecos);
                    $this->object_broker->instance['core_persist']->store('heralding.lastchange.ts', time());
                    $this->object_broker->instance['core_persist']->store('heralding.lastchange.gecos', $sendergecos);
                    $this->object_broker->instance['core_persist']->store('heralding.msg', 'No estimated shutdown time');

                }
                elseif($spacestate == 'membersonly')
                {
                    // Feedback to the user: Space blocked by semaphore!
                    $message = "<b>Failed!</b>\n The space is currently owned by $spaceownergecos";
                    $this->object_broker->instance['api_telegram']->send_message($senderid, $message);
                }
                elseif($spacestate == 'open')
                {
                    if($spaceowner == $senderid)
                    {
                        // Feedback to the user
                        $message = "<b>Success!</b>\n The space state is now <b>open for members only</b>!";
                        $this->object_broker->instance['api_telegram']->send_message($senderid, $message);

                        if($payload)
                        {
                            // Heralding to the private channel
                            $message = "<b>The Vault has switched to members only</b>\n New estimated shutdown: $payload";
                            $this->object_broker->instance['core_persist']->store('heralding.msg', 'Estimated shutdown: ' . $payload);
                        }
                        else
                        {
                            // Heralding to the private channel
                            $message = "<b>The Vault has switched to members only</b>\n Hosted by: $sendergecos";
                        }
                        $this->object_broker->instance['api_telegram']->send_message($privateChannel, $message);

                        // Heralding to the public channel
                        $message = "<b>The Vault is now closed</b>\n See you soon";
                        $this->object_broker->instance['api_telegram']->send_message($publicChannel, $message);

                        // Write data to heat file
                        $close_timestamp = time();
                        $open_timestamp = $this->object_broker->instance['core_persist']->retrieve('heralding.lastchange.ts');
                        $open_duration = $close_timestamp - $open_timestamp;
                        $heat_line = "$open_timestamp,$close_timestamp,$open_duration,$spaceowner,$spaceownergecos\n";
                        file_put_contents('heralding.data', $heat_line, FILE_APPEND | LOCK_EX);

                        // Persist the state
                        $this->object_broker->instance['core_persist']->store('heralding.state', 'membersonly');
                        $this->object_broker->instance['core_persist']->store('heralding.lastchange.ts', time());
                        $this->object_broker->instance['core_persist']->store('heralding.lastchange.gecos', $sendergecos);
                    }
                    else
                    {
                        // Feedback to the user: Space blocked by semaphore!
                        $message = "<b>Failed!</b>\n The space is currently owned by $spaceownergecos";
                        $this->object_broker->instance['api_telegram']->send_message($senderid, $message);
                    }
                }
                else
                {
                    // Command not applicable
                    $message = "<b>Sorry!</b>\n you can't boot the space, since it's currently flagged as $spacestate";
                    $this->object_broker->instance['api_telegram']->send_message($senderid, $message);
                }
                break;

            case "shutdown":

                // SHUTTING DOWN THE SPACE ================================================================
                // ======================================================================================
                if($spacestate == 'closed')
                {
                    // Feedback to the user
                    $message = "<b>Failed!</b>\n The space state is already <b>closed</b>!";
                    $this->object_broker->instance['api_telegram']->send_message($senderid, $message);
                }
                elseif($spacestate == 'membersonly')
                {
                    if($spaceowner == $senderid)
                    {
                        // Feedback to the user
                        $message = "Changing state from <b>Members Only</b> to <b>closed</b>";
                        $this->object_broker->instance['api_telegram']->send_message($senderid, $message);

                        // Heralding to the private channel
                        $message = "<b>The Vault is now closed</b>\n See you soon";
                        $this->object_broker->instance['api_telegram']->send_message($privateChannel, $message);

                        // Persist the state
                        $this->object_broker->instance['core_persist']->store('heralding.state', 'closed');
                        $this->object_broker->instance['core_persist']->store('heralding.owner', '');
                        $this->object_broker->instance['core_persist']->store('heralding.transfer', '');
                        $this->object_broker->instance['core_persist']->store('heralding.lastchange.ts', time());
                        $this->object_broker->instance['core_persist']->store('heralding.lastchange.gecos', $sendergecos);
                        $this->object_broker->instance['core_persist']->store('heralding.msg', '');

                    }
                    else
                    {
                        // Feedback to the user: Space blocked by semaphore!
                        $message = "<b>Failed!</b>\n The space is currently owned by $spaceownergecos";
                        $this->object_broker->instance['api_telegram']->send_message($senderid, $message);
                    }
                }
                elseif($spacestate == 'open')
                {
                    if($spaceowner == $senderid)
                    {
                        // Feedback to the user
                        $message = "Changing state from <b>open</b> to <b>closed</b>";
                        $this->object_broker->instance['api_telegram']->send_message($senderid, $message);

                        // Heralding to the public channel
                        $message = "<b>The Vault is now closed</b>\nSee you soon";
                        $this->object_broker->instance['api_telegram']->send_message($publicChannel, $message);

                        // Write data to heat file
                        $close_timestamp = time();
                        $open_timestamp = $this->object_broker->instance['core_persist']->retrieve('heralding.lastchange.ts');
                        $open_duration = $close_timestamp - $open_timestamp;
                        $heat_line = "$open_timestamp,$close_timestamp,$open_duration,$spaceowner,$spaceownergecos\n";
                        file_put_contents('heralding.data', $heat_line, FILE_APPEND | LOCK_EX);

                        // Persist the state
                        $this->object_broker->instance['core_persist']->store('heralding.state', 'closed');
                        $this->object_broker->instance['core_persist']->store('heralding.owner', '');
                        $this->object_broker->instance['core_persist']->store('heralding.transfer', '');
                        $this->object_broker->instance['core_persist']->store('heralding.lastchange.ts', $close_timestamp);
                        $this->object_broker->instance['core_persist']->store('heralding.lastchange.gecos', $sendergecos);
                        $this->object_broker->instance['core_persist']->store('heralding.msg', '');
                    }
                    else
                    {
                        // Feedback to the user: Space blocked by semaphore!
                        $message = "<b>Failed!</b>\n The space is currently owned by $spaceownergecos";
                        $this->object_broker->instance['api_telegram']->send_message($senderid, $message);
                    }
                }
                else
                {
                    // Command not applicable
                    $message = "<b>Sorry!</b>\n you can't shut down the space, since it's currently flagged as $spacestate";
                    $this->object_broker->instance['api_telegram']->send_message($senderid, $message);
                }
                break;

            case "handover":

                // INITIATING TRANSFER OF OWNERSHIP =====================================================
                // ======================================================================================
                if($spacestate == 'open' || $spacestate == 'membersonly')
                {
                    if($spacetransfer != 'hovering')
                    {
                        // Feedback to the user
                        $message = "<b>TRANSFER: READY PLAYER ONE</b>\n Waiting for another keymember to take over the space. Send /abort to cancel";
                        $this->object_broker->instance['api_telegram']->send_message($senderid, $message);

                        // Heralding to the keymember channel
                        $message = "<b>TRANSFER: READY PLAYER ONE</b>\n $sendergecos wants to transfer this shift. Send /takeover to accept";
                        $this->object_broker->instance['api_telegram']->send_message($keymemberChannel, $message);

                        // Persist the transfer state
                        $this->object_broker->instance['core_persist']->store('heralding.transfer', 'hovering');
                    }
                    else
                    {
                        // Feedback to the user
                        $message = "<b>Failed!</b>\n The space is already open for takeover. Send /abort to cancel";
                        $this->object_broker->instance['api_telegram']->send_message($senderid, $message);
                    }
                }
                else
                {
                    // Command not applicable
                    $message = "<b>Sorry!</b>\n you can't hand over the space, since it's currently flagged as $spacestate";
                    $this->object_broker->instance['api_telegram']->send_message($senderid, $message);
                }
                break;

            case "takeover":

                // ACCEPT TRANSFER OF OWNERSHIP =====================================================
                // ======================================================================================
                if($spacestate == 'open' || $spacestate == 'membersonly')
                {
                    if ($spacetransfer == 'hovering')
                    {
                        // Feedback to the user who takes over
                        $message = "<b>TRANSFER: READY PLAYER TWO</b>\nYou have accepted to continue this space shift";
                        $this->object_broker->instance['api_telegram']->send_message($senderid, $message);

                        // Heralding to the user who hands over
                        $message = "<b>TRANSFER: READY PLAYER TWO</b>\nYou are now released from your space shift";
                        $this->object_broker->instance['api_telegram']->send_message($spaceowner, $message);

                        if($payload)
                        {
                            // Heralding to the keymember channel
                            $message = "<b>TRANSFER: READY PLAYER TWO</b>\n$sendergecos accepted to continue this space shift until $payload";
                            $this->object_broker->instance['api_telegram']->send_message($keymemberChannel, $message);

                            // Heralding to the public channel, since we extended the opening time
                            $message = "The current session is now hosted by: $sendergecos, new estimated shutdown: $payload";
                            $this->object_broker->instance['api_telegram']->send_message($publicChannel, $message);
                            $this->object_broker->instance['core_persist']->store('heralding.msg', 'Estimated shutdown: ' . $payload);
                        }
                        else
                        {
                            // Heralding to the keymember channel
                            $message = "<b>TRANSFER: READY PLAYER TWO</b>\n$sendergecos accepted to continue this space shift";
                            $this->object_broker->instance['api_telegram']->send_message($keymemberChannel, $message);
                        }

                        // Persist the transfer state
                        $this->object_broker->instance['core_persist']->store('heralding.transfer', '');
                        $this->object_broker->instance['core_persist']->store('heralding.owner', $senderid);
                        $this->object_broker->instance['core_persist']->store('heralding.owner.gecos', $sendergecos);
                        $this->object_broker->instance['core_persist']->store('heralding.lastchange.ts', time());
                        $this->object_broker->instance['core_persist']->store('heralding.lastchange.gecos', $sendergecos);
                    }
                    else
                    {
                        // Feedback to the user
                        $message = "<b>Failed!</b>\n The space is currently not available to take over";
                        $this->object_broker->instance['api_telegram']->send_message($senderid, $message);
                    }
                }
                else
                {
                    // Command not applicable
                    $message = "<b>Sorry!</b>\n You can't take over the space, since it's currently flagged as $spacestate";
                    $this->object_broker->instance['api_telegram']->send_message($senderid, $message);
                }
                break;

            case "abort":

                // CANCELING TRANSFER OF OWNERSHIP ======================================================
                // ======================================================================================
                if($spacestate == 'open' || $spacestate == 'membersonly')
                {
                    if ($spacetransfer == 'hovering')
                    {
                        if($spaceowner == $senderid)
                        {
                            // Feedback to the user
                            $message = "<b>TRANSFER ABORTED</b>\nYou are still running this shift";
                            $this->object_broker->instance['api_telegram']->send_message($senderid, $message);

                            // Heralding to the keymember channel
                            $message = "<b>TRANSFER ABORTED</b>\n$spaceownergecos is still running this shift";
                            $this->object_broker->instance['api_telegram']->send_message($keymemberChannel, $message);

                            // Persist the transfer state
                            $this->object_broker->instance['core_persist']->store('heralding.transfer', '');
                        }
                        else
                        {
                            // Feedback to the user: Not allowed
                            $message = "<b>Sorry!</b>\n You are not allowed to abort this transfer of ownership";
                            $this->object_broker->instance['api_telegram']->send_message($senderid, $message);
                        }
                    }
                    else
                    {
                        // Feedback to the user: Not hovering
                        $message = "<b>Failed!</b>\n The space is currently not subject to being transferred";
                        $this->object_broker->instance['api_telegram']->send_message($senderid, $message);
                    }
                }
                else
                {
                    // Command not applicable
                    $message = "<b>Sorry,</b> you can't abort a takeover, the space is currently flagged as $spacestate";
                    $this->object_broker->instance['api_telegram']->send_message($senderid, $message);
                }
                break;

        }
    }
}

?>
