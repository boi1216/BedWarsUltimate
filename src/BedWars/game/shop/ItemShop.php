<?php


namespace BedWars\game\shop;


use BedWars\BedWars;
use BedWars\game\Game;
use BedWars\game\Team;
use BedWars\utils\Utils;
use pocketmine\utils\TextFormat;
use pocketmine\item\Armor;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIds;
use pocketmine\network\mcpe\protocol\ModalFormRequestPacket;
use pocketmine\player\Player;

class ItemShop
{

    const PURCHASE_TYPE_IRON = 0;
    const PURCHASE_TYPE_GOLD = 1;
    const PURCHASE_TYPE_EMERALD = 2;

    /**
     * @var array $shopWindows
     */
    public static $shopWindows = [
        1 => ["name" =>"Armor", "image" => "https://static.wikia.nocookie.net/minecraft_gamepedia/images/e/e0/Iron_Chestplate_%28item%29_JE2_BE2.png/revision/latest?cb=20190406141220"],
        2 => ["name" =>"Weapons", "image" => "http://pixelartmaker-data-78746291193.nyc3.digitaloceanspaces.com/image/fdf3c1e9be5e207.png"],
        3 => ["name" =>"Blocks", "image" => "https://static.wikia.nocookie.net/minecraft_gamepedia/images/f/fb/Red_Wool_JE2_BE2.png/revision/latest?cb=20200413084539"],
        4 => ["name" => "Bows", "image" => "https://static.wikia.nocookie.net/minecraft/images/6/6b/EnchantedBow.gif/revision/latest/smart/width/300/height/300?cb=20200117003024"],
        5 => ["name" => "Potions" ,"image" => "https://static.wikia.nocookie.net/minecraft_gamepedia/images/f/fb/Glass_Bottle_JE2_BE2.png/revision/latest?cb=20200523234146"],
        6 => ["name" => "Specials", "image" => "https://minecraft-max.net/upload/iblock/6f4/6f40ca614124b2ab9f10dac2ea78b4d3.png"]
    ];

    /**
     * @var array $shopPages
     */
    public static $shopPages = [
        0 => ["§6Chainmail Armor\n§l§e40 IRON" => ["image" => "https://lh3.googleusercontent.com/i8OJvH_x9a1SFd-F358jlCKe9KAXrpw0WO_22SuQRHWjLyFcOy0GFQyBFqlcENNEzXktnruwr71KBzP-j87zYg"],
            "§6Iron Armor §c[PERMANENT]\n§l§e12 GOLD" => ["image" => "https://static.wikia.nocookie.net/minecraft_gamepedia/images/e/e0/Iron_Chestplate_%28item%29_JE2_BE2.png/revision/latest?cb=20190406141220"],
            "§6Diamond Armor §c[PERMANENT]\n§l§e6 EMERALD" => ["image" => "https://www.seekpng.com/png/full/819-8194450_minecraft-diamond-chestplate-chestplate-minecraft.png"]],
        1 => ["§6Stone Sword\n§l§e10 IRON" => ["image" => "https://static.wikia.nocookie.net/minecraft_gamepedia/images/b/b1/Stone_Sword_JE2_BE2.png/revision/latest/scale-to-width-down/160?cb=20200217235849"],
            "§6Iron Sword\n§l§e7 GOLD" => ["image" => "https://static.wikia.nocookie.net/minecraft_gamepedia/images/0/01/Iron_Sword_JE1_BE1.png/revision/latest?cb=20190516111355"],
            "§6Diamond Sword\n§l§e7 EMERALD" => ["image" => "https://static.wikia.nocookie.net/minecraft_gamepedia/images/6/6a/Diamond_Sword_JE2_BE2.png/revision/latest?cb=20200217235945"],
            "§6Knockback Stick\n§l§e2 EMERALD" => ["image" => "https://www.spigotmc.org/attachments/weather_stick-png.553082/"]],
        2 => ["§6Wool 16x\n§l§e4 IRON" => ["image" => "https://static.wikia.nocookie.net/minecraft_gamepedia/images/6/66/White_Wool_JE2_BE2.png/revision/latest?cb=20200317231954"],
            "§6Sandstone 16x\n§l§e12 IRON" => ["image" => "https://static.wikia.nocookie.net/minecraft_gamepedia/images/9/95/Sandstone_JE6_BE3.png/revision/latest?cb=20200317204927"],
            "§6Endstone 12x\n§l§e24 IRON" => ["image" => "https://static.wikia.nocookie.net/minecraft_gamepedia/images/4/43/End_Stone_JE3_BE2.png/revision/latest/scale-to-width-down/1200?cb=20200315175115"],
            "§6Ladder 16x\n§l§e4 IRON" => ["image" => "https://minecraft-max.net/upload/iblock/521/521d019985da95114ffd23f7591ff2ea.png"],
            "§6Oak Wood 16x\n§l§e4 GOLD" => ["image" => "https://static.wikia.nocookie.net/minecraft_gamepedia/images/9/94/Oak_Planks_JE5.png/revision/latest?cb=20200317041701"],
            "§6Obsidian 4x\n§l§e4 EMERALD" => ["image" => "https://static.wikia.nocookie.net/minecraft_gamepedia/images/9/99/Obsidian_JE3_BE2.png/revision/latest?cb=20200124042057"]],
        3 => ["§6Normal Bow\n§l§e12 GOLD" => ["image" => "https://static.wikia.nocookie.net/minecraft_gamepedia/images/0/0f/Bow_%28MCD%29.png/revision/latest?cb=20200411164518"],
            "§6Bow §b(Power 1)\n§l§e24 GOLD" => ["image" => "https://static.wikia.nocookie.net/minecraft/images/6/6b/EnchantedBow.gif/revision/latest/smart/width/300/height/300?cb=20200117003024"],
            "§6Bow §b(Power 1, Punch 1)\n§l§e6 EMERALD" => ["image" => "https://static.wikia.nocookie.net/minecraft/images/6/6b/EnchantedBow.gif/revision/latest/smart/width/300/height/300?cb=20200117003024"],
            "§6Arrow 16x\n§l§e4 GOLD" => ["image" => "https://pngset.com/images/arrow-minecraft-wiki-fandom-minecraft-arrow-symbol-cross-transparent-png-2488006.png"]],
        4 => ["§6Speed II Potion (45 sec.)\n§l§e1 EMERALD" => ["image" => "https://static.wikia.nocookie.net/minecraft_gamepedia/images/2/2c/Uncraftable_Potion_JE2.png/revision/latest?cb=20191027040943"],
            "§6Jump V Potion (45 sec.)\n§l§e1 EMERALD" => ["https://static.wikia.nocookie.net/minecraft_gamepedia/images/2/22/Potion_of_Leaping_JE1_BE1.png/revision/latest?cb=20200108004444"],
            "§6Invisibility Potion (30 sec.)\n§l§e1 EMERALD" => ["https://static.wikia.nocookie.net/minecraft/images/b/bf/PotionOfSlowFallingNew.png/revision/latest?cb=20200116042148"]],
        5 => ["§6Golden Apple\n§l§e3 GOLD" => ["image" => "https://static.wikia.nocookie.net/minecraft_gamepedia/images/5/54/Golden_Apple_JE2_BE2.png/revision/latest?cb=20200521041809"],
            "§6Bedbug\n§l§e50 IRON" => ["image" => "https://static.wikia.nocookie.net/minecraft_gamepedia/images/2/2a/Snowball_JE3_BE3.png/revision/latest/scale-to-width-down/1200?cb=20190522005550"],
            "§cFireBall\n§l§e50 IRON" => ["image" => "https://lh3.googleusercontent.com/LoRJx8LPqIMwmmRpQ9OFpv4OMgTKlbfLfRy_WW1TT06nsVVQEVIkNMOyfGRUMtgdSK4X2vYyXTU2mK1b6DOC6ldKCGeRP36zPy8=s400"],
            "§6TNT\n§l§e8 GOLD" => ["image" => "https://static.wikia.nocookie.net/minecraft_gamepedia/images/a/a2/TNT_JE3_BE2.png/revision/latest?cb=20210110120939"],
            "§6Enderpearl\n§l§e4 EMERALD" => ["image" => "https://static.wikia.nocookie.net/minecraft_gamepedia/images/f/f6/Ender_Pearl_JE3_BE2.png/revision/latest/scale-to-width-down/1200?cb=20200512195721"],
            "§6Water Bucker\n§l§e1 EMERALD" => ["image" => "https://static.wikia.nocookie.net/minecraft_gamepedia/images/d/dc/Water_Bucket_JE2_BE2.png/revision/latest?cb=20190430112051"],
            "§6Bridge Egg\n§l§e4 EMERALD" => ["image" => "https://static.wikia.nocookie.net/minecraft_gamepedia/images/9/96/Egg_JE2_BE2.png/revision/latest?cb=20200512195802"],
            "§6Compact Popup-Tower\n§l§e24 IRON" => ["image" => "https://static.wikia.nocookie.net/minecraft/images/b/b3/Chest.png/revision/latest?cb=20191220013856"]],
        6 => ["§7Iron Axe\n§l§e8 GOLD" => ["image" => "https://static.wikia.nocookie.net/minecraft_gamepedia/images/5/5e/Iron_Axe_JE5_BE2.png/revision/latest?cb=20200217234438"],
            "§bDiamond Axe\n§l§e6 EMERALD" => ["image" => "https://static.wikia.nocookie.net/minecraft_gamepedia/images/4/40/Diamond_Axe_JE3_BE3.png/revision/latest?cb=20200226193844"],
            "§7Iron Pickaxe\n§l§e8 GOLD" => ["image" => "https://static.wikia.nocookie.net/minecraft_gamepedia/images/8/8e/Iron_Sword_JE2_BE2.png/revision/latest?cb=20200217235910"],
            "§bDiamond Pickaxe\n§l§e6 EMERALD" => ["image" => "https://static.wikia.nocookie.net/minecraft_gamepedia/images/4/44/Diamond_Sword_JE3_BE3.png/revision/latest?cb=20201017135722"]
        ],
    ];

    /**
     * @var array $itemData
     */
    public static $itemData = [
        0 => [0 => ["name" => "Chainmal Armor", "type" => self::PURCHASE_TYPE_IRON, "amount" => 0, "price" => 40, "item" => ["id" => ItemIds::CHAIN_LEGGINGS, "damage" => 0]],
            1 => ["name" => "Iron Armor", "type" => self::PURCHASE_TYPE_GOLD, "amount" => 0, "price" => 12, "item" => ["id" => ItemIds::IRON_LEGGINGS, "damage" => 0]],
            2 => ["name" => "Diamond Armor ", "type" => self::PURCHASE_TYPE_EMERALD, "amount" => 0, "price" => 6, "item" => ["id" => ItemIds::DIAMOND_LEGGINGS, "damage" => 0]]
        ],
        1 => [0 => ["name" => "Stone Sword", "type" => self::PURCHASE_TYPE_IRON, "amount" => 1, "price" => 10, "item" => ["id" => ItemIds::STONE_SWORD, "damage" => 0]],
            1 => ["name" => "Iron Sword", "type" => self::PURCHASE_TYPE_GOLD, "amount" => 1, "price" => 7, "item" => ["id" => ItemIds::IRON_SWORD, "damage" => 0]],
            2 => ["name" => "Diamond Sword", "type" => self::PURCHASE_TYPE_EMERALD, "amount" => 1, "price" => 7, "item" => ["id" => ItemIds::DIAMOND_SWORD, "damage" => 0]],
            3 => ["name" => "Knockback Stick", "type" => self::PURCHASE_TYPE_EMERALD, "amount" => 1, "price" => 2, "item" => ["id" => ItemIds::STICK, "damage" => 0]]
        ],
        2 => [0 => ["name" => "Wool 16x", "type" => self::PURCHASE_TYPE_IRON, "amount" => 16, "price" => 4, "item" => ["id" => ItemIds::WOOL, "damage" => "depend"]],
            1 => ["name" => "Sandstone 16x", "type" => self::PURCHASE_TYPE_IRON, "amount" => 16, "price" => 12, "item" => ["id" => ItemIds::SANDSTONE, "damage" => 0]],
            2 => ["name" => "Endstone 12x", "type" => self::PURCHASE_TYPE_IRON, "amount" => 12, "price" => 24,"item" => ["id" => ItemIds::END_STONE, "damage" => 0]],
            3 => ["name" => "Ladder 16x", "type" => self::PURCHASE_TYPE_IRON, "amount" => 16, "price" => 4,"item" => ["id" => ItemIds::LADDER, "damage" => 0]],
            4 => ["name" => "Oak Wood 16x", "type" => self::PURCHASE_TYPE_GOLD, "amount" => 16, "price" => 4, "item" => ["id" => 5, "damage" => 0]],
            5 => ["name" => "Obsidian 4x", "type" => self::PURCHASE_TYPE_EMERALD, "amount" => 4, "price" => 4, "item" => ["id" => ItemIds::OBSIDIAN, "damage" =>0]]
        ],
        3 => [0 => ["name" => "Bow 1", "type" => self::PURCHASE_TYPE_GOLD, "amount" => 1, "price" => 12, "item" => ["id" => ItemIds::BOW, "damage" => 0]],
            1 => ["name" => "Bow 2", "type" => self::PURCHASE_TYPE_GOLD, "amount" => 1, "price" => 24, "item" => ["id" => ItemIds::BOW, "damage" => 0]],
            2 => ["name" => "Bow 3", "type" => self::PURCHASE_TYPE_EMERALD, "amount" => 1, "price" => 6, "item" => ["id" => ItemIds::BOW, "damage" => 0]],
            3 => ["name" => "Arrow", "type" => self::PURCHASE_TYPE_GOLD, "amount" => 16, "price" => 4, "item" => ["id" => ItemIds::ARROW, "damage" => 0]]
        ],
        4 => [0 => ["name" => "Potion of Speed", "type" => self::PURCHASE_TYPE_EMERALD, "amount" => 1, "price" => 1, "item" => ["id" => ItemIds::POTION, "damage" => 8194]],
            1 => ["name" => "Jump Potion", "type" => self::PURCHASE_TYPE_EMERALD, "amount" => 1, "price" => 1, "item" => ["id" => ItemIds::POTION, "damage" => 8203]],
            2 => ["name" => "Invisibility Potion", "type" => self::PURCHASE_TYPE_EMERALD, "amount" => 1, "price" => 1, "item" => ["id" => ItemIds::POTION, "damage" => 8206]]
        ],
        5 => [0 => ["name" => "Golden Apple", "type" => self::PURCHASE_TYPE_GOLD, "amount" => 1, "price" => 3, "item" => ["id" => ItemIds::GOLDEN_APPLE, "damage" => 0]],
            1 => ["name" => "Bedbug", "type" => self::PURCHASE_TYPE_IRON, "amount" => 1, "price" => 50, "item" => ["id" => 326, "damage" => 8]],
            2 => ["name" => "Fireball", "type" => self::PURCHASE_TYPE_IRON, "amount" => 1, "price" => 50, "item" => ["id" => ItemIds::FIRE_CHARGE, "damage" => 0, 'custom_name' => "§l§cFireBall"]],
            3 => ["name" => "TNT", "type" => self::PURCHASE_TYPE_GOLD, "amount" => 1, "price" => 8, "item" => ["id" => ItemIds::TNT, "damage" => 0]],
            4 => ["name" => "Enderpearl", "type" => self::PURCHASE_TYPE_EMERALD, "amount" => 1, "price" => 4, "item" => ["id" => ItemIds::ENDER_PEARL, "damage" => 0]],
            5 => ["name" => "Water Bucket", "type" => self::PURCHASE_TYPE_EMERALD, "amount" => 1, "price" => 1, "item" => ["id" => 325, "damage" => 0]],
            6 => ["name" => "Bridge Egg", "type" => self::PURCHASE_TYPE_EMERALD, "amount" => 1, "price" => 4, "item" => ["id" => ItemIds::EGG, "damage" => 0, 'custom_name' => "§6Bridge Egg"]],
            7 => ["name" => "Compact Popup-Tower", "type" => self::PURCHASE_TYPE_IRON, "amount" => 1, "price" => 24, "item" => ["id" => ItemIds::CHEST, "damage" => 0, "custom_name" => "§bPopup Tower"]]
        ],
        6 => [0 => ["name" => "Iron Axe", "type" => self::PURCHASE_TYPE_GOLD, "amount" => 1, "price" => 8, "item" => ["id" => ItemIds::IRON_AXE, "damage" => 0]],
            1 => ["name" => "Diamond Axe", "type" => self::PURCHASE_TYPE_EMERALD, "amount" => 1, "price" => 6, "item" => ["id" => ItemIds::DIAMOND_AXE, "damage" => 0]],
            2 => ["name" => "Iron Pickaxe", "type" => self::PURCHASE_TYPE_GOLD, "amount" => 1, "price" => 8, "item" => ["id" => ItemIds::IRON_PICKAXE, "damage" => 0]],
            3 => ["name" => "Diamond Pickaxe", "type" => self::PURCHASE_TYPE_EMERALD, "amount" => 1, "price" => 6, "item" => ["id" => ItemIds::DIAMOND_PICKAXE, "damage" => 0]]
        ]
    ];

    /**
     * @param int $category
     * @return mixed
     */
    public static function getCategory(int $category){
        return self::$shopWindows[$category];
    }

    public static function handleTransaction(int $id, $data, Player $p, BedWars $plugin, int $formId){
        if(is_null($data)){
            return;
        }
        $itemData = self::$itemData[$id][$data];
        $amount = $itemData['amount'];
        $price = $itemData['price'];
        $id = $itemData['item']['id'];
        $damage = (int)$itemData['item']['damage'];
        $check = "";
        $type = $itemData['type'];
        $hasCustomName = isset($itemData['item']['custom_name']);
        $typeString = "";
        $removeItem = null;
        switch($type){
            case self::PURCHASE_TYPE_IRON;
                $typeString = "iron";
                $removeItem = ItemFactory::getInstance()->get(ItemIds::IRON_INGOT, 0, $price);
                $check = $p->getInventory()->contains(ItemFactory::getInstance()->get(ItemIds::IRON_INGOT, $damage, $price));
                break;
            case self::PURCHASE_TYPE_GOLD;
                $typeString = "gold";
                $removeItem = ItemFactory::getInstance()->get(ItemIds::GOLD_INGOT, 0 , $price);
                $check = $p->getInventory()->contains(ItemFactory::getInstance()->get(ItemIds::GOLD_INGOT, $damage, $price));
                break;
            case self::PURCHASE_TYPE_EMERALD;
                $typeString = "emerald";
                $removeItem = ItemFactory::getInstance()->get(ItemIds::EMERALD, 0, $price);
                $check = $p->getInventory()->contains(ItemFactory::getInstance()->get(ItemIds::EMERALD, $damage, $price));
                break;
        }

        if(!$check){
            $p->sendMessage(TextFormat::RED . "§l§5»§r§c You don't have enough " . strtolower(ucfirst($typeString)) . " to purchase " . $itemData['name']);
            return;
        }

        $playerTeam = $plugin->getPlayerTeam($p);
        if($playerTeam == null)return;


        if($id == ItemIds::WOOL){
            $damage = Utils::colorIntoWool($playerTeam->getColor());
        }elseif(ItemFactory::getInstance()->get($id) instanceof Armor){
            if(self::handleArmorTransaction($data, $p, $playerTeam)){
                $p->getInventory()->removeItem($removeItem);
            }
            return;
        }
        $item = ItemFactory::getInstance()->get($id, $damage, $amount);
        $wasPurchased = false;

        //handle custom sword transactions
        if(self::isSword($id)){
        foreach($p->getInventory()->getContents() as $index => $content){
            if(self::isSword($content->getId())) {
                $wasPurchased = true;
                if ($id !== $content->getId()) {
                    $p->getInventory()->removeItem($content);
                    $p->getInventory()->setItem($index, $item);
                }else{
                    $p->sendMessage(TextFormat::RED . "§l§5»§r§c You already have this sword!");
                    return;
                }
            }
          }
        }
        $p->sendMessage(TextFormat::GREEN . "§l§5»§r§a  You purchased " . TextFormat::YELLOW .  $itemData['name']);
        if($wasPurchased){
            return;
        }

        if($id == ItemIds::BOW){
            self::handleBowTransaction($data, $item);
        }

        $p->getInventory()->removeItem($removeItem);
        if($hasCustomName){
            $item->setCustomName($itemData['item']['custom_name']);
        }
        $p->getInventory()->addItem($item);
        self::sendPage($p, $formId);
    }

    /**
     * @param int $data
     * @param Player $p
     */
    public static function handleArmorTransaction(int $data, Player $p, Team $team) : bool{
        $data = intval($data);
        $boots = "";
        $leggings = "";
        if($team->getArmor($p) == array(0 => 'chain', 1 => 'iron', 2 => 'diamond')[$data]){
            $p->sendMessage(TextFormat::RED . "§l§5»§r§c You cannot purchase this twice!");
            return false;
        }

        switch ($data){
            case 0;
                $boots = ItemFactory::getInstance()->get(ItemIds::CHAIN_BOOTS, 0, 1);
                $leggings = ItemFactory::getInstance()->get(ItemIds::CHAIN_LEGGINGS, 0, 1);
                $team->setArmor($p, 'chain');
                break;
            case 1;
                $boots = ItemFactory::getInstance()->get(ItemIds::IRON_BOOTS);
                $leggings = ItemFactory::getInstance()->get(ItemIds::IRON_LEGGINGS);
                $team->setArmor($p, 'iron');
                break;
            case 2;
                $boots = ItemFactory::getInstance()->get(ItemIds::DIAMOND_BOOTS);
                $leggings = ItemFactory::getInstance()->get(ItemIds::DIAMOND_LEGGINGS);
                $team->setArmor($p, 'diamond');
        }
        if(($enchLevel = $team->getUpgrade('armorProtection')) > 0){
           foreach([$boots, $leggings] as $item){
              $item->addEnchantment(new EnchantmentInstance(VanillaEnchantments::SHARPNESS(), $enchLevel));
           }
        }
        $p->getArmorInventory()->setBoots($boots);
        $p->getArmorInventory()->setLeggings($leggings);
        return true;

    }

    /**
     * @param int $data
     * @param Item $item
     */
    public static function handleBowTransaction(int $data, Item $item){
        switch ($data){
            case 1;
                $enchantment = new EnchantmentInstance(VanillaEnchantments::POWER(), 1);
                $item->addEnchantment($enchantment);
                break;
            case 2;
                $enchantment = new EnchantmentInstance(VanillaEnchantments::POWER(), 1);
                $enchantment1 = new EnchantmentInstance(VanillaEnchantments::POWER(), 1);
                foreach([$enchantment, $enchantment1] as $eIns){
                     $item->addEnchantment($eIns);
                }
        }
    }

    /**
     * @param int $itemId
     * @return bool
     */
    public static function isSword(int $itemId){
        $swords = [ItemIds::IRON_SWORD, ItemIds::STONE_SWORD, ItemIds::WOODEN_SWORD, ItemIds::DIAMOND_SWORD];
        if(in_array($itemId, $swords)){
            return true;
        }
        return false;
    }

    /**
     * @param int $itemId
     * @return bool
     */
    public static function isArmor(int $itemId){
        $armors = [ItemIds::CHAIN_BOOTS, ItemIds::CHAIN_BOOTS, ItemIds::IRON_BOOTS, ItemIds::IRON_LEGGINGS, ItemIds::DIAMOND_BOOTS, ItemIds::DIAMOND_LEGGINGS];
        if(in_array($itemId, $armors)){
            return true;
        }
        return false;
    }

    /**
     * @param Player $p
     */
    public static function sendDefaultShop(Player $p){
        $data['title'] = "Item Shop";
        $data['type'] = "form";
        $data['content'] = "";
        foreach(self::$shopWindows as $windows){
            $button =  ['text' => TextFormat::BOLD . TextFormat::GREEN . $windows['name']];
            $button['image']['type'] = "url";
            $button['image']['data'] = $windows['image'];
            $data['buttons'][] = $button;
        }

        $packet = new ModalFormRequestPacket();
        $packet->formId = 50;
        $packet->formData = json_encode($data);
        $p->getNetworkSession()->sendDataPacket($packet);

    }


    /**
     * @param Player $p
     * @param int $page
     */
    public static function sendPage(Player $p, int $page){
        $formId = $page;
        $data['title'] = 'Page ' . $page;
        $data['type'] = 'form';
        $page = self::$shopPages[$page];
        $data['content'] = "";
        foreach($page as $itemsToBuy => $key){
            $string = strval($itemsToBuy);
            $button = ['text' => $string];
            if(!empty($key['image'])){
                $button['image']['type'] = 'url';
                $button['image']['data'] = $key['image'];
            }
            $data['buttons'][] = $button;
        }

        $packet = new ModalFormRequestPacket();
        $packet->formId = $formId;
        $packet->formData = json_encode($data);
        $p->getNetworkSession()->sendDataPacket($packet);
    }



}
