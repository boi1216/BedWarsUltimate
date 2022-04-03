<?php


namespace BedWars;

use pocketmine\scheduler\Task;
use pocketmine\world\World;
use pocketmine\math\Vector3;
use pocketmine\block\tile\Sign;
use pocketmine\utils\TextFormat;
use pocketmine\block\utils\SignText;

class SignUpdater extends Task
{

    /** @var BedWars $plugin */
    private $plugin;

    public function __construct(BedWars $plugin)
    {
        $this->plugin = $plugin;
    }

    public function onRun() : void
    {
        foreach ($this->plugin->signs as $arena => $positions) {
            foreach ($positions as $position) {
                $pos = explode(":", $position);
                $world = $this->plugin->getServer()->getWorldManager()->getWorldByName($pos[3]);

                if (!$world instanceof World) {
                    continue;
                }

                if (!in_array($arena, array_keys($this->plugin->games))) {
                    continue;
                }

                $game = $this->plugin->games[$arena];
                $tile = $world->getTileAt(intval($pos[0]), intval($pos[1]), intval($pos[2]));
                if (!$tile instanceof Sign) {
                    continue;
                }

                $tile->setText(new SignText(array(TextFormat::BOLD . TextFormat::DARK_RED . "BedWars",
                    TextFormat::AQUA . "[" . count($game->players) . "/" . $game->getMaxPlayers() . "]",
                    TextFormat::BOLD . TextFormat::GOLD . $game->getMapName(),
                    $this->getStatus($game->getState()))));
                $world->setBlockAt(intval($pos[0]), intval($pos[1]), intval($pos[2]), $world->getBlockAt(intval($pos[0]), intval($pos[1]), intval($pos[2])));



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