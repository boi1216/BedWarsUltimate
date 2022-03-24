<?php

declare(strict_types=1);

namespace BedWars\game\player;

use pocketmine\entity\effect\EffectInstance;
use pocketmine\player\Player;
use pocketmine\world\Position;


class PlayerCache
{
	/** @var Player $player */
	private $player;
	/** @var string $nametag */
	private $nametag;
	/** @var array $inventoryContents */
	private $inventoryContents;
	/** @var int $health */
	private $health;
	/** @var int $maxHealth */
	private $maxHealth;
	/** @var int $food */
	private $food;
	/** @var Position $position */
	private $position;
	/** @var EffectInstance[] $effects */
	private $effects;

	public function __construct(Player $player)
	{
		$this->nametag = $player->getNameTag();
		$this->inventoryContents = $player->getInventory()->getContents();
		$this->health = $player->getHealth();
		$this->maxHealth = $player->getMaxHealth();
		$this->food = $player->getHungerManager()->getMaxFood();
		$this->position = $player->getPosition();
		$this->effects = $player->getEffects()->all();
	}

	public function load()
	{
		$this->player->setNameTag($this->nametag);
		$this->player->getInventory()->setContents($this->inventoryContents);
		$this->player->setHealth($this->health);
		$this->player->setMaxHealth($this->maxHealth);
		$this->player->getHungerManager()->setFood($this->food);
		$this->player->teleport($this->position);
		foreach ($this->effects as $effect) {
			$this->player->getEffects()->add($effect);
		}
	}

}