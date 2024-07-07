<?php

namespace Dobezor;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\player\Player;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\utils\TextFormat;
use pocketmine\utils\Utils;

class PunishPro extends PluginBase implements Listener {

    private array $tempBans = [];
    private array $mutes = [];
    private array $messages = [];

    public function onEnable(): void {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);

        if(!is_dir($this->getDataFolder())){
            mkdir($this->getDataFolder());
        }

        if (!file_exists($this->getDataFolder() . 'tempbans.yml')) {
            file_put_contents($this->getDataFolder() . 'tempbans.yml', '');
        }
        if (!file_exists($this->getDataFolder() . 'mutes.yml')) {
            file_put_contents($this->getDataFolder() . 'mutes.yml', '');
        }
        if (!file_exists($this->getDataFolder() . 'messages.yml')) {
            file_put_contents($this->getDataFolder() . 'messages.yml', $this->getResource('messages.yml'));
        }

        $this->messages = yaml_parse(file_get_contents($this->getDataFolder() . 'messages.yml')) ?: [];
        $this->tempBans = yaml_parse(file_get_contents($this->getDataFolder() . 'tempbans.yml')) ?: [];
        $this->mutes = yaml_parse(file_get_contents($this->getDataFolder() . 'mutes.yml')) ?: [];
        
        $this->getLogger()->info(TextFormat::BOLD . TextFormat::BLUE . "PLUGIN ENABLED!");
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        switch($command->getName()) {
            case "tempban":
                if (count($args) < 2) {
                    $sender->sendMessage($this->messages['messages']['tempban']['usage']);
                    return false;
                }

                $player = $this->getServer()->getPlayerByPrefix($args[0]);
                if (!$player) {
                    $sender->sendMessage($this->messages['messages']['tempban']['not_found']);
                    return false;
                }

                $time = $this->parseTime($args[1]);
                if ($time === false) {
                    $sender->sendMessage($this->messages['messages']['tempban']['invalid_time_format']);
                    return false;
                }

                $reason = isset($args[2]) ? implode(" ", array_slice($args, 2)) : "No reason";
                $this->banPlayer($player, $time, $reason);

                $sender->sendMessage(str_replace(['{player}', '{time}', '{reason}'], [$player->getName(), $this->getFormattedTime($time), $reason], $this->messages['messages']['tempban']['success']));
                return true;

            case "chatoff":
                if (count($args) < 2) {
                    $sender->sendMessage($this->messages['messages']['chatoff']['usage']);
                    return false;
                }

                $player = $this->getServer()->getPlayerByPrefix($args[0]);
                if (!$player) {
                    $sender->sendMessage($this->messages['messages']['chatoff']['not_found']);
                    return false;
                }

                $time = $this->parseTime($args[1]);
                if ($time === false) {
                    $sender->sendMessage($this->messages['messages']['chatoff']['invalid_time_format']);
                    return false;
                }

                $reason = isset($args[2]) ? implode(" ", array_slice($args, 2)) : "No reason";
                $this->mutePlayer($player, $time, $reason);

                $sender->sendMessage(str_replace(['{player}', '{time}', '{reason}'], [$player->getName(), $this->getFormattedTime($time), $reason], $this->messages['messages']['chatoff']['success']));
                return true;

            case "unban":
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
                return true;

            case "chaton":
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
                return true;
        }
        return false;
    }

    private function parseTime(string $timeString): int|false {
        $unit = mb_substr($timeString, -1);
        $value = (int)mb_substr($timeString, 0, -1);

        return match ($unit) {
            's' => $value,
            'm' => $value * 60,
            'h' => $value * 3600,
            'd' => $value * 86400,
            default => false,
        };
    }

    private function banPlayer(Player $player, int $time, string $reason): void {
		$playerName = $player->getName();
		$expiry = (new \DateTime())->modify('+' . $time . ' seconds');
		$this->getServer()->getIPBans()->addBan($player->getNetworkSession()->getIp(), $reason, $expiry);
		$player->kick(str_replace(['{time}', '{reason}'], [$this->getFormattedTime($time), $reason], $this->messages['messages']['tempban']['behalf']), false);

		$this->tempBans[$playerName] = [
			"expiry" => $expiry->getTimestamp(),
			"reason" => $reason
		];
		file_put_contents($this->getDataFolder() . 'tempbans.yml', yaml_emit($this->tempBans));
	}

    private function mutePlayer(Player $player, int $time, string $reason): void {
        $playerName = $player->getName();
        $expiry = (new \DateTime())->modify('+' . $time . ' seconds')->getTimestamp();

        $this->mutes[$playerName] = [
            "expiry" => $expiry,
            "reason" => $reason
        ];
        file_put_contents($this->getDataFolder() . 'mutes.yml', yaml_emit($this->mutes));
    }

    public function onPlayerJoin(PlayerJoinEvent $event): void {
        $player = $event->getPlayer();
        $playerName = $player->getName();

        if(isset($this->tempBans[$playerName])) {
            if($this->tempBans[$playerName]["expiry"] > time()) {
                $reason = $this->tempBans[$playerName]["reason"];
                $message = str_replace(['{time}', '{reason}'], [$this->getFormattedTime($this->tempBans[$playerName]["expiry"] - time()), $reason], $this->messages['messages']['tempban']['notification']);
                $player->kick($message, false);
            } else {
                unset($this->tempBans[$playerName]);
                file_put_contents($this->getDataFolder() . 'tempbans.yml', yaml_emit($this->tempBans));
            }
        }
    }

    public function onPlayerPreLogin(PlayerPreLoginEvent $event): void {
		$playerName = $event->getPlayerInfo()->getUsername();
		if(isset($this->tempBans[$playerName])) {
			if($this->tempBans[$playerName]["expiry"] > time()) {
				$reason = $this->tempBans[$playerName]["reason"];
				$message = str_replace(['{time}', '{reason}'], [$this->getFormattedTime($this->tempBans[$playerName]["expiry"] - time()), $reason], $this->messages['messages']['tempban']['notification']);
				$event->setKickReason($message);
				$event->setCancelled(true);
			} else {
				unset($this->tempBans[$playerName]);
				file_put_contents($this->getDataFolder() . 'tempbans.yml', yaml_emit($this->tempBans));
			}
		}
	}

    public function onPlayerChat(PlayerChatEvent $event): void {
        $player = $event->getPlayer();
        $playerName = $player->getName();

        if(isset($this->mutes[$playerName])) {
            if($this->mutes[$playerName]["expiry"] > time()) {
                $reason = $this->mutes[$playerName]["reason"];
                $message = str_replace(['{time}', '{reason}'], [$this->getFormattedTime($this->mutes[$playerName]["expiry"] - time()), $reason], $this->messages['messages']['chatoff']['notification']);
                $player->sendMessage($message);
                $event->cancel();
            } else {
                unset($this->mutes[$playerName]);
                file_put_contents($this->getDataFolder() . 'mutes.yml', yaml_emit($this->mutes));
                $player->sendMessage($this->messages['messages']['chatoff']['unlock']);
            }
        }
    }

    private function getFormattedTime(int $time): string {
        $days = intval($time / 86400);
        $hours = intval(($time % 86400) / 3600);
        $minutes = intval(($time % 3600) / 60);
        $seconds = $time % 60;

        $daysString = $this->getWordForm($days, ['day', 'days']);
        $hoursString = $this->getWordForm($hours, ['hour', 'hours']);
        $minutesString = $this->getWordForm($minutes, ['minute', 'minutes']);
        $secondsString = $this->getWordForm($seconds, ['second', 'seconds']);

        if ($days > 0) {
            return "$days $daysString, $hours $hoursString, $minutes $minutesString, $seconds $secondsString";
        } elseif ($hours > 0) {
            return "$hours $hoursString, $minutes $minutesString, $seconds $secondsString";
        } elseif ($minutes > 0) {
            return "$minutes $minutesString, $seconds $secondsString";
        } else {
            return "$seconds $secondsString";
        }
    }

    private function getWordForm(int $number, array $forms): string {
        return $number === 1 ? $forms[0] : $forms[1];
    }
}
