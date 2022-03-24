<?php

declare(strict_types=1);

namespace BedWars\utils;

use JsonException;
use pocketmine\color\Color;
use pocketmine\entity\Skin;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\Server;
use pocketmine\world\Position;


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
	 * @return int
	 */
	public static function colorIntoWool(string $color): int
	{
		return self::WOOL_COLOR[$color];
	}

	/**
	 * @param int $woolDamage
	 * @return string
	 */
	public static function woolIntoColor(int $woolDamage): string
	{
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
	public static function colorIntoObject(string $color): Color
	{
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
	public static function stringToVector(string $delimeter, string $string, &$yaw = 0.0, &$pitch = 0.0): Vector3
	{
		$split = explode($delimeter, $string);
		if (isset($split[3]) && isset($split[4])) {
			$yaw = floatval($split[3]);
			$pitch = floatval($split[4]);
		}
		return new Vector3(intval($split[0]), intval($split[1]), intval($split[2]));
	}

	public static function stringToPosition(string $delimeter, string $string, &$yaw = 0.0, &$pitch = 0.0): Vector3
	{
		$split = explode($delimeter, $string);
		if (isset($split[3]) && isset($split[4])) {
			$yaw = floatval($split[3]);
			$pitch = floatval($split[4]);
		}
		return new Position(intval($split[0]), intval($split[1]), intval($split[2]), Server::getInstance()->getWorldManager()->getWorldByName($split[3]));
	}

	/**
	 * @param string $delimeter
	 * @param Vector3 $vector
	 * @param float $yaw
	 * @param float $pitch
	 * @return string
	 */
	public static function vectorToString(string $delimeter, Vector3 $vector, $yaw = 0.0, $pitch = 0.0): string
	{
		$array = [$vector->getX(), $vector->getY(), $vector->getZ()];
		if ($yaw > 0 && $pitch > 0) {
			$array[] = $yaw;
			$array[] = $pitch;
		}
		$string = "";
		foreach ($array as $splitValue) {
			$string .= $splitValue . ":";
		}
		return $string;
	}

	/**
	 * @param int $integer
	 * @return string
	 */
	public static function rome(int $integer): string
	{
		$result = '';
		$lookup = ['M' => 1000, 'CM' => 900, 'D' => 500, 'CD' => 400, 'C' => 100, 'XC' => 90, 'L' => 50, 'XL' => 40, 'X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1];
		foreach ($lookup as $roman => $value) {
			$matches = intval($integer / $value);
			$result .= str_repeat($roman, $matches);
			$integer = $integer % $value;
		}
		return $result;
	}

	/**
	 * @param string $path
	 * @return Skin|null
	 * @throws JsonException
	 */
	public static function getSkinFromFile(string $path): ?Skin
	{

		$img = @imagecreatefrompng($path);
		$bytes = '';
		$l = (int)@getimagesize($path)[1];
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

	/**
	 *                            FORM PM
	 *
	 * Helper function which creates minimal NBT needed to spawn an entity.
	 */
	public static function makeNBT(Vector3 $position, ?Vector3 $motion = null, float $yaw = 0.0, float $pitch = 0.0): CompoundTag
	{
		return CompoundTag::create()
			->setTag("Pos", new ListTag([
				new DoubleTag($position->x),
				new DoubleTag($position->y),
				new DoubleTag($position->z)
			]))
			->setTag("Motion", new ListTag([
				new DoubleTag($motion !== null ? $motion->x : 0),
				new DoubleTag($motion !== null ? $motion->y : 0),
				new DoubleTag($motion !== null ? $motion->z : 0)
			]))
			->setTag("Rotation", new ListTag([
				new FloatTag($yaw),
				new FloatTag($pitch)
			]));
	}
}