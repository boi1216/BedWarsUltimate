<?php

declare(strict_types=1);

namespace BedWars\game;

use BedWars\BedWars;
use BedWars\game\player\PlayerCache;
use BedWars\utils\Scoreboard;
use BedWars\utils\Utils;
use pocketmine\data\bedrock\EnchantmentIdMap;
use pocketmine\data\bedrock\EnchantmentIds;
use pocketmine\entity\Entity;
use pocketmine\entity\Location;
use pocketmine\entity\Villager;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Compass;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIdentifier;
use pocketmine\item\ItemIds;
use pocketmine\math\Vector3;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use pocketmine\world\Position;
use pocketmine\world\World;

class Game
{

	const STATE_LOBBY = 0;
	const STATE_RUNNING = 1;
	const STATE_REBOOT = 2;
	/** @var int $playersPerTeam */
	public $playersPerTeam;
	/** @var string $worldName */
	public $worldName = "inca";
	/** @var array $players */
	public $players = [];
	/** @var array $spectators */
	public $spectators = [];
	/** @var array $teamInfo */
	public $teamInfo = [];
	/** @var array $teams */
	public $teams = [];
	/** @var array $deadQueue */
	public $deadQueue = [];
	/** @var Entity[] $npcs */
	public $npcs = [];
	/** @var Generator[] $generators */
	public $generators = [];
	/** @var array $placedBlocks */
	public $placedBlocks = [];
	/** @var BedWars $plugin */
	private $plugin;
	/** @var string $gameName */
	private $gameName;
	/** @var int $minPlayers */
	private $minPlayers;
	/** @var int $maxPlayers */
	private $maxPlayers;
	/** @var string $lobbyName */
	private $lobbyName;
	/** @var string $mapName */
	private $mapName;
	/** @var int $state */
	private $state = self::STATE_LOBBY;
	/** @var bool $starting */
	private $starting = false;
	/** @var Vector3 $lobby */
	private $lobby;
	/** @var int $startTime */
	private $startTime;
	/** @var int $rebootTime */
	private $rebootTime;
	/** @var int $voidY */
	private $voidY;
	/** @var string $winnerTeam */
	private $winnerTeam = '';
	/** @var array $trackingPositions */
	private $trackingPositions = [];
	/** @var array $generatorInfo */
	private $generatorInfo = [];
	/** @var float|int $tierUpdate */
	private $tierUpdate = 60 * 10;
	/** @var string $tierUpdateGen */
	private $tierUpdateGen = "diamond";
	/** @var PlayerCache[] $cachedPlayers */
	private $cachedPlayers = [];

	/**
	 * Game constructor.
	 * @param BedWars $plugin
	 * @param array $data
	 */
	public function __construct(BedWars $plugin, array $data)
	{
		$this->plugin = $plugin;
		$this->startTime = $data['startTime'];
		$this->rebootTime = $plugin->staticRestartTime;
		$this->gameName = $data['id'];
		$this->minPlayers = $data['minPlayers'];
		$this->playersPerTeam = $data['playersPerTeam'];
		//   $this->worldName = $data['world'];
		$this->lobbyName = explode(":", $data['lobby'])[3];
		$this->mapName = $data['mapName'];
		$this->teamInfo = $data['teamInfo'];
		//   $this->voidY = $data['void_y'];
		$this->plugin->getServer()->getWorldManager()->loadWorld(explode(":", $data['lobby'])[3]);
		$this->lobby = Utils::stringToPosition(":", $data['lobby']);
		$this->generatorInfo = !isset($data['generatorInfo'][$this->gameName]) ? [] : $data['generatorInfo'][$this->gameName];

		foreach ($this->teamInfo as $teamName => $data) {
			$this->teams[$teamName] = new Team($teamName, BedWars::TEAMS[strtolower($teamName)]);
		}

		$this->maxPlayers = count($this->teams) * $this->playersPerTeam;
		$this->reload();
		$this->plugin->getScheduler()->scheduleRepeatingTask(new GameTick($this), 20);
	}

	public function reload(): void
	{ //// ???
		$this->plugin->getServer()->getWorldManager()->loadWorld($this->mapName);
		$world = $this->plugin->getServer()->getWorldManager()->getWorldByName($this->mapName);
		if (!$world instanceof World) {
			$this->plugin->getServer()->getLogger()->info(BedWars::PREFIX . TextFormat::YELLOW . "Failed to load arena " . $this->gameName . " because it's world does not exist!");
			return;
		}
		$world->setAutoSave(false);
	}

	/**
	 * @param int $limit
	 */
	public function setVoidLimit(int $limit): void
	{
		$this->voidY = $limit;
	}

	/**
	 * @return int
	 */
	public function getVoidLimit(): int
	{
		return 0; //TODO
	}

	/**
	 * @return int
	 */
	public function getState(): int
	{
		return $this->state;
	}

	/**
	 * @return string
	 */
	public function getName(): string
	{
		return $this->gameName;
	}

	/**
	 * @return string
	 */
	public function getMapName(): string
	{
		return $this->mapName;
	}

	/**
	 * @return int
	 */
	public function getMaxPlayers(): int
	{
		return $this->maxPlayers;
	}

	/**
	 * @param Player $player
	 */
	public function join(Player $player): void
	{
		if ($this->state !== self::STATE_LOBBY) {
			$player->sendMessage(BedWars::PREFIX . TextFormat::YELLOW . "Arena is full!");
			return;
		}

		$this->cachedPlayers[$player->getName()] = new PlayerCache($player);
		$player->teleport($this->lobby);
		$this->players[$player->getName()] = $player;

		$this->broadcastMessage(TextFormat::GRAY . $player->getName() . " " . TextFormat::YELLOW . "has joined the game " . TextFormat::GOLD . "(" . TextFormat::AQUA . count($this->players) . TextFormat::YELLOW . "/" . TextFormat::AQUA . $this->maxPlayers . TextFormat::YELLOW . ")");
		$player->getInventory()->clearAll();
		//   $a = 0;

		//   $items = array_fill(0, count($this->teams), ItemFactory::getInstance()->get(ItemIds::WOOL));
		foreach ($this->teams as $team) {
			var_dump(Utils::colorIntoWool($team->getColor()));
			//  $items[$a]->setDamage(Utils::colorIntoWool($team->getColor()));
			$player->getInventory()->addItem($i = new Item(new ItemIdentifier(ItemIds::WOOL, Utils::colorIntoWool($team->getColor()))));

			//    $a++;
		}

		$player->getInventory()->setItem(8, ItemFactory::getInstance()->get(ItemIds::COMPASS)->setCustomName(TextFormat::YELLOW . "Leave"));
		$this->checkLobby();

		Scoreboard::new($player, 'bedwars', TextFormat::BOLD . TextFormat::YELLOW . "Bed Wars");

		Scoreboard::setLine($player, 1, " ");
		Scoreboard::setLine($player, 2, " " . TextFormat::WHITE . "Map: " . TextFormat::GREEN . $this->mapName . str_repeat(" ", 3));
		Scoreboard::setLine($player, 3, " " . TextFormat::WHITE . "Players: " . TextFormat::GREEN . count($this->players) . "/" . $this->maxPlayers . str_repeat(" ", 3));
		Scoreboard::setLine($player, 4, "  ");
		Scoreboard::setLine($player, 5, " " . $this->starting ? TextFormat::WHITE . "Starting in " . TextFormat::GREEN . $this->startTime . str_repeat(" ", 3) : TextFormat::GREEN . "Waiting for players..." . str_repeat(" ", 3));
		Scoreboard::setLine($player, 6, "   ");
		Scoreboard::setLine($player, 7, " " . TextFormat::WHITE . "Mode: " . TextFormat::GREEN . substr(str_repeat($this->playersPerTeam . "v", count($this->teams)), 0, -1) . str_repeat(" ", 3));
		Scoreboard::setLine($player, 8, " " . TextFormat::WHITE . "Version: " . TextFormat::GRAY . "v1.0" . str_repeat(" ", 3));
		Scoreboard::setLine($player, 9, "    ");
		Scoreboard::setLine($player, 10, " " . TextFormat::YELLOW . "www.example.net");
	}

	/**
	 * @param string $message
	 */
	public function broadcastMessage(string $message): void
	{
		foreach (array_merge($this->spectators, $this->players) as $player) {
			$player->sendMessage(BedWars::PREFIX . $message);
		}
	}

	private function checkLobby(): void
	{
		if (!$this->starting && count($this->players) >= $this->minPlayers) {
			$this->starting = true;
			$this->broadcastMessage(TextFormat::GREEN . "Countdown started");
		}
	}

	/**
	 * @param Player $player
	 */
	public function trackCompass(Player $player): void
	{
		$currentTeam = $this->trackingPositions[$player->getName()];
		$arrayTeam = $this->teams;
		$position = array_search($currentTeam, array_keys($arrayTeam));
		$teams = array_values($this->teams);
		$team = null;

		if (isset($teams[$position + 1])) {
			$team = $teams[$position + 1]->getName();
		} else {
			$team = $teams[0]->getName();
		}

		$this->trackingPositions[$player->getName()] = $team;

		$player->setSpawn(Utils::stringToVector(":", $spawnPos = $this->teamInfo[$team]['SpawnPos']));
		$player->setSpawn(Utils::stringToVector(":", $spawnPos = $this->teamInfo[$team]['SpawnPos']));

		foreach ($player->getInventory()->getContents() as $slot => $item) {
			if ($item instanceof Compass) {
				$player->getInventory()->removeItem($item);
				$player->getInventory()->setItem($slot, ItemFactory::getInstance()->get(ItemIds::COMPASS)->setCustomName(TextFormat::GREEN . "Tap to switch"));
			}
		}
	}

	/**
	 * @param Team $team
	 * @param Player $player
	 */
	public function breakBed(Team $team, Player $player): void
	{
		$team->updateBedState(false);

		$playerTeam = $this->plugin->getPlayerTeam($player);

		$this->broadcastMessage($team->getColor() . $team->getName() . "'s '" . TextFormat::GRAY . "bed was destroyed by " . $playerTeam->getColor() . $player->getName());
		foreach ($team->getPlayers() as $player) {
			$player->sendTitle(TextFormat::RED . "BED DESTROYED!", TextFormat::GRAY . "You will no longer respawn", 0, 10, 50);
		}
	}

	/**
	 * @param Player $player
	 */
	public function quit(Player $player): void
	{
		if (isset($this->players[$player->getName()])) {
			$team = $this->plugin->getPlayerTeam($player);
			if ($team instanceof Team) {
				$team->remove($player);
			}
			unset($this->players[$player->getName()]);
		}
		if (isset($this->spectators[$player->getName()])) {
			unset($this->spectators[$player->getName()]);
		}


		Scoreboard::remove($player);
	}

	/**
	 * @param Player $player
	 */
	public function killPlayer(Player $player): void
	{
		$playerTeam = $this->plugin->getPlayerTeam($player);
		if ($player->isSpectator()) return;

		if (!$playerTeam->hasBed()) {
			$playerTeam->dead++;
			$this->spectators[$player->getName()] = $player;
			unset($this->players[$player->getName()]);
			$player->setGamemode(GameMode::SPECTATOR());
			$player->sendTitle(TextFormat::BOLD . TextFormat::RED . "Bed Destroyed!", TextFormat::GRAY . "You will no longer respawn");
		} else {
			$player->setGamemode(GameMode::SPECTATOR());
			$this->deadQueue[$player->getName()] = 5;
		}

		$cause = $player->getLastDamageCause();
		if ($cause == null) return; //probably handled the event itself
		switch ($cause->getCause()) {
			case EntityDamageEvent::CAUSE_ENTITY_ATTACK;
				$damager = $cause->getDamager();
				$this->broadcastMessage($this->plugin->getPlayerTeam($player)->getColor() . $player->getName() . " " . TextFormat::GRAY . "was killed by " . $this->plugin->getPlayerTeam($damager)->getColor() . $damager->getName());
				break;
			case EntityDamageEvent::CAUSE_PROJECTILE;
				if ($cause instanceof EntityDamageByChildEntityEvent) {
					$damager = $cause->getDamager();
					$this->broadcastMessage($this->plugin->getPlayerTeam($player)->getColor() . $player->getName() . " " . TextFormat::GRAY . "was shot by " . $this->plugin->getPlayerTeam($damager)->getColor() . $damager->getName());
				}
				break;
			case EntityDamageEvent::CAUSE_FIRE;
				$this->broadcastMessage($this->plugin->getPlayerTeam($player)->getColor() . $player->getName() . " " . TextFormat::GRAY . "went up in flame");
				break;
			case EntityDamageEvent::CAUSE_VOID;
				$player->teleport($player->getPosition()->add(0, $this->voidY + 5, 0));
				break;
		}

	}

	public function tick(): void
	{

		switch ($this->state) {
			case self::STATE_LOBBY;
				if ($this->starting) {
					if (count($this->players) < $this->minPlayers) {
						$this->starting = false;
						$this->broadcastMessage(TextFormat::YELLOW . "Countdown stopped");
					}

					$this->startTime--;

					foreach ($this->players as $player) {
						$player->sendTip(TextFormat::YELLOW . "Starting in " . TextFormat::AQUA . gmdate("i:s", $this->startTime));
					}

					switch ($this->startTime) {
						case 30;
							$this->broadcastMessage(TextFormat::YELLOW . "Starting in " . TextFormat::RED . "30");
							break;
						case 15;
							$this->broadcastMessage(TextFormat::YELLOW . "Starting in " . TextFormat::GOLD . "15");
							break;
						case 5;
						case 4;
						case 3;
						case 2;
						case 1;
							foreach ($this->players as $player) {
								$player->sendTitle(TextFormat::RED . $this->startTime);
							}
							break;
					}

					if ($this->startTime == 0) {
						$this->start();
					}
				} else {
					foreach ($this->players as $player) {
						$player->sendTip(TextFormat::YELLOW . "Waiting for players (" . TextFormat::AQUA . ($this->minPlayers - count($this->players)) . TextFormat::YELLOW . ")");
					}
				}

				foreach (array_merge($this->players, $this->spectators) as $player) {
					Scoreboard::remove($player);
					Scoreboard::new($player, 'bedwars', TextFormat::BOLD . TextFormat::YELLOW . "Bed Wars");
					Scoreboard::setLine($player, 1, " ");
					Scoreboard::setLine($player, 2, " " . TextFormat::WHITE . "Map: " . TextFormat::GREEN . $this->mapName . str_repeat(" ", 3));
					Scoreboard::setLine($player, 3, " " . TextFormat::WHITE . "Players: " . TextFormat::GREEN . count($this->players) . "/" . $this->maxPlayers . str_repeat(" ", 3));
					Scoreboard::setLine($player, 4, "  ");
					Scoreboard::setLine($player, 5, " " . ($this->starting ? TextFormat::WHITE . "Starting in " . TextFormat::GREEN . $this->startTime . str_repeat(" ", 3) : TextFormat::GREEN . "Waiting for players..." . str_repeat(" ", 3)));
					Scoreboard::setLine($player, 6, "   ");
					Scoreboard::setLine($player, 7, " " . TextFormat::WHITE . "Mode: " . TextFormat::GREEN . substr(str_repeat($this->playersPerTeam . "v", count($this->teams)), 0, -1) . str_repeat(" ", 3));
					Scoreboard::setLine($player, 8, " " . TextFormat::WHITE . "Version: " . TextFormat::GRAY . "v1.0" . str_repeat(" ", 3));
					Scoreboard::setLine($player, 9, "    ");
					Scoreboard::setLine($player, 10, " " . TextFormat::YELLOW . "www.example.net");
				}

				break;
			case self::STATE_RUNNING;

				foreach ($this->players as $player) {
					if ($player->getInventory()->contains(ItemFactory::getInstance()->get(ItemIds::COMPASS))) {
						$trackIndex = $this->trackingPositions[$player->getName()];
						$team = $this->teams[$trackIndex];
						$player->sendTip(TextFormat::WHITE . "Tracking: " . TextFormat::BOLD . $team->getColor() . ucfirst($team->getName()) . " " . TextFormat::RESET . TextFormat::WHITE . "- Distance: " . TextFormat::BOLD . $team->getColor() . round(Utils::stringToVector(":", $this->teamInfo[$trackIndex]['SpawnPos'])->distance($player->getPosition())) . "m");
					}

					if (isset($this->deadQueue[$player->getName()])) {

						$player->sendTitle(TextFormat::RED . "You died!", TextFormat::YELLOW . "You will respawn in " . TextFormat::RED . $this->deadQueue[$player->getName()] . " " . TextFormat::YELLOW . "seconds!");
						$player->sendMessage(TextFormat::YELLOW . "You will respawn in " . TextFormat::RED . $this->deadQueue[$player->getName()] . " " . TextFormat::YELLOW . "seconds!");

						$this->deadQueue[$player->getName()] -= 1;
						if ($this->deadQueue[$player->getName()] == 0) {
							unset($this->deadQueue[$player->getName()]);

							$this->respawnPlayer($player);
							$player->sendTitle(TextFormat::GREEN . "RESPAWNED!");
							$player->sendMessage(TextFormat::YELLOW . "You have respawned!");
						}
					}
				}

				foreach (array_merge($this->players, $this->spectators) as $player) {

					Scoreboard::remove($player);
					Scoreboard::new($player, 'bedwars', TextFormat::BOLD . TextFormat::YELLOW . "Bed Wars");

					Scoreboard::setLine($player, 1, " ");
					Scoreboard::setLine($player, 2, " " . TextFormat::WHITE . ucfirst($this->tierUpdateGen) . " Upgrade: " . TextFormat::GREEN . gmdate("i:s", $this->tierUpdate));
					Scoreboard::setLine($player, 3, "  ");

					$currentLine = 4;
					$playerTeam = $this->plugin->getPlayerTeam($player);
					foreach ($this->teams as $team) {
						$status = "";
						if ($team->hasBed()) {
							$status = TextFormat::GREEN . "[+]";
						} elseif (count($team->getPlayers()) < $team->dead) {
							$status = count($team->getPlayers()) === 0 ? TextFormat::DARK_RED . "[-]" : TextFormat::GRAY . "[" . count($team->getPlayers()) . "]";
						} elseif (count($team->getPlayers()) >= $team->dead) {
							$status = TextFormat::DARK_RED . "[-]";
						}
						$isPlayerTeam = $team->getName() == $playerTeam->getName() ? TextFormat::GRAY . "(YOU)" : "";
						$stringFormat = TextFormat::BOLD . $team->getColor() . ucfirst($team->getName()[0]) . " " . TextFormat::RESET . TextFormat::WHITE . ucfirst($team->getName()) . ": " . $status . " " . $isPlayerTeam;
						Scoreboard::setLine($player, $currentLine, $stringFormat);
						$currentLine++;
					}
					Scoreboard::setLine($player, $currentLine, "   ");
					Scoreboard::setLine($player, $currentLine, TextFormat::YELLOW . $this->plugin->serverWebsite);
				}


				if (count($team = $this->getAliveTeams()) === 1 && count($this->players) == count($team[0]->getPlayers())) {
					/*   $this->winnerTeam = $team[0];

					   $this->state = self::STATE_REBOOT;*/
					//solo testing
				}

				foreach ($this->generators as $generator) {
					$generator->tick();
				}

				$this->tierUpdate--;

				if ($this->tierUpdate == 0) {
					$this->tierUpdate = 60 * 10;
					foreach ($this->generators as $generator) {
						if ($generator->itemID == ItemIds::DIAMOND && $this->tierUpdateGen == "diamond") {
							$generator->updateTier();
						} elseif ($generator->itemID == ItemIds::EMERALD && $this->tierUpdateGen == "emerald") {
							$generator->updateTier();
						}
					}
					$this->tierUpdateGen = $this->tierUpdateGen == 'diamond' ? 'emerald' : 'diamond';
				}
				break;
			case Game::STATE_REBOOT;
				$team = $this->teams[$this->winnerTeam];
				if ($this->rebootTime == 15) {
					foreach ($team->getPlayers() as $player) {
						$player->sendTitle(TextFormat::BOLD . TextFormat::GOLD . "VICTORY!",);
					}
				}

				--$this->rebootTime;
				if ($this->rebootTime == 0) {
					$this->stop();
				}
				break;
		}
	}

	public function start(): void
	{
		$this->broadcastMessage(TextFormat::GREEN . "Game has started! ");
		$this->state = self::STATE_RUNNING;

		foreach ($this->players as $player) {
			$playerTeam = $this->plugin->getPlayerTeam($player);

			if ($playerTeam == null) {
				$players = [];
				foreach ($this->teams as $name => $object) {
					$players[$name] = count($object->getPlayers());
				}

				$lowest = min($players);
				$teamName = array_search($lowest, $players);

				$team = $this->teams[$teamName];
				$team->add($player);
				$playerTeam = $team;
			}

			$playerTeam->setArmor($player, 'leather');

			$this->respawnPlayer($player);
			$player->setNameTag(TextFormat::BOLD . $playerTeam->getColor() . strtoupper($playerTeam->getName()[0]) . " " . TextFormat::RESET . $playerTeam->getColor() . $player->getName());

			$this->trackingPositions[$player->getName()] = $playerTeam->getName();
			$player->setSpawn(Utils::stringToVector(":", $spawnPos = $this->teamInfo[$playerTeam->getName()]['SpawnPos']));
		}

		$this->initShops();
		$this->initGenerators();
		$this->initTeams();
	}

	/**
	 * @param Player $player
	 */
	public function respawnPlayer(Player $player): void
	{
		$team = $this->plugin->getPlayerTeam($player);
		if ($team == null) return;

		$spawnPos = $this->teamInfo[$team->getName()]['SpawnPos'];

		$player->setGamemode(GameMode::SURVIVAL());
		$player->getHungerManager()->setFood(20);
		$player->setHealth($player->getMaxHealth());
		$player->getInventory()->clearAll();

		$player->teleport($this->plugin->getServer()->getWorldManager()->getWorldByName($this->mapName)->getSafeSpawn());
		$player->teleport(Utils::stringToVector(":", $spawnPos));

		//inventory
		$helmet = ItemFactory::getInstance()->get(ItemIds::LEATHER_CAP);
		$chestplate = ItemFactory::getInstance()->get(ItemIds::LEATHER_CHESTPLATE);
		$leggings = ItemFactory::getInstance()->get(ItemIds::LEATHER_LEGGINGS);
		$boots = ItemFactory::getInstance()->get(ItemIds::LEATHER_BOOTS);

		$hasArmorUpdated = true;

		switch ($team->getArmor($player)) {
			case "iron";
				$leggings = ItemFactory::getInstance()->get(ItemIds::IRON_LEGGINGS);
				break;
			case "diamond";
				$boots = ItemFactory::getInstance()->get(ItemIds::IRON_BOOTS);
				break;
			default;
				$hasArmorUpdated = false;
				break;
		}


		foreach (array_merge([$helmet, $chestplate], !$hasArmorUpdated ? [$leggings, $boots] : []) as $armor) {
			$armor->setCustomColor(Utils::colorIntoObject($team->getColor()));
		}

		$armorUpgrade = $team->getUpgrade('armorProtection');
		if ($armorUpgrade > 0) {
			foreach ([$helmet, $chestplate, $leggings, $boots] as $armor) {
				$armor->addEnchantment(new EnchantmentInstance(EnchantmentIdMap::getInstance()->fromId(EnchantmentIds::PROTECTION)), $armorUpgrade);
			}
		}

		$player->getArmorInventory()->setHelmet($helmet);
		$player->getArmorInventory()->setChestplate($chestplate);
		$player->getArmorInventory()->setLeggings($leggings);
		$player->getArmorInventory()->setBoots($boots);

		$sword = ItemFactory::getInstance()->get(ItemIds::WOODEN_SWORD);

		$swordUpgrade = $team->getUpgrade('sharpenedSwords');
		if ($swordUpgrade > 0) {
			$sword->addEnchantment(new EnchantmentInstance(EnchantmentIdMap::getInstance()->fromId(EnchantmentIds::SHARPNESS)), $swordUpgrade);
		}

		$player->getInventory()->setItem(0, $sword);
		$player->getInventory()->setItem(8, ItemFactory::getInstance()->get(ItemIds::COMPASS)->setCustomName(TextFormat::GREEN . "Tap to switch"));

	}

	private function initShops(): void
	{
		foreach ($this->teamInfo as $team => $info) {
			$shopPos = Utils::stringToVector(":", $info['shopPos']);
			$rotation = explode(":", $info['shopPos']);

			$location = Location::fromObject($shopPos, $this->plugin->getServer()->getWorldManager()->getWorldByName($this->mapName));
			$entity = new Villager($location);
			$entity->setNameTag(TextFormat::AQUA . "ITEM SHOP\n" . TextFormat::BOLD . TextFormat::YELLOW . "TAP TO USE");
			$entity->setNameTagAlwaysVisible(true);
			$entity->spawnToAll();

			$this->npcs[$entity->getId()] = [$team, 'shop'];

			$upgradePos = Utils::stringToVector(":", $info['upgradePos']);
			$rotation = explode(":", $info['upgradePos']);
			$location = Location::fromObject($upgradePos, $this->plugin->getServer()->getWorldManager()->getWorldByName($this->mapName));
			$entity = new Villager($location);
			$entity->setNameTag(TextFormat::AQUA . "TEAM UPGRADES\n" . TextFormat::BOLD . TextFormat::YELLOW . "TAP TO USE");
			$entity->setNameTagAlwaysVisible(true);
			$entity->spawnToAll();

			$this->npcs[$entity->getId()] = [$team, 'upgrade'];
		}
	}

	private function initGenerators(): void
	{
		foreach ($this->generatorInfo as $generator) {
			$generatorData = BedWars::GENERATOR_PRIORITIES[$generator['type']];
			$item = $generatorData['item'];
			$spawnText = $generatorData['spawnText'];
			$spawnBlock = $generatorData['spawnBlock'];
			$delay = $generatorData['refreshRate'];

			$vector = Utils::stringToVector(":", $generator['position']);
			$position = new Position($vector->x, $vector->y, $vector->z, $this->plugin->getServer()->getWorldManager()->getWorldByName($this->mapName));

			$this->generators[] = new Generator($item, $delay, $position, $spawnText, $spawnBlock);

		}
	}

	private function initTeams(): void
	{
		foreach ($this->teams as $team) {
			if (count($team->getPlayers()) === 0) {
				$team->updateBedState(false);
			}
		}
	}

	/**
	 * @return array
	 */
	public function getAliveTeams(): array
	{
		$teams = [];
		foreach ($this->teams as $team) {
			if (count($team->getPlayers()) <= 0 || !$team->hasBed()) continue;
			$players = [];

			foreach ($team->getPlayers() as $player) {
				if (!$player->isOnline()) continue;
				if ($player->isAlive() && $player->getWorld()->getFolderName() === $this->mapName) {
					$players[] = $player;
				}
			}

			if (count($players) >= 1) {
				$teams[] = $team;
			}
		}
		return $teams;
	}

	public function stop(): void
	{
		foreach (array_merge($this->players, $this->spectators) as $player) {
			$this->cachedPlayers[$player->getName()]->load();
			Scoreboard::remove($player);
		}

		foreach ($this->teams as $team) {
			$team->reset();
		}

		foreach ($this->generators as $generator) {
			if ($generator->getBlockEntity() !== null) {
				$generator->getBlockEntity()->flagForDespawn();
			}

			if ($generator->getFloatingText() !== null) {
				$generator->getFloatingText()->setInvisible(true);
				foreach ($this->plugin->getServer()->getOnlinePlayers() as $player) {
					foreach ($generator->getFloatingText()->encode($generator->getBlockEntity()->getPosition()->add(0.5, 3, 0.5)) as $packet) {
						$player->getNetworkSession()->sendDataPacket($packet);
					}
				}
			}
		}

		$this->spectators = [];
		$this->players = [];
		$this->winnerTeam = '';
		$this->startTime = 30;
		$this->rebootTime = 10;
		$this->generators = [];
		$this->cachedPlayers = [];
		$this->state = self::STATE_LOBBY;
		$this->starting = false;
		$this->plugin->getServer()->getWorldManager()->unloadWorld($this->plugin->getServer()->getWorldManager()->getWorldByName($this->mapName));
		$this->reload();

		$this->setLobby(new Vector3($this->lobby->x, $this->lobby->y, $this->lobby->z), $this->lobbyName);

	}

	/**
	 * @param Vector3 $lobby
	 * @param string $mapName
	 */
	public function setLobby(Vector3 $lobby, string $mapName): void
	{
		$this->lobby = new Position($lobby->x, $lobby->y, $lobby->z, $this->plugin->getServer()->getWorldManager()->getWorldByName($mapName));
	}

}