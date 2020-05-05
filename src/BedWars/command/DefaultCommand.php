<?php


namespace BedWars\command;


use BedWars\BedWars;
use BedWars\game\Game;
use BedWars\utils\Utils;
use pocketmine\block\Block;
use pocketmine\block\TNT;
use pocketmine\command\CommandSender;
use pocketmine\command\PluginCommand;
use pocketmine\item\Bed;
use pocketmine\level\Level;
use pocketmine\Player;
use pocketmine\plugin\Plugin;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\math\Vector3;

//form api
use jojoe77777\FormAPI\CustomForm;
use jojoe77777\FormAPI\SimpleForm;
use jojoe77777\FormAPI\Form;


class DefaultCommand extends PluginCommand
{

    /** @var array $errors */
    private $cachedCommandResponse = array();
    /** @var array $cachedResponses */
    private $cachedFormResponse = array();

    /**
     * DefaultCommand constructor.
     * @param BedWars $owner
     */
    public function __construct(BedWars $owner)
    {
        parent::__construct("bedwars", $owner);
        parent::setDescription("BedWars command");
        parent::setPermission("bedwars.command");
    }

    /**
     * @param Player $player
     * @param string $command
     * @return array|null
     */
    public function getErrorsForCommand(Player $player, string $command) : ?array{
        if(!isset($this->cachedCommandResponse[$player->getRawUniqueId()]))return null;
        $errors = $this->cachedCommandResponse[$player->getRawUniqueId()];
        if($errors['command'] == $command && count($errors['errors']) > 0){
                return $errors['errors'];
        }
        return null;
    }

    /**
     * @param Player $player
     * @param string $command
     * @return array|null
     */
    public function getValuesForCommand(Player $player, string $command) : ?array{
        if(!isset($this->cachedCommandResponse[$player->getRawUniqueId()]))return null;
        $values = $this->cachedCommandResponse[$player->getRawUniqueId()];
        if($values['command'] == $command && count($values['values']) > 0){
            return $values['values'];
        }
        return null;
    }

    public function sendFormCustom(Player $player, CustomForm $form, string $command) : void{
        $errors = $this->getErrorsForCommand($player, $command);
        $values = $this->getValuesForCommand($player, $command);

        $form->setTitle("BedWars: Setup Manager");
        switch ($command){
            case "create";
            $form->addInput(isset($errors[0]) ? "GameID: " . $errors[0] : "Game ID", "String/Integer", isset($values[0]) ? $values[0] : "");
            $form->addInput(isset($errors[1]) ? "Minimum players: " . $errors[1] : "Minimum players", "Integer", isset($values[1]) ? $values[1] : "");
            $form->addInput(isset($errors[2]) ? "Players per team: " . $errors[2] : "Players per team", "Integer", isset($values[2]) ? $values[2] : "");
            $form->addInput(isset($errors[3]) ? "Start time: " . $errors[3] : "Start time", "Integer", isset($values[3]) ? $values[3] : "");
            $form->addInput(isset($errors[4]) ? "Map name: " . $errors[4] : "Map name", "String", isset($values[4]) ? $values[4] : "");
            $form->addDropdown("Teams", array_keys(BedWars::TEAMS));
            $form->sendToPlayer($player);
            break;
            case 'addteam';
            $form->addInput(isset($errors[0]) ? "GameID: " . $errors[0] : "Game ID", "String/Integer", isset($values[0]) ? $values[0] : "");
            $form->addDropdown(isset($errors[1]) ? "Team: " . $errors[1] : "Team", array_keys(BedWars::TEAMS), isset($values[1]) ? $values[1] : null);
            $form->sendToPlayer($player);
            break;
            case "delete";
            $form->addInput(isset($errors[0]) ? "GameID: " . $errors[0] : "Game ID", "String/Integer", isset($values[0]) ? $values[0] : "");
            $form->sendToPlayer($player);
            break;
            case "setlobby";
            $form->addInput(isset($errors[0]) ? "GameID: " . $errors[0] : "Game ID", "String/Integer", isset($values[0]) ? $values[0] : "");
            $form->addInput(isset($errors[1]) ? "Coord X: " . $errors[0] : "Coord X", "Integer/Float", isset($values[1]) ? $values[1] : $player->getX());
            $form->addInput(isset($errors[2]) ? "Coord Y: " . $errors[0] : "Coord Y", "Integer/Float", isset($values[2]) ? $values[2] : $player->getY());
            $form->addInput(isset($errors[3]) ? "Coord Z: " . $errors[0] : "Coord Z", "Integer/Float", isset($values[3]) ? $values[3] : $player->getZ());
            $form->addInput(isset($errors[4]) ? "Level name: " . $errors[4] : "Level name", "String", isset($values[4]) ? $values[4] : "");
            $form->sendToPlayer($player);
            break;
            case "setposition";
            $form->addInput(isset($errors[0]) ? "GameID: " . $errors[0] : "Game ID", "String/Integer", isset($values[0]) ? $values[0] : "");
            $form->addDropdown(isset($errors[1]) ? "Team: " . $errors[1] : "Team", array_keys(BedWars::TEAMS), isset($values[1]) ? $values[1] : null);
            $form->addDropdown("Position", array('ShopClassic', 'ShopUpgrades', 'Spawn'), isset($values[2]) ? $values[2] : null);
            $form->addInput(isset($errors[0]) ? "Coord X: " . $errors[0] : "Coord X", "Integer/Float", isset($values[1]) ? $values[1] : $player->getX());
            $form->addInput(isset($errors[0]) ? "Coord Y: " . $errors[0] : "Coord Y", "Integer/Float", isset($values[2]) ? $values[2] : $player->getY());
            $form->addInput(isset($errors[0]) ? "Coord Z: " . $errors[0] : "Coord Z", "Integer/Float", isset($values[3]) ? $values[3] : $player->getZ());
            $form->sendToPlayer($player);
            break;
            case "setbed";
            $form->addInput(isset($errors[0]) ? "GameID: " . $errors[0] : "Game ID", "String/Integer", isset($values[0]) ? $values[0] : "");
            $form->addDropdown(isset($errors[1]) ? "Team: " . $errors[1] : "Team", array_keys(BedWars::TEAMS), isset($values[1]) ? $values[1] : null);
            $form->sendToPlayer($player);
            break;
            case "setgenerator";
            $form->addInput(isset($errors[0]) ? "GameID: " . $errors[0] : "Game ID", "String/Integer", isset($values[0]) ? $values[0] : "");
            $form->addDropdown(isset($errors[1]) ? "Team: " . $errors[1] : "Team", array_keys(BedWars::TEAMS), isset($values[1]) ? $values[1] : null);
            $form->addDropdown(isset($errors[2]) ? "Type: " . $errors[2] : "Type", array('Diamond, Emerald, Gold, Iron'), isset($values[2]) ? $values[2] : null);
            $form->sendToPlayer($player);
            break;
        }

        if($errors !== null) {
            unset($this->cachedCommandResponse[$player->getRawUniqueId()]);
        }
        $this->cachedFormResponse[$command] = $form;
        $refOb = new \ReflectionObject($this->cachedFormResponse[$command]);
        $property = $refOb->getProperty('data');
        $property->setAccessible(true);
        $clonedData = $property->getValue($this->cachedFormResponse[$command]);
        $clonedData['content'] = [];
        $property->setValue($this->cachedFormResponse[$command], $clonedData);
    }

    /**
     * @param CommandSender $sender
     * @param string $commandLabel
     * @param array $args
     * @return bool|mixed|void
     */
    public function execute(CommandSender $sender, string $commandLabel, array $args)
    {
        if(empty($args[0])){

            return;
        }

        switch(strtolower($args[0])){
            case "list";
            commandList:
            $listForm = new SimpleForm(function (Player $player, ?array $data){
                if($data === null) {
                    return;
                }

                $gameClicked = $this->getPlugin()->games((int)$data);

            });

            foreach($this->getPlugin()->games as $game){
                $listForm->addButton(TextFormat::YELLOW . $game->getName() . "\n" . TextFormat::RESET . "Click to edit");
            }
            break;
            case "create";
            if(!$sender instanceof Player){
                $sender->sendMessage(TextFormat::RED . "This command can be executed only in game");
                return;
            }

            commandCreate:

            $createForm = new CustomForm(function(Player $player, array $data){
                if($data === null) {
                    return;
                }

                $error = array();

                if(isset($data[0]) && $data[0] !== ""){
                    if(strlen($data[0]) < 5){
                        $error[0] = TextFormat::RED . "Too short";
                        goto b;
                    }

                    if($this->getPlugin()->gameExists($data[0])){
                        $error[0] = TextFormat::RED . "Already exists";
                    }
                }else{
                    $error[0] = TextFormat::RED . "Column can't be blank";
                }
                b:

                if(isset($data[1]) && $data[1] !== ""){
                    if(!is_int((int)$data[1])){
                        $error[1] = TextFormat::RED . "Must be an Integer";
                        goto c;
                    }

                    if((int)$data[1] < 1){
                        $error[1] = TextFormat::RED . "Must be higher than 0";
                    }
                }else{
                    $error[1] = TextFormat::RED . "Column can't be blank";
                }
                c:

                if(isset($data[2]) && $data[2] !== ""){
                    if(!is_int((int)$data[2])){
                        $error[2] = TextFormat::RED . "Must be an Integer";
                        goto d;
                    }

                    if((int)$data[2] < 1){
                        $error[2] = TextFormat::RED . "Must be higher than 0";
                    }
                }else{
                    $error[2] = TextFormat::RED . "Column can't be blank";
                }
                d:

                if(isset($data[3]) && $data[3] !== ""){
                    if(!is_int((int)$data[3])){
                        $error[3] = TextFormat::RED . "Must be an Integer";
                        goto e;
                    }

                    if((int)$data[3] < 1){
                        $error[3] = TextFormat::RED . "Must be higher than 0";
                    }

                }else{
                    $error[3] = TextFormat::RED . "Column can't be blank";
                }

                if(isset($data[4]) && $data[4] !== ""){
                    if(strlen($data[4]) <= 1){
                        $error[4] = TextFormat::RED . "Too short";
                    }
                }else{
                    $error[4] = TextFormat::RED . "Column can't be blank";
                }
                e:

                if(count($error) > 0){
                    $this->cachedCommandResponse[$player->getRawUniqueId()] = array('command' => 'create', 'errors' => $error, 'values' => $data);
                    $this->sendFormCustom($player, $this->cachedFormResponse['create'], 'create');
                }else{
                    $this->getPlugin()->createGame($data[0], $data[1], $data[2], $data[3]);
                    $player->sendMessage(TextFormat::GREEN . "Game created");
                }
            });
            $this->sendFormCustom($sender, $createForm, 'create');
            break;
            case "addteam";
            if(!$sender instanceof Player){
                $sender->sendMessage(TextFormat::RED . "This command can be executed only in game");
                return;
            }

            commandAddteam:

            $addteamForm = new CustomForm(function(Player $player, array $data){
                if($data === null){
                    return;
                }

                $error = array();
                if(isset($data[0]) && $data[0] !== ""){
                    if(!$this->getPlugin()->gameExists($data[0])){
                        $error[0] = TextFormat::RED . "Doesn't exist";
                    }
                }else{
                    $error[0] = TextFormat::RED . "Column can't be blank";
                }
                if(isset($data[1]) && $data[1] !== ""){
                    if(!in_array(strtolower($data[1]), array_keys(BedWars::TEAMS))){
                        $error[1] = TextFormat::RED . "Invalid team";
                    }

                    if($this->getPlugin()->teamExists($data[0], $data[1])){
                        $error[1] = TextFormat::RED . "Already exists for " . $data[0];
                    }
                }else{
                    $error[1] = TextFormat::RED . "Column can't be blank";
                }

                if(count($error) > 0){
                    $this->cachedCommandResponse[$player->getRawUniqueId()] = array('command' => 'addteam', 'errors' => $error, 'values' => $data);
                    $this->sendFormCustom($player, $this->cachedFormResponse['addteam'], 'addteam');
                }else{
                    $this->getPlugin()->addTeam($data[0], $data[1]);
                    $player->sendMessage(TextFormat::GREEN . "Team added");
                }
            });
            $this->sendFormCustom($sender, $addteamForm, 'addteam');
            break;
            case "delete";
            if(!$sender instanceof Player){
                $sender->sendMessage(TextFormat::RED . "This command can be executed only in game");
                return;
            }

            commandDelete:

            $deleteForm = new CustomForm(function(Player $player, array $data){
                if($data === null){
                    return;
                }

                $error = array();

                if(isset($data[0]) && $data[0] !== ""){
                    if(!$this->getPlugin()->gameExists($data[0])){
                        $error[0] = TextFormat::RED . "Doesn't exist";
                    }
                }else{
                    $error[0] = TextFormat::RED . "Column can't be blank";
                }

                if(count($error) > 0){
                    $this->cachedCommandResponse[$player->getRawUniqueId()] = array('command' => 'delete', 'errors' => $error, 'values' => $data);
                    $this->sendFormCustom($player, $this->cachedFormResponse['delete'], 'delete');
                }else{
                    $this->getPlugin()->deleteGame($data[0]);
                    $player->sendMessage(TextFormat::GREEN . "Game deleted");
                }
            });
            $this->sendFormCustom($sender, $deleteForm, 'delete');
            break;
            case "setlobby";
            if(!$sender instanceof Player){
                $sender->sendMessage(TextFormat::RED . "This command can be executed only in game");
                return;
            }

            $setlobbyForm = new CustomForm(function(Player $player, array $data){
                if($data === null){
                    return;
                }

                $error = array();

                if(isset($data[0]) && $data[0] !== ""){
                    if(!$this->getPlugin()->gameExists($data[0])){
                        $error[0] = TextFormat::RED . "Doesn't exist";
                    }
                }else{
                    $error[0] = TextFormat::RED . "Column can't be blank";
                }
                if(isset($data[1]) && $data[1] !== ""){
                    if(!is_numeric($data[1])){
                        $error[1] = TextFormat::RED . "Must be numeric";
                    }
                }else{
                    $error[1] = TextFormat::RED . "Column can't be blank";
                }
                if(isset($data[2]) && $data[2] !== ""){
                    if(!is_numeric($data[2])){
                        $error[2] = TextFormat::RED . "Must be numeric";
                    }
                }else{
                    $error[2] = TextFormat::RED . "Column can't be blank";
                }
                if(isset($data[3]) && $data[3] !== ""){
                    if(!is_numeric($data[3])){
                        $error[3] = TextFormat::RED . "Must be numeric";
                    }
                }else {
                    $error[3] = TextFormat::RED . "Column can't be blank";
                }

                if(isset($data[4]) && $data[4] !== ""){
                    if(!$this->getServer()->isLevelLoaded($data[4])){
                        $error[4] = TextFormat::RED . "Level not loaded";
                    }
                }else {
                    $error[4] = TextFormat::RED . "Column can't be blank";
                }

                if(count($error) > 0){
                    $this->cachedCommandResponse[$player->getRawUniqueId()] = array('command' => 'setlobby', 'errors' => $error, 'values' => $data);
                    $this->sendFormCustom($player, $this->cachedFormResponse['setlobby'], 'setlobby');
                }else{
                    $this->getPlugin()->setLobby($data[0], $data[1], $data[2], $data[3], $data[4]);
                    $player->sendMessage(TextFormat::GREEN . "Lobby set");
                }
            });
            $this->sendFormCustom($sender, $setlobbyForm, 'setlobby');
            break;
            case "setposition";
            if(!$sender instanceof Player){
                $sender->sendMessage(TextFormat::RED . "This command can be executed only in game");
                return;
            }

            $setpositionForm = new CustomForm(function(Player $player, array $data){
                if($data === null){
                    return;
                }

                $error = array();

                if(isset($data[0]) && $data[0] !== ""){
                    if(!$this->getPlugin()->gameExists($data[0])){
                        $error[0] = TextFormat::RED . "Doesn't exist";
                    }
                }else{
                    $error[0] = TextFormat::RED . "Column can't be blank";
                }

                if(isset($data[1]) && $data[1] !== ""){
                    if(!$this->getPlugin()->teamExists($data[0], strtolower($data[1]))){
                        $error[1] = TextFormat::RED . "Doesn't exist";
                    }
                }else{
                    $error[1] = TextFormat::RED . "Column can't be blank";
                }

                if(isset($data[2]) && $data[2] !== ""){
                    if(!is_numeric($data[2])){
                        $error[2] = TextFormat::RED . "Must be numeric";
                    }
                }else{
                    $error[2] = TextFormat::RED . "Column can't be blank";
                }

                if(isset($data[3]) && $data[3] !== ""){
                    if(!is_numeric($data[3])){
                        $error[3] = TextFormat::RED . "Must be numeric";
                    }
                }else{
                    $error[3] = TextFormat::RED . "Column can't be blank";
                }
                if(isset($data[4]) && $data[4] !== ""){
                    if(!is_numeric($data[4])){
                        $error[4] = TextFormat::RED . "Must be numeric";
                    }
                }else {
                    $error[4] = TextFormat::RED . "Column can't be blank";
                }

                if(count($error) > 0){
                    $this->cachedCommandResponse[$player->getRawUniqueId()] = array('command' => 'setposition', 'errors' => $error, 'values' => $data);
                    $this->sendFormCustom($player, $this->cachedFormResponse['setposition'], 'setposition');
                }else{
                    $this->getPlugin()->setTeamPosition($data[0], $data[1], $data[2], (int)$data[3], (int)$data[4], (int)$data[5]);
                    $player->sendMessage(TextFormat::GREEN . "Position set");
                }
            });

            $this->sendFormCustom($sender, $setpositionForm, 'setposition');
            break;
            case "setbed";
            if(!$sender instanceof Player){
                $sender->sendMessage(TextFormat::RED . "This command can be executed only in game");
                return;
            }

            $setbedForm = new CustomForm(function(Player $player, array $data){

                if($data === null){
                    return;
                }

                $error = array();

                if(isset($data[0]) && $data[0] !== ""){
                    if(!$this->getPlugin()->gameExists($data[0])){
                        $error[0] = TextFormat::RED . "Doesn't exist";
                    }
                }else{
                    $error[0] = TextFormat::RED . "Column can't be blank";
                }
                if(isset($data[1]) && $data[1] !== ""){
                    if(!$this->getPlugin()->teamExists($data[1])){
                        $error[1] = TextFormat::RED . "Doesn't exist";
                    }
                }else{
                    $error[1] = TextFormat::RED . "Column can't be blank";
                }

                if(count($error) > 0){
                    $this->cachedCommandResponse[$player->getRawUniqueId()] = array('command' => 'setbed', 'errors' => $error, 'values' => $data);
                    $this->sendFormCustom($player, $this->cachedFormResponse['setbed'], 'setbed');
                }else{
                    $this->getPlugin()->bedSetup[$player->getRawUniqueId()] = ['game' => $data[0], 'team' => $data[1] , 'step' => 1];
                    $player->sendMessage(TextFormat::RED . "Break the bed");
                }
            });

            $this->sendFormCustom($sender, $setbedForm, 'setbed');
            break;
            case "setgenerator";
            $setgeneratorForm = new CustomForm(function(Player $player, array $data) {
                if($data === null){
                    return;
                }

                $error = array();

                if(isset($data[0]) && $data[0] !== ""){
                    if(!$this->getPlugin()->gameExists($data[0])){
                        $error[0] = TextFormat::RED . "Doesn't exist";
                    }
                }else{
                    $error[0] = TextFormat::RED . "Column can't be blank";
                }

                if(!isset($data[1])){
                    $error[1] = TextFormat::RED . "Type not selected";
                }

                if(!isset($data[2])){
                    $error[2] = TextFormat::RED . "Type not selected";
                }

                if(isset($data[3]) && $data[3] !== ""){
                    if(!is_numeric($data[3])){
                        $error[3] = TextFormat::RED . "Must be numeric";
                    }
                }else{
                    $error[3] = TextFormat::RED . "Column can't be blank";
                }

                if(isset($data[4]) && $data[4] !== ""){
                    if(!is_numeric($data[4])){
                        $error[4] = TextFormat::RED . "Must be numeric";
                    }
                }else{
                    $error[4] = TextFormat::RED . "Column can't be blank";
                }
                if(isset($data[5]) && $data[5] !== ""){
                    if(!is_numeric($data[5])){
                        $error[5] = TextFormat::RED . "Must be numeric";
                    }
                }else {
                    $error[5] = TextFormat::RED . "Column can't be blank";
                }

                if(count($error) > 0){
                    $this->cachedCommandResponse[$player->getRawUniqueId()] = array('command' => 'setgenerator', 'errors' => $error, 'values' => $data);
                    $this->sendFormCustom($player, $this->cachedFormResponse['setgenerator'], 'setgenerator');
                }else{
                    $this->getPlugin()->addGenerator($data[0], $data[1], $data[2], $data[3], $data[4], $data[5]);
                    $player->sendMessage(TextFormat::GREEN . "Generator added");
                }
            });
            $this->sendFormCustom($sender, $setgeneratorForm, 'setgenerator');
            break;
        }
    }
}