<?php


namespace BedWars\game\entity;


use pocketmine\entity\Human;
use pocketmine\entity\object\ItemEntity;
use pocketmine\entity\Skin;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\level\Level;
use pocketmine\nbt\tag\CompoundTag;

class FakeItemEntity extends Human
{

    public const GEOMETRY = '{"geometry.player_head":{"texturewidth":64,"textureheight":64,"bones":[{"name":"head","pivot":[0,24,0],"cubes":[{"origin":[-4,0,-4],"size":[8,8,8],"uv":[0,0]}]}]}}';

    /** @var int $gravity */
    protected $gravity = 0;
    public $width = 0.5, $height = 0.6;

    /**
     * @param int $currentTick
     * @return bool
     */
    public function onUpdate(int $currentTick): bool
    {

        $this->yaw+=5.5;
        //TODO: Add bouncing
        $this->move($this->motion->x, $this->motion->y, $this->motion->z);
        $this->updateMovement();

        parent::onUpdate($currentTick);
        return true;
    }

    /**
     * @param Skin $skin
     */
    public function setSkin(Skin $skin) : void{
        parent::setSkin(new Skin($skin->getSkinId(), $skin->getSkinData(), '', 'geometry.player_head', self::GEOMETRY));
    }

    /**
     * @param EntityDamageEvent $source
     */
    public function attack(EntityDamageEvent $source): void
    {
        $source->setCancelled();
    }

}