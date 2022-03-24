<?php

declare(strict_types=1);

namespace BedWars;

use BedWars\command\DefaultCommand;
use BedWars\game\entity\FakeItemEntity;
use BedWars\game\Game;
use BedWars\game\GameListener;
use BedWars\game\Team;
use BedWars\utils\Utils;
use pocketmine\entity\EntityDataHelper;
use pocketmine\entity\EntityFactory;
use pocketmine\item\ItemIds;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\SingletonTrait;
use pocketmine\utils\TextFormat;
use pocketmine\world\World;

class BedWars extends PluginBase
{
	use SingletonTrait;

	const PREFIX = TextFormat::BOLD . TextFormat::DARK_RED . "BedWars " . TextFormat::RESET;
	const TEAMS = [
		'blue' => "§1",
		'red' => "§c",
		'yellow' => "§e",
		"green" => "§a",
		"aqua" => "§b",
		"gold" => "§6",
		"white" => "§f"
	];
	const GENERATOR_PRIORITIES = [
		'gold' => ['item' => ItemIds::GOLD_INGOT, 'spawnText' => false, 'spawnBlock' => false, 'refreshRate' => 10],
		'iron' => ['item' => ItemIds::IRON_INGOT, 'spawnText' => false, 'spawnBlock' => false, 'refreshRate' => 3],
		'diamond' => ['item' => ItemIds::DIAMOND, 'spawnText' => true, 'spawnBlock' => true, 'refreshRate' => 30],
		'emerald' => ['item' => ItemIds::EMERALD, 'spawnText' => true, 'spawnBlock' => true, 'refreshRate' => 60]
	];
	private static $ins;
	/** @var Game[] $games */
	public $games = [];
	/** @var array $signs */
	public $signs = [];
	/** @var array $bedSetup */
	public $bedSetup = [];
	/** @var string $serverWebsite */
	public $serverWebsite;
	/** @var int $staticRestartTime */
	public $staticRestartTime;

	public function onEnable(): void
	{
		if ($this->getServer()->getPluginManager()->getPlugin('FormAPI') === null) {
			err:
			$this->getLogger()->error("Invalid dependency author | FormAPI");
			$this->onEnableStateChange(false);
			return;
		}elseif($this->getServer()->getPluginManager()->getPlugin('FormAPI') !== null){
			$plugin = $this->getServer()->getPluginManager()->getPlugin('FormAPI');
			if($plugin->getDescription()->getAuthors()[0] !== "jojoe77777"){
				goto err;
			}
		}

		$this->saveDefaultConfig();
		$this->serverWebsite = $this->getConfig()->get('website');

		@mkdir($this->getDataFolder() . "games");
		@mkdir($this->getDataFolder() . "skins");
		$this->saveResource("skins/264.png");
		$this->saveResource("skins/388.png");

		$this->getScheduler()->scheduleRepeatingTask(new SignUpdater($this), 20);
		$this->getServer()->getPluginManager()->registerEvents(new GameListener($this), $this);

		foreach (glob($this->getDataFolder() . "games/*.json") as $location) {
			$fileContents = file_get_contents($location);
			$jsonData = json_decode($fileContents, true);

			if (!$this->validateGame($jsonData)) {
				$this->getLogger()->info("Finish setup");
				continue;
			}

			if (count($jsonData['signs']) > 0) {
				$this->signs[$jsonData['id']] = $jsonData['signs'];
			}
			$this->getLogger()->info("Game loaded " . $jsonData['id']);
			$this->games[$jsonData['id']] = new Game($this, $jsonData);
		}

		EntityFactory::getInstance()->register(FakeItemEntity::class, function (World $world, CompoundTag $nbt): FakeItemEntity{
			return new FakeItemEntity(EntityDataHelper::parseLocation($nbt, $world), /* it will change when it will spawn */Utils::getSkinFromFile($this->getDataFolder() . "264.png"), $nbt);
		});

		$this->getServer()->getCommandMap()->register("bedwars", new DefaultCommand());
	}

	/**
	 * @param array $arenaData
	 * @return bool
	 */
	public function validateGame(array $arenaData): bool
	{
		$requiredParams = [
			'id',
			'minPlayers',
			'playersPerTeam',
			'lobby',
			//     'world',
			'teamInfo',
			'generatorInfo',
			'lobby',
			//  'void_y',
			'mapName'
		];

		$error = 0;
		foreach ($requiredParams as $param) {
			if (!in_array($param, array_keys($arenaData))) {
				$error++;
			}
		}

		return !$error > 0;
	}

	/**
	 * @param string $id
	 * @return array|null
	 */
	public function getGameData(string $id): ?array
	{
		if (!$this->gameExists($id)) return null;

		$location = $this->gamePath($id);

		$file = file_get_contents($location);
		return json_decode($file, true);
	}

	/**
	 * @param string $gameID
	 * @return bool
	 */
	public function gameExists(string $gameID): bool
	{
		if (!is_file($this->gamePath($gameID))) {
			return false;
		}
		return true;
	}

	/**
	 * @param string $id
	 * @return string
	 */
	public function gamePath(string $id): string
	{
		return $this->getDataFolder() . "games/" . $id . ".json";
	}

	/**
	 * @param string $id
	 * @param int $minPlayers
	 * @param int $playersPerTeam
	 * @param int $startTime
	 * @param $mapName
	 */
	public function createGame(string $id, $minPlayers, $playersPerTeam, $startTime, $mapName): void
	{
		$dataStructure = [
			'id' => $id,
			'minPlayers' => intval($minPlayers),
			'playersPerTeam' => intval($playersPerTeam),
			'startTime' => intval($startTime),
			'signs' => [],
			'teamInfo' => [],
			'generatorInfo' => [],
			'mapName' => $mapName
		];
		file_put_contents($this->gamePath($id), json_encode($dataStructure));
	}

	/**
	 * @param string $id
	 */
	public function deleteGame(string $id): void
	{
		unlink($this->gamePath($id));
	}

	/**
	 * @param string $id
	 * @param int $x
	 * @param int $y
	 * @param int $z
	 * @param string $levelName
	 */
	public function setLobby(string $id, int $x, int $y, int $z, string $levelName): void
	{
		$file = file_get_contents($path = $this->gamePath($id));
		$json = json_decode($file, true);
		var_dump([$x, $y, $z, $levelName]);
		$json['lobby'] = implode(":", array($x, $y, $z, $levelName));
		file_put_contents($path, json_encode($json));
	}

	public function addGenerator(string $id, string $team, string $type, int $x, int $y, int $z): void
	{

	}

	/**
	 * @param string $id
	 * @param string $team
	 * @param string $keyPos
	 * @param int $x
	 * @param int $y
	 * @param int $z
	 */
	public function setTeamPosition(string $id, string $team, string $keyPos, int $x, int $y, int $z): void
	{
		$file = file_get_contents($path = $this->gamePath($id));
		$json = json_decode($file, true);

		$json['teamInfo'][$team][$keyPos . "Pos"] = implode(":", array($x, $y, $z));
		file_put_contents($path, json_encode($json));
	}

	/**
	 * @param string $id
	 * @return array
	 */
	public function getTeams(string $id): array
	{
		$file = file_get_contents($this->gamePath($id));
		$json = json_decode($file, true);

		$teams = [];
		foreach ($json['teamInfo'] as $name => $data) {
			$teams[] = $name;
		}
		return $teams;
	}

	/**
	 * @param string $id
	 * @param string $team
	 */
	public function addTeam(string $id, string $team)
	{
		$file = file_get_contents($path = $this->gamePath($id));
		$json = json_decode($file, true);
		$json['teamInfo'][$team] = ['spawnPos' => '', 'bedPos' => '', 'shopPos' => ''];
		file_put_contents($path, json_encode($json));
	}

	/**
	 * @param string $id
	 * @param string $team
	 * @return bool
	 */
	public function teamExists(string $id, string $team): bool
	{
		$file = file_get_contents($this->gamePath($id));
		if ($file == null) {
			return false;
		}
		$json = (array)json_decode($file);
		return isset($json['teamInfo'][strtolower($team)]);
	}

	/**
	 * @param string $gameName
	 * @return bool
	 */
	public function isGameLoaded(string $gameName): bool
	{
		return isset($this->games[$gameName]);
	}

	/**
	 * @param Player $player
	 * @return Team|null
	 */
	public function getPlayerTeam(Player $player): ?Team
	{
		$game = $this->getPlayerGame($player);
		if ($game == null) return null;

		foreach ($game->teams as $team) {
			if (in_array($player->getName(), array_keys($team->getPlayers()))) {
				return $team;
			}
		}
		return null;
	}

	/**
	 * @param Player $player
	 * @param bool $isSpectator
	 * @return Game|null
	 */
	public function getPlayerGame(Player $player, bool $isSpectator = false): ?Game
	{
		foreach ($this->games as $game) {
			if (isset($game->players[$player->getName()])) return $game;
			if (isset($game->spectators[$player->getName()])) return $game;
		}
		return null;
	}
}