<?php

namespace BedWars\game\structure\popup_tower;

use pocketmine\scheduler\Task;
use pocketmine\block\Block;
use pocketmine\block\Ladder;
use pocketmine\world\World;
use pocketmine\block\BlockFactory;
use pocketmine\block\BlockLegacyIds;
use pocketmine\math\Vector3;

use BedWars\utils\Utils;
use BedWars\game\Team;
use BedWars\game\Game;
use BedWars\BedWars;

class TowerConstructTask extends Task{

	private $block;
	private $instructions;
	private $world;
	private $team;
	private $game;

	private $insDone = 0;
	private $insToD = 0;

	public function __construct(Block $block, $instructions, World $world, Team $team, Game $game){
        $this->block = $block;
        $this->instructions = $instructions;
        $this->world = $world;
        $this->insToD = count($instructions);
        $this->team = $team;
        $this->game = $game;
	}

	public function onRun() : void{
		 $pos = $this->block->getPosition();
         $x = $pos->getX();
         $y = $pos->getY();
         $z = $pos->getZ();

         $instruction = $this->instructions[$this->insDone];
         $inX = $instruction[0];
         $inY = $instruction[1];
         $inZ = $instruction[2];
         $isLadder = isset($instruction[3]) ? true: false;
         $this->insDone++;
         $vector = new Vector3($x + $inX, $y + $inY, $z + $inZ);
         $this->game->placedBlocks[] = Utils::vectorToString(":", $vector);
         if(!$isLadder){
            $this->world->setBlock($vector, BlockFactory::getInstance()->get(BlockLegacyIds::WOOL, Utils::colorIntoWool($this->team->getColor())));
         }else{
         	$block = BlockFactory::getInstance()->get(BlockLegacyIds::LADDER, 0);
         	if($block instanceof Ladder){
         		$this->world->setBlock($vector, $block->setFacing($instruction[3]));
         	}
         }
         if($this->insDone >= $this->insToD){
             $this->getHandler()->cancel();    
         }

	}
}