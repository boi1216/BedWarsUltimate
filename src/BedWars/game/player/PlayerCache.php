<?php


namespace BedWars\game\player;

use pocketmine\player\Player;
use pocketmine\level\Position;
use pocketmine\entity\EffectInstance;
use pocketmine\entity\Skin;


class PlayerCache
{
    /** @var Player $player */
    private $player;
    /** @var string $nametag */
    private $nametag;
    /** @var array $inventoryContents */
    private $inventoryContents = array();
    /** @var array $armorContents */
    private $armorContents = array();
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
    /** @var bool $allowFlight */
    private $allowFlight;
    /** @var string */
    private $geometryName;
    /** @var string */
    private $geometryData;

    public function __construct(Player $player)
    {
        $this->player = $player;
        $this->nametag = $player->getNameTag();
        $this->inventoryContents = $player->getInventory()->getContents();
        $this->armorContents = $player->getArmorInventory()->getContents();
        $this->health = $player->getHealth();
        $this->maxHealth = $player->getMaxHealth();
        $this->food = $player->getHungerManager()->getMaxFood();
        $this->position = $player->getPosition();
        $this->effects = $player->getEffects()->all();
        $this->allowFlight = $player->getAllowFlight();
        $this->geometryName = $player->getSkin()->getGeometryName();
        $this->geometryData = $player->getSkin()->getGeometryData();
    }

    public function load(){
        $this->player->getArmorInventory()->clearAll();
        $this->player->setNameTag($this->nametag);
        $this->player->getInventory()->setContents($this->inventoryContents);
        $this->player->getArmorInventory()->setContents($this->armorContents);
        $this->player->setHealth($this->health);
        $this->player->setMaxHealth($this->maxHealth);
        $this->player->getHungerManager()->setFood($this->food);
        $this->player->teleport($this->position);
        foreach($this->effects as $effect){
            $this->player->getEffects()->add($effect);
        }
        $this->player->setAllowFlight($this->allowFlight);
        $skin = $this->player->getSkin();
        $this->player->setSkin(new Skin($skin->getSkinId(), $skin->getSkinData(), $skin->getCapeData(), $this->geometryName, $this->geometryData));
    }

}