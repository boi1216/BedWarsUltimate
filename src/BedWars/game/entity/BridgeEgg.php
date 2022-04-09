<?php

namespace BedWars\game\entity;

use pocketmine\entity\projectile\Egg;
use pocketmine\block\BlockFactory;
use pocketmine\block\BlockLegacyIds;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityCombustByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\block\Wool;
use pocketmine\math\RayTraceResult;
use pocketmine\block\Block;
use pocketmine\player\Player;
use pocketmine\entity\Entity;
use pocketmine\entity\Location;

use BedWars\BedWars;
use BedWars\game\Game;
use BedWars\game\Team;
use BedWars\utils\Utils;

class BridgeEgg extends Egg{

	private $startY;
	private $startVec;
	private $skippedFirst = false;

	private $isNotBE = false;

	public function __construct(Location $location, ?Entity $shootingEntity, ?CompoundTag $nbt = null){
		parent::__construct($location, null, $nbt);
		if($shootingEntity !== null){
			$this->setOwningEntity($shootingEntity);

			if(!$shootingEntity instanceof Player){
				$isNotBE = true;
				return;
			}

			if(is_null(BedWars::getInstance()->getPlayerGame($shootingEntity))){
				$isNotBE = true;
			}
		}
	}


	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);
        $this->startVec = $this->getPosition()->asVector3();
		$this->startY = $this->getPosition()->getY();

	}

	protected function move(float $dx, float $dy, float $dz) : void{
		if($this->isNotBE){
			parent::move($dx, $dy, $dz);
			return;
		}
		$game = BedWars::getInstance()->getPlayerGame($this->getOwningEntity());
		$team = BedWars::getInstance()->getPlayerTeam($this->getOwningEntity());
		if(!$team instanceof Team || !$game instanceof Game){
			parent::move($dx, $dy, $dz);
			return;
		}

		$world = $this->getWorld();
		$pos = $this->getPosition();
		$placePos = $pos->asVector3()->subtract(0, 1, 0);
		if(ceil($pos->getY() - $this->startY) > 2){
          $placePos = new Vector3($pos->getX(), $this->startY + 2, $pos->getZ());
		}else if(ceil($pos->getY() - $this->startY) < 2){
			$placePos = new Vector3($pos->getX(), $this->startY -2, $pos->getZ());
		}
		if($placePos->distance($this->startVec) > 20){
			$this->flagForDespawn();
            return;
		}

		parent::move($dx, $dy, $dz);
		if($this->skippedFirst){ //simple skip for players position
			foreach ([
				$placePos,
				$placePos->subtract(0, 0, 1),
				$placePos->subtract(1, 0, 0),
				$placePos->add(1, 0, 0),
				$placePos->add(0, 0, 1)
				] as $pos){
					if($world->getBlock($pos)->getId() !== BlockLegacyIds::BED_BLOCK){
						$world->setBlock($pos, BlockFactory::getInstance()->get(BlockLegacyIds::WOOL, Utils::colorIntoWool($team->getColor())));
						$game->placedBlocks[] = Utils::vectorToString(":", $world->getBlock($pos)->getPosition()->asVector3());
					}
				}
	    }
	    $this->skippedFirst = true;
	}


	protected function calculateInterceptWithBlock(Block $block, Vector3 $start, Vector3 $end) : ?RayTraceResult{
        if($block instanceof Wool && !$this->isNotBE){
        	return null;
        }
		return $block->calculateIntercept($start, $end);
	}

	/**
	 * Called when the projectile collides with an Entity.
	 */
	protected function onHitEntity(Entity $entityHit, RayTraceResult $hitResult) : void{
		if(!$this->isNotBE){
			return;
		}
		$damage = $this->getResultDamage();

		if($damage >= 0){
			if($this->getOwningEntity() === null){
				$ev = new EntityDamageByEntityEvent($this, $entityHit, EntityDamageEvent::CAUSE_PROJECTILE, $damage);
			}else{
				$ev = new EntityDamageByChildEntityEvent($this->getOwningEntity(), $this, $entityHit, EntityDamageEvent::CAUSE_PROJECTILE, $damage);
			}

			$entityHit->attack($ev);

			if($this->isOnFire()){
				$ev = new EntityCombustByEntityEvent($this, $entityHit, 5);
				$ev->call();
				if(!$ev->isCancelled()){
					$entityHit->setOnFire($ev->getDuration());
				}
			}
		}

		$this->flagForDespawn();
	}

}
