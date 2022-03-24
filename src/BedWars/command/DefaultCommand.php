<?php

declare(strict_types=1);

namespace BedWars\command;

use BedWars\BedWars;
use BedWars\utils\Utils;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

class DefaultCommand extends Command
{

	/**
	 * DefaultCommand constructor.
	 */
	public function __construct()
	{
		parent::__construct("bedwars", "BedWars", null, ["bw"]);
		parent::setDescription("BedWars command");
	}

	/**
	 * @param CommandSender $sender
	 * @param string $commandLabel
	 * @param array $args
	 * @return void
	 */
	public function execute(CommandSender $sender, string $commandLabel, array $args)
	{
		if (empty($args)) {
			$this->sendHelp($sender);
			return;
		}

		switch ($args[0]) {
			case 'create';
				if (count($args) < 5) {
					$sender->sendMessage(TextFormat::RED . ":create <game_id> <min_players> <players_per_team> <start_time> <map_name>");
					return;
				}
				$game_id = $args[1];
				$min_players = $args[2];
				$players_per_team = $args[3];
				$start_time = $args[4];
				$map_name = $args[5];

				if (!is_int(intval($min_players))) {
					$sender->sendMessage(TextFormat::RED . "min_players - must be a number!");
					return;
				}

				if (!is_int(intval($players_per_team))) {
					$sender->sendMessage(TextFormat::RED . "players_per_team - must be a number!");
					return;
				}

				if (!is_int(intval($start_time))) {
					$sender->sendMessage(TextFormat::RED . "start_time - must be a number!");
					return;
				}

				if (strlen($map_name) < 1) {
					$sender->sendMessage(TextFormat::RED . "map_name - too short!");
				}
				$sender->sendMessage(TextFormat::GREEN . "Game created");
				$this->getPlugin()->createGame($game_id, $min_players, $players_per_team, $start_time, $map_name);
				break;
			case 'delete';
				if (count($args) < 1) {
					$sender->sendMessage(":delete <game_id>");
					return;
				}
				$game_id = $args[1];
				if ($this->getPlugin()->gameExists($game_id)) {
					$this->getPlugin()->deleteGame($game_id);
					$sender->sendMessage(TextFormat::GREEN . "Game deleted");
					return;
				}

				$sender->sendMessage($game_id . " - game not found");
				break;
			case 'setlobby';
				if (!$sender instanceof Player) {
					$sender->sendMessage("in-game command");
					return;
				}
				if (count($args) < 2) {
					$sender->sendMessage(":setlobby <game_id>");
					return;
				}

				$game_id = $args[1];
				if ($this->getPlugin()->gameExists($game_id)) {
					$pos = $sender->getPosition();
					$this->getPlugin()->setLobby($game_id, (int)$pos->getX(), (int)$pos->getY(), (int)$pos->getZ(), $pos->getWorld()->getFolderName());
					$sender->sendMessage(TextFormat::GREEN . "Lobby set");
				}
				break;
			case 'setposition';
				if (!$sender instanceof Player) {
					$sender->sendMessage("in-game command");
					return;
				}

				if (count($args) < 4) {
					$sender->sendMessage(":setposition <game_id> <team> <1|2|3>");
					$sender->sendMessage("1 - Shop Classic, 2 - Shop Upgrade, 3 - Team Spawn");
					return;
				}

				$game_id = $args[1];
				if (!$this->getPlugin()->gameExists($game_id)) {
					$sender->sendMessage($game_id . " - Invalid game id");
					return;
				}
				$team = $args[2];

				$positionType = [1 => 'shop', 2 => 'upgrade', 3 => 'Spawn'][intval($args[3])];
				if ($positionType == null) {
					$sender->sendMessage("Invalid position");
					return;
				}
				$pos = $sender->getPosition();
				$this->getPlugin()->setTeamPosition($game_id, $team, $positionType, (int)$pos->getX(), (int)$pos->getY(), (int)$pos->getZ());
				$sender->sendMessage(TextFormat::GREEN . "Position set");
				break;
			case "setgenerator";
				if (!$sender instanceof Player) {
					$sender->sendMessage(TextFormat::RED . "This command can be executed only in game");
					return;
				}

				if (count($args) < 3) {
					$sender->sendMessage(":setgenerator <game_id> <iron, gold, diamond, emerald>");
					return;
				}

				$game_id = $args[1];
				if (!$this->getPlugin()->gameExists($game_id)) {
					$sender->sendMessage("Invalid game id");
					return;
				}

				$generatorType = $args[2];
				if (!in_array($generatorType, ['iron', 'gold', 'emerald', 'diamond'])) {
					$sender->sendMessage("Generators: iron, gold, diamond, emerald");
					return;
				}

				$gameData = $this->getPlugin()->getGameData($game_id);
				$gameData['generatorInfo'][$game_id][] = ['type' => $generatorType, 'position' => Utils::vectorToString("", $sender->getPosition()->asVector3()), 'game'];

				file_put_contents($this->getPlugin()->gamePath($game_id), json_encode($gameData));

				$sender->sendMessage(TextFormat::GREEN . "Added generator ");
				break;
			case 'setbed';
				if (count($args) < 2) {
					$sender->sendMessage(":setbed <game_id> <team>");
					return;
				}

				$game_id = $args[1];
				if (!$this->getPlugin()->gameExists($game_id)) {
					$sender->sendMessage($game_id . " - Invalid game id");
					return;
				}

				$team = $args[2];
				$game_teams = $this->getPlugin()->getTeams($game_id);
				if (empty($game_teams)) {
					$sender->sendMessage("First add teams via :addteam subcommand");
					$sender->sendMessage(":addteam <game_id> <team>");
					$sender->sendMessage("Available teams: " . implode(" ", array_values(BedWars::TEAMS)));
					return;
				}

				if (!isset($game_teams[strtolower($team)])) {
					$sender->sendMessage("Team not found");
					$sender->sendMessage("Available for " . $game_id . " - " . implode(" ", $game_teams));
					return;
				}
				$this->getPlugin()->bedSetup[$sender->getName()] = ['game' => $game_id, 'team' => $team, 'step' => 1];
				break;
			case 'addteam';
				if (count($args) < 2) {
					$sender->sendMessage(TextFormat::RED . ":addteam <game_id> <team>");
					return;
				}
				$game_id = $args[1];
				if (!$this->getPlugin()->gameExists($game_id)) {
					$sender->sendMessage($game_id . " - Invalid game id");
					return;
				}
				$team = strtolower($args[2]);
				if (!isset(BedWars::TEAMS[$team])) {
					$sender->sendMessage($team . " - Invalid team");
					$sender->sendMessage("Available: " . implode(" ", array_keys(BedWars::TEAMS)));
					return;
				}
				$this->getPlugin()->addTeam($game_id, $team);
				break;
			case 'join';

				break;
		}


	}

	/**
	 * @param CommandSender $sender
	 */
	private function sendHelp(CommandSender $sender)
	{
		$sender->sendMessage("BedWars commands");
		$sender->sendMessage("Setup - ");
		$sender->sendMessage(":create - Create new arena");
		$sender->sendMessage(":delete - Delete existing arena");
		$sender->sendMessage(":setlobby - Set lobby position");
		$sender->sendMessage(":setposition - Set other locations");
		$sender->sendMessage(":setbed - Set team's bed position");
		$sender->sendMessage(":setgenerator - Set generator position");
		$sender->sendMessage("Other - ");
		$sender->sendMessage(":list - Info about existing arenas");
		$sender->sendMessage(":join - Join arena via command");
		$sender->sendMessage(":joinrandom - Join random arena prioritized by players count etc..");
	}

	private function getPlugin(): BedWars
	{
		return BedWars::getInstance();
	}
}