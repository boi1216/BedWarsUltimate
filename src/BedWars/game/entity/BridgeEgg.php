<?php

namespace BedWars\game\entity;

use pocketmine\entity\projectile\Egg;
use pocketmine\block\BlockFactory;
use pocketmine\block\BlockLegacyIds;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\block\Wool;
use pocketmine\math\RayTraceResult;
use pocketmine\block\Block;
use pocketmine\player\Player;
use pocketmine\entity\Entity;
use pocketmine\entity\Location;

use BedWars\BedWars;
use BedWars\game\Team;
use BedWars\utils\Utils;

class BridgeEgg extends Egg{

	private $startY;
	private $startVec;
	private $inAirTicks;

	private $isNotBE = false;

	public function __construct(Location $location, ?Entity $shootingEntity, ?CompoundTag $nbt = null){
		parent::__construct($location, $nbt);
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

		$owner = $this->getOwningEntity();



	}

	protected function move(float $dx, float $dy, float $dz) : void{
		if($this->isNotBE){
			parent::move($dx, $dy, $dz);
			return;
		}
		$team = BedWars::getInstance()->getPlayerTeam($this->getOwningEntity());
		if(!$team instanceof Team){
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
		$this->inAirTicks++;
		parent::move($dx, $dy, $dz);
		if($this->inAirTicks > 1){ //simple skip for players position
		$world->setBlock($placePos, BlockFactory::getInstance()->get(BlockLegacyIds::WOOL, Utils::colorIntoWool($team->getColor())));
		$world->setBlock($placePos->subtract(0, 0, 1), BlockFactory::getInstance()->get(BlockLegacyIds::WOOL, Utils::colorIntoWool($team->getColor())));
		$world->setBlock($placePos->subtract(1, 0, 0), BlockFactory::getInstance()->get(BlockLegacyIds::WOOL, Utils::colorIntoWool($team->getColor())));
        $world->setBlock($placePos->add(1, 0, 0), BlockFactory::getInstance()->get(BlockLegacyIds::WOOL, Utils::colorIntoWool($team->getColor())));
        $world->setBlock($placePos->add(0, 0, 1), BlockFactory::getInstance()->get(BlockLegacyIds::WOOL, Utils::colorIntoWool($team->getColor())));
	  }
	}

	protected function calculateInterceptWithBlock(Block $block, Vector3 $start, Vector3 $end) : ?RayTraceResult{
        if($block instanceof Wool && !$this->isNotBE){
        	return null;
        }
		return $block->calculateIntercept($start, $end);
	}

}