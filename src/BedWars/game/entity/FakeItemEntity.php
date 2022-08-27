<?php


namespace BedWars\game\entity;


use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Human;
use pocketmine\entity\Skin;
use pocketmine\event\entity\EntityDamageEvent;

class FakeItemEntity extends Human
{

    public const GEOMETRY = '{"geometry.player_head":{"texturewidth":64,"textureheight":64,"bones":[{"name":"head","pivot":[0,24,0],"cubes":[{"origin":[-4,0,-4],"size":[8,8,8],"uv":[0,0]}]}]}}';

    protected $gravity = 0;

    protected function getInitialSizeInfo() : EntitySizeInfo { return new EntitySizeInfo(0.6, 0.5); }

    /**
     * @param int $currentTick
     * @return bool
     */
    public function onUpdate(int $currentTick): bool
    {
	if($this->isClosed()){
			return false;
        }
        if($this->location->yaw >= 360){
            $this->location->yaw = 0;
        }

        $this->location->yaw += 5.5;

        $this->updateMovement();
        $this->scheduleUpdate();

        //TODO: Add bouncing
        $this->move($this->motion->x, $this->motion->y, $this->motion->z);

        parent::onUpdate($currentTick);
        return true;
    }

    /**
     * @param Skin $skin
     * @throws \JsonException
     */
    public function setSkin(Skin $skin) : void{
        parent::setSkin(new Skin($skin->getSkinId(), $skin->getSkinData(), '', 'geometry.player_head', self::GEOMETRY));
    }

    /**
     * @param EntityDamageEvent $source
     */
    public function attack(EntityDamageEvent $source): void
    {
        $source->cancel();
    }
}
