<?php
namespace simplekits;


use pocketmine\plugin\PluginBase;
use pocketmine\level\format\LevelProvider;
use pocketmine\event\Listener;
use pocketmine\Player;
use pocketmine\utils\Config;
use pocketmine\entity\Effect;
use pocketmine\utils\TextFormat;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\item\Item;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\tile\Sign;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\event\player\PlayerItemHeldEvent;

class main extends PluginBase implements Listener{
	
	
	private $haskit = array();
	private $kits;
	private $time;
	private $started = false;
	public function onEnable(){
		@mkdir($this->getDataFolder());
		$this->getLogger()->info("Loaded Kits!");
		$this->kits = new Config($this->getDataFolder()."kits.yml", Config::YAML, array(
				"pvp" => array(
						"time" => 2,
						"name" => "pvp",
						"Content" => array(
								"267:0:1",
								"307:0:1",
								"364:0:15"
						),
						"Effects" => array(
								"1:30"
						)	
				)
		));
		$this->kits->save();
		$this->time = new Config($this->getDataFolder()."time.yml", Config::YAML,array());
		$this->time->save();
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		if ($this->started == false){
			$this->started = true;
			$this->getServer()->getScheduler()->scheduleRepeatingTask(new time($this), 1200);
		}
	}
	public function renewConfig(){
		$this->kits->reload();
	}
	//events functions
	public function onDeath(PlayerDeathEvent $ev){
		if (in_array($ev->getEntity()->getName(), $this->haskit)){
			$key = array_search($ev->getEntity()->getName(), $this->haskit);
			unset($this->haskit[$key]);
		}
	}
	public function onPlayerLeave(PlayerQuitEvent $ev){
		if (in_array($ev->getPlayer()->getName(), $this->haskit)){
			$key = array_search($ev->getPlayer()->getName(), $this->haskit);
			unset($this->haskit[$key]);
		}
	}
	public function signTap(PlayerInteractEvent $ev){
		if ($ev->getBlock()->getId() == 63 || $ev->getBlock()->getId() == 68){
		$signtile = $ev->getBlock()->getLevel()->getTile($ev->getBlock());
		$p = $ev->getPlayer();
		if ($signtile instanceof Sign){
			$text= $signtile->getText();
			if ($text[0] === "§e[Kit]"){
				if (isset($text[1])){
				if ($this->kits->exists($text[1]) and !in_array($p->getName(), $this->haskit)){
					$this->canHaveKit($p, $text[1]);
				}else {
					$p->sendMessage("[SimpleKits] Already have kit or kit does not exsist :(");
				}}
			}
		}}
	}
	public function timer(){
		$time = $this->time->getAll();
		foreach ($time as $player => $kit){
			$kits = $this->time->get("$player");
			foreach ($kits as $kit =>$time){
				$k = $this->kits->get($kit); 
				$time++;
				$this->time->setNested($player.".".$kit, $time);
				$this->time->save();
				if (/*($time*60)*16 >= 16*/ $time == $k['time']){
					$time = $this->time->getAll();
					unset($time[$player][$kit]);
					$this->time->setAll($time);
					$this->time->save();
					echo "remove from timer!";
				}
			}
		}
	}
	public function addToTimer(Player $player, $kitname){
		$time = $this->time->getAll();
		$time[$player->getName()][$kitname] = 0;
		$this->time->setAll($time);
		$this->time->save();
	}
	public function checkTimer(Player $player, $kitname){
		$time = $this->time->getAll();
		$kit = $this->kits->get($kitname);
		$t = $kit['time'];
		echo "check";
		if (isset($time[$player->getName()][$kitname])){
			echo "yes";
			return $t - $time[$player->getName()][$kitname];
		}
		if (!isset($time[$player->getName()][$kitname])){
			return false;
		}
	}
	//checks permissions and kit costs
	public function canHaveKit(Player $player, $kitname){
		$kit = $this->kits->get($kitname);
		//cost kits
		$time = $this->checkTimer($player, $kitname);
		if (!$time){
			echo $time;
		if (isset($kit["cost"])){
			$pm = $this->getServer()->getPluginManager();
			$sc = $pm->getPlugin("SimpleTockens");
			if ($sc->getTockens($player->getName()) >= $kit["cost"]){
				$sc->addTockens($player->getName(), -1 * $kit["cost"]);
		if ($player->hasPermission("simplekits.kit.".$kitname)){
			$this->giveKit($player, $kitname);
		}else{
			$player->sendMessage(TextFormat::RED."[SimpleKits] You dont have permission for this kit.. :(");
		}
			return true;
		}
		else {
			$player->sendMessage("[SimpleKits] Dont have enough money for this kit :(");
			return false;
		}
		}else {
			//no cost kits
			if ($player->hasPermission("simplekits.kit.".$kitname)){
				$this->giveKit($player, $kitname);
				return true;
			}else{
				$player->sendMessage(TextFormat::RED."[SimpleKits] You dont have permission for this kit.. :(");
			}
		}}else{
			$player->sendMessage("[SimpleKits] You have $time mins left till you can get that kit");
		}
	}
	//function that handles kits
	public function giveKit(Player $player, $kitname){
		$player->sendMessage(TextFormat::GREEN."[Kits] Added items to your inventory!");
		$kit = $this->kits->get($kitname);
		if (isset($kit["Content"])){
		foreach ($kit["Content"] as $i){
			$is = explode(":", $i);
			$item = new Item($is[0],$is[1],$is[2]);
			if (isset($is[3]) and isset($is[4])){
				$ench = Enchantment::getEnchantment($is[3]);
				if ($ench instanceof Enchantment){
					$ench->setLevel($is[4]);
					$item->addEnchantment($ench);
				}
			}
			foreach ($is as $name){
				$n = explode(";", $name);
				if (isset($n[1])){
					$name  = explode("_", $n[1]);
					$item->setCustomName(implode(" ", $name));
				}
			}
			$player->getInventory()->addItem($item);
		}}
		if (isset($kit["Effects"])){
		foreach ($kit["Effects"] as $e){
			$effect = explode(":", $e);
			if ($player instanceof Player){
				$player->addEffect(Effect::getEffect($effect[0])->setDuration($effect[1] *20));
			}
		}}
		$this->addToTimer($player, $kitname);
		//array_push($this->haskit, $player->getName());
		return true;
	}
	//commands
	public function onCommand(CommandSender $sender,Command $command, $label,array $args){
		switch (strtolower($command->getName())){
			case "kit":
				if ($sender instanceof Player){
				if (isset($args[0])){
					$kitname = strtolower($args[0]);
				if ($this->kits->exists($kitname)){
					if (!in_array($sender->getName(), $this->haskit)){
						//$this->giveKit($sender, $kitname);
						$this->canHaveKit($sender, $kitname);
					}else {
						$sender->sendMessage("[SimpleKits] You already got a kit!");
					}
				}else {
					$sender->sendMessage("[SimpleKits] Kit does not exsist!");
				}
				return true;
				}else {
					//$sender->sendMessage($command->getUsage());
					$kits = $this->kits->getAll();
					$num = 0;
					$sender->sendMessage(TextFormat::GOLD.TextFormat::BOLD."Kits Available:");
					foreach ($kits as $kit => $stuff){
						if ($sender->hasPermission("simplekits.kit.".$kit)){
							$sender->sendMessage(TextFormat::GREEN."* ".$kit);
						}
						if (!$sender->hasPermission("simplekits.kit.".$kit)){
							$sender->sendMessage(TextFormat::RED."* ".$kit);
						}
					}
					$sender->sendMessage(TextFormat::AQUA."Usage: /kit [name]");
					return true;
				}}else {
					$sender->sendMessage("[SimpleKits] Use command in-game!");
				}
			break;
			case "skreload":
				$this->renewConfig();
				$sender->sendMessage(TextFormat::RED."Attempted to reload config for kits!");
			break;
		}
	}
}