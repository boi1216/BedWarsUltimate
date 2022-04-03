<?php

namespace BedWars\game\structure\popup_tower;

use pocketmine\block\Block;
use pocketmine\block\BlockFactory;
use pocketmine\block\BlockLegacyIds;
use BedWars\game\Game;
use pocketmine\math\Vector3;
use BedWars\game\Team;
use pocketmine\math\Facing;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use BedWars\BedWars;
use BedWars\utils\Utils;

class PopupTower{
    
	public function __construct(Block $block, Game $game, Player $player, Team $team) {
         $rotation = $player->getHorizontalFacing();
         $instructions = TowerInstructions::TOWER_SOUTH;
         if($rotation == Facing::SOUTH){
           $instructions = TowerInstructions::TOWER_SOUTH;
         }else if($rotation == Facing::NORTH){
             $instructions = TowerInstructions::TOWER_NORTH;
         }else if($rotation == Facing::WEST){
           $instructions = TowerInstructions::TOWER_WEST;
         }else if($rotation == Facing::EAST){
             $instructions = TowerInstructions::TOWER_EAST;
         }

         $pos = $block->getPosition();
         $x = $pos->getX();
         $y = $pos->getY();
         $z = $pos->getZ();

         $cancel = false;

         foreach($instructions as $instruction){
         	$inX = $instruction[0];
            $inY = $instruction[1];
            $inZ = $instruction[2];
         	$insBlock = $player->getWorld()->getBlockAt($x + $inX, $y + $inY, $z + $inZ);
         	if($insBlock->getId() !== BlockLegacyIds::AIR){
         		$insVec = $insBlock->getPosition()->asVector3();
         		if(!in_array(Utils::vectorToString(":", $insVec), $game->placedBlocks)){
         			$player->sendMessage(TextFormat::RED . "Cannot place PopupTower due to collision with map block(s)");
         			$cancel = true;
                    return;
         		}
         	}
         }
         
         if($cancel)return;
         BedWars::getInstance()->getScheduler()->scheduleRepeatingTask(new TowerConstructTask($block, $instructions, $player->getWorld(), $team, $game), 1);

	}

}