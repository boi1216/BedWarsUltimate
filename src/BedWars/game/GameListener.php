<?php


namespace BedWars\game;

use BedWars\BedWars;
use BedWars\game\shop\ItemShop;
use BedWars\game\shop\UpgradeShop;
use BedWars\utils\Utils;
use pocketmine\block\Bed;
use pocketmine\block\Block;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\entity\EntityExplodeEvent;
use pocketmine\item\Item;
use pocketmine\item\ItemIds;
use pocketmine\entity\object\PrimedTNT;
use pocketmine\network\mcpe\protocol\ModalFormResponsePacket;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use pocketmine\math\Vector3;
use pocketmine\event\player\PlayerCommandPreprocessEvent;


class GameListener implements Listener
{

    /** @var BedWars $plugin */
    private $plugin;

    /**
     * GameListener constructor.
     * @param BedWars $plugin
     */
    public function __construct(BedWars $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * @param SignChangeEvent $event
     */
    public function onSignChange(SignChangeEvent $event) : void{
        $player = $event->getPlayer();
        $sign = $event->getSign();
        $text = $event->getNewText();
        if($text->getLine(0) == "[bedwars]" && $text->getLine(1) !== ""){
            if(!in_array($text->getLine(1), array_keys($this->plugin->games))){
                $player->sendMessage($text->getLine(1));
              //  $player->sendMessage(BedWars::PREFIX . TextFormat::YELLOW . "Arena doesn't exist!");
                return;
            }

            $dataFormat = $sign->getPosition()->getX() . ":" . $sign->getPosition()->getY() . ":" . $sign->getPosition()->getZ() . ":" . $player->getWorld()->getFolderName();
            $this->plugin->signs[$text->getLine(1)][] = $dataFormat;

            $location = $this->plugin->getDataFolder() . "arenas/" . $text->getLine(1) . ".json";
            if(!is_file($location)){
                //wtf ??
                return;
            }

            $fileContent = file_get_contents($location);
            $jsonData = json_decode($fileContent, true);
            $positionData = [
                "signs" => $this->plugin->signs[$text->getLine(1)]
            ];

            file_put_contents($location, json_encode(array_merge($jsonData, $positionData)));
            $player->sendMessage(BedWars::PREFIX . TextFormat::GREEN . "Sign created");

        }
    }

    public function onExplode(EntityExplodeEvent $ev) : void{
        $entity = $ev->getEntity();
        if(!$entity instanceof PrimedTNT)return;
        $level = $entity->level;
        $game = null;
        foreach ($level->getPlayers() as $player) {
            if($g = $this->plugin->getPlayerGame($player) !== null){
                $game = $g;
            }
        }
        if($game == null)return;

        $newList = array();

        foreach($ev->getBlockList() as $block){
            if(in_array(Utils::vectorToString(":", $block->asVector3()), $game->placedBlocks)){
                $newList[] = $block;
            }
        }
        $ev->setBlockList($newList);
    }

    /**
     * @param PlayerInteractEvent $event
     */
    public function onInteract(PlayerInteractEvent $event) : void{
        $player = $event->getPlayer();
        $block = $event->getBlock();

        foreach($this->plugin->signs as $arena => $positions){
            foreach($positions as $position) {
                $pos = explode(":", $position);
                if ($block->getPosition()->getX() == $pos[0] && $block->getPosition()->getY() == $pos[1] && $block->getPosition()->getZ() == $pos[2] && $player->getWorld()->getFolderName() == $pos[3]) {
                    $game = $this->plugin->games[$arena];
                    $game->join($player);
                    return;
                }
            }
        }

        $item = $event->getItem();

        if($item->getId() == ItemIds::WOOL){
            $teamColor = Utils::woolIntoColor($item->getMeta());

            $playerGame = $this->plugin->getPlayerGame($player);
            if($playerGame == null || $playerGame->getState() !== Game::STATE_LOBBY)return;

          /*  if(!$player->hasPermission('lobby.ranked')){
                $player->sendMessage(BedWars::PREFIX . TextFormat::YELLOW . "You don't have permission to use this");
                return;
            }*/

            $playerTeam = $this->plugin->getPlayerTeam($player);
            if($playerTeam !== null){
                $player->sendMessage(BedWars::PREFIX . TextFormat::YELLOW . "You are already in a team!");
                return;
            }

            foreach($playerGame->teams as $team){
                if($team->getColor() == $teamColor){

                    if(count($team->getPlayers()) >= $playerGame->playersPerTeam){
                        $player->sendMessage(BedWars::PREFIX . TextFormat::RED . "Team is full");
                        return;
                    }
                    $team->add($player);
                    $player->sendMessage(BedWars::PREFIX . TextFormat::GRAY . "You've joined team " . $teamColor . $team->getName());
                }
            }
        }elseif($item->getId() == ItemIds::COMPASS){
            $playerGame = $this->plugin->getPlayerGame($player);
            if($playerGame == null)return;

            if($playerGame->getState() == Game::STATE_RUNNING){
                 $playerGame->trackCompass($player);
            }elseif($playerGame->getState() == Game::STATE_LOBBY){
                $playerGame->quit($player);
                $player->teleport($this->plugin->getServer()->getWorldManager()->getDefaultWorld()->getSafeSpawn());
                $player->getInventory()->clearAll();
            }
        }
    }

    /**
     * @param PlayerQuitEvent $event
     */
    public function onQuit(PlayerQuitEvent $event) : void{
        $player = $event->getPlayer();
        foreach($this->plugin->games as $game){
            if(in_array($player->getName(), array_keys(array_merge($game->players, $game->spectators)))){
                $game->quit($player);
            }
        }
    }

    /**
     * @param EntityLevelChangeEvent $event
     */
    public function onEntityLevelChange(EntityTeleportEvent $event) : void{
        $player = $event->getEntity();
        if(!$player instanceof Player){
            return;
        }

        $playerGame = $this->plugin->getPlayerGame($player);
        if($playerGame !== null && $event->getTo()->getWorld()->getFolderName() !== $playerGame->worldName)$playerGame->quit($player);
    }

    /**
     * @param PlayerMoveEvent $event
     */
    public function onMove(PlayerMoveEvent $event) : void
    {
        $player = $event->getPlayer();
        foreach ($this->plugin->games as $game) {
            if (isset($game->players[$player->getName()])) {
                if ($game->getState() == Game::STATE_RUNNING) {
                    if($player->getPosition()->getY() < $game->getVoidLimit() && !$player->isSpectator()){
                        $game->killPlayer($player);
                        $playerTeam = $this->plugin->getPlayerTeam($player);
                        $game->broadcastMessage($playerTeam->getColor() . $player->getName() . " " . TextFormat::GRAY . "was killed by void");
                    }
                }
            }
        }
    }

    /**
     * @param BlockBreakEvent $event
     */
    public function onBreak(BlockBreakEvent $event) : void
    {
        $player = $event->getPlayer();
        $block = $event->getBlock();

        if(isset($this->plugin->bedSetup[$player->getName()])){
            if(!$event->getBlock() instanceof Bed){
                $player->sendMessage(BedWars::PREFIX . TextFormat::YELLOW . "The block is not bed!");
                return;
            }
            $setup = $this->plugin->bedSetup[$player->getName()];

            $step =  (int)$setup['step'];

            $location = $this->plugin->getDataFolder() . "arenas/" . $setup['game'] . ".json";
            $fileContent = file_get_contents($location);
            $jsonData = json_decode($fileContent, true);

            $jsonData['teamInfo'][$setup['team']]['bedPos' . $step] = $block->getX() . ":" . $block->getY() . ":" . $block->getZ();
            file_put_contents($location, json_encode($jsonData));

            $player->sendMessage(BedWars::PREFIX . TextFormat::GREEN . "Bed $step has been set!");

            if($step == 2){
                unset($this->plugin->bedSetup[$player->getName()]);
                return;
            }

            $this->plugin->bedSetup[$player->getName()]['step']+=1;

            return;
        }

        $playerGame = $this->plugin->getPlayerGame($player);
        if($playerGame !== null){
            if($playerGame->getState() == Game::STATE_LOBBY){
                $event->cancel();
            }elseif($event->getBlock() instanceof Bed){
                $blockPos = $event->getBlock()->asPosition();

                $game = $this->plugin->getPlayerGame($player);
                $team = $this->plugin->getPlayerTeam($player);
                if($team == null || $game == null)return;

                foreach($game->teamInfo as $name => $info){
                    $bedPos = Utils::stringToVector(":", $info['bedPos1']);
                    $teamName = "";

                    if($bedPos->x == $blockPos->x && $bedPos->y == $blockPos->y && $bedPos->z == $blockPos->z){
                        $teamName = $name;
                    }else{
                        $bedPos = Utils::stringToVector(":", $info['bedPos2']);
                        if($bedPos->x == $blockPos->x && $bedPos->y == $blockPos->y && $bedPos->z == $blockPos->z){
                            $teamName = $name;
                        }
                    }

                    if($teamName !== ""){
                        $teamObject = $game->teams[$name];
                        if($name == $this->plugin->getPlayerTeam($player)->getName()){
                            $player->sendMessage(TextFormat::RED . "You can't break your bed!");
                            $event->cancel();
                            return;
                        }
                        $event->setDrops([]);
                        $game->breakBed($teamObject, $player);

                    }
                }
            }else{
                if($playerGame->getState() == Game::STATE_RUNNING){
                    if(!in_array(Utils::vectorToString(":", $block->asVector3()), $playerGame->placedBlocks)){
                        $event->cancel();
                    }
                }
            }
        }
    }

    /**
     * @param BlockPlaceEvent $event
     */
    public function onPlace(BlockPlaceEvent $event) : void{
        $player = $event->getPlayer();
        $playerGame = $this->plugin->getPlayerGame($player);
        if($playerGame !== null){
            if($playerGame->getState() == Game::STATE_LOBBY){
                $event->cancel();
            }elseif($playerGame->getState() == Game::STATE_RUNNING){
                foreach($playerGame->teamInfo as $team => $data){
                    $spawn = Utils::stringToVector(":", $data['SpawnPos']);
                    if($spawn->distance($event->getBlock()) < 6){
                        $event->cancel();
                    }else{
                        $playerGame->placedBlocks[] = Utils::vectorToString(":", $event->getBlock());
                    }
                }

                if($event->getBlock()->getId() == Block::TNT){
                    $event->getBlock()->ignite();
                }
            }
        }
    }

    /**
     * @param EntityDamageEvent $event
     */
    public function onDamage(EntityDamageEvent $event) : void{
        $entity = $event->getEntity();
        foreach ($this->plugin->games as $game) {
            if ($entity instanceof Player && isset($game->players[$entity->getName()])) {

                if($game->getState() == Game::STATE_LOBBY){
                     $event->cancel();
                     return;
                }

                if($event instanceof EntityDamageByEntityEvent){
                    $damager = $event->getDamager();

                    if(!$damager instanceof Player)return;

                    if(isset($game->players[$damager->getName()])){
                        $damagerTeam = $this->plugin->getPlayerTeam($damager);
                        $playerTeam = $this->plugin->getPlayerTeam($entity);

                        if($damagerTeam->getName() == $playerTeam->getName()){
                            $event->cancel();
                        }
                    }
                }

                if($event->getFinalDamage() >= $entity->getHealth()){
                    $game->killPlayer($entity);
                    $event->cancel();


                }

            }elseif(isset($game->npcs[$entity->getId()])){
                $event->cancel();

                if($event instanceof EntityDamageByEntityEvent){
                    $damager = $event->getDamager();

                    if($damager instanceof Player){
                        $npcTeam = $game->npcs[$entity->getId()][0];
                        $npcType = $game->npcs[$entity->getId()][1];

                        if(($game = $this->plugin->getPlayerGame($damager)) == null){
                            return;
                        }

                        if($game->getState() !== Game::STATE_RUNNING){
                            return;
                        }

                        $playerTeam = $this->plugin->getPlayerTeam($damager)->getName();
                        if($npcTeam !== $playerTeam && $npcType == "upgrade"){
                            $damager->sendMessage(TextFormat::RED . "You can upgrade only your base!");
                            return;
                        }

                        if($npcType == "upgrade"){
                            UpgradeShop::sendDefaultShop($damager);
                        }else{
                            ItemShop::sendDefaultShop($damager);
                        }
                    }
                }
            }
        }
    }

    public function onCommandPreprocess(PlayerCommandPreprocessEvent $event) : void{
          $player = $event->getPlayer();

          $game = $this->plugin->getPlayerGame($player);

          if($game == null)return;

          $args = explode(" ", $event->getMessage());

          if($args[0] == '/fly' || isset($args[1]) && $args[1] == 'join'){
              $player->sendMessage(TextFormat::RED . "You cannot run this in-game!");
              $event->cancel();
          }
    }



    /**
     * @param DataPacketReceiveEvent $event
     */
    public function handlePacket(DataPacketReceiveEvent $event) : void{
        $packet = $event->getPacket();
        $player = $event->getOrigin()->getPlayer();



        if($packet instanceof ModalFormResponsePacket){
            $playerGame = $this->plugin->getPlayerGame($player);
            if($playerGame == null)return;
              $data = json_decode($packet->formData);
              if (is_null($data)) {
                return;
              }
                if($packet->formId == 50) {
                    ItemShop::sendPage($player, intval($data));
                }elseif($packet->formId < 100){
                    ItemShop::handleTransaction(($packet->formId), json_decode($packet->formData), $player, $this->plugin);
                }elseif($packet->formId == 100){
                    UpgradeShop::sendBuyPage(json_decode($packet->formData), $player, $this->plugin);
                }elseif($packet->formId > 100){
                    UpgradeShop::handleTransaction(($packet->formId), $player, $this->plugin);
                }
            }
    }
}