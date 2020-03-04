<?php


namespace BedWars\game;

use BedWars\game\player\PlayerCache;
use BedWars\utils\Utils;
use pocketmine\entity\Entity;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\Compass;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\Item;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\Player;
use pocketmine\plugin\Plugin;
use pocketmine\scheduler\Task;
use pocketmine\utils\TextFormat;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use BedWars\BedWars;

class Game
{

    const STATE_LOBBY = 0;
    const STATE_RUNNING = 1;
    const STATE_REBOOT = 2;

    /** @var BedWars $plugin */
    private $plugin;

    /** @var string $gameName */
    private $gameName;

    /** @var int $minPlayers */
    private $minPlayers;

    /** @var int $maxPlayers */
    private $maxPlayers;

    /** @var int $playersPerTeam */
    public $playersPerTeam;

    /** @var string $worldName */
    public $worldName;

    /** @var string $lobbyName */
    private $lobbyName;

    /** @var int $state */
    private $state = self::STATE_LOBBY;

    /** @var array $players */
    public $players = array();

    /** @var array $spectators */
    public $spectators = array();

    /** @var bool $starting */
    private $starting = false;

    /** @var Vector3 $lobby */
    private $lobby;

    /** @var int $startTime */
    private $startTime = 30;

    /** @var int $rebootTime */
    private $rebootTime = 10;

    /** @var int $voidY */
    private $voidY ;

    /** @var array $teamInfo */
    public $teamInfo = array();

    /** @var array $teams */
    public $teams = array();

    /** @var array $deadQueue */
    public $deadQueue = [];

    /** @var string $winnerTeam */
    private $winnerTeam = '';

    /** @var Entity[] $npcs */
    public $npcs = [];

    /** @var array $trackingPositions */
    private $trackingPositions = [];

    /** @var Generator[] $generators */
    public $generators = array();

    /** @var array $generatorInfo */
    private $generatorInfo = array();

    /** @var float|int $tierUpdate */
    private $tierUpdate = 60 * 10;

    /** @var string $tierUpdateGen */
    private $tierUpdateGen = "diamond";

    /** @var array $placedBlocks */
    public $placedBlocks = array();

    /** @var PlayerCache[] $cachedPlayers */
    private $cachedPlayers = array();




    /**
     * Game constructor.
     * @param BedWars $plugin
     * @param string $arenaName
     * @param int $minPlayers
     * @param int $playersPerTeam
     * @param string $worldName
     * @param string $lobbyWorld
     * @param array $teamInfo
     * @param array $generatorInfo
     */
    public function __construct(BedWars $plugin, string $arenaName, int $minPlayers, int $playersPerTeam, string $worldName, string $lobbyWorld, array $teamInfo, array $generatorInfo)
    {
        $this->plugin = $plugin;
        $this->gameName = $arenaName;
        $this->minPlayers = $minPlayers;
        $this->playersPerTeam = $playersPerTeam;
        $this->worldName = $worldName;
        $this->lobbyName = explode(":", $lobbyWorld)[3];
        $this->teamInfo = $teamInfo;
        $this->generatorInfo = !isset($generatorInfo[$this->gameName]) ? [] : $generatorInfo[$this->gameName];

        foreach($this->teamInfo as $teamName => $data){
             $this->teams[$teamName] = new Team($teamName, BedWars::TEAMS[strtolower($teamName)]);
        }

        $this->maxPlayers = count($this->teams) * $playersPerTeam;


        $this->reload();

        $this->plugin->getScheduler()->scheduleRepeatingTask(new class($this) extends Task{

            private $plugin;

            /**
             *  constructor.
             * @param Game $plugin
             */
            public function __construct(Game $plugin)
            {
                $this->plugin = $plugin;
            }

            public function onRun(int $currentTick)
            {
                $this->plugin->tick();
            }
        }, 20);

    }

    /**
     * @param int $limit
     */
    public function setVoidLimit(int $limit) : void{
        $this->voidY = $limit;
    }

    /**
     * @param Vector3 $lobby
     * @param string $worldName
     */
    public function setLobby(Vector3 $lobby, string $worldName) : void{
        $this->lobby = new Position($lobby->x, $lobby->y, $lobby->z, $this->plugin->getServer()->getLevelByName($worldName));
    }

    /**
     * @return int
     */
    public function getVoidLimit() : int{
        return $this->voidY;
    }

    /**
     * @return int
     */
    public function getState() : int{
        return $this->state;
    }

    /**
     * @return string
     */
    public function getName() : string{
        return $this->gameName;
    }

    /**
     * @return int
     */
    public function getMaxPlayers() : int{
        return $this->maxPlayers;
    }

    public function reload() : void{
        $this->plugin->getServer()->loadLevel($this->worldName);
        $world = $this->plugin->getServer()->getLevelByName($this->worldName);
        if(!$world instanceof Level){
            $this->plugin->getLogger()->info(BedWars::PREFIX . TextFormat::YELLOW . "Failed to load arena " . $this->gameName . " because it's world does not exist!");
            return;
        }
        $world->setAutoSave(false);

    }

    /**
     * @param string $message
     */
    public function broadcastMessage(string $message) : void{
        foreach(array_merge($this->spectators, $this->players) as $player){
            $player->sendMessage(BedWars::PREFIX . $message);
        }
    }

    /**
     * @return array
     */
    public function getAliveTeams() : array{
        $teams = [];
        foreach($this->teams as $team){
            if(count($team->getPlayers()) <= 0 || !$team->hasBed())continue;
            $players = [];

            foreach($team->getPlayers() as $player){
                if($player->isAlive() && $player->getLevel()->getFolderName() == $this->worldName){
                    $players[] = $player;
                }
            }

            if(count($players) >= 1){
                $teams[] = $team->getName();
            }

        }
        return $teams;
    }

    public function stop() : void{
        foreach(array_merge($this->players, $this->spectators) as $player){
            $this->cachedPlayers[$player->getRawUniqueId()]->load();
            \BedWars\utils\Scoreboard::remove($player);
        }

        foreach($this->teams as $team){
            $team->reset();
        }

        foreach($this->generators as $generator){
            if($generator->getBlockEntity() !== null){
                $generator->getBlockEntity()->flagForDespawn();
            }

            if($generator->getFloatingText() !== null){
                $generator->getFloatingText()->setInvisible(true);
                foreach($this->plugin->getServer()->getOnlinePlayers() as $player){
                    foreach($generator->getFloatingText()->encode() as $packet){
                        $player->dataPacket($packet);
                    }
                }
            }
        }

        $this->spectators = array();
        $this->players = array();
        $this->winnerTeam = '';
        $this->startTime = 30;
        $this->rebootTime = 10;
        $this->generators = array();
        $this->cachedPlayers = array();
        $this->state = self::STATE_LOBBY;
        $this->starting = false;
        $this->plugin->getServer()->unloadLevel($this->plugin->getServer()->getLevelByName($this->worldName));
        $this->reload();

        $this->setLobby(new Vector3($this->lobby->x, $this->lobby->y, $this->lobby->z), $this->lobbyName);

    }

    public function start() : void{
         $this->broadcastMessage(TextFormat::GREEN . "Game has started! ");
         $this->state = self::STATE_RUNNING;

         foreach($this->players as $player){
             $playerTeam = $this->plugin->getPlayerTeam($player);

             if($playerTeam == null){
                 $players = array();
                 foreach($this->teams as $name => $object){
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
             $player->setNameTag(TextFormat::BOLD . $playerTeam->getColor() . strtoupper($playerTeam->getName()[0]) . " " .  TextFormat::RESET . $playerTeam->getColor() . $player->getName());

             $this->trackingPositions[$player->getRawUniqueId()] = $playerTeam->getName();
             $player->setSpawn(Utils::stringToVector(":",  $spawnPos = $this->teamInfo[$playerTeam->getName()]['spawnPos']));
         }

         $this->initShops();
         $this->initGenerators();
         $this->initTeams();
    }

    private function initTeams() : void{
        foreach($this->teams as $team){
            if(count($team->getPlayers()) === 0){
                $team->updateBedState(false);
            }
        }
    }

    private function initGenerators() : void{
        foreach($this->generatorInfo as $generator){
            $generatorData = BedWars::GENERATOR_PRIORITIES[$generator['type']];
            $item = $generatorData['item'];
            $spawnText = $generatorData['spawnText'];
            $spawnBlock = $generatorData['spawnBlock'];
            $delay = $generatorData['refreshRate'];

            $vector = Utils::stringToVector(":", $generator['position']);
            $position = new Position($vector->x, $vector->y, $vector->z,$this->plugin->getServer()->getLevelByName($this->worldName));

            $this->generators[] = new Generator($item, $delay,$position, $spawnText, $spawnBlock);

        }
    }

    private function initShops() : void{
        foreach($this->teamInfo as $team => $info){
            $shopPos = Utils::stringToVector(":", $info['shopPos']);
            $rotation = explode(":", $info['shopPos']);

            $nbt = Entity::createBaseNBT($shopPos->add(0.5, 0, 0.5), null, $rotation[3], $rotation[4]);
            $entity = Entity::createEntity("Villager", $this->plugin->getServer()->getLevelByName($this->worldName), $nbt);
            $entity->setNameTag(TextFormat::AQUA . "ITEM SHOP\n" . TextFormat::BOLD . TextFormat::YELLOW . "TAP TO USE");
            $entity->setNameTagAlwaysVisible(true);
            $entity->spawnToAll();

            $this->npcs[$entity->getId()] = [$team, 'shop'];

            $upgradePos = Utils::stringToVector(":", $info['upgradePos']);
            $rotation = explode(":", $info['upgradePos']);

            $nbt = Entity::createBaseNBT($upgradePos, null, $rotation[3], $rotation[4]);
            $entity = Entity::createEntity("Villager", $this->plugin->getServer()->getLevelByName($this->worldName), $nbt);
            $entity->setNameTag(TextFormat::AQUA . "TEAM UPGRADES\n" . TextFormat::BOLD . TextFormat::YELLOW . "TAP TO USE");
            $entity->setNameTagAlwaysVisible(true);
            $entity->spawnToAll();

            $this->npcs[$entity->getId()] = [$team, 'upgrade'];
        }
    }

    /**
     * @param Player $player
     */
    public function join(Player $player) : void{
         if($this->state !== self::STATE_LOBBY){
             $player->sendMessage(BedWars::PREFIX . TextFormat::YELLOW . "Arena is full!");
             return;
         }

         $this->cachedPlayers[$player->getRawUniqueId()] = new PlayerCache($player);
         $player->teleport($this->lobby);
         $this->players[$player->getRawUniqueId()] = $player;

         $this->broadcastMessage(TextFormat::GRAY . $player->getName() . " " . TextFormat::YELLOW . "has joined the game " . TextFormat::GOLD . "(" . TextFormat::AQUA .  count($this->players) . TextFormat::YELLOW . "/" . TextFormat::AQUA .  $this->maxPlayers . TextFormat::YELLOW .  ")");
         $player->getInventory()->clearAll();
         $a = 0;
         $items = array_fill(0, count($this->teams), Item::get(Item::WOOL));
         foreach($this->teams as $team){
             $items[$a]->setDamage(Utils::colorIntoWool($team->getColor()));
             $player->getInventory()->addItem($items[$a]);
             $a++;
         }

         $player->getInventory()->setItem(8, Item::get(Item::COMPASS)->setCustomName(TextFormat::YELLOW . "Leave"));
         $this->checkLobby();

        \BedWars\utils\Scoreboard::new($player, 'bedwars', TextFormat::BOLD . TextFormat::YELLOW . "Bed Wars");

        \BedWars\utils\Scoreboard::setLine($player, 1, " ");
        \BedWars\utils\Scoreboard::setLine($player, 2, " " . TextFormat::WHITE ."Map: " . TextFormat::GREEN .  $this->worldName . str_repeat(" ", 3));
        \BedWars\utils\Scoreboard::setLine($player, 3, " " . TextFormat::WHITE . "Players: " . TextFormat::GREEN . count($this->players) . "/" . $this->maxPlayers . str_repeat(" ", 3));
        \BedWars\utils\Scoreboard::setLine($player, 4, "  ");
        \BedWars\utils\Scoreboard::setLine($player, 5, " " . $this->starting ? TextFormat::WHITE . "Starting in " . TextFormat::GREEN .  $this->startTime . str_repeat(" ", 3) : TextFormat::GREEN . "Waiting for players..." . str_repeat(" ", 3));
        \BedWars\utils\Scoreboard::setLine($player, 6, "   ");
        \BedWars\utils\Scoreboard::setLine($player, 7, " " . TextFormat::WHITE . "Mode: " . TextFormat::GREEN . substr(str_repeat($this->playersPerTeam . "v", count($this->teams)), 0, -1) . str_repeat(" ", 3));
        \BedWars\utils\Scoreboard::setLine($player, 8, " " . TextFormat::WHITE . "Version: " . TextFormat::GRAY . "v1.0" . str_repeat(" ", 3));
        \BedWars\utils\Scoreboard::setLine($player, 9, "    ");
        \BedWars\utils\Scoreboard::setLine($player, 10, " " . TextFormat::YELLOW . "www.example.net");
    }

    /**
     * @param Player $player
     */
    public function trackCompass(Player $player) : void{
        $currentTeam = $this->trackingPositions[$player->getRawUniqueId()];
        $arrayTeam = $this->teams;
        $position = array_search($currentTeam, array_keys($arrayTeam));
        $teams = array_values($this->teams);
        $team = null;

        if(isset($teams[$position+1])){
            $team = $teams[$position+1]->getName();
        }else{
            $team = $teams[0]->getName();
        }

        $this->trackingPositions[$player->getRawUniqueId()] = $team;

        $player->setSpawn(Utils::stringToVector(":",  $spawnPos = $this->teamInfo[$team]['spawnPos']));
        $player->setSpawn(Utils::stringToVector(":",  $spawnPos = $this->teamInfo[$team]['spawnPos']));

        foreach($player->getInventory()->getContents() as $slot => $item){
            if($item instanceof Compass){
                $player->getInventory()->removeItem($item);
                $player->getInventory()->setItem($slot, Item::get(Item::COMPASS)->setCustomName(TextFormat::GREEN . "Tap to switch"));
            }
        }
    }

    /**
     * @param Team $team
     * @param Player $player
     */
    public function breakBed(Team $team, Player $player) : void{
        $team->updateBedState(false);

        $playerTeam = $this->plugin->getPlayerTeam($player);

        $this->broadcastMessage($team->getColor() . $team->getName() . "'s '" . TextFormat::GRAY . "bed was destroyed by " . $playerTeam->getColor() . $player->getName());
        foreach($team->getPlayers() as $player){
            $player->addTitle(TextFormat::RED . "Bed Destroyed!", TextFormat::GRAY . "You will no longer respawn");
        }
    }

    /**
     * @param Player $player
     */
    public function quit(Player $player) : void{
         if(isset($this->players[$player->getRawUniqueId()])){
             unset($this->players[$player->getRawUniqueId()]);
         }
         if(isset($this->spectators[$player->getRawUniqueId()])){
             unset($this->spectators[$player->getRawUniqueId()]);
         }



         \BedWars\utils\Scoreboard::remove($player);
    }

    private function checkLobby() : void{
        if(!$this->starting && count($this->players) >= $this->minPlayers){
            $this->starting = true;
            $this->broadcastMessage(TextFormat::GREEN . "Countdown started");
        }elseif($this->starting && count($this->players) < $this->minPlayers){
            $this->starting = false;
            $this->broadcastMessage(TextFormat::YELLOW . "Countdown stopped");
        }
    }

    /**
     * @param Player $player
     */
    public function killPlayer(Player $player) : void{
        $playerTeam = $this->plugin->getPlayerTeam($player);
        if($player->isSpectator())return;

        if(!$playerTeam->hasBed()){
            $playerTeam->dead++;
            $this->spectators[$player->getRawUniqueId()] = $player;
            unset($this->players[$player->getRawUniqueId()]);
            $player->setGamemode(Player::SPECTATOR);
            $player->addTitle(TextFormat::BOLD . TextFormat::RED . "Bed Destroyed!", TextFormat::GRAY . "You will no longer respawn");
        }else{
            $player->setGamemode(Player::SPECTATOR);
            $this->deadQueue[$player->getRawUniqueId()] = 5;
         }

        $cause = $player->getLastDamageCause();
        if($cause == null)return; //probadly handled the event itself
        switch($cause->getCause()){
            case EntityDamageEvent::CAUSE_ENTITY_ATTACK;
            $damager = $cause->getDamager();
            $this->broadcastMessage($this->plugin->getPlayerTeam($player)->getColor() . $player->getName() . " " . TextFormat::GRAY . "was killed by " . $this->plugin->getPlayerTeam($damager)->getColor() . $damager->getName());
            break;
            case EntityDamageEvent::CAUSE_PROJECTILE;
            if($cause instanceof EntityDamageByChildEntityEvent){
                $damager = $cause->getDamager();
                $this->broadcastMessage($this->plugin->getPlayerTeam($player)->getColor() . $player->getName() . " " . TextFormat::GRAY . "was shot by " . $this->plugin->getPlayerTeam($damager)->getColor() . $damager->getName());
            }
            break;
            case EntityDamageEvent::CAUSE_FIRE;
            $this->broadcastMessage($this->plugin->getPlayerTeam($player)->getColor() . $player->getName() . " " . TextFormat::GRAY . "went up in flame");
            break;
            case EntityDamageEvent::CAUSE_VOID;
            $player->teleport($player->add(0, $this->voidY + 5, 0));
            break;
        }

    }



    /**
     * @param Player $player
     */
    public function respawnPlayer(Player $player) : void{
        $team = $this->plugin->getPlayerTeam($player);
        if($team == null)return;

        $spawnPos = $this->teamInfo[$team->getName()]['spawnPos'];

        $player->setGamemode(Player::SURVIVAL);
        $player->setFood($player->getMaxFood());
        $player->setHealth($player->getMaxHealth());
        $player->getInventory()->clearAll();

        $player->teleport($this->plugin->getServer()->getLevelByName($this->worldName)->getSafeSpawn());
        $player->teleport(Utils::stringToVector(":", $spawnPos));

        //inventory
        $helmet = Item::get(Item::LEATHER_CAP);
        $chestplate = Item::get(Item::LEATHER_CHESTPLATE);
        $leggings = Item::get(Item::LEATHER_LEGGINGS);
        $boots = Item::get(Item::LEATHER_BOOTS);

        $hasArmorUpdated = true;

        switch($team->getArmor($player)){
            case "iron";
            $leggings = Item::get(Item::IRON_LEGGINGS);
            break;
            case "diamond";
            $boots = Item::get(Item::IRON_BOOTS);
            break;
            default;
            $hasArmorUpdated = false;
            break;
        }


        foreach(array_merge([$helmet, $chestplate], !$hasArmorUpdated ? [$leggings, $boots] : []) as $armor){
            $armor->setCustomColor(Utils::colorIntoObject($team->getColor()));
        }

        $armorUpgrade = $team->getUpgrade('armorProtection');
        if($armorUpgrade > 0){
            foreach([$helmet, $chestplate, $leggings, $boots] as $armor){
                $armor->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(Enchantment::PROTECTION)), $armorUpgrade);
            }
        }

        $player->getArmorInventory()->setHelmet($helmet);
        $player->getArmorInventory()->setChestplate($chestplate);
        $player->getArmorInventory()->setLeggings($leggings);
        $player->getArmorInventory()->setBoots($boots);

        $sword = Item::get(Item::WOODEN_SWORD);

        $swordUpgrade = $team->getUpgrade('sharpenedSwords');
        if($swordUpgrade > 0){
            $sword->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(Enchantment::SHARPNESS)), $swordUpgrade);
        }

        $player->getInventory()->setItem(0, $sword);
        $player->getInventory()->setItem(8, Item::get(Item::COMPASS)->setCustomName(TextFormat::GREEN . "Tap to switch"));

    }


    public function tick() : void{

         switch($this->state) {
             case self::STATE_LOBBY;
                 if ($this->starting) {
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
                                 $player->addTitle(TextFormat::RED . $this->startTime);
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
                     \BedWars\utils\Scoreboard::remove($player);
                     \BedWars\utils\Scoreboard::new($player, 'bedwars', TextFormat::BOLD . TextFormat::YELLOW . "Bed Wars");
                     \BedWars\utils\Scoreboard::setLine($player, 1, " ");
                     \BedWars\utils\Scoreboard::setLine($player, 2, " " . TextFormat::WHITE . "Map: " . TextFormat::GREEN . $this->worldName . str_repeat(" ", 3));
                     \BedWars\utils\Scoreboard::setLine($player, 3, " " . TextFormat::WHITE . "Players: " . TextFormat::GREEN . count($this->players) . "/" . $this->maxPlayers . str_repeat(" ", 3));
                     \BedWars\utils\Scoreboard::setLine($player, 4, "  ");
                     \BedWars\utils\Scoreboard::setLine($player, 5, " " . ($this->starting ? TextFormat::WHITE . "Starting in " . TextFormat::GREEN . $this->startTime . str_repeat(" ", 3) : TextFormat::GREEN . "Waiting for players..." . str_repeat(" ", 3)));
                     \BedWars\utils\Scoreboard::setLine($player, 6, "   ");
                     \BedWars\utils\Scoreboard::setLine($player, 7, " " . TextFormat::WHITE . "Mode: " . TextFormat::GREEN . substr(str_repeat($this->playersPerTeam . "v", count($this->teams)), 0, -1) . str_repeat(" ", 3));
                     \BedWars\utils\Scoreboard::setLine($player, 8, " " . TextFormat::WHITE . "Version: " . TextFormat::GRAY . "v1.0" . str_repeat(" ", 3));
                     \BedWars\utils\Scoreboard::setLine($player, 9, "    ");
                     \BedWars\utils\Scoreboard::setLine($player, 10, " " . TextFormat::YELLOW . "www.example.net");
                 }

                 break;
             case self::STATE_RUNNING;

                 foreach ($this->players as $player) {
                     if ($player->getInventory()->contains(Item::get(Item::COMPASS))) {
                         $trackIndex = $this->trackingPositions[$player->getRawUniqueId()];
                         $team = $this->teams[$trackIndex];
                         $player->sendTip(TextFormat::WHITE . "Tracking: " . TextFormat::BOLD . $team->getColor() . ucfirst($team->getName()) . " " . TextFormat::RESET . TextFormat::WHITE . "- Distance: " . TextFormat::BOLD . $team->getColor() . round(Utils::stringToVector(":", $this->teamInfo[$trackIndex]['spawnPos'])->distance($player)) . "m");
                     }

                     if (isset($this->deadQueue[$player->getRawUniqueId()])) {

                         $player->addTitle(TextFormat::RED . "You died!", TextFormat::YELLOW . "You will respawn in " . TextFormat::RED . $this->deadQueue[$player->getRawUniqueId()] . " " . TextFormat::YELLOW . "seconds!");
                         $player->sendMessage(TextFormat::YELLOW . "You will respawn in " . TextFormat::RED . $this->deadQueue[$player->getRawUniqueId()] . " " . TextFormat::YELLOW . "seconds!");

                         $this->deadQueue[$player->getRawUniqueId()] -= 1;
                         if ($this->deadQueue[$player->getRawUniqueId()] == 0) {
                             unset($this->deadQueue[$player->getRawUniqueId()]);

                             $this->respawnPlayer($player);
                             $player->addTitle(TextFormat::GREEN . "RESPAWNED!");
                             $player->sendMessage(TextFormat::YELLOW . "You have respawned!");
                         }
                     }
                 }

                 foreach (array_merge($this->players, $this->spectators) as $player) {

                     \BedWars\utils\Scoreboard::remove($player);
                     \BedWars\utils\Scoreboard::new($player, 'bedwars', TextFormat::BOLD . TextFormat::YELLOW . "Bed Wars");

                     \BedWars\utils\Scoreboard::setLine($player, 1, " ");
                     \BedWars\utils\Scoreboard::setLine($player, 2, " " . TextFormat::WHITE . ucfirst($this->tierUpdateGen) . " Upgrade: " . TextFormat::GREEN . gmdate("i:s", $this->tierUpdate));
                     \BedWars\utils\Scoreboard::setLine($player, 3, "  ");

                     $currentLine = 4;
                     $playerTeam = $this->plugin->getPlayerTeam($player);
                     foreach ($this->teams as $team) {
                         $status = "";
                         if ($team->hasBed()) {
                             $status = TextFormat::GREEN . "[+]";
                         } elseif(count($team->getPlayers()) < $team->dead) {
                             $status = count($team->getPlayers()) === 0 ? TextFormat::DARK_RED . "[-]" : TextFormat::GRAY . "[" . count($team->getPlayers()) . "]";
                         }elseif(count($team->getPlayers()) >= $team->dead){
                             $status = TextFormat::DARK_RED . "[-]";
                         }
                         $isPlayerTeam = $team->getName() == $playerTeam->getName() ? TextFormat::GRAY . "(YOU)" : "";
                         $stringFormat = TextFormat::BOLD . $team->getColor() . ucfirst($team->getName()[0]) . " " . TextFormat::RESET . TextFormat::WHITE . ucfirst($team->getName()) . ": " . $status . " " . $isPlayerTeam;
                         \BedWars\utils\Scoreboard::setLine($player, " " . $currentLine, $stringFormat);
                         $currentLine++;
                     }
                     \BedWars\utils\Scoreboard::setLine($player, " " . $currentLine, "   ");
                     \BedWars\utils\Scoreboard::setLine($player, " " . $currentLine++, " " . TextFormat::YELLOW . "www.example.net");
                 }


             if(count($team = $this->getAliveTeams()) === 1 && count($this->players) == count($team[0]->getPlayers())){
                 $this->winnerTeam = $team[0];

                 $this->state = self::STATE_REBOOT;
             }

             foreach($this->generators as $generator){
                 $generator->tick();
             }

             $this->tierUpdate --;

             if($this->tierUpdate == 0){
                 $this->tierUpdate = 60 * 10;
                 foreach($this->generators as $generator){
                     if($generator->itemID == Item::DIAMOND && $this->tierUpdateGen == "diamond") {
                          $generator->updateTier();
                     }elseif($generator->itemID == Item::EMERALD && $this->tierUpdateGen == "emerald"){
                          $generator->updateTier();
                     }
                 }
                 $this->tierUpdateGen = $this->tierUpdateGen == 'diamond' ? 'emerald' : 'diamond';
             }
             break;
             case Game::STATE_REBOOT;
             $team = $this->teams[$this->winnerTeam];
             if($this->rebootTime == 15){
                 foreach($team->getPlayers() as $player){
                     $player->addTitle(TextFormat::BOLD . TextFormat::GOLD . "VICTORY!");
                 }
             }

             --$this->rebootTime;
             if($this->rebootTime == 0){
                 $this->stop();
             }
             break;
         }
    }







}