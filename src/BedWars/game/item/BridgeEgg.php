<?php

namespace BedWars\game\item;

use pocketmine\item\Egg;
use pocketmine\entity\Location;
use pocketmine\entity\projectile\Throwable;
use pocketmine\player\Player;

use BedWars\game\entity\BridgeEgg as BridgeEggEntity;
class BridgeEgg extends Egg {

	public function createEntity(Location $location, Player $thrower) : Throwable{
        return new BridgeEggEntity($location, $thrower);
	}
}