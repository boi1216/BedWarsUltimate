<?php


namespace BedWars\game;

use BedWars\game\entity\FakeItemEntity;
use BedWars\utils\Utils;
use pocketmine\entity\Entity;
use pocketmine\item\Item;
use pocketmine\level\particle\FloatingTextParticle;
use pocketmine\level\Position;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\nbt\tag\ByteArrayTag;

class Generator
{

    /** @var int $repeatRate */
    private $repeatRate;

    /** @var int $itemID */
    public $itemID;

    /** @var Position $position */
    private $position;

    /** @var bool $spawnText */
    private $spawnText;

    /** @var bool $spawnBlock */
    private $spawnBlock;

    /** @var int $dynamicSpawnTime */
    private $dynamicSpawnTime;

    /** @var FloatingTextParticle $floatingText */
    private $floatingText;

    /** @var $blockEntity */
    private $blockEntity;

    /** @var int $tier */
    private $tier = 1;

    const TITLE = [
        Item::DIAMOND => TextFormat::BOLD . TextFormat::AQUA . "Diamond",
        Item::EMERALD => TextFormat::BOLD . TextFormat::GREEN . "Emerald"
    ];

    const FAKE_BLOCK = [
        Item::DIAMOND => Item::DIAMOND_BLOCK,
        Item::EMERALD => Item::EMERALD_BLOCK
    ];


    /**
     * Generator constructor.
     * @param int $itemID
     * @param int $repeatRate
     * @param Position $position
     * @param bool $spawnText
     * @param bool $spawnBlock
     * @param Team|null $team
     */
    public function __construct(int $itemID, int $repeatRate, Position $position, bool $spawnText, bool $spawnBlock, Team $team = null)
    {
        $this->itemID = $itemID;
        $this->repeatRate = $repeatRate;
        $this->position = $position;
        $this->spawnText = $spawnText;
        $this->spawnBlock = $spawnBlock;

        $this->dynamicSpawnTime = $repeatRate;

        if($this->spawnText){
            $text = TextFormat::YELLOW . "Tier " . TextFormat::RED . Utils::rome($this->tier) . "\n".
                self::TITLE[$itemID] . "\n\n".
                TextFormat::YELLOW . "Spawns in " . TextFormat::RED . $this->dynamicSpawnTime . "seconds";
            $this->floatingText = new FloatingTextParticle($position->add(0.5, 3, 0.5), $text, "");
        }

        if($this->spawnBlock){
           $path = Server::getInstance()->getDataPath() . "plugin_data/BedWars/skins/" . $itemID . ".png";
           $skin = Utils::getSkinFromFile($path);
           $nbt = Entity::createBaseNBT($position->add(0.5, 2.3, 0.5), null);
           $nbt->setTag(new CompoundTag('Skin', [
                new StringTag('Data', $skin->getSkinData()),
                new StringTag('Name', 'Standard_CustomSlim'),
                new StringTag('GeometryName', 'geometry.player_head'),
                new ByteArrayTag('GeometryData', FakeItemEntity::GEOMETRY)]));
           $fakeItem = new FakeItemEntity($position->level, $nbt);
           $fakeItem->setScale(1.4);
           $fakeItem->spawnToAll();
        }
    }


    /**
     * @param int $repeatRate
     */
    public function setRepeatRate(int $repeatRate) : void{
        $this->repeatRate = $repeatRate;
    }

    public function tick() : void{
        if($this->spawnText){
            $text = TextFormat::YELLOW . "Tier " . TextFormat::RED . Utils::rome($this->tier) . "\n".
                self::TITLE[$this->itemID] . "\n".
                TextFormat::YELLOW . "Spawn in " . TextFormat::RED . $this->dynamicSpawnTime;
            $this->floatingText->setText($text);
            foreach($this->floatingText->encode() as $packet){
                foreach($this->position->getLevel()->getPlayers() as $player){
                    $player->dataPacket($packet);
                }
            }
        }

        $this->dynamicSpawnTime--;

        if($this->dynamicSpawnTime == 0){
            $this->dynamicSpawnTime = $this->repeatRate;

            $this->position->getLevel()->dropItem($this->position->asVector3(), Item::get($this->itemID));

        }
    }

    public function getTier() : int{
        return $this->tier;
    }

    public function updateTier() : void{
        $this->tier++;
        //-20%
        $this->repeatRate = $this->repeatRate - ($this->repeatRate * 100 / 20);
    }

    /**
     * @return FakeItemEntity
     */
    public function getBlockEntity() : ?FakeItemEntity{
        return $this->blockEntity;
    }

    /**
     * @return FloatingTextParticle|null
     */
    public function getFloatingText() : ?FloatingTextParticle{
        return $this->floatingText;
    }




}