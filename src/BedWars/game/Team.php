<?php


namespace BedWars\game;


use pocketmine\player\Player;

class Team
{

    /** @var Player[] $players */
    protected $players = array();

    /** @var string $color */
    protected $color;

    /** @var string $name */
    protected $name;

    /** @var bool $hasBed */
    protected $hasBed = true;

    /** @var array $armorUpdates */
    private $armorUpdates = array();

    /** @var int $dead */
    public $dead = 0;

    /** @var array $upgrades */
    private $upgrades = array(
        'sharpenedSwords' => 0,
        'armorProtection' => 0,
        'maniacMiner' => 0,
        'ironForge' => 0,
        'healPool' => 0
    );


    /**
     * Team constructor.
     * @param string $name
     * @param string $color
     */
    public function __construct(string $name, string $color)
    {
        $this->name = $name;
        $this->color = $color;
    }

    /**
     * @param Player $player
     */
    public function add(Player $player) : void{
        $this->players[$player->getName()] = $player;
    }

    public function remove(Player $player) : void{
        unset($this->players[$player->getName()]);
    }

    /**
     * @return string
     */
    public function getColor() : string{
        return $this->color;
    }

    /**
     * @return string
     */
    public function getName() : string{
        return $this->name;
    }

    /**
     * @return array
     */
    public function getPlayers() : array{
        return $this->players;
    }

    /**
     * @param bool $state
     */
    public function updateBedState(bool $state) : void{
        $this->hasBed = $state;
    }

    /**
     * @return bool
     */
    public function hasBed() : bool{
        return $this->hasBed;
    }

    /**
     * @param Player $player
     * @param string $armor
     */
    public function setArmor(Player $player, string $armor){
        $this->armorUpdates[$player->getName()] = $armor;
    }

    /**
     * @param Player $player
     * @return string|null
     */
    public function getArmor(Player $player) : ?string{
        return $this->armorUpdates[$player->getName()];
    }

    /**
     * @param string $property
     */
    public function upgrade(string $property) : void{
        $this->upgrades[$property] +=1;
    }

    /**
     * @param string $property
     * @return int
     */
    public function getUpgrade(string $property) : int{
        return $this->upgrades[$property];
    }

    public function reset() : void{
        $this->upgrades = array(
            'sharpenedSwords' => 0,
            'armorProtection' => 0,
            'maniacMiner' => 0,
            'ironForge' => 0,
            'healPool' => 0
        );

        $this->hasBed = true;
        $this->armorUpdates = array();
        $this->players = array();
    }

}