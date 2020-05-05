<?php


namespace BedWars\game;

use pocketmine\scheduler\Task;

class GameTick extends Task
{

    private $plugin;

    /**
     *  constructor.
     * @param Game $plugin
     */
    public function __construct(Game $plugin)
    {
        $this->plugin = $plugin;
    }

    public function onRun(int $currentTick)
    {
        $this->plugin->tick();
    }

}