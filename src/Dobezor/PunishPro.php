<?php

namespace Dobezor;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\Player;
use pocketmine\command\Command;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\command\CommandSender;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\utils\TextFormat;
use pocketmine\utils\Utils;

class PunishPro extends PluginBase implements Listener {

    //private $config = []; temporarily removed
    private $tempBans = [];
    private $mutes = [];
    private $messages = [];

    public function onEnable() {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);

        if(!is_dir($this->getDataFolder())){
            mkdir($this->getDataFolder());
        }

        /*if (!file_exists($this->getDataFolder() . 'config.yml')) temporarily removed
            file_put_contents($this->getDataFolder() . 'config.yml', $this->getResource('config.yml'));*/ 

        if (!file_exists($this->getDataFolder() . 'tempbans.yml'))
            file_put_contents($this->getDataFolder() . 'tempbans.yml', '');    

        if (!file_exists($this->getDataFolder() . 'mutes.yml'))
            file_put_contents($this->getDataFolder() . 'mutes.yml', '');    

        if (!file_exists($this->getDataFolder() . 'messages.yml'))
            file_put_contents($this->getDataFolder() . 'messages.yml', $this->getResource('messages.yml'));

        $this->messages = yaml_parse(file_get_contents($this->getDataFolder().'messages.yml'));
        //$this->config = yaml_parse(file_get_contents($this->getDataFolder().'config.yml')); temporarily removed
        $this->tempBans = yaml_parse(file_get_contents($this->getDataFolder().'tempbans.yml'));
        $this->mutes = yaml_parse(file_get_contents($this->getDataFolder().'mutes.yml'));
	$this->getLogger()->info("§b§l§nPLUGIN ENABLED!");
    }

    public function onCommand(CommandSender $sender, Command $command, $label, array $args): bool {
        if ($command->getName() === "tempban") {
            if (count($args) < 2) {                
                $sender->sendMessage($this->messages['messages']['tempban']['usage']);
                return false;
            }
            
            $player = $this->getServer()->getPlayer($args[0]);
            if (!$player) {                
                $sender->sendMessage($this->messages['messages']['tempban']['not_found']);
                return false;
            }
            
            $time = $this->parseTime($args[1]);
            if ($time === false) {                
                $sender->sendMessage($this->messages['messages']['tempban']['invalid_time_format']);
                return false;
            }
            
            $reason = isset($args[2]) ? implode(" ", array_slice($args, 2)) : "Без причины";
            
            
            $this->banPlayer($player, $time, $reason);            
            
            $sender->sendMessage(str_replace(['{player}', '{time}', '{reason}'], [$player->getName(), $this->getFormattedTime($time), $reason], $this->messages['messages']['tempban']['success']));            
        } elseif ($command->getName() === "chatoff") {
            if (count($args) < 2) {                
                $sender->sendMessage($this->messages['messages']['chatoff']['usage']);
                return false;
            }
            
            $player = $this->getServer()->getPlayer($args[0]);
            if (!$player) {                
                $sender->sendMessage($this->messages['messages']['chatoff']['not_found']);
                return false;
            }
            
            $time = $this->parseTime($args[1]);
            if ($time === false) {                
                $sender->sendMessage($this->messages['messages']['chatoff']['invalid_time_format']);
                return false;
            }
            
            $reason = isset($args[2]) ? implode(" ", array_slice($args, 2)) : "Без причины";
            
            $this->mutePlayer($player, $time, $reason);            
            
            $sender->sendMessage(str_replace(['{player}', '{time}', '{reason}'], [$player->getName(), $this->getFormattedTime($time), $reason], $this->messages['messages']['chatoff']['success']));            
        } elseif ($command->getName() === "unban") {
            if (count($args) < 1) {
                $sender->sendMessage($this->messages['messages']['unban']['usage']);
                return false;
            }
            
            $playerName = $args[0];
            if (!isset($this->tempBans[$playerName])) {
                $sender->sendMessage($this->messages['messages']['unban']['not_banned']);
                return false;
            }
            
            unset($this->tempBans[$playerName]);
            file_put_contents($this->getDataFolder() . 'tempbans.yml', yaml_emit($this->tempBans));
            $sender->sendMessage(str_replace('{player}', $playerName, $this->messages['messages']['unban']['success']));
        } elseif ($command->getName() === "chaton") {
            if (count($args) < 1) {
                $sender->sendMessage($this->messages['messages']['chaton']['usage']);
                return false;
            }
            
            $playerName = $args[0];
            if (!isset($this->mutes[$playerName])) {
                $sender->sendMessage($this->messages['messages']['chaton']['not_muted']);
                return false;
            }
            
            unset($this->mutes[$playerName]);
            file_put_contents($this->getDataFolder() . 'mutes.yml', yaml_emit($this->mutes));
            $sender->sendMessage(str_replace('{player}', $playerName, $this->messages['messages']['chaton']['success']));
        }
        return false;
    }
    
    private function parseTime($timeString) {
		$unit = mb_substr($timeString, -1);
		$value = (int)mb_substr($timeString, 0, -1);
    
		switch ($unit) {
			case "s":
				return $value;
			case "m":
				return $value * 60;
			case "h":
				return $value * 3600;
			case "d":
				return $value * 86400;
			default:
				return false;
			}
	}
    
    private function banPlayer(Player $player, $time, $reason) {
        $playerName = $player->getName();
        $expiry = new \DateTime(); 
        $expiry->modify('+' . $time . ' seconds'); 
        $expiryTimestamp = $expiry->getTimestamp(); 
        $this->getServer()->getIPBans()->addBan($player->getAddress(), $reason, $expiry);
        $player->kick(str_replace(['{time}', '{reason}'], [$this->getFormattedTime($time), $reason], $this->messages['messages']['tempban']['behalf']), false);
        
       
        $this->tempBans[$playerName] = [
            "expiry" => $expiryTimestamp,
            "reason" => $reason
        ];
        file_put_contents($this->getDataFolder() . 'tempbans.yml', yaml_emit($this->tempBans));
    }

    private function mutePlayer(Player $player, $time, $reason) {
        $playerName = $player->getName();
        $expiry = new \DateTime(); 
        $expiry->modify('+' . $time . ' seconds'); 
        $expiryTimestamp = $expiry->getTimestamp(); 
        
        $this->mutes[$playerName] = [
            "expiry" => $expiryTimestamp,
            "reason" => $reason
        ];
        file_put_contents($this->getDataFolder() . 'mutes.yml', yaml_emit($this->mutes));
    }

    public function TempbanPJE(PlayerJoinEvent $event) {
        $player = $event->getPlayer();
        $playerName = $player->getName();
        
        if(isset($this->tempBans[$playerName])) {
            if($this->tempBans[$playerName]["expiry"] > time()) {
                $reason = $this->tempBans[$playerName]["reason"];
                $expiryTime = date("Y-m-d H:i:s", $this->tempBans[$playerName]["expiry"]);
                $message = str_replace(['{time}', '{reason}'], [$this->getFormattedTime($this->tempBans[$playerName]["expiry"] - time()), $reason], $this->messages['messages']['tempban']['notification']);
                $player->kick($message, false);
            } else {
                unset($this->tempBans[$playerName]);
                file_put_contents($this->getDataFolder() . 'tempbans.yml', yaml_emit($this->tempBans));
            }
        }
    }
    public function onPlayerPreLogin(PlayerPreLoginEvent $event) {
        $playerName = $event->getPlayer()->getName();
        if(isset($this->tempBans[$playerName])) {
            if($this->tempBans[$playerName]["expiry"] > time()) {
                $reason = $this->tempBans[$playerName]["reason"];
                $expiryTime = date("Y-m-d H:i:s", $this->tempBans[$playerName]["expiry"]);
                $message = str_replace(['{time}', '{reason}'], [$this->getFormattedTime($this->tempBans[$playerName]["expiry"] - time()), $reason], $this->messages['messages']['tempban']['notification']);
                $event->setCancelled(true);
                $event->setKickMessage($message);
            } else {
                unset($this->tempBans[$playerName]);
                file_put_contents($this->getDataFolder() . 'tempbans.yml', yaml_emit($this->tempBans));
            }
        }
    }

    public function MutePCE(PlayerChatEvent $event) {
		$player = $event->getPlayer();
		$playerName = $player->getName();
		
		if(isset($this->mutes[$playerName])) {
			if($this->mutes[$playerName]["expiry"] > time()) {
				$reason = $this->mutes[$playerName]["reason"];
				$expiryTime = date("Y-m-d H:i:s", $this->mutes[$playerName]["expiry"]);
				$message = str_replace(['{time}', '{reason}'], [$this->getFormattedTime($this->mutes[$playerName]["expiry"] - time()), $reason], $this->messages['messages']['chatoff']['notification']);
				$player->sendMessage($message);
				$event->setCancelled(true);
			} else {
				unset($this->mutes[$playerName]);
				file_put_contents($this->getDataFolder() . 'mutes.yml', yaml_emit($this->mutes));                
				$player->sendMessage($this->messages['messages']['chatoff']['unlock']);
			}
		}
	}

    public function getFormattedTime(int $time): string {
	    $formatted = '';
	    $days = intval($time / 86400);
	    $hours = intval(($time % 86400) / 3600);
	    $minutes = intval(($time % 3600) / 60);
	    $seconds = $time % 60;
	    
	    $daysString = $this->getWordForm($days, ['day', 'days']);
	    $hoursString = $this->getWordForm($hours, ['hour', 'hours']);
	    $minutesString = $this->getWordForm($minutes, ['minute', 'minutes']);
	    $secondsString = $this->getWordForm($seconds, ['second', 'seconds']);
	    
	    if ($days > 0) {
	        $formatted = "$days $daysString, $hours $hoursString, $minutes $minutesString, $seconds $secondsString";
	    } else if ($hours > 0) {
	        $formatted = "$hours $hoursString, $minutes $minutesString, $seconds $secondsString";
	    } else if ($minutes > 0) {
	        $formatted = "$minutes $minutesString, $seconds $secondsString";
	    } else {
	        $formatted = "$seconds $secondsString";
	    }
	    
	    return $formatted;
	}
	
	public function getWordForm($number, $forms): string {
	    if ($number === 1) {
	        return $forms[0];
	    } else {
	        return $forms[1];
	    }
	}
}
