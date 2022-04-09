<?php


namespace BedWars;


use pocketmine\block\utils\SignText;
use pocketmine\item\Item;
use pocketmine\item\ItemIds;
use pocketmine\level\Level;
use pocketmine\player\Player;
use pocketmine\scheduler\Task;
use pocketmine\tile\Sign;
use pocketmine\utils\TextFormat;
use pocketmine\math\Vector3;
use pocketmine\world\World;
use pocketmine\plugin\Plugin;
use pocketmine\plugin\PluginBase;
use pocketmine\entity\EntityFactory;
use BedWars\command\DefaultCommand;
use BedWars\game\entity\FakeItemEntity;
use BedWars\game\entity\BridgeEgg;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\entity\EntityDataHelper;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIdentifier;
use BedWars\game\Game;
use BedWars\game\GameListener;
use BedWars\game\Team;


class BedWars extends PluginBase
{

    const PREFIX = TextFormat::BOLD . TextFormat::DARK_RED . "BedWars " . TextFormat::RESET;

    /** @var Game[] $games */
    public $games = array();

    /** @var array $signs */
    public $signs = array();

    /** @var array $bedSetup */
    public $bedSetup = array();
    
    /** @var array $saSetup */
    public $saSetup = array();

    /** @var string $serverWebsite */
    public $serverWebsite;

    /** @var int $staticStartTime */
    public $staticStartTime;

    /** @var int $staticRestartTime */
    public $staticRestartTime = 15;
    
    /** @var bool $debug */
    private $debug = true;

    private static $ins;

    const TEAMS = [
        'blue' => TextFormat::DARK_BLUE,
        'red' => TextFormat::RED,
        'yellow' => TextFormat::YELLOW,
        "green" => TextFormat::GREEN,
        "aqua" => TextFormat::AQUA,
        "gold" => TextFormat::GOLD,
        "white" => TextFormat::WHITE
    ];

    const GENERATOR_PRIORITIES = [
        'gold' => ['item' => ItemIds::GOLD_INGOT, 'spawnText' => false, 'spawnBlock' => false, 'refreshRate' => 10],
        'iron' => ['item' => ItemIds::IRON_INGOT, 'spawnText' => false, 'spawnBlock' => false, 'refreshRate' => 3],
        'diamond' => ['item' => ItemIds::DIAMOND, 'spawnText' => true, 'spawnBlock' => true, 'refreshRate' => 30],
        'emerald' => ['item' => ItemIds::EMERALD, 'spawnText' => true, 'spawnBlock' => true, 'refreshRate' => 60]
    ];

    public function onEnable() : void
    {

        $this->saveDefaultConfig();
        $this->serverWebsite = $this->getConfig()->get('website');

        @mkdir($this->getDataFolder() . "games");
        @mkdir($this->getDataFolder() . "skins");
        $this->saveResource("skins/264.png");
        $this->saveResource("skins/388.png");

        $this->getScheduler()->scheduleRepeatingTask(
            new SignUpdater($this), 20
        );
        $this->getServer()->getPluginManager()->registerEvents(new GameListener($this), $this);
        $this->getServer()->getCommandMap()->register("bedwars", new DefaultCommand($this));
        EntityFactory::getInstance()->register(FakeItemEntity::class, function(World $world, CompoundTag $nbt) : FakeItemEntity{
            return new FakeItemEntity(EntityDataHelper::parseLocation($nbt, $world), null, $nbt);
        }, ["FakeItemEntity"]);
        EntityFactory::getInstance()->register(BridgeEgg::class, function(World $world, CompoundTag $nbt) : BridgeEgg{
            return new BridgeEgg(EntityDataHelper::parseLocation($nbt, $world), null, $nbt);
        }, ["Egg"]);

        //register items
        ItemFactory::getInstance()->register(new \BedWars\game\item\BridgeEgg(new ItemIdentifier(ItemIds::EGG, 0), "Bridge Egg"), true);
        $this->loadAllGames();
        self::$ins = $this;

    }

    public static function getInstance() : BedWars{
        return self::$ins;
    }

    public function debug(string $message){
        if($this->debug){
            $this->getLogger()->info($message);
        }
    }

    private function loadAllGames() : void{
        foreach(glob($this->getDataFolder() . "games/*.json") as $location){
            $fileContents = file_get_contents($location);
            $jsonData = json_decode($fileContents, true);

            if(!$this->validateGame($jsonData)){
                $this->debug("Could not load game " . $jsonData['id'] . " due to uncompleted setup");
                continue;
            }

            if(count($jsonData['signs']) > 0){
                $this->signs[$jsonData['id']] = $jsonData['signs'];
            }
            $this->getLogger()->info("Game loaded " . $jsonData['id']);
            $this->loadGame($jsonData['id']);
        }
    }

    /**
     * @param string $id
     * @return array|null
     */
    public function getGameData(string $id) : ?array{
        if(!$this->gameExists($id))return null;

        $location = $this->gamePath($id);

        $file = file_get_contents($location);
        return json_decode($file, true);
    }

    /**
     * @param string $id
     * @param int $minPlayers
     * @param int $playersPerTeam
     * @param int $startTime
     */
    public function createGame(string $id, $minPlayers, $playersPerTeam, $startTime, $mapName, $worldName) : void{
        $dataStructure = [
            'id' => $id,
            'minPlayers' => intval($minPlayers),
            'playersPerTeam' => intval($playersPerTeam),
            'startTime' => intval($startTime),
            'signs' => [],
            'teamInfo' => [],
            'generatorInfo' => [],
            'mapName' => $mapName,
            'world' => $worldName,
        ];
        file_put_contents($this->gamePath($id), json_encode($dataStructure));
    }

    public function updateGame(string $id, $parameter, $value, bool $isMulti = false) : bool{
        if(!$this->gameExists($id)){
            return false;
        }

        $game = $this->getGameData($id);
        if(!$isMulti){
           $game[$parameter] = $value;
        }else{
            $game[$parameter][] = $value;
        }
        try {
          file_put_contents($this->gamePath($id), json_encode($game));
        }catch(\Exception $e){
            return false;
        }
        return true;
    }

    public function updateGameTeam(string $id, string $team, string $key, $value) : bool {
        if(!$this->gameExists($id)){
            return false;
        }

        $game = $this->getGameData($id);
        $game['teamInfo'][$team][$key] = $value;
        try {
          file_put_contents($this->gamePath($id), json_encode($game));
        }catch(\Exception $e){
            return false;
        }
        return true;
    }

    /**
     * @param string $id
     */
    public function deleteGame(string $id) : void{
        unlink($this->gamePath($id));
    }

    public function isGameLoaded(string $id) : bool{
        return isset($this->games[$id]);
    }

    public function loadGame(string $id) : void{
        if($this->isGameLoaded($id)){
            $this->unloadGame($id);
        }
        $data = $this->getGameData($id);
        $this->games[$id] = new Game($this, $data);
        if(count($data['signs']) > 0){
            $this->signs[$data['id']] = $data['signs'];
        }
    }

    public function unloadGame(string $id) : void{
        if(!$this->gameExists($id)){
            return;
        }
        unset($this->games[$id]);
    }

    public function createSign(string $id, int $x, int $y, int $z, string $worldName) :void {
        $position = $x . ":" . $y . ":" . $z . ":" . $worldName;
        $this->signs[$id][] = $position;
        $positionData = [
                "signs" => $this->signs[$id]
            ];
        file_put_contents($this->gamePath($id), json_encode(array_merge($this->getGameData($id), $positionData)));
    }

    /**
     * @param string $id
     * @param int $x
     * @param int $y
     * @param int $z
     * @param string $worldName
     */
    public function setLobby(string $id, int $x, int $y, int $z, string $worldName) : void{
        $this->updateGame($id, 'lobby', implode(":", [$x, $y + 0.5, $z, $worldName]));
    }

    public function addGenerator(string $id, $team, string $type, int $x, int $y, int $z) : void{
        $this->updateGame($id, 'generatorInfo', ['type' => $type, 'position' => implode(":", [$x, $y, $z]), 'team' => $team], true);
    }

    /**
     * @param string $id
     * @param string $team
     * @param string $keyPos
     * @param int $x
     * @param int $y
     * @param int $z
     */
    public function setTeamPosition(string $id, string $team, string $keyPos, int $x, int $y, int $z, $yaw = null) : void{
        $this->updateGameTeam($id, $team, $keyPos . "Pos", implode(":", [$x, $y,$z, $yaw]));
    }

    /**
     * @param string $id
     * @return array
     */
    public function getTeams(string $id) : array{
        $json = $this->getGameData($id);
        $teams = array();
        foreach($json['teamInfo'] as $name => $data){
            $teams[] = $name;
        }
        return $teams;
    }

    /**
     * @param string $id
     * @param string $team
     */
    public function addTeam(string $id, string $team){
        $inserts = ["SpawnPos", "ShopPos", "UpgradePos", "Bed1Pos", "Bed2Pos"];
        array_map(function($i) use ($id, $team) {
            $this->updateGameTeam($id, $team, $i, "");
        }, $inserts);
    }

    /**
     * @param string $id
     * @param string $team
     * @return bool
     */
    public function teamExists(string $id, string $team) : bool{
        $file = file_get_contents($this->gamePath($id));
        if($file == null){
            return false;
        }
        $json = (array)json_decode($file, true);
        return isset($json['teamInfo'][strtolower($team)]);
    }

    /**
     * @param string $gameID
     * @return bool
     */
    public function gameExists(string $gameID) : bool {
        if(!is_file($this->gamePath($gameID))){
            return false;
        }
        return true;
    }


    /**
     * @param string $id
     * @return string
     */
    public function gamePath(string $id) : string{
        return $this->getDataFolder() . "games/" . $id . ".json";
    }

    /**
     * @param array $gameData
     * @return bool
     */
    public function validateGame(array $gameData) : bool{
        $requiredParams = [
            'id',
            'minPlayers',
            'playersPerTeam',
            'world',
            'teamInfo',
            'generatorInfo',
            'lobby',
            'mapName'
        ];

        $requiredTeamParams = [
            "SpawnPos",
            "ShopPos",
            "UpgradePos",
            "Bed1Pos", 
            "Bed2Pos"
        ];

        $error = 0;
        foreach($requiredParams as $param){
            if(!in_array($param, array_keys($gameData))){
                $error ++;
            }

            if($param == 'teamInfo'){
                if(count($gameData['teamInfo']) < 2){
                    $error++;
                }else{
                    foreach($gameData['teamInfo'] as $team => $info) {
                        foreach($requiredTeamParams as $param){
                            if(!isset($info[$param]) || $info[$param] == ""){
                                $error++;
                                break;
                            }
                        }
                    }
                }
            }
        }

        return !$error > 0;
    }

    /**
     * @param Player $player
     * @param bool $isSpectator
     * @return Game|null
     */
    public function getPlayerGame(Player $player, bool $isSpectator = false) : ?Game{
        $isSpectator = false;
        foreach($this->games as $game){
            if(isset($game->players[$player->getName()]))return $game;
            if(isset($game->spectators[$player->getName()]))return $game;
        }
        return null;
    }

    /**
     * @param Player $player
     * @return Team|null
     */
    public function getPlayerTeam(Player $player) : ?Team{
        $game = $this->getPlayerGame($player);
        if($game == null)return null;

        foreach($game->teams as $team){
            if(in_array($player->getName(), array_keys($team->getPlayers()))){
                return $team;
            }
        }
        return null;
    }
}