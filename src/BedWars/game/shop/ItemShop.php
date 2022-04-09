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
        1 => ["name" =>"Armor", "image" => ""],
        2 => ["name" =>"Weapons", "image" => ""],
        3 => ["name" =>"Blocks", "image" => ""],
        4 => ["name" => "Bows", "image" => ""],
        5 => ["name" => "Potions" ,"image" => ""],
        6 => ["name" => "Specials", "image" => ""]
    ];

    /**
     * @var array $shopPages
     */
    public static $shopPages = [
        0 => ["§6Chainmal Armor\n§l§e40 IRON" => ["image" => ""],
            "§6Iron Armor §c[PERMANENT]\n§l§e12 GOLD" => ["image" => ""],
            "§6Diamond Armor §c[PERMANENT]\n§l§e6 EMERALD" => ["image" => ""]],
        1 => ["§6Stone Sword\n§l§e10 IRON" => ["image" => ""],
            "§6Iron Sword\n§l§e7 GOLD" => ["image" => ""],
            "§6Diamond Sword\n§l§e7 EMERALD" => ["image" => ""],
            "§6Knockback Stick\n§l§e2 EMERALD" => ["image" => ""]],
        2 => ["§6Wool 16x\n§l§e4 IRON" => ["image" => ""],
            "§6Sandstone 16x\n§l§e12 IRON" => ["image" => ""],
            "§6Endstone 12x\n§l§e24 IRON" => ["image" => ""],
            "§6Ladder 16x\n§l§e4 IRON" => ["image" => ""],
            "§6Oak Wood 16x\n§l§e4 GOLD" => ["image" => ""],
            "§6Obsidian 4x\n§l§e4 EMERALD" => ["image" => ""]],
        3 => ["§6Normal Bow\n§l§e12 GOLD" => ["image" => ""],
            "§6Bow §b(Power 1)\n§l§e24 GOLD" => ["image" => ""],
            "§6Bow §b(Power 1, Punch 1)\n§l§e6 EMERALD" => ["image" => ""]],
        4 => ["§6Speed II Potion (45 sec.)\n§l§e1 EMERALD" => ["image" => ""],
            "§6Jump V Potion (45 sec.)\n§l§e1 EMERALD" => [""],
            "§6Invisibility Potion (30 sec.)\n§l§e1 EMERALD" => [""]],
        5 => ["§6Golden Apple\n§l§e3 GOLD" => ["image" => ""],
            "§6Bedbug\n§l§e50 IRON" => ["image" => ""],
            "§6Fireball\n§l§e50 IRON" => ["image" => ""],
            "§6TNT\n§l§e8 GOLD" => ["image" => ""],
            "§6Enderpearl\n§l§e4 EMERALD" => ["image" => ""],
            "§6Water Bucker\n§l§e1 EMERALD" => ["image" => ""],
            "§6Bridge Egg\n§l§e4 EMERALD" => ["image" => ""],
            "§6Compact Popup-Tower\n§l§e24 IRON" => ["image" => ""]
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
            2 => ["name" => "Bow 3", "type" => self::PURCHASE_TYPE_EMERALD, "amount" => 1, "price" => 6, "item" => ["id" => ItemIds::BOW, "damage" => 0]]
        ],
        4 => [0 => ["name" => "Potion of Speed", "type" => self::PURCHASE_TYPE_EMERALD, "amount" => 1, "price" => 1, "item" => ["id" => ItemIds::POTION, "damage" => 8194]],
            1 => ["name" => "Jump Potion", "type" => self::PURCHASE_TYPE_EMERALD, "amount" => 1, "price" => 1, "item" => ["id" => ItemIds::POTION, "damage" => 8203]],
            2 => ["name" => "Invisibility Potion", "type" => self::PURCHASE_TYPE_EMERALD, "amount" => 1, "price" => 1, "item" => ["id" => ItemIds::POTION, "damage" => 8206]]
        ],
        5 => [0 => ["name" => "Golden Apple", "type" => self::PURCHASE_TYPE_GOLD, "amount" => 1, "price" => 3, "item" => ["id" => ItemIds::GOLDEN_APPLE, "damage" => 0]],
            1 => ["name" => "Bedbug", "type" => self::PURCHASE_TYPE_IRON, "amount" => 1, "price" => 50, "item" => ["id" => ItemIds::SNOWBALL, "damage" => 0]],
            2 => ["name" => "Fireball", "type" => self::PURCHASE_TYPE_IRON, "amount" => 1, "price" => 50, "item" => ["id" => ItemIds::FIRE_CHARGE, "damage" => 0]],
            3 => ["name" => "TNT", "type" => self::PURCHASE_TYPE_GOLD, "amount" => 1, "price" => 8, "item" => ["id" => ItemIds::TNT, "damage" => 0]],
            4 => ["name" => "Enderpearl", "type" => self::PURCHASE_TYPE_EMERALD, "amount" => 1, "price" => 4, "item" => ["id" => ItemIds::ENDER_PEARL, "damage" => 0]],
            5 => ["name" => "Water Bucket", "type" => self::PURCHASE_TYPE_EMERALD, "amount" => 1, "price" => 1, "item" => ["id" => 326, "damage" => 0]],
            6 => ["name" => "Bridge Egg", "type" => self::PURCHASE_TYPE_EMERALD, "amount" => 1, "price" => 4, "item" => ["id" => ItemIds::EGG, "damage" => 0, 'custom_name' => "§6Bridge Egg"]],
            7 => ["name" => "Compact Popup-Tower", "type" => self::PURCHASE_TYPE_IRON, "amount" => 1, "price" => 24, "item" => ["id" => ItemIds::CHEST, "damage" => 0, "custom_name" => "§bPopup Tower"]]
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
            $p->sendMessage(TextFormat::RED . "You don't have enough " . strtolower(ucfirst($typeString)) . " to purchase " . $itemData['name']);
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
                    $p->sendMessage(TextFormat::RED . "You already have this sword!");
                    return;
                }
            }
          }
        }
        $p->sendMessage(TextFormat::GREEN . "You purchased " . TextFormat::YELLOW .  $itemData['name']);
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
            $p->sendMessage(TextFormat::RED . "You cannot purchase this twice!");
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