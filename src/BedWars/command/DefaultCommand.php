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
	private $commandInfo = [
		'create' => ['desc' => "Create new game", 'usage' => "<game_id> <min_players> <players_per_team> <start_time> <map_name> <world_name>"],
		'delete' => ['desc' => "Delete game", 'usage' => "<game_id>"],
		'setlobby' => ['desc' => "Set lobby of a game", 'usage' => "<game_id>"],
		'addteam' => ['desc' => "Add team to a game", 'usage' => "<game_id> <blue|red|yellow|green|aqua|gold|white> "],
		'setpos' => ['desc' => "Set spawn & shop positions", 'usage' => "<game_id> <team_name> <1|2|3> - 3 = Player Spawn, 2 = Upgrade Shop, 1 = Item Shop"],
		'addgenerator' => ['desc' => "Add generator", 'usage' => "<game_id> <iron|gold|diamond|emerald> "],
		'setbed' => ['desc' => "Set team's bed", 'usage' => "<game_id> <team>"],
		'addsafearea' => ['desc' => "Add area restricted for placing blocks", 'usage' => "<game_id> 365:2,13,1:0"],
		'load' => ['desc' => "Load arena after finishing setup", 'usage' => '<game_id>'],
		'join' => ['desc' => "Join arena by id", 'usage' => '<game_id>'],
		'random' => ['desc' => "Join random arena", 'usage' => ''],
		'start' => ['desc' => "force start the game", 'usage' => '']
	];
	
    /**
     * DefaultCommand constructor.
     * @param BedWars $owner
     */
	public function __construct(
		private BedWars $owner
	){
		parent::__construct("bedwars", "BedWars", null, ["bw"]);
		parent::setDescription("BedWars command");
    }
	
	private function getPlugin() : BedWars{
		return $this->owner;
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
				if(count($args) < 7){
					$sender->sendMessage($this->getSubUsage('create'));
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
					$sender->sendMessage(TextFormat::RED . "map_name - name too short!");
				}
				
				$world = $this->getPlugin()->getServer()->getWorldManager()->getWorldByName($world_name);
				if(!$world instanceof World){
					$sender->sendMessage(TextFormat::RED . "World " . $world_name . " does not exist");
					return;
				}
				
				$sender->sendMessage(TextFormat::GREEN . "Game created");
				$this->getPlugin()->createGame($game_id, $min_players, $players_per_team, $start_time, $map_name, $world_name);
			break;
			
			case 'delete';
				if(count($args) < 2){
					$sender->sendMessage($this->getSubUsage('delete'));
					return;
				}
				
				$game_id = $args[1];
				if($this->getPlugin()->gameExists($game_id)){
					$this->getPlugin()->deleteGame($game_id);
					$sender->sendMessage(TextFormat::GREEN . "Game deleted");
					return;
				}
				
				$sender->sendMessage(TextFormat::RED . $game_id . " - game not found");
			break;
			
			case 'setlobby';
				if(!$sender instanceof Player){
					$sender->sendMessage(TextFormat::GREEN . "This command can be used only in-game");
					return;
				}
				
				if(count($args) < 2){
					$sender->sendMessage($this->getSubUsage('setlobby'));
					return;
				}
				
				$game_id = $args[1];	
				if($this->getPlugin()->gameExists($game_id)){
					$pos = $sender->getPosition();
					$this->getPlugin()->setLobby($game_id, intval($pos->getX()) + 0.5, $pos->getY(), intval($pos->getZ()) + 0.5, $pos->getWorld()->getFolderName());
					$sender->sendMessage(TextFormat::GREEN . "Lobby set");
				}
			break;
			
			case 'setposition';
			case 'setpos';
				if(!$sender instanceof Player){
					$sender->sendMessage(TextFormat::GREEN . "This command can be used only in-game");
					return;
				}
				
				if(count($args) < 4){
					$sender->sendMessage($this->getSubUsage('setpos'));
					return;
				}
				
				$game_id = $args[1];
				if(!$this->getPlugin()->gameExists($game_id)){
					$sender->sendMessage(TextFormat::RED . $game_id . " - Invalid game id");
					return;
				}
				
				$team = $args[2];
				if(!$this->getPlugin()->teamExists($game_id, $team)){
					$sender->sendMessage(TextFormat::RED . "Invalid team - " . $team);
					return;
				}
				
				$positionType = array(1 => 'Shop', 2 => 'Upgrade', 3 => 'Spawn')[intval($args[3])];
				if($positionType == null){
					$sender->sendMessage(TextFormat::RED . "Invalid position");
					return;
				}
				
				$pos = $sender->getPosition();
				$this->getPlugin()->setTeamPosition($game_id, $team, $positionType, intval($pos->getX()), $pos->getY(), intval($pos->getZ()), $sender->getLocation()->getYaw());
				$sender->sendMessage(TextFormat::GREEN . "Position set");
			break;
			
			case 'setbed';	
				if(!$sender instanceof Player){
					$sender->sendMessage(TextFormat::GREEN . "This command can be used only in-game");
					return;
				}
				
				if(count($args) < 2){
					$sender->sendMessage($this->getSubUsage('setbed'));
					return;
				}
				
				$game_id = $args[1];
				if(!$this->getPlugin()->gameExists($game_id)){
					$sender->sendMessage(TextFormat::RED . $game_id . " - Invalid game id");
					return;
				}
				
				$team = strtolower($args[2]);
				if(!$this->getPlugin()->teamExists($game_id, $team)){
					$sender->sendMessage(TextFormat::RED . "Invalid team - " . $team);
					return;
				}
				
				$this->getPlugin()->bedSetup[$sender->getName()] = ['game' => $game_id, 'team' => $team , 'step' => 1];
				$sender->sendMessage(TextFormat::GREEN . "Please touch the 1st part of the bed");
			break;
			
			case 'addteam';
				if(count($args) < 2){
					$sender->sendMessage($this->getSubUsage('addteam'));
					return;
				}
				
				$game_id = $args[1];
				if(!$this->getPlugin()->gameExists($game_id)){
					$sender->sendMessage(TextFormat::RED . $game_id . " - Invalid game id");
					return;
				}
				
				$team = strtolower($args[2]);
				if(!isset(BedWars::TEAMS[$team])){
					$sender->sendMessage(TextFormat::RED . $team . " - Invalid team");
					$sender->sendMessage(TextFormat::RED . "Available: " . implode(" ", array_keys(BedWars::TEAMS)));
					return;
				}
				
				$this->getPlugin()->addTeam($game_id, $team);
				$sender->sendMessage(TextFormat::GREEN . "Team added!");
			break;
			
			case 'addgenerator';
			case 'setgenerator';
			
			if(!$sender instanceof Player){
				$sender->sendMessage(TextFormat::GREEN . "This command can be used only in-game");
				return;
			}
			
			if(count($args) < 3){
				$sender->sendMessage($this->getSubUsage('addgenerator'));
				return;
			}
			
			$game_id = $args[1];
			if(!$this->getPlugin()->gameExists($game_id)){
				$sender->sendMessage(TextFormat::RED . $game_id . " - Invalid game id");
				return;
			}
			
			$generatorTypes = array('iron', 'gold', 'diamond', 'emerald');
			if(!in_array($args[2], $generatorTypes)){
				$sender->sendMessage(TextFormat::RED . $args[2] . " - Invalid generator type");
				return;
			}
			
			$generatorType = $args[2];
			$pos = $sender->getPosition();
			$team = isset($args[3]) ? $args[3] : "";
			if(!$this->getPlugin()->teamExists($game_id, $team) && $team !== ""){
				$sender->sendMessage(TextFormat::RED . "Invalid team - " . $team);
				return;
			}
			
			$this->getPlugin()->addGenerator($game_id, $team, $generatorType, intval($pos->getX()), $pos->getY(), intval($pos->getZ()));
			$sender->sendMessage(TextFormat::GREEN . "Added");
		break;
		
		case 'addsafearea';
			if(!$sender instanceof Player){
				$sender->sendMessage(TextFormat::GREEN . "This command can be used only in-game");
				return;
			}
			
			if(count($args) < 3){
				$sender->sendMessage($this->getSubUsage('addsafearea'));
				return;
			}
			
			$game_id = $args[1];
			if(!$this->getPlugin()->gameExists($game_id)){
				$sender->sendMessage(TextFormat::RED . $game_id . " - Invalid game id");
				return;
			}
			
			$ignored = array();
			str_replace(" ", "", $args[2]);
			if(isset($args[2])){
				if(!strpos($args[2], ',')){
					if(is_numeric($args[2])){
						$ignored[] = intval($args[2]);
					} else {
						if(!strpos($args[2], ':')){
							err:
							$sender->sendMessage(TextFormat::RED . "Invalid format of ignored blocks!");
							$sender->sendMessage(TextFormat::YELLOW . "Example: <game_id> 365:2,13,1:0");
							return;
						}
						
						$e = explode(":", $args[2]);
						$ignored[] = $e[0] . ":" . $e[1];
					}
				} else {
					foreach(explode(",", $args[2]) as $id) {
						if(is_numeric($id)){
							$ignored[] = intval($id);
						} else {
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
			
			$block = null;
			foreach($ignored as $blockID){
				if(strpos($blockID, ':')){
					$idMeta = explode(":", $blockID);
					try {
						$block = BlockFactory::getInstance()->get(intval($idMeta[0]), intval($idMeta[1]));
					} catch (\InvalidArgumentException $e){
						goto invalid;
					}
					
					if(!$block instanceof Block){
						goto invalid;
					}
				} else {
					try {
						$block = BlockFactory::getInstance()->get($blockID, 0);
					} catch (\InvalidArgumentException $e){
						goto invalid;
					}
					
					if(!$block instanceof Block){
						invalid:
						$sender->sendMessage(TextFormat::RED . $blockID . " is not a valid block id");
						return;
					}
				}
			}
			
			$this->getPlugin()->saSetup[$sender->getName()] = ['step' => 1, 'pos1' => null, 'pos2' => null, 'ignoredIds' => implode(",", $ignored), 'game_id' => $game_id];
			$sender->sendMessage(TextFormat::GREEN . "Break a block to set the 1st position");
			break;
			
			case 'load';
				if(count($args) < 2){
					$sender->sendMessage($this->getSubUsage('load'));
					return;
				}
				
				$gameData = $this->getPlugin()->getGameData($args[1]);
				if($gameData == null){
					return;
				}
				
				if(!$this->getPlugin()->validateGame($gameData)){
					$sender->sendMessage(TextFormat::RED . "Setup not finished for game - " . $args[1]);
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
					$sender->sendMessage(TextFormat::GREEN . "This command can be used only in-game");
					return;
				}
				
				if(count($args) < 2){
					$sender->sendMessage($this->getSubUsage('join'));
					return;
				}
				
				if(!$this->getPlugin()->gameExists($args[1])){
					$sender->sendMessage(TextFormat::RED . $args[1] . " - Invalid game id");
					return;
				}
				
				$this->getPlugin()->games[$args[1]]->join($sender);
				
			break;
			case 'random';
				if($sender instanceof Player){
					$sender->sendMessage(TextFormat::YELLOW . "Searching for avillable arena for you!");
					$avillableArenas = [];
					
					$index = [];
					foreach ($this->getPlugin()->games as $arena){
						if($arena->getState() == $arena::STATE_LOBBY){
							$index[$arena->getName()] = $arena->getPlayers();
						}
					}
					
					arsort($index);
					
					if(count($index) > 0){
						foreach ($index as $key => $val){
							if(count($avillableArenas) < 3){
								$avillableArenas[] = $key;
							}
						}
					}
					
					if(count($avillableArenas) == 0){
						$sender->sendMessage(TextFormat::RED . "No arenas found!");
						return;
					}
					
					$random = $avillableArenas[array_rand($avillableArenas)];
					$this->getPlugin()->games[$random]->join($sender);
					$sender->sendMessage(TextFormat::YELLOW . "You send to " . $random . "!");
				} else {
					$sender->sendMessage("run command in-game only!");
				}
			break;

			case "start";
				if($sender instanceof Player){
					if($sender->hasPermission("bedwars.command.start")){
						if(($arena = $this->owner->getPlayerGame($sender)) !== null){

							if(count($arena->getPlayers()) < 2){
								$sender->sendMessage(TextFormat::RED . "Need more players to start the game!");
								return;
							}

							if($arena->getState() == $arena::STATE_LOBBY){
								if($arena->getStartTime() > 10){
									$arena->setForcedStart(true);
									$arena->setStartTime(5);
									$sender->sendMessage(TextFormat::YELLOW . "Starting in 5s");
								} else {
									$sender->sendMessage(TextFormat::RED . "cannot start the game right now!");
								}
							} else {
								$sender->sendMessage(TextFormat::RED . "Game already started!");
							}
						} else {
							$sender->sendMessage(TextFormat::RED . "You're not in arena!");
						}
					} else {
						$sender->sendMessage(TextFormat::RED . "You don't have permission to use this command!");
					}
				} else {
					$sender->sendMessage("run command in-game only!");
				}
			break;
		}	
	}
	
	private function getSubUsage($command){
		return TextFormat::RED . "Usage: " . $this->commandInfo[$command]['usage'];
	}
	
    /**
     * @param CommandSender $sender
     */
	private function sendHelp(CommandSender $sender) {
		$sender->sendMessage(TextFormat::BOLD . TextFormat::YELLOW . "BedWars Commands");
		$sender->sendMessage(TextFormat::GRAY . "Type the sub-command for usage info");
		$sender->sendMessage(TextFormat::BOLD . TextFormat::WHITE . "[] - required parameters");
		$sender->sendMessage(TextFormat::BOLD . TextFormat::WHITE . "[] - optional parameters");
		foreach($this->commandInfo as $command => $info){
			$sender->sendMessage(TextFormat::RED . $command . TextFormat::GRAY . " - " . $info['desc']);
		}	
	}
}
