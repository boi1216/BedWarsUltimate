<?php


namespace BedWars\game\player;

use pocketmine\Player;
use pocketmine\level\Position;
use pocketmine\entity\EffectInstance;


class PlayerCache
{
    /** @var Player $player */
    private $player;
    /** @var string $nametag */
    private $nametag;
    /** @var array $inventoryContents */
    private $inventoryContents = array();
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
        $this->food = $player->getMaxFood();
        $this->position = $player->asPosition();
        $this->effects = $player->getEffects();
    }

    public function load(){
        $this->player->setNameTag($this->nametag);
        $this->player->getInventory()->setContents($this->inventoryContents);
        $this->player->setHealth($this->health);
        $this->player->setMaxHealth($this->maxHealth);
        $this->player->setFood($this->food);
        $this->player->teleport($this->position);
        foreach($this->effects as $effect){
            $this->player->addEffect($effect);
        }
    }

}