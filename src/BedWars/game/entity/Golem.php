<?php

namespace BedWars\game\entity;

use pocketmine\event\entity\{EntityDamageByChildEntityEvent,EntityDamageEvent, EntityDamageByEntityEvent};
use pocketmine\block\{Block,Fence,FenceGate,Liquid,Stair,Air,Slab};
use pocketmine\math\{Math,Vector2,Vector3,VoxelRayTrace};
use pocketmine\entity\animation\ArmSwingAnimation;
use pocketmine\entity\Entity;
use pocketmine\entity\Location;
use pocketmine\item\Item;
use pocketmine\entity\effect\Effect;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\world\World;
use pocketmine\network\mcpe\protocol\ActorEventPacket;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use pocketmine\nbt\tag\CompoundTag;
use BedWars\game\Game;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\player\GameMode;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\entity\Living;


class Golem extends Living{

    public static function getNetworkTypeId() : string{ return EntityIds::IRON_GOLEM; }

    public const TARGET_MAX_DISTANCE = 30;

    public $target;

    public $arena = null;
    public $owner = null;
    public $timer = 0;
    public $deadtime = 120;

    public $speed = 0.2;

    public $attackDelay = 0;

    public $stayTime = 0;

    public $moveTime = 0;

    public function __construct(Location $location, CompoundTag $nbt){
        parent::__construct($location, $nbt);
    }

    protected function getInitialSizeInfo() : EntitySizeInfo{
        return new EntitySizeInfo(0.6, 1.8); //TODO
    }

    public function initEntity(CompoundTag $nbt): void{
        $this->setNameTagAlwaysVisible(true);
        $this->setNameTagVisible(true);
        $this->setHealth(60);
        $this->setMaxHealth(60);
        parent::initEntity($nbt);
    }

    public function getName(): string{
        return "Dream Defender";
    }

    public function entityBaseTick(int $tickDiff = 1): bool{
        if(!$this->isAlive() || $this->isClosed()){
            return false;
        }
        if(!$this->arena instanceof Game){
            $this->flagForDespawn();
            return false;
        }
        if(!$this->owner instanceof Player){
            $this->flagForDespawn();
            return false;
        }
        if(!$this->getWorld() instanceof World){
            $this->flagForDespawn();
            return false;
        }
        parent::entityBaseTick($tickDiff);

        $this->timer++;
        if($this->timer >= 20){
            $this->deadtime--;
            $this->timer = 0;
        }
        if($this->deadtime <= 0){
            //$this->flagForDespawn();
            $this->kill();
            return false;
        }
        $this->updateNametag();

        $this->updateMove($tickDiff);

        if($this->target instanceof Player){
            $this->checkEntity($this->target);
        }
        if($this->target instanceof Player){
            $this->attackEntity($this->target);
        }elseif(
            $this->target instanceof Vector3
            && $this->getLocation()->distanceSquared($this->target) <= 1
            && $this->motion->y == 0
        ){
            $this->moveTime = 0;
        }

        return true;
    }

    public function checkEntity(Entity $player): void{
        if($player instanceof Player){
            if($this->arena->getTeam($player) == $this->arena->getTeam($this->owner)){
                $this->target = null;
            } 
            if($player->getGamemode() !== GameMode::SURVIVAL() && $player->getGamemode() !== GameMode::ADVENTURE()){
                $this->target = null;
            }
            if($this->getLocation()->distance($player->getPosition()) > self::TARGET_MAX_DISTANCE){
                $this->target = null;
            }
        }
    }

    public function attackEntity(Living $player): void{
		if($player instanceof Player) {
			if ($this->attackDelay > 16 && $this->boundingBox->intersectsWith($player->getBoundingBox(), -1)) {

				$damage = 3;
				$ev = new EntityDamageByEntityEvent($this->owner, $player, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $damage);
				$player->attack($ev);
				$this->broadcastAnimation(new ArmSwingAnimation($this));

				$this->attackDelay = 0;
			}
			$this->attackDelay++;
		}
    }

    public function updateMove($tickDiff){
        if($this->getWorld() === null){
            return null;
        }

        $before = $this->target;
        $this->changeTarget();
        if($this->target instanceof Player || $this->target instanceof Block || $before !== $this->target && $this->target !== null){
            $x = $this->target->getPosition()->x - $this->getPosition()->x;
            $y = $this->target->getPosition()->y - ($this->getPosition()->y + $this->getEyeHeight());
            $z = $this->target->getPosition()->z - $this->getPosition()->z;

            $diff = abs($x) + abs($z);
            if($x ** 2 + $z ** 2 < 0.7){
                $this->motion->x = 0;
                $this->motion->z = 0;
            }elseif($diff > 0){
                $this->motion->x = $this->speed * 0.15 * ($x / $diff);
                $this->motion->z = $this->speed * 0.15 * ($z / $diff);
                $this->getLocation()->yaw = -atan2($x / $diff, $z / $diff) * 180 / M_PI;
            }
            $this->getLocation()->pitch = $y == 0 ? 0 : rad2deg(-atan2($y, sqrt($x ** 2 + $z ** 2)));
        }

        $dx = $this->motion->x * $tickDiff;
        $dz = $this->motion->z * $tickDiff;
        $isJump = false;
        $this->checkBlockIntersections();

        $bb = $this->boundingBox;

        $minX = (int) floor($bb->minX - 0.5);
        $minY = (int) floor($bb->minY - 0);
        $minZ = (int) floor($bb->minZ - 0.5);
        $maxX = (int) floor($bb->maxX + 0.5);
        $maxY = (int) floor($bb->maxY + 0);
        $maxZ = (int) floor($bb->maxZ + 0.5);

        for($z = $minZ; $z <= $maxZ; ++$z){
            for($x = $minX; $x <= $maxX; ++$x){
                for($y = $minY; $y <= $maxY; ++$y){
                    $block = $this->getWorld()->getBlockAt($x, $y, $z);
                    if(!$block->canBeFlowedInto()){
                        foreach($block->getCollisionBoxes() as $blockBB){
                            if($blockBB->intersectsWith($bb, -0.01)){
                                $this->isCollidedHorizontally = true;
                            }
                        }
                    }
                }
            }
        }

        if($this->isCollidedHorizontally or $this->isUnderwater()){
            $isJump = $this->checkJump($dx, $dz);
            $this->updateMovement();
        }
        if($this->stayTime > 0){
            $this->stayTime -= $tickDiff;
            $this->move(0, $this->motion->y * $tickDiff, 0);
        }else{
            $futureLocation = new Vector2($this->getPosition()->x + $dx, $this->getPosition()->z + $dz);
            $this->move($dx, $this->motion->y * $tickDiff, $dz);
            $myLocation = new Vector2($this->getPosition()->x, $this->getPosition()->z);
            if(($futureLocation->x != $myLocation->x || $futureLocation->y != $myLocation->y) && !$isJump){
                $this->moveTime -= 90 * $tickDiff;
            }
        }

        if(!$isJump){
            if($this->isOnGround()){
                $this->motion->y = 0;
            }elseif($this->motion->y > -$this->gravity * 4){
                if(!($this->getWorld()->getBlock(new Vector3(Math::floorFloat($this->getPosition()->x), (int) ($this->getPosition()->y + 0.8), Math::floorFloat($this->getPosition()->z))) instanceof Liquid)){
                    $this->motion->y -= $this->gravity * 1;
                }
            }else{
                $this->motion->y -= $this->gravity * $tickDiff;
            }
        }
        $this->move($this->motion->x, $this->motion->y, $this->motion->z);
        $this->updateMovement();

        parent::updateMovement();

        return $this->target;
    }

    private function checkJump($dx, $dz): bool{
        if($this->motion->y == $this->gravity * 2){
            return $this->getWorld()->getBlock(new Vector3(Math::floorFloat($this->getPosition()->x), (int) $this->getPosition()->y, Math::floorFloat($this->getPosition()->z))) instanceof Liquid;
        }else{
            if($this->getWorld()->getBlock(new Vector3(Math::floorFloat($this->getPosition()->x), (int) ($this->getPosition()->y + 0.8), Math::floorFloat($this->getPosition()->z))) instanceof Liquid){
                $this->motion->y = $this->gravity * 2;
                return true;
            }
        }
        if($this->motion->y > 0.1 or $this->stayTime > 0){
            return false;
        }
        if($this->getDirectionVector() === null){
            return false;
        }

        $blockingBlock = $this->getWorld()->getBlock($this->getPosition());
        if($blockingBlock->canBeFlowedInto()){
            $blockingBlock = $this->getTargetBlock(2);
        }
        if($blockingBlock != null and !$blockingBlock->canBeFlowedInto()){
            $upperBlock = $this->getWorld()->getBlock($blockingBlock->getPosition()->add(0,0,1));
            $secondUpperBlock = $this->getWorld()->getBlock($blockingBlock->getPosition()->add(0,0,2));

            if($upperBlock->canBeFlowedInto() && $secondUpperBlock->canBeFlowedInto()){
                if($blockingBlock instanceof Fence || $blockingBlock instanceof FenceGate){
                    $this->motion->y = $this->gravity;
                }else if($blockingBlock instanceof Slab or $blockingBlock instanceof Stair){
                    $this->motion->y = $this->gravity * 4;
                }else if($this->motion->y < ($this->gravity * 3.2)){ // Magic
                    $this->motion->y = $this->gravity * 3.2;
                }else{
                    $this->motion->y += $this->gravity * 0.25;
                }
                return true;
            }elseif(!$upperBlock->canBeFlowedInto()){
                $this->getLocation()->yaw = $this->getLocation()->getYaw() + mt_rand(-120, 120) / 10;
            }
        }
        return false;
    }

//     private function updateNametag(): void{ // this is soon!
//         $team = $this->arena->getTeam($this->owner);
//         $color = [
//             "red" => "§c",
//             "blue" => "§9",
//             "yellow" => "§e",
//             "green" => "§a" More Todo - ItsToxicGG
//         ];
//         $bar = "§fDream Defender {$this->deadtime}s";
//         $tag = "\n§r§f{$this->getHealth()}";
//         $this->setNameTag("" . $bar . "" . $tag . "");
//     }

    private function changeTarget(): void{
        if($this->target instanceof Player and $this->target->isAlive()){
            return;
        } 
        if(!$this->target instanceof Player || !$this->target->isAlive() || $this->target->isClosed()){
            foreach($this->getWorld()->getEntities() as $entity){
                if($entity === $this || !($entity instanceof Player) || $entity instanceof self){
                    continue;
                }
                if($entity->getGamemode() !== GameMode::ADVENTURE() && $entity->getGamemode() !== GameMode::SURVIVAL()){
                     continue;
                }
                if($this->arena->getTeam($entity) == $this->arena->getTeam($this->owner)){
                     continue;
                }

                $this->target = $entity;
            }
        }
    }

    public function getTargetBlock(int $maxDistance, array $transparent = []): ?Block{
        $line = $this->getLineOfSight($maxDistance, 1, $transparent);
        if(!empty($line)){
            return array_shift($line);
        }

        return null;
    }

    /**
     * @param int   $maxDistance
     * @param int   $maxLength
     * @param array $transparent
     *
     * @return Block[]
     */
    public function getLineOfSight(int $maxDistance, int $maxLength = 0, array $transparent = []) : array{
        if($maxDistance > 120){
            $maxDistance = 120;
        }

        if(count($transparent) === 0){
            $transparent = null;
        }

        $blocks = [];
        $nextIndex = 0;

        foreach(VoxelRayTrace::inDirection($this->getPosition(), $this->getDirectionVector(), $maxDistance) as $vector3){
            $block = $this->getWorld()->getBlockAt($vector3->x, $vector3->y, $vector3->z);
            $blocks[$nextIndex++] = $block;

            if($maxLength !== 0 and count($blocks) > $maxLength){
                array_shift($blocks);
                --$nextIndex;
            }

            $id = $block->getId();

            if($transparent === null){
                if($id !== 0){
                    break;
                }
            }else{
                if(!isset($transparent[$id])){
                    break;
                }
            }
        }

        return $blocks;
    }

    public function attack(EntityDamageEvent $source): void{
        if($this->noDamageTicks > 0){
            $source->cancel();
        }elseif($this->attackTime > 0){
            $lastCause = $this->getLastDamageCause();
            if($lastCause !== null and $lastCause->getBaseDamage() >= $source->getBaseDamage()){
                $source->cancel();
            }
        }
        if($source instanceof EntityDamageByEntityEvent){
            if($this->arena->getTeam($source->getDamager()) == $this->arena->getTeam($this->owner)){
                $source->cancel();
            } 
            $source->setKnockback(0.1);
        }
        parent::attack($source);
    }

    public function getXpDropAmount(): int {
        return 0;
    }
} 
