<?php


namespace BedWars\game\shop;


use BedWars\BedWars;
use pocketmine\item\Armor;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\Item;
use pocketmine\item\Sword;
use pocketmine\network\mcpe\protocol\ModalFormRequestPacket;
use pocketmine\Player;
use pocketmine\utils\TextFormat;

class UpgradeShop
{

    /**
     * @var array $shopWindows
     */
    public static $shopWindows = [
        1 => ["name" => "§bSharpened Swords", "image" => "https://www.spigotmc.org/attachments/ftiroac-png.241966/"],
        2 => ["name" => "§bArmor Protection", "image" => "http://icons.iconarchive.com/icons/chrisl21/minecraft/512/Stone-Sword-icon.png"]
    ];

    public static $page_ids = [
        0 => 101,
        1 => 102
    ];

    public static $upgrades = [
        0 => ['name' => "§bSharpened Swords", "cost" => 2, "costMultiply" => false, 'identifier' => 'sharpenedSwords'],
        1 => ['name' => "§bArmor Protection", "cost" => 2, "costMultiply" => true, 'identifier' => 'armorProtection']
    ];

    const UPGRADE_DESCRIPTION = [
        1 => TextFormat::YELLOW . "Your team gets Protection on all armor pieces\n\n" . TextFormat::AQUA . "Protection I " . TextFormat::BOLD . TextFormat::YELLOW . "2 Diamonds\n" . TextFormat::RESET . TextFormat::AQUA . "Protection II " . TextFormat::BOLD . TextFormat::YELLOW . "4 Diamonds\n" . TextFormat::RESET . TextFormat::AQUA . "Protection III " . TextFormat::BOLD . TextFormat::YELLOW . "6 diamonds",
        0 => TextFormat::YELLOW . "Your team gets Sharpness I on all swords!\n\n" . TextFormat::BOLD . TextFormat::YELLOW . "2 Diamonds"
    ];

    const MAX_LEVELS = [
        'sharpenedSwords' => 1,
        'armorProtection' => 3
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
            $button = ['text' => $windows['name']];
            $button['image']['type'] = "url";
            $button['image']['data'] = $windows['image'];
            $data['buttons'][] = $button;
        }

        $packet = new ModalFormRequestPacket();
        $packet->formId = 100;
        $packet->formData = json_encode($data);
        $p->dataPacket($packet);

    }

    /**
     * @param $formId
     * @param Player $player
     * @param BedWars $plugin
     */
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

        if (!$player->getInventory()->contains(Item::get(Item::DIAMOND, 0, $cost))) {
            return;
        }


        $playerTeam->upgrade($upgradeData['identifier']);

        $player->getInventory()->removeItem(Item::get(Item::DIAMOND, 0, $cost));

        $player->sendMessage(TextFormat::GREEN . "Upgraded!");

        switch ($upgradeData['identifier']) {
            case 'sharpenedSwords';
            foreach ($playerTeam->getPlayers() as $player) {
                    foreach ($player->getInventory()->getContents() as $index => $item) {
                        if ($item instanceof Sword){
                            $item->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(Enchantment::SHARPNESS)), $playerTeam->getUpgrade('sharpenedSwords'));
                            $player->getInventory()->setItem($index, $item);
                        }
                    }
                }
        break;
        case 'armorProtection';
        $player->sendMessage("Upgradearmor");
        foreach ($playerTeam->getPlayers() as $player) {
            $helmet = $player->getArmorInventory()->getHelmet();
            $chestplate = $player->getArmorInventory()->getChestplate();
            $leggings = $player->getArmorInventory()->getLeggings();
            $boots = $player->getArmorInventory()->getBoots();

            foreach([$helmet, $chestplate, $leggings, $boots] as $armor){
                $armor->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(Enchantment::PROTECTION)), $playerTeam->getUpgrade('armorProtection'));
            }

            $player->getArmorInventory()->setHelmet($helmet);
            $player->getArmorInventory()->setChestplate($chestplate);
            $player->getArmorInventory()->setLeggings($leggings);
            $player->getArmorInventory()->setBoots($boots);
        }
        break;
    }
}

    /**
     * @param $selectedUpgrade
     * @param Player $player
     * @param BedWars $plugin
     */
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
        }elseif($playerTeam->getUpgrade($upgradeData['identifier']) < self::MAX_LEVELS[$upgradeData['identifier']] && $player->getInventory()->contains(Item::get(Item::DIAMOND, 0, $cost))){
            $formData['content'].="\n" . TextFormat::RESET . TextFormat::GREEN . "Tap to buy\n" . TextFormat::WHITE . "Level: " . TextFormat::YELLOW . $playerTeam->getUpgrade($upgradeData['identifier']);
        }elseif($playerTeam->getUpgrade($upgradeData['identifier']) < self::MAX_LEVELS[$upgradeData['identifier']]){
            $formData['content'].="\n" . TextFormat::RESET . TextFormat::RED . "You need $cost diamonds\n" . TextFormat::WHITE . "Level: " . TextFormat::YELLOW . $playerTeam->getUpgrade($upgradeData['identifier']);
        }

        $formData['buttons'][] = ['text' => "Confirm"];

        $packet = new ModalFormRequestPacket();
        $packet->formId = $formId;
        $packet->formData = json_encode($formData);

        $player->dataPacket($packet);
    }
}