<?php


namespace BedWars\game\shop;


use BedWars\BedWars;
use BedWars\game\Generator;
use pocketmine\item\Armor;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\Item;
use pocketmine\item\ItemIds;
use pocketmine\item\ItemFactory;
use pocketmine\item\Sword;
use pocketmine\network\mcpe\protocol\ModalFormRequestPacket;
use pocketmine\player\Player;
use BedWars\game\Team;
use pocketmine\utils\TextFormat;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\item\enchantment\VanillaEnchantments;

class UpgradeShop
{

    /**
     * @var array $shopWindows
     */
    public static $shopWindows = [
        1 => ["name" => "Sharpened Swords", "image" => "https://static.wikia.nocookie.net/minecraft_gamepedia/images/6/6a/Diamond_Sword_JE2_BE2.png/revision/latest?cb=20200217235945"],
        2 => ["name" => "Reinforced Armor", "image" => "https://www.seekpng.com/png/full/819-8194450_minecraft-diamond-chestplate-chestplate-minecraft.png"],
        3 => ["name" => "Maniac Miner", "image" => "https://static.wikia.nocookie.net/minecraft_gamepedia/images/a/a6/Golden_Pickaxe_JE4_BE3.png/revision/latest/scale-to-width-down/160?cb=20200226194041"],
        4 => ["name" => "Iron Forge", "image" => "https://cdn.nookazon.com/hypixel/Accessories/Dungeon_Chest_Key.png"],
        5 => ["name" => "Heal Pool", "image" => "https://static.wikia.nocookie.net/minecraft_gamepedia/images/e/e2/Feather_JE3_BE2.png/revision/latest/scale-to-width-down/160?cb=20190430052113"]
    ];

    public static $page_ids = [
        0 => 101,
        1 => 102,
        2 => 103,
        3 => 104,
        4 => 105
    ];

    public static $upgrades = [
        0 => ['name' => "Sharpened Swords", "cost" => 4, "costMultiply" => false, 'identifier' => 'sharpenedSwords'],
        1 => ['name' => "Reinforced Armor", "cost" => 2, "costMultiply" => true, 'identifier' => 'armorProtection'],
        2 => ['name' => "Maniac Miner", "cost" => 2, "costMultiply" => true, 'identifier' => 'maniacMiner'],
        3 => ['name' => "Iron Forge", "cost" => 2, "costMultiply" => true, 'identifier' => 'ironForge'],
        4 => ['name' => 'Heal Pool', "cost" => 2, "costMultiply" => true, 'identifier' => 'healPool']
    ];

    const UPGRADE_DESCRIPTION = [
        1 => TextFormat::YELLOW . "§l§b» §r§eYour team gets Protection on all armor pieces\n\n" . TextFormat::AQUA . "Protection I " . TextFormat::BOLD . TextFormat::YELLOW . "2 Diamonds\n" . TextFormat::RESET . TextFormat::AQUA . "Protection II " . TextFormat::BOLD . TextFormat::YELLOW . "4 Diamonds\n" . TextFormat::RESET . TextFormat::AQUA . "Protection III " . TextFormat::BOLD . TextFormat::YELLOW . "6 diamonds",
        0 => TextFormat::YELLOW . "§l§b» §r§eYour team gets Sharpness I on all swords and axes!\n\n" . TextFormat::BOLD . TextFormat::YELLOW . "2 Diamonds",
        2 => TextFormat::YELLOW . "§l§b» §r§eAll players on your team permanently gain Haste\n\n" . TextFormat::AQUA . "Haste I " . TextFormat::BOLD . TextFormat::YELLOW . "2 Diamonds\n" .
            TextFormat::RESET . TextFormat::AQUA . "Haste II " . TextFormat::BOLD . TextFormat::YELLOW . "4 Diamonds",
        3 => TextFormat::YELLOW . "§l§b» §r§eUpgrade resource spawning on your island\n\n" . TextFormat::AQUA . "+50% resources " . TextFormat::BOLD . TextFormat::YELLOW . "2 Diamonds\n" . TextFormat::RESET . TextFormat::AQUA . "+100% resources " . TextFormat::BOLD . TextFormat::YELLOW . "4 Diamonds\n" . TextFormat::RESET . TextFormat::AQUA . "Spawn emeralds " . TextFormat::BOLD . TextFormat::YELLOW . "8 Diamonds\n" . TextFormat::RESET . TextFormat::AQUA . "+200% resources " . TextFormat::BOLD . TextFormat::YELLOW . "16 Diamonds",
        4 => TextFormat::YELLOW . "§l§b» §r§eCreates a regeneration field around your base\n\n" . TextFormat::AQUA . "1 Diamond" 
    ];

    const MAX_LEVELS = [
        'sharpenedSwords' => 1,
        'armorProtection' => 3,
        'maniacMiner' => 2,
        'ironForge' => 4,
        'healPool' => 1
    ];


    /**
     * @param Player $p
     */
    public static function sendDefaultShop(Player $p)
    {
        $data['title'] = "Upgrade Shop";
        $data['type'] = "form";
        $data['content'] = "";
        foreach (self::$shopWindows as $windows) {
            $button = ['text' => TextFormat::AQUA . $windows['name']];
            $button['image']['type'] = "url";
            $button['image']['data'] = $windows['image'];
            $data['buttons'][] = $button;
        }

        $packet = new ModalFormRequestPacket();
        $packet->formId = 100;
        $packet->formData = json_encode($data);
        $p->getNetworkSession()->sendDataPacket($packet);

    }

    public static function handleTransaction($formId, Player $player, BedWars $plugin): void
    {
        $playerTeam = $plugin->getPlayerTeam($player);
        $upgradeData = self::$upgrades[array_search($formId, self::$page_ids)];
        $cost = $upgradeData['cost'];
        if ($upgradeData['costMultiply']) {
            $upgradeValue = $playerTeam->getUpgrade($upgradeData['identifier']);
            $upgradeValue = $upgradeValue + 1;
            $cost = $cost * $upgradeValue;
        }

        $id = $upgradeData['identifier'];
        if($playerTeam->getUpgrade($id) == self::MAX_LEVELS[$id]){
            return;
        }

        if (!$player->getInventory()->contains(ItemFactory::getInstance()->get(ItemIds::DIAMOND, 0, $cost))) {
            return;
        }


        $playerTeam->upgrade($upgradeData['identifier']);

        $player->getInventory()->removeItem(ItemFactory::getInstance()->get(ItemIds::DIAMOND, 0, $cost));

        $player->sendMessage(TextFormat::GREEN . "§l§b» §r§aUpgraded!");

        switch ($upgradeData['identifier']) {
            case 'sharpenedSwords';
            foreach ($playerTeam->getPlayers() as $player) {
                    foreach ($player->getInventory()->getContents() as $index => $item) {
                        if ($item instanceof Sword){
                            $item->setUnbreakable(true);
                            $item->addEnchantment(new EnchantmentInstance(VanillaEnchantments::SHARPNESS(), $playerTeam->getUpgrade('sharpenedSwords')));
                            $player->getInventory()->setItem($index, $item);
                        }
                    }
                }
        break;
        case 'armorProtection';
        foreach ($playerTeam->getPlayers() as $player) {
            $helmet = $player->getArmorInventory()->getHelmet();
            $chestplate = $player->getArmorInventory()->getChestplate();
            $leggings = $player->getArmorInventory()->getLeggings();
            $boots = $player->getArmorInventory()->getBoots();

            foreach([$helmet, $chestplate, $leggings, $boots] as $armor){
                $armor->addEnchantment(new EnchantmentInstance(VanillaEnchantments::PROTECTION(), $playerTeam->getUpgrade('armorProtection')));
            }

            $player->getArmorInventory()->setHelmet($helmet);
            $player->getArmorInventory()->setChestplate($chestplate);
            $player->getArmorInventory()->setLeggings($leggings);
            $player->getArmorInventory()->setBoots($boots);
        }
        break;
        case 'maniacMiner';
        foreach($playerTeam->getPlayers() as $player){
            $player->getEffects()->add(new EffectInstance(VanillaEffects::HASTE(), 6*1000*5, $playerTeam->getUpgrade('maniacMiner')));
        }
        break;
        case 'ironForge';
        $playerGame = BedWars::getInstance()->getPlayerGame($player);
        $upgrade = $playerTeam->getUpgrade('ironForge');
        if($upgrade == 1 || $upgrade == 2 || $upgrade == 4){
        foreach($playerGame->generators as $generator){
            if($generator->team instanceof Team){
                if($playerTeam->getName() == $generator->team->getName()){
                    if($upgrade == 1){
                        $generator->setMultiply(50);
                    }else if($upgrade == 2){
                        $generator->setMultiply(100);
                    }else if($upgrade == 4){
                        if($generator->itemID == ItemIds::EMERALD){
                            $generator->setRepeatRate(ceil($generator->getRepeatRate() / 2));
                            return;
                        }
                        $generator->setMultiply(200);
                    }
                }
            }
          }
        }else if($upgrade == 3){
            foreach($playerGame->generators as $generator){
            if($generator->team instanceof Team){
                if($playerTeam->getName() == $generator->team->getName()){
                    $playerGame->generators[] = new Generator(ItemIds::EMERALD, 20, $generator->getPosition(), false, false, $playerTeam);
                    return;
                }
            }
          }
        }
        break;
    }
}

    public static function sendBuyPage($selectedUpgrade, Player $player, BedWars $plugin) : void{
        $playerTeam = $plugin->getPlayerTeam($player);
        $formId = self::$page_ids[intval($selectedUpgrade)];

        $upgradeData = self::$upgrades[intval($selectedUpgrade)];

        $cost = $upgradeData['cost'];

        if($upgradeData['costMultiply']){
            $upgradeValue = $playerTeam->getUpgrade($upgradeData['identifier']);
            $upgradeValue = $upgradeValue + 1;
            $cost = $cost * $upgradeValue;
        }


        $formData = array();
        $formData['type'] = 'form';
        $formData['title'] = 'Upgrade Info';
        $formData['content'] = self::UPGRADE_DESCRIPTION[intval($selectedUpgrade)] . "\n";

        if($playerTeam->getUpgrade($upgradeData['identifier']) == self::MAX_LEVELS[$upgradeData['identifier']]){
            $formData['content'].="\n" . TextFormat::WHITE . "Level: " . TextFormat::YELLOW . "MAX";
        }elseif($playerTeam->getUpgrade($upgradeData['identifier']) < self::MAX_LEVELS[$upgradeData['identifier']] && $player->getInventory()->contains(ItemFactory::getInstance()->get(ItemIds::DIAMOND, 0, $cost))){
            $formData['content'].="\n" . TextFormat::RESET . TextFormat::GREEN . "Tap to buy\n" . TextFormat::WHITE . "Level: " . TextFormat::YELLOW . $playerTeam->getUpgrade($upgradeData['identifier']);
        }elseif($playerTeam->getUpgrade($upgradeData['identifier']) < self::MAX_LEVELS[$upgradeData['identifier']]){
            $formData['content'].="\n" . TextFormat::RESET . TextFormat::RED . "You need $cost diamonds\n" . TextFormat::WHITE . "Level: " . TextFormat::YELLOW . $playerTeam->getUpgrade($upgradeData['identifier']);
        }

        $formData['buttons'][] = ['text' => "Confirm"];

        $packet = new ModalFormRequestPacket();
        $packet->formId = $formId;
        $packet->formData = json_encode($formData);

        $player->getNetworkSession()->sendDataPacket($packet);
    }
}
