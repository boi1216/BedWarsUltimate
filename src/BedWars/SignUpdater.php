<?php


namespace BedWars;

use pocketmine\scheduler\Task;
use pocketmine\level\Level;
use pocketmine\math\Vector3;
use pocketmine\tile\Sign;
use pocketmine\utils\TextFormat;

class SignUpdater extends Task
{

    /** @var BedWars $plugin */
    private $plugin;

    public function __construct(BedWars $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * @param int $currentTick
     */
    public function onRun(int $currentTick) : void
    {
        foreach ($this->plugin->signs as $arena => $positions) {
            foreach ($positions as $position) {
                $pos = explode(":", $position);
                $vector = new Vector3(intval($pos[0]), intval($pos[1]), intval($pos[2]));

                $level = $this->plugin->getServer()->getLevelByName($pos[3]);

                if (!$level instanceof Level) {
                    continue;
                }

                if (!in_array($arena, array_keys($this->plugin->games))) {
                    continue;
                }

                $game = $this->plugin->games[$arena];
                $tile = $level->getTile($vector);
                if (!$tile instanceof Sign) {
                    continue;
                }

                $tile->setText(TextFormat::BOLD . TextFormat::DARK_RED . "BedWars",
                    TextFormat::AQUA . "[" . count($game->players) . "/" . $game->getMaxPlayers() . "]",
                    TextFormat::BOLD . TextFormat::GOLD . $game->getMapName(),
                    $this->getStatus($game->getState()));


            }
        }
    }

    /**
     * @param int $state
     * @return string
     */
    public function getStatus(int $state) : string{
        switch($state){
            case 0;
                return TextFormat::YELLOW . "Touch Me";
            case 1;
                return TextFormat::RED . "InGame";
        }
        return "";
    }

}