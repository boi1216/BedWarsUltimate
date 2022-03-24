<?php

declare(strict_types=1);

namespace BedWars\game;

use BedWars\BedWars;
use BedWars\game\entity\FakeItemEntity;
use BedWars\utils\Utils;
use JsonException;
use pocketmine\entity\EntityDataHelper;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIds;
use pocketmine\nbt\tag\ByteArrayTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\utils\TextFormat;
use pocketmine\world\particle\FloatingTextParticle;
use pocketmine\world\Position;

class Generator
{

	const TITLE = [
		ItemIds::DIAMOND => TextFormat::BOLD . TextFormat::AQUA . "Diamond",
		ItemIds::EMERALD => TextFormat::BOLD . TextFormat::GREEN . "Emerald"
	];

	/** @var int $itemID */
	public $itemID;
	/** @var int $repeatRate */
	private $repeatRate;
	/** @var Position $position */
	private $position;
	/** @var bool $spawnText */
	private $spawnText;
	/** @var bool $spawnBlock */
	private $spawnBlock;
	/** @var int $dynamicSpawnTime */
	private $dynamicSpawnTime;
	/** @var FloatingTextParticle $floatingText */
	private $floatingText;
	/** @var $blockEntity */
	private $blockEntity;
	/** @var int $tier */
	private $tier = 1;

	/**
	 * Generator constructor.
	 * @param int $itemID
	 * @param int $repeatRate
	 * @param Position $position
	 * @param bool $spawnText
	 * @param bool $spawnBlock
	 * @param Team|null $team
	 * @throws JsonException
	 */
	public function __construct(int $itemID, int $repeatRate, Position $position, bool $spawnText, bool $spawnBlock, Team $team = null)
	{
		$this->itemID = $itemID;
		$this->repeatRate = $repeatRate;
		$this->position = $position;
		$this->spawnText = $spawnText;
		$this->spawnBlock = $spawnBlock;

		$this->dynamicSpawnTime = $repeatRate;

		if ($this->spawnText) {
			$text = TextFormat::YELLOW . "Tier " . TextFormat::RED . Utils::rome($this->tier) . "\n" .
				self::TITLE[$itemID] . "\n\n" .
				TextFormat::YELLOW . "Spawns in " . TextFormat::RED . $this->dynamicSpawnTime . "seconds";
			$this->floatingText = new FloatingTextParticle($position->add(0.5, 3, 0.5), $text, "");
		}

		if ($this->spawnBlock) {
			$path = BedWars::getInstance()->getDataFolder() . "skins/" . $itemID . ".png";
			$skin = Utils::getSkinFromFile($path);
			$nbt = Utils::makeNBT($position->add(0.5, 2.3, 0.5), null);
			$skinNbt = CompoundTag::create()->setTag('Skin', CompoundTag::create()
					->setString('Data', $skin->getSkinData())
					->setString('Name', 'Standard_CustomSlim')
					->setString('GeometryName', 'geometry.player_head')
					->setByteArray('GeometryData', FakeItemEntity::GEOMETRY));
			$fakeItem = new FakeItemEntity(EntityDataHelper::parseLocation($nbt, $position->getWorld()), FakeItemEntity::parseSkinNBT($skinNbt), $nbt);
			$fakeItem->setScale(1.4);
			$fakeItem->spawnToAll();
		}
	}


	/**
	 * @param int $repeatRate
	 */
	public function setRepeatRate(int $repeatRate): void
	{
		$this->repeatRate = $repeatRate;
	}

	public function tick(): void
	{
		if ($this->spawnText) {
			$text = TextFormat::YELLOW . "Tier " . TextFormat::RED . Utils::rome($this->tier) . "\n" .
				self::TITLE[$this->itemID] . "\n" .
				TextFormat::YELLOW . "Spawn in " . TextFormat::RED . $this->dynamicSpawnTime . TextFormat::YELLOW . TextFormat::WHITE . "seconds";
			$this->floatingText->setTitle($text);
			$this->floatingText->setText("");
			foreach ($this->floatingText->encode($this->position->asVector3()->add(0.5, 3, 0.5)) as $packet) {
				foreach ($this->position->getWorld()->getPlayers() as $player) {
					$player->getNetworkSession()->sendDataPacket($packet);
				}
			}
		}

		$this->dynamicSpawnTime--;

		if ($this->dynamicSpawnTime == 0) {
			$this->dynamicSpawnTime = $this->repeatRate;

			$this->position->getWorld()->dropItem($this->position->asVector3(), ItemFactory::getInstance()->get($this->itemID));

		}
	}

	public function getTier(): int
	{
		return $this->tier;
	}

	public function updateTier(): void
	{
		$this->tier++;
		//-20%
		$this->repeatRate = $this->repeatRate - ($this->repeatRate * 100 / 20);
	}

	/**
	 * @return FakeItemEntity
	 */
	public function getBlockEntity(): ?FakeItemEntity
	{
		return $this->blockEntity;
	}

	/**
	 * @return FloatingTextParticle|null
	 */
	public function getFloatingText(): ?FloatingTextParticle
	{
		return $this->floatingText;
	}

}
