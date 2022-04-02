<?php


namespace BedWars\command;


use BedWars\BedWars;
use BedWars\game\Game;
use BedWars\utils\Utils;
use pocketmine\block\Block;
use pocketmine\block\TNT;
use pocketmine\command\CommandSender;
use pocketmine\command\PluginCommand;
use pocketmine\item\Bed;
use pocketmine\block\BlockFactory;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\math\Vector3;
use pocketmine\world\World;

//form api
use jojoe77777\FormAPI\CustomForm;
use jojoe77777\FormAPI\SimpleForm;
use jojoe77777\FormAPI\Form;


class DefaultCommand extends \pocketmine\command\Command
{

    /**
     * DefaultCommand constructor.
     * @param BedWars $owner
     */
    public function __construct(BedWars $owner)
    {
        parent::__construct("bedwars", "BedWars", null, ["bw"]);
        parent::setDescription("BedWars command");
    }

    private function getPlugin() : BedWars{
        return BedWars::getInstance();
    }

    /**
     * @param CommandSender $sender
     * @param string $commandLabel
     * @param array $args
     * @return bool|mixed|void
     */
    public function execute(CommandSender $sender, string $commandLabel, array $args){
       if(empty($args)){
           $this->sendHelp($sender);
           return;
       }
   
       switch($args[0]){
          case 'create';
          if(count($args) < 6){
            $sender->sendMessage(TextFormat::RED . ":create <game_id> <min_players> <players_per_team> <start_time> <map_name> <world_name>");
            return;
            }
            $game_id = $args[1];
            $min_players = $args[2];
            $players_per_team = $args[3];
            $start_time = $args[4];
            $map_name = $args[5];
            $world_name = $args[6];

            if($this->getPlugin()->gameExists($game_id)){
                $sender->sendMessage(TextFormat::RED . $game_id . " - game already exists!");
                return;
            }

            if(!is_int(intval($min_players))){
                $sender->sendMessage(TextFormat::RED . "min_players - must be a number!");
                return;                
            }

            if(!is_int(intval($players_per_team))){
                $sender->sendMessage(TextFormat::RED . "players_per_team - must be a number!");
                return;   
            }

            if(!is_int(intval($start_time))){
                $sender->sendMessage(TextFormat::RED . "start_time - must be a number!");
                return;   
            }

            if(strlen($map_name) < 1){
                $sender->sendMessage(TextFormat::RED . "map_name - too short!");
            }

            $world = $this->getPlugin()->getServer()->getWorldManager()->getWorldByName($world_name);
            if(!$world instanceof World){
                $sender->sendMessage("World " . $world_name . " does not exist");
                return;
            }
            $sender->sendMessage(TextFormat::GREEN . "Game created");
            $this->getPlugin()->createGame($game_id, $min_players, $players_per_team, $start_time, $map_name, $world_name);
          break;
          case 'delete';
          if(count($args) < 1){
            $sender->sendMessage(":delete <game_id>");
            return;
          }
          $game_id = $args[1];
          if($this->getPlugin()->gameExists($game_id)){
            $this->getPlugin()->deleteGame($game_id);
            $sender->sendMessage(TextFormat::GREEN . "Game deleted");
            return;
          }

          $sender->sendMessage($game_id . " - game not found");
          break;
          case 'setlobby';
          if(!$sender instanceof Player){
            $sender->sendMessage("in-game command");
            return;
          }
          if(count($args) < 2){
            $sender->sendMessage(":setlobby <game_id>");
            return;
          }

          $game_id = $args[1];
          if($this->getPlugin()->gameExists($game_id)){
            $pos = $sender->getPosition();
            $this->getPlugin()->setLobby($game_id, $pos->getX(), $pos->getY(), $pos->getZ(), $pos->getWorld()->getFolderName());
            $sender->sendMessage(TextFormat::GREEN . "Lobby set");
          }
          break;
          case 'setposition';
          if(!$sender instanceof Player){
            $sender->sendMessage("in-game command");
            return;
          }

          if(count($args) < 4){
            $sender->sendMessage(":setposition <game_id> <team> <1|2|3>");
            $sender->sendMessage("1 - Shop Classic, 2 - Shop Upgrade, 3 - Team Spawn");
            return;
          }

          $game_id = $args[1];
          if(!$this->getPlugin()->gameExists($game_id)){
             $sender->sendMessage($game_id . " - Invalid game id");
            return;
          }
          $team = $args[2];

          if(!$this->getPlugin()->teamExists($game_id, $team)){
            $sender->sendMessage("Invalid team - " . $team);
            return;
          }

          $positionType = array(1 => 'Shop', 2 => 'Upgrade', 3 => 'Spawn')[intval($args[3])];
          if($positionType == null){
              $sender->sendMessage("Invalid position");
              return;
          }
          $pos = $sender->getPosition();
          $this->getPlugin()->setTeamPosition($game_id, $team, $positionType, $pos->getX(), $pos->getY(), $pos->getZ(), $sender->getLocation()->getYaw());
          $sender->sendMessage(TextFormat::GREEN . "Position set");
          break;
          case 'setbed';
          if(!$sender instanceof Player){
            $sender->sendMessage("in-game command");
            return;
          }

          if(count($args) < 2){
            $sender->sendMessage(":setbed <game_id> <team>");
            return;
          }

          $game_id = $args[1];
          if(!$this->getPlugin()->gameExists($game_id)){
            $sender->sendMessage($game_id . " - Invalid game id");
            return;
          }

          $team = strtolower($args[2]);
          if(!$this->getPlugin()->teamExists($game_id, $team)){
            $sender->sendMessage("Invalid team - " . $team);
            return;
          }
          $this->getPlugin()->bedSetup[$sender->getName()] = ['game' => $game_id, 'team' => $team , 'step' => 1];
          $sender->sendMessage(TextFormat::GREEN . "Please touch the 1st part of the bed");
          break;
          case 'addteam';
          if(count($args) < 2){
            $sender->sendMessage(TextFormat::RED . ":addteam <game_id> <team>");
            return;
          }
          $game_id = $args[1];
          if(!$this->getPlugin()->gameExists($game_id)){
            $sender->sendMessage($game_id . " - Invalid game id");
            return;
          }
          $team = strtolower($args[2]);
          if(!isset(BedWars::TEAMS[$team])){
            $sender->sendMessage($team . " - Invalid team");
            $sender->sendMessage("Available: " . implode(" ", array_keys(BedWars::TEAMS)));
            return;
          }
          $this->getPlugin()->addTeam($game_id, $team);
          break;
          case 'addgenerator';
          if(!$sender instanceof Player){
            $sender->sendMessage("in-game command");
            return;
          }
          if(count($args) < 3){
            $sender->sendMessage(":addgenerator <game_id> <generator> (team)");
            return;
          }

          $game_id = $args[1];
          if(!$this->getPlugin()->gameExists($game_id)){
            $sender->sendMessage($game_id . " - Invalid game id");
            return;
          }

          $generatorTypes = array(1 => 'iron', 2 => 'gold', 3 => 'diamond', 4 => 'emerald');
          if(!isset($generatorTypes[intval($args[2])])){
            $sender->sendMessage($args[2] . " - Invalid generator type");
            $sender->sendMessage("Available: 1 - iron, 2 - gold, 3 - diamond, 4 - emerald");
            return;
          }
          $generatorType = $generatorTypes[intval($args[2])];
          $pos = $sender->getPosition();
          $team = isset($args[3]) ? $args[3] : "";
          if(!$this->getPlugin()->teamExists($game_id, $team) && $team !== ""){
            $sender->sendMessage("Invalid team - " . $team);
            return;
          }
          $this->getPlugin()->addGenerator($game_id, $team, $generatorType, $pos->getX(), $pos->getY(), $pos->getZ(), $team);
          $sender->sendMessage(TextFormat::GREEN . "Added");
          //NON-SETUP
          break;
          case 'addsafearea';
          if(!$sender instanceof Player){
            $sender->sendMessage("in-game command");
            return;
          }

          if(count($args) < 2){
            $sender->sendMessage(":addsafearea <game_id> <ignored_block_ids>");
            return;
          }

          $game_id = $args[1];
          if(!$this->getPlugin()->gameExists($game_id)){
            $sender->sendMessage($game_id . " - Invalid game id");
            return;
          }

          $ignored = array();
          str_replace(" ", "", $args[2]);
          if(isset($args[2])){
            if(!strpos($args[2], ',')){
                if(is_numeric($args[2])){
                   $ignored[] = intval($args[2]);
                }else{
                    if(!strpos($args[2], ':')){
                        err:
                        $sender->sendMessage("Invalid format of ignored blocks!");
                        $sender->sendMessage("Example: addsafearea <game_id> 365:2,13,1:0");
                        return;
                    }
                    $e = explode(":", $args[2]);
                    $ignored[] = $e[0] . ":" . $e[1];
                }
            }else{
                foreach(explode(",", $args[2]) as $id) {
                    if(is_numeric($id)){
                         $ignored[] = intval($id);
                    }else{
                        if(!strpos($args[2], ':')){
                        goto err;
                        return;
                        }
                        $e = explode(":", $id);
                        if(!isset($e[0]) || !isset($e[1])){
                            goto err;
                        }
                        $ignored[] = $e[0] . ":" . $e[1];
                    }
                }
            }
          }

          foreach($ignored as $blockID){
            if(strpos($blockID, ':')){
                $idMeta = explode(":", $blockID);
                try{
                   $block = BlockFactory::getInstance()->get(intval($idMeta[0], intval($idMeta[1])));
                }catch(\InvalidArgumentException $e){
                   goto invalid;
                }
                
                if(!$block instanceof Block){
                    goto invalid;
                }
            }else{
                try{
                  $block = BlockFactory::getInstance()->get($blockID, 0);
                }catch(\InvalidArgumentException $e){
                  goto invalid;
                }
                
                if(!$block instanceof Block){
                    invalid:
                    $sender->sendMessage($blockID . " is not a valid block id");
                    return;
                }
            }
          }
          $this->getPlugin()->saSetup[$sender->getName()] = ['step' => 1, 'pos1' => null, 'pos2' => null, 'ignoredIds' => implode(",", $ignored), 'game_id' => $game_id];
          break;
          case 'load';
          if(count($args) < 1){
            $sender->sendMessage(":load <game_id>");
            return;
          }

          $gameData = $this->getPlugin()->getGameData($args[1]);
          if($gameData == null){
            return;
          }

          if(!$this->getPlugin()->validateGame($gameData)){
            $sender->sendMessage("Setup not finished for game - " . $args[1]);
            return;
          }

          $this->getPlugin()->loadGame($args[1]);
          $sender->sendMessage(TextFormat::GREEN . "Done");
          break;
          case 'list';
          if(empty($this->getPlugin()->games)){
            $sender->sendMessage(TextFormat::RED . "There are no games loaded");
            return;
          }

          $status = array(0 => TextFormat::GREEN . 'LOBBY', 1 => TextFormat::RED . 'IN-GAME', 2 => TextFormat::DARK_RED . 'RESTARTING');

          foreach($this->getPlugin()->games as $game){
              $sender->sendMessage(TextFormat::GREEN . "Game id - " . $game->getId());
              $sender->sendMessage(TextFormat::YELLOW . "Status: " . $status[$game->getState()]);
              $sender->sendMessage(TextFormat::YELLOW . "Players: " . count($game->getPlayers()) . " / " . $game->getMaxPlayers());
              $sender->sendMessage(TextFormat::GREEN . "---");
          }
          break;
          case 'join';
          if(!$sender instanceof Player){
            $sender->sendMessage("in-game command");
          }
          if(count($args) < 1){
            $sender->sendMessage(":join <game_id>");
            return;
          }

          if(!$this->getPlugin()->gameExists($args[1])){
            $sender->sendMessage($args[1] . " - Invalid game id");
            return;
          }
          $this->getPlugin()->games[$args[1]]->join($sender);
          break;
          case 'random';
        /*  if(!$sender instanceof Player){
            $sender->sendMessage("in-game command");
          }
          $random = array_rand($this->getPlugin()->games, 1);
          $this->getPlugin()->games[$random]->join($sender);*/
          break;
       }


    }

    /**
     * @param CommandSender $sender
     */
    private function sendHelp(CommandSender $sender) {
       $sender->sendMessage("BedWars commands");
       $sender->sendMessage("Setup - ");
       $sender->sendMessage(":create - Create new arena");
       $sender->sendMessage(":delete - Delete existing arena");
       $sender->sendMessage(":setlobby - Set lobby position");
       $sender->sendMessage(":setposition - Set other locations");
       $sender->sendMessage(":setbed - Set team's bed position");
       $sender->sendMessage(":setgenerator - Set generator position");
       $sender->sendMessage(":addsafearea - Create area restricted for placing blocks (for example team's spawn)");
       $sender->sendMessage(":load - Load game after finishing setup");
       $sender->sendMessage("Other - ");
       $sender->sendMessage(":list - Info about existing arenas");
       $sender->sendMessage(":join - Join arena via command");
       $sender->sendMessage(":joinrandom - Join random arena prioritized by players count etc..");
    }
}