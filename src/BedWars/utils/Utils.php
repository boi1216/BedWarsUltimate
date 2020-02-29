<?php


namespace BedWars\utils;

use pocketmine\entity\Skin;
use pocketmine\math\Vector3;
use pocketmine\utils\Color;
use pocketmine\utils\TextFormat;


class Utils
{

    const WOOL_COLOR = [
        '§a' => 5,
        '§c' => 14,
        '§e' => 4,
        '§6' => 1,
        '§f' => 0,
        '§b' => 3,
        '§1' => 11
    ];

    /**
     * @param string $color
     * @return string
     */
    public static function colorIntoWool(string $color) : int{
        return self::WOOL_COLOR[$color];
    }

    /**
     * @param int $woolDamage
     * @return string
     */
    public static function woolIntoColor(int $woolDamage) : string{
        $replace = [
            '§a' => 5,
            '§c' => 14,
            '§e' => 4,
            '§6' => 1,
            '§f' => 0,
            '§b' => 3,
            '§1' => 11
        ];

        return array_search($woolDamage, self::WOOL_COLOR);
    }

    /**
     * @param string $color
     * @return Color
     */
    public static function colorIntoObject(string $color) : Color{
        $replace = [
            '§a' => [0, 225, 0],
            '§c' => [225, 0, 0],
            '§e' => [225, 225, 0],
            '§6' => [255, 153, 51],
            '§f' => [225, 225, 225],
            '§b' => [51, 255, 255],
            '§1' => [0, 0, 225]
        ];

        $a = $replace[$color];
        return new Color($a[0], $a[1], $a[2]);
    }

    /**
     * @param string $delimeter
     * @param string $string
     * @param float $yaw
     * @param float $pitch
     * @return Vector3
     */
    public static function stringToVector(string $delimeter, string $string, &$yaw = 0.0, &$pitch = 0.0) : Vector3{
        $split = explode($delimeter, $string);
        if(isset($split[3]) && isset($split[4])){
            $yaw = floatval($split[3]);
            $pitch = floatval($split[4]);
        }
        return new Vector3(intval($split[0]), intval($split[1]), intval($split[2]));
    }

    /**
     * @param string $delimeter
     * @param Vector3 $vector
     * @return string
     */
    public static function vectorToString(string $delimeter, Vector3 $vector, $yaw = 0.0, $pitch = 0.0) : string{
        $array = [$vector->getX(), $vector->getY(), $vector->getZ()];
        if($yaw > 0 && $pitch > 0){
            $array[] = $yaw;
            $array[] = $pitch;
        }
        $string = "";
        foreach($array as $splitValue){
            $string.=$splitValue . ":";
        }
        return $string;
    }

    /**
     * @param $N
     * @return string
     */
    public static function rome($N){
        $c='IVXLCDM';
        for($a=5,$b=$s='';$N;$b++,$a^=7)//todo add 2 sector
            for($o=$N%$a,$N=$N/$a^0;$o--;$s=@$c[$o>2?$b+$N-($N&=-2)+$o=1:$b].$s);
        return $s;
    }

    /**
     * @param string $path
     * @return Skin|null
     */
    public static function getSkinFromFile(string $path) : ?Skin{

        $img = @imagecreatefrompng($path);
        $bytes = '';
        $l = (int) @getimagesize($path)[1];
        for ($y = 0; $y < $l; $y++) {
            for ($x = 0; $x < 64; $x++) {
                $rgba = @imagecolorat($img, $x, $y);
                $a = ((~((int)($rgba >> 24))) << 1) & 0xff;
                $r = ($rgba >> 16) & 0xff;
                $g = ($rgba >> 8) & 0xff;
                $b = $rgba & 0xff;
                $bytes .= chr($r) . chr($g) . chr($b) . chr($a);
            }
        }
        @imagedestroy($img);
        return new Skin("Standard_CustomSlim", $bytes);
    }


}