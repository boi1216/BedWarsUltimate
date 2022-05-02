<?php


namespace BedWars\game;

use BedWars\game\player\PlayerCache;
use BedWars\utils\Utils;
use pocketmine\entity\Entity;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\Armor;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\item\Compass;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\Item;
use pocketmine\item\ItemIdentifier;
use pocketmine\item\ItemFactory;
use pocketmine\block\BlockFactory;
use pocketmine\item\ItemIds;
use pocketmine\entity\Location;
use pocketmine\world\World;
use pocketmine\world\Position;
use pocketmine\player\Player;
use pocketmine\player\GameMode;
use pocketmine\plugin\Plugin;
use pocketmine\scheduler\Task;
use pocketmine\item\Durable;
use pocketmine\utils\TextFormat;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\IntTag;
use BedWars\BedWars;
use BedWars\utils\Scoreboard;

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

    /** @var string $mapName */
    private $mapName;

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
    private $startTime;
    /** @var int $startTimeStatic */
    private $startTimeStatic;

    /** @var int $rebootTime */
    private $rebootTime;

    /** @var array $teamInfo */
    public $teamInfo = array();

    /** @var array $teams */
    public $teams = array();

    /** @var array $deadQueue */
    public $deadQueue = [];

    /** @var Team $winnerTeam */
    private $winnerTeam;

    /** @var array $npcs */
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

    public $shopPositions = array();

    /** @var PlayerCache[] $cachedPlayers */
    private $cachedPlayers = array();
    
    /** @var array $safeAreas */
    private $safeAreas = array();

    /** @var bool $forceStart */
    private $forceStart = false;

    /**
     * Game constructor.
     * @param BedWars $plugin
     * @param array $data
     */
    public function __construct(BedWars $plugin, array $data)
    {
        $this->plugin = $plugin;
        $this->startTime = $data['startTime'];
        $this->startTimeStatic = $this->startTime;
        $this->rebootTime = $plugin->staticRestartTime;
        $this->gameName = $data['id'];
        $this->minPlayers = $data['minPlayers'];
        $this->playersPerTeam = $data['playersPerTeam'];
        $this->worldName = $data['world'];
        $this->lobbyName = explode(":", $data['lobby'])[3];
        $this->mapName = $data['mapName'];
        $this->teamInfo = $data['teamInfo'];
        $this->plugin->getServer()->getWorldManager()->loadWorld(explode(":", $data['lobby'])[3]);
        $this->lobby = Utils::stringToPosition(":", $data['lobby']);
        $this->generatorInfo = $data['generatorInfo'];
        $this->safeAreas = isset($data['safe_areas']) ? $data['safe_areas'] : [];

        foreach($this->teamInfo as $teamName => $data){
             $this->teams[$teamName] = new Team($teamName, BedWars::TEAMS[strtolower($teamName)]);
        }

        $this->maxPlayers = count($this->teams) * $this->playersPerTeam;
        $this->reload();
        $this->plugin->getScheduler()->scheduleRepeatingTask(new GameTick($this), 20);
    }

    /**
     * @param Vector3 $lobby
     * @param string $worldName
     */
    public function setLobby(Vector3 $lobby, string $worldName) : void{
        $this->lobby = new Position($lobby->x, $lobby->y, $lobby->z, $this->plugin->getServer()->getWorldManager()->getWorldByName($worldName));
    }

    /**
     * @return int
     */
    public function getVoidLimit() : int{
        return 0; //TODO
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

    public function getId() : string {
        return $this->gameName;
    }

    /**
     * @return string
     */
    public function getMapName() : string{
        return $this->mapName;
    }

    public function getStartTime(): int{
        return $this->startTime;
    }
    
    public function setStartTime(int $newVal){
        if($this->startTime > $newVal){
            $this->startTime = $newVal;
        }
    }

    public function isForcedStart(): bool{
        return $this->forceStart;
    }
    
    public function setForcedStart(bool $newVal){
        $this->forceStart = $newVal;
    }

    /**
     * @return int
     */
    public function getMaxPlayers() : int{
        return $this->maxPlayers;
    }

    public function getPlayers() : ?array{
        return $this->players;
    }

    public function getSpectators() : ?array{
        return $this->spectators;
    }

    public function getPlayerCache(string $name) : ?PlayerCache{
        return $this->cachedPlayers[$name];
    }

    public function reload() : void{
        $this->plugin->getServer()->getWorldManager()->loadWorld($this->worldName);
        $world = $this->plugin->getServer()->getWorldManager()->getWorldByName($this->worldName);
        if(!$world instanceof World){
            $this->plugin->getLogger()->info(BedWars::PREFIX . TextFormat::YELLOW . "Failed to load game " . $this->gameName . " because it's world does not exist!");
            return;
        }
        $world->setAutoSave(false);
    }

    /**
     * @param string $message
     */
    public function broadcastMessage(string $message) : void{
        foreach(array_merge($this->spectators, $this->players) as $player){
            $player->sendMessage($message);
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
                if(!$player->isOnline())continue;
                if($player->isAlive() && $player->getWorld()->getFolderName() == $this->worldName && !isset($this->spectators[$player->getName()])){
                    $players[] = $player;
                }
            }

            if(count($players) >= 1){
                if($team->dead == count($team->getPlayers())){
                    continue;
                }
                $teams[] = $team;
            }
        }
        return $teams;
    }

    public function stop() : void{
        foreach(array_merge($this->players, $this->spectators) as $player){
            $this->cachedPlayers[$player->getName()]->load();
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
                    foreach($generator->getFloatingText()->encode($generator->getPosition()->asVector3()->add(0.5, 3.3, 0.5)) as $packet){
                        $player->getNetworkSession()->sendDataPacket($packet);
                    }
                }
            }
        }

        $this->spectators = array();
        $this->players = array();
        $this->startTime = $this->startTimeStatic;
        $this->rebootTime = 15;
        $this->generators = array();
        $this->cachedPlayers = array();
        $this->state = self::STATE_LOBBY;
        $this->starting = false;
        $this->forceStart = false;
        $this->plugin->getServer()->getWorldManager()->unloadWorld($this->plugin->getServer()->getWorldManager()->getWorldByName($this->worldName));
        $this->reload();

        $this->setLobby(new Vector3($this->lobby->x, $this->lobby->y, $this->lobby->z), $this->lobbyName);

    }

    public function start() : void{
         $this->broadcastMessage(TextFormat::GREEN . "Game has started!");
         $this->state = self::STATE_RUNNING;
         foreach($this->players as $player){
            $player->sendTitle("");
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

             $this->trackingPositions[$player->getName()] = $playerTeam->getName();
             $player->setSpawn(Utils::stringToVector(":",  $spawnPos = $this->teamInfo[$playerTeam->getName()]['SpawnPos']));
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
            $position = new Position($vector->x + 0.5, $vector->y, $vector->z + 0.5, $this->plugin->getServer()->getWorldManager()->getWorldByName($this->worldName));
            
            $this->generators[] = new Generator($item, $delay,$position, $spawnText, $spawnBlock, $generator['team'] == "" ? null : $this->teams[$generator['team']]);
        }
    }

    private function initShops() : void{
        foreach($this->teamInfo as $team => $info){
            $shopPos = Utils::stringToVector(":", $info['ShopPos']);
            $rotation = explode(":", $info['ShopPos']);

            $location = Location::fromObject($shopPos, $this->plugin->getServer()->getWorldManager()->getWorldByName($this->worldName));
            $entity = new \pocketmine\entity\Villager($location);
            if(isset($rotation[3])){
                $entity->setRotation(intval($rotation[3]), 0); //todo: round yaw
            }
            $entity->setNameTag(TextFormat::AQUA . "ITEM SHOP\n" . TextFormat::BOLD . TextFormat::YELLOW . "TAP TO USE");
            $entity->setNameTagAlwaysVisible(true);
            $entity->spawnToAll();

            $this->npcs[$entity->getId()] = [$team, 'shop'];

            $upgradePos = Utils::stringToVector(":", $info['UpgradePos']);
            $rotation = explode(":", $info['UpgradePos']);
            $location = Location::fromObject($upgradePos, $this->plugin->getServer()->getWorldManager()->getWorldByName($this->worldName));
            $entity = new \pocketmine\entity\Villager($location);
            if(isset($rotation[3])){
                $entity->setRotation(intval($rotation[3]), 0);
            }
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

         $this->cachedPlayers[$player->getName()] = new PlayerCache($player);
         $player->teleport($this->lobby);
         $this->players[$player->getName()] = $player;

         $this->broadcastMessage(TextFormat::GRAY . $player->getName() . " " . TextFormat::YELLOW . "has joined " . TextFormat::YELLOW . "(" . TextFormat::AQUA .  count($this->players) . TextFormat::YELLOW . "/" . TextFormat::AQUA .  $this->maxPlayers . TextFormat::YELLOW .  ")");
         $player->getInventory()->clearAll();
         $player->getArmorInventory()->clearAll();
         $player->getCraftingGrid()->clearAll();
         $player->getOffHandInventory()->clearAll();
         foreach($this->teams as $team){
            //  $player->getInventory()->addItem($i = new Item(new ItemIdentifier(ItemIds::WOOL, Utils::colorIntoWool($team->getColor()))));
             $item = ItemFactory::getInstance()->get(ItemIds::WOOL, Utils::colorIntoWool($team->getColor()));
             $item->setCustomName($team->getColor() . ucfirst($team->getName()) . "'s " . TextFormat::WHITE . "Team");
             $player->getInventory()->addItem($item);
         }
         $player->getInventory()->setItem(8, ItemFactory::getInstance()->get(ItemIds::COMPASS)->setCustomName(TextFormat::YELLOW . "Leave"));
         $player->setGamemode(GameMode::ADVENTURE());
         $this->checkLobby();

        Scoreboard::new($player, 'bedwars', TextFormat::BOLD . TextFormat::YELLOW . "§6BED§gWARS");

        Scoreboard::setLine($player, 1, "  ");
        Scoreboard::setLine($player, 4, " " . TextFormat::YELLOW ."Map: " . TextFormat::WHITE .  $this->mapName . str_repeat(" ", 3));
        Scoreboard::setLine($player, 5, " " . TextFormat::YELLOW . "Players: " . TextFormat::WHITE . count($this->players) . "/" . $this->maxPlayers . str_repeat(" ", 3));
        Scoreboard::setLine($player, 6, "  ");
        Scoreboard::setLine($player, 7, " " . count($this->players) >= $this->minPlayers ? TextFormat::AQUA . "Starting in: " . TextFormat::GREEN .  $this-> startTime . str_repeat(" ", 3) : TextFormat::GREEN . "Waiting for players..." . str_repeat(" ", 3));
        Scoreboard::setLine($player, 8, "   ");
        Scoreboard::setLine($player, 9, " " . TextFormat::YELLOW . "Mode: " . TextFormat::WHITE . substr(str_repeat($this->playersPerTeam . "v", count($this->teams)), 0, -1) . str_repeat(" ", 3));
        Scoreboard::setLine($player, 10, " " . TextFormat::YELLOW . "Version: " . TextFormat::WHITE . "v2.0" . str_repeat(" ", 3));
        Scoreboard::setLine($player, 9, " ");
        Scoreboard::setLine($player, 10, " " . TextFormat::LIGHT_PURPLE . $this->plugin->serverWebsite);
    }

    /**
     * @param Player $player
     */
    public function trackCompass(Player $player) : void{
        $currentTeam = $this->trackingPositions[$player->getName()];
        $arrayTeam = $this->teams;
        $position = array_search($currentTeam, array_keys($arrayTeam));
        $teams = array_values($this->teams);
        $team = null;

        if(isset($teams[$position+1])){
            $team = $teams[$position+1]->getName();
        }else{
            $team = $teams[0]->getName();
        }

        $this->trackingPositions[$player->getName()] = $team;

        $player->setSpawn(Utils::stringToVector(":",  $spawnPos = $this->teamInfo[$team]['SpawnPos']));
        $player->setSpawn(Utils::stringToVector(":",  $spawnPos = $this->teamInfo[$team]['SpawnPos']));

        foreach($player->getInventory()->getContents() as $slot => $item){
            if($item instanceof Compass){
                $player->getInventory()->removeItem($item);
                $player->getInventory()->setItem($slot, ItemFactory::getInstance()->get(ItemIds::COMPASS)->setCustomName(TextFormat::WHITE . "Tap to switch!"));
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

        $this->broadcastMessage(TextFormat::BOLD . TextFormat::RED . "BED DESTRUCTION §f» " . TextFormat::RESET .  $team->getColor() . $team->getName() . "'s " . TextFormat::GRAY . "bed was destroyed by " . $playerTeam->getColor() . $player->getName());
        foreach($team->getPlayers() as $player){
            $player->sendTitle(TextFormat::RED . "BED DESTROYED!", TextFormat::GRAY . "You will no longer respawn", 10);
        }
    }
 
    public function isSafeArea(Vector3 $pos, $blockId) : bool {
        $safe = false;
        foreach($this->safeAreas as $area){
            $pos1 = Utils::stringToVector(":", $area['pos1']);
            $pos2 = Utils::stringToVector(":", $area['pos2']);
            $ignored = explode(",", $area['ignored']);

            if(((min($pos1->getX(), $pos2->getX()) <= $pos->getX()) && (max($pos1->getX(), $pos2->getX()) >= $pos->getX()) && (min($pos1->getY(), $pos2->getY()) <= $pos->getY()) && (max($pos1->getY(), $pos2->getY()) >= $pos->getY()) && (min($pos1->getZ(), $pos2->getZ()) <= $pos->getZ()) && (max($pos1->getZ(), $pos2->getZ()) >= $pos->getZ()))){
                $safe = true;
                foreach($ignored as $id){
                    $i = explode(":", $id);
                    $block = BlockFactory::getInstance()->get(intval($i[0]), isset($i[1]) ? intval($i[1]) : 0);
                    if($block->getId() == $blockId){
                       $safe = false;
                    }
                }
            }
        }
        return $safe;
    }

    /**
     * @param Player $player
     */
    public function quit(Player $player) : void{
         if(isset($this->players[$player->getName()])){
             $team = $this->plugin->getPlayerTeam($player);
             if($team instanceof Team){
                 $team->remove($player);
             }
             unset($this->players[$player->getName()]);
         }
         if(isset($this->spectators[$player->getName()])){
             unset($this->spectators[$player->getName()]);
         }



         \BedWars\utils\Scoreboard::remove($player);
    }

    private function checkLobby() : void{
        if(!$this->starting && count($this->players) >= $this->minPlayers && !$this->isForcedStart()) {
            $this->starting = true;
            $this->broadcastMessage(TextFormat::GREEN . TextFormat::BOLD . "§l§5» §r§aCountdown started!");
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
            $this->spectators[$player->getName()] = $player;
            unset($this->players[$player->getName()]);
            $player->setGamemode(GameMode::SPECTATOR());
            $player->sendTitle(TextFormat::BOLD . TextFormat::RED . "DEFEAT", TextFormat::GRAY . "You are now spectating!");
            $player->getInventory()->clearAll();
            $player->getArmorInventory()->clearAll();
            $player->setFlying(true);
        }else{
            $player->setGamemode(GameMode::SPECTATOR());
            $this->deadQueue[$player->getName()] = 5;
         }

         $playerGame = $this->plugin->getPlayerTeam($player);
        $cause = $player->getLastDamageCause();
        if($cause == null || $playerGame == null) return; 
        switch($cause->getCause()){
            case EntityDamageEvent::CAUSE_ENTITY_ATTACK;
            if($cause instanceof EntityDamageByEntityEvent){
            $damager = $cause->getDamager();
            if($damager instanceof Player && $this->plugin->getPlayerTeam($damager) !== null){
                $this->broadcastMessage($this->plugin->getPlayerTeam($player)->getColor() . $player->getName() . " " . TextFormat::GRAY . "was killed by " . $this->plugin->getPlayerTeam($damager)->getColor() . $damager->getName());
               }
            }
            break;
            case EntityDamageEvent::CAUSE_PROJECTILE;
            if($cause instanceof EntityDamageByChildEntityEvent){
                $damager = $cause->getDamager();
                if($damager instanceof Player && $this->plugin->getPlayerTeam($damager) !== null){
                $this->broadcastMessage($this->plugin->getPlayerTeam($player)->getColor() . $player->getName() . " " . TextFormat::GRAY . "was shot by " . $this->plugin->getPlayerTeam($damager)->getColor() . $damager->getName());
                }
            }
            break;
            case EntityDamageEvent::CAUSE_FIRE;
            $this->broadcastMessage($playerTeam->getColor() . $player->getName() . " " . TextFormat::GRAY . "went up in flameS");
            break;
            case EntityDamageEvent::CAUSE_VOID;
            $spawnPos = $this->teamInfo[$playerTeam->getName()]['SpawnPos'];
            $spawn = Utils::stringToVector(":", $spawnPos);
            $player->teleport(new Vector3($player->getPosition()->getX(), $spawn->getY() + 10, $player->getPosition()->getZ()));
            break;
            case EntityDamageEvent::CAUSE_ENTITY_EXPLOSION;
            $this->broadcastMessage($playerTeam->getColor() . $player->getName() . " " . TextFormat::GRAY . "was killed in an explosion");
            break;
            case EntityDamageEvent::CAUSE_FALL;
            $this->broadcastMessage($playerTeam->getColor() . $player->getName() . " " . TextFormat::GRAY . "fell from high place");
            break;
            case EntityDamageEvent::CAUSE_FIRE;
            $this->broadcastMessage($playerTeam->getColor() . $player->getName() . " " . TextFormat::GRAY . "went up in flames");
            break;
            case EntityDamageEvent::CAUSE_SUFFOCATION;
            $this->broadcastMessage($playerTeam->getColor() . $player->getName() . " " . TextFormat::GRAY . "suffocated in a wall");
            break;
        }

    }



    /**
     * @param Player $player
     */
    public function respawnPlayer(Player $player) : void{
        $team = $this->plugin->getPlayerTeam($player);
        if($team == null)return;

        $spawnPos = $this->teamInfo[$team->getName()]['SpawnPos'];

        $player->setGamemode(GameMode::SURVIVAL());
        $player->getHungerManager()->setFood(20);
        $player->setHealth($player->getMaxHealth());
        $player->getInventory()->clearAll();

        $player->teleport($this->plugin->getServer()->getWorldManager()->getWorldByName($this->worldName)->getSafeSpawn());
        $player->teleport(Utils::stringToVector(":", $spawnPos));


        //inventory
        $helmet = ItemFactory::getInstance()->get(ItemIds::LEATHER_CAP);
        $chestplate = ItemFactory::getInstance()->get(ItemIds::LEATHER_CHESTPLATE);
        $leggings = ItemFactory::getInstance()->get(ItemIds::LEATHER_LEGGINGS);
        $boots = ItemFactory::getInstance()->get(ItemIds::LEATHER_BOOTS);

        $hasArmorUpdated = true;

        switch($team->getArmor($player)){
            case "chain";
            $boots = ItemFactory::getInstance()->get(ItemIds::CHAIN_BOOTS, 0, 1);
            $leggings = ItemFactory::getInstance()->get(ItemIds::CHAIN_LEGGINGS, 0, 1);
            break;
            case "iron";
            $leggings = ItemFactory::getInstance()->get(ItemIds::IRON_LEGGINGS);
            $boots = ItemFactory::getInstance()->get(ItemIds::IRON_BOOTS);
            break;
            case "diamond";
            $leggings = ItemFactory::getInstance()->get(ItemIds::DIAMOND_LEGGINGS);
            $boots = ItemFactory::getInstance()->get(ItemIds::DIAMOND_BOOTS);
            break;
            default;
            $hasArmorUpdated = false;
            break;
        }


        foreach(array_merge([$helmet, $chestplate], !$hasArmorUpdated ? [$leggings, $boots] : []) as $armor){
            if($armor instanceof Armor){
                  $armor->setCustomColor(Utils::colorIntoObject($team->getColor()));
            }
        }

        $armorUpgrade = $team->getUpgrade('armorProtection');
        if($armorUpgrade > 0){
            foreach([$helmet, $chestplate, $leggings, $boots] as $armor){
                $armor->addEnchantment(new EnchantmentInstance(VanillaEnchantments::PROTECTION(), $armorUpgrade));
            }
        }

        $player->getArmorInventory()->setHelmet($helmet);
        $player->getArmorInventory()->setChestplate($chestplate);
        $player->getArmorInventory()->setLeggings($leggings);
        $player->getArmorInventory()->setBoots($boots);

        $sword = ItemFactory::getInstance()->get(ItemIds::WOODEN_SWORD);
        if($sword instanceof Durable){
            $sword->setUnbreakable(true);
        }

        $swordUpgrade = $team->getUpgrade('sharpenedSwords');
        if($swordUpgrade > 0){
            $sword->addEnchantment(new EnchantmentInstance(VanillaEnchantments::SHARPNESS(), $swordUpgrade));
        }

        $player->getInventory()->setItem(0, $sword);
        $player->getInventory()->setItem(8, ItemFactory::getInstance()->get(ItemIds::COMPASS)->setCustomName(TextFormat::WHITE . "Tap to switch!"));

    }


    public function tick() : void{
         switch($this->state) {
             case self::STATE_LOBBY;
                 if ($this->starting || $this->isForcedStart()) {
                     if(count($this->players) < $this->minPlayers && !$this->isForcedStart()) {
                         $this->starting = false;
                         $this->broadcastMessage(TextFormat::YELLOW . "Countdown stopped");
                         $this->startTime = $this->startTimeStatic;
                     }

                     $this->startTime--;

                     foreach ($this->players as $player) {
                         $player->sendTip(TextFormat::YELLOW . "Starting in: " . TextFormat::AQUA . gmdate("i:s", $this->startTime));
                     }

                     switch ($this->startTime) {
                         case 30;
                             $this->broadcastMessage(TextFormat::AQUA . "§l§5» §r§aStarting in: " . TextFormat::GREEN . "30");
                             break;
                         case 15;
                             $this->broadcastMessageTextFormat::AQUA . "§l§5» §r§aStarting in: " . TextFormat::GREEN . "15");
                             break;
                         case 5;
                         case 4;
                         case 3;
                         case 2;
                         case 1;
                             foreach ($this->players as $player) {
                                 $player->sendTitle(TextFormat::GREEN . $this->startTime);
                             }
                             break;
                     }

                     if ($this->startTime == 0) {
                         $this->start();
                     }
                 } else {
                     foreach ($this->players as $player) {
                         $player->sendTip(TextFormat::YELLOW . "Waiting for players... (" . TextFormat::AQUA . ($this->minPlayers - count($this->players)) . TextFormat::YELLOW . ")");
                     }
                 }

                 foreach (array_merge($this->players, $this->spectators) as $player) {
                     \BedWars\utils\Scoreboard::new($player, 'bedwars', TextFormat::BOLD . TextFormat::GOLD . "BED§gWARS");
                     
                     \BedWars\utils\Scoreboard::setLine($player, 1, "     ");
                     \BedWars\utils\Scoreboard::setLine($player, 2, "  ");
                     \BedWars\utils\Scoreboard::setLine($player, 3, " " . TextFormat::YELLOW . "Map: " . TextFormat::WHITE . $this->mapName . str_repeat(" ", 3));
                     \BedWars\utils\Scoreboard::setLine($player, 4, " " . TextFormat::YELLOW . "Players: " . TextFormat::WHITE . count($this->players) . "/" . $this->maxPlayers . str_repeat(" ", 3));
                     \BedWars\utils\Scoreboard::setLine($player, 5, "  ");
                     \BedWars\utils\Scoreboard::setLine($player, 6, " " . ($this->starting || $this->isForcedStart() ? TextFormat::AQUA . "Starting in: " . TextFormat::GREEN . $this->startTime . str_repeat(" ", 3) : TextFormat::GREEN . "Waiting for players..." . str_repeat(" ", 3)));
                     \BedWars\utils\Scoreboard::setLine($player, 7, "   ");
                     \BedWars\utils\Scoreboard::setLine($player, 9, " " . TextFormat::YELLOW . "Mode: " . TextFormat::WHITE . substr(str_repeat($this->playersPerTeam . "v", count($this->teams)), 0, -1) . str_repeat(" ", 3));
                     \BedWars\utils\Scoreboard::setLine($player, 10, " " . TextFormat::YELLOW . "Version: " . TextFormat::WHITE . "v2.0" . str_repeat(" ", 3));
                     \BedWars\utils\Scoreboard::setLine($player, 11, " ");
                     \BedWars\utils\Scoreboard::setLine($player, 12, " " . TextFormat::LIGHT_PURPLE . $this->plugin->serverWebsite);
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
                         $player->getInventory()->clearAll();
                         $player->sendTitle(TextFormat::RED . "You died!", TextFormat::YELLOW . "You will respawn in: " . TextFormat::RED . $this->deadQueue[$player->getName()] . " " . TextFormat::YELLOW . "seconds!");
                         $player->sendMessage(TextFormat::YELLOW . "You will respawn in: " . TextFormat::RED . $this->deadQueue[$player->getName()] . " " . TextFormat::YELLOW . "seconds!");

                         
                         if ($this->deadQueue[$player->getName()] == 0) {
                             unset($this->deadQueue[$player->getName()]);

                             $this->respawnPlayer($player);
                             $player->sendTitle(TextFormat::GREEN . "RESPAWNED!");
                             $player->sendMessage(TextFormat::YELLOW . "You have respawned!");
                             return;
                         }
                         $this->deadQueue[$player->getName()] -= 1;
                     }
                 }

                 foreach (array_merge($this->players, $this->spectators) as $player) {

                     \BedWars\utils\Scoreboard::remove($player);
                     \BedWars\utils\Scoreboard::new($player, 'bedwars', TextFormat::BOLD . TextFormat::YELLOW . "§6BED§gWARS");

                     \BedWars\utils\Scoreboard::setLine($player, 1, "    ");
                     \BedWars\utils\Scoreboard::setLine($player, 1, "     ");
                     \BedWars\utils\Scoreboard::setLine($player, 2, " " . TextFormat::AQUA . ucfirst($this->tierUpdateGen) . " Upgrade: " . TextFormat::GREEN . gmdate("i:s", $this->tierUpdate));
                     \BedWars\utils\Scoreboard::setLine($player, 3, "  ");

                     $currentLine = 4;
                     $playerTeam = $this->plugin->getPlayerTeam($player);
                     foreach ($this->teams as $team) {
                         $status = "";
                         if ($team->hasBed()) {
                             $status = TextFormat::GREEN . TextFormat::BOLD . "✔";
                         } elseif(count($team->getPlayers()) > $team->dead) {
                             $status = count($team->getPlayers()) === 0 ? TextFormat::DARK_RED . TextFormat::BOLD. "✖" : TextFormat::GRAY . TextFormat::BOLD . "[" . (count($team->getPlayers()) - $team->dead) . "]";
                         }elseif(count($team->getPlayers()) <= $team->dead){
                             $status = TextFormat::BLACK . TextFormat::BOLD . "✖";
                         }
                         $isPlayerTeam = $team->getName() == $playerTeam->getName() ? TextFormat::YELLOW . TextFormat::BOLD . "	 §7(YOU)" : "";
                         $stringFormat = TextFormat::BOLD . $team->getColor() . ucfirst($team->getName()[0]) . " " . TextFormat::WHITE . TextFormat::RESET . ucfirst($team->getName()) . ": " . $status . " " . $isPlayerTeam;
                         \BedWars\utils\Scoreboard::setLine($player, " " . $currentLine, $stringFormat);
                         $currentLine++;
                     }

                     $allTeams = BedWars::TEAMS;

                     foreach($allTeams as $name => $color){
                        if(!isset($this->teams[$name])){
                            \BedWars\utils\Scoreboard::setLine($player, "   " . $currentLine, TextFormat::BOLD . TextFormat::RESET . $color . ucfirst($name)[0] . " " . TextFormat::WHITE . ucfirst($name) . " " . TextFormat::DARK_RED . "[-]");
                            $currentLine++;
                        }
                     }


                     \BedWars\utils\Scoreboard::setLine($player, " " . $currentLine, "   ");
                     $currentLine++;
                     \BedWars\utils\Scoreboard::setLine($player, " " . $currentLine, " " . TextFormat::LIGHT_PURPLE . $this->plugin->serverWebsite);
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
                     if($generator->itemID == ItemIds::DIAMOND && $this->tierUpdateGen == "diamond") {
                          $generator->updateTier();
                     }elseif($generator->itemID == ItemIds::EMERALD && $this->tierUpdateGen == "emerald"){
                          $generator->updateTier();
                     }
                 }
                 $this->tierUpdateGen = $this->tierUpdateGen == 'diamond' ? 'emerald' : 'diamond';
             }
             break;
             case Game::STATE_REBOOT;
             $team = $this->winnerTeam;
             foreach($this->winnerTeam->getPlayers() as $player){
                if($this->rebootTime == 15){
                         $player->sendTitle(TextFormat::BOLD . TextFormat::GOLD . "VICTORY!", "", 5, 1000);
                }
             }
             foreach(array_merge($this->players, $this->spectators) as $player){
                     Scoreboard::remove($player);
                     Scoreboard::new($player, 'bedwars', TextFormat::BOLD . TextFormat::GOLD . "BED§gWARS");
                 
                     Scoreboard::setLine($player, 1, "     ");
                     Scoreboard::setLine($player, 2, "  ");
                     Scoreboard::setLine($player, 3, "§eWinner: " . TextFormat::GREEN . $this->winnerTeam->getName());
                     Scoreboard::setLine($player, 4, "     ");
                     Scoreboard::setLine($player, 5, "§3Thanks for playing!");
                     Scoreboard::setLine($player, 6, "     ");
                     Scoreboard::setLine($player, 7, "§bRestarting in: " . TextFormat::GREEN . $this->rebootTime);
                     Scoreboard::setLine($player, 8, "");
                     Scoreboard::setLine($player, 9, " " . TextFormat::LIGHT_PURPLE . $this->plugin->serverWebsite);
              }
             

             --$this->rebootTime;
             if($this->rebootTime == 0){
                 $this->stop();
             }
             break;
         }
    }
}
