<?php

namespace BedWars\game\structure\popup_tower;

use pocketmine\block\Block;
use pocketmine\block\BlockFactory;
use pocketmine\block\BlockLegacyIds;
use BedWars\game\Game;
use pocketmine\math\Vector3;
use BedWars\game\Team;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use BedWars\BedWars;
use BedWars\utils\Utils;

class PopupTower{
    
	public function __construct(Block $block, Game $game, Player $player, Team $team) {
         $rotation = ($player->getLocation()->getYaw() - 90) % 360;
         if($rotation < 0){
         	$rotation += 360;
         } 
         $instructions = TowerInstructions::TOWER_SOUTH;
         if(45 <= $rotation && $rotation < 135){
           $instructions = TowerInstructions::TOWER_SOUTH;
         }else if(225 <= $rotation && $rotation < 315){
             $instructions = TowerInstructions::TOWER_NORTH;
         }else if(135 <= $rotation && $rotation < 225){
           $instructions = TowerInstructions::TOWER_WEST;
         }else if($rotation < 45){
             $instructions = TowerInstructions::TOWER_EAST;
         }else if(315 <= $rotation && $rotation < 360){
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