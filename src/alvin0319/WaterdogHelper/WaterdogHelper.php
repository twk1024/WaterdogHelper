<?php

declare(strict_types=1);

namespace alvin0319\WaterdogHelper;

use alvin0319\WaterdogHelper\command\GoLobbyCommand;
use alvin0319\WaterdogHelper\command\TransferCommand;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerPreLoginEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\mcpe\protocol\LoginPacket;
use pocketmine\network\mcpe\protocol\ScriptCustomEventPacket;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Binary;
use pocketmine\utils\SingletonTrait;
use pocketmine\utils\UUID;
use ReflectionClass;

use function array_values;
use function strtolower;

class WaterdogHelper extends PluginBase implements Listener{
	use SingletonTrait;

	protected $caches = [];

	public function onLoad() : void{
		self::setInstance($this);
	}

	public function onEnable() : void{
		$this->saveDefaultConfig();
		$this->getServer()->getPluginManager()->registerEvents($this, $this);

		$this->getServer()->getCommandMap()->registerAll("waterdoghelper", [
			new TransferCommand(),
			new GoLobbyCommand()
		]);
	}

	public function onDataPacketReceive(DataPacketReceiveEvent $event) : void{
		$packet = $event->getPacket();
		if($packet instanceof LoginPacket){
			$this->caches[strtolower($packet->clientData["ThirdPartyName"])] = [
				"ip" => $packet->clientData["Waterdog_IP"] ?? null
			];
		}
	}

	public function onPlayerPreLogin(PlayerPreLoginEvent $event) : void{
		$player = $event->getPlayer();

		[$ip] = array_values($this->caches[$player->getLowerCaseName()]);

		if($ip === null){
			$event->setKickMessage($this->getConfig()->get("connect_as_lobby"));
			$event->setCancelled();
			return;
		}
		static $playerReflection = null;
		if($playerReflection === null){
			$playerReflection = new ReflectionClass(Player::class);
		}
		$ipProperty = $playerReflection->getProperty("ip");

		$ipProperty->setAccessible(true);

		$ipProperty->setValue($player, $ip);

		$this->getLogger()->debug("{$player->getName()} is logged into XBOX Live");
		unset($this->caches[$player->getLowerCaseName()]);
	}

	/**
	 * @param Player $player
	 * @param String $server
	 *
	 * @return bool
	 */
	public function transfer(Player $player, string $server) : bool{
		$pk = new ScriptCustomEventPacket();
		$pk->eventName = "bungeecord:main";
		$pk->eventData = Binary::writeShort(strlen("Connect")) . "Connect" . Binary::writeShort(strlen($server)) . $server;
		return $player->sendDataPacket($pk);
	}

	/**
	 * @param String $player
	 * @param String $message
	 *
	 * @return bool
	 */
	public function sendMessage(string $player, string $message){
		$sender = $this->getServer()->getOnlinePlayers()[array_rand($this->getServer()->getOnlinePlayers())];
		if($sender != null && $sender instanceof Player){
			$pk = new ScriptCustomEventPacket();
			$pk->eventName = "bungeecord:main";
			$pk->eventData = Binary::writeShort(strlen("Message")) . "Message" . Binary::writeShort(strlen($player)) . $player . Binary::writeShort(strlen($message)) . $message;
			return $sender->sendDataPacket($pk);
		}else{
			$this->getLogger()->warning("You cannot send a message to a player when no player is online on this server!");
			return false;
		}
	}
}
