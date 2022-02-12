<?php

declare(strict_types=1);

namespace cooldogedev\BetterBosses\entity;

use cooldogedev\BetterBosses\entity\traits\BossEntityTrait;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Living;
use pocketmine\entity\Location;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\MobEquipmentPacket;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\inventory\ContainerIds;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackWrapper;
use pocketmine\player\Player;
use pocketmine\world\Position;

final class SkeletonBoss extends Living implements BossEntity
{
    use BossEntityTrait;

    protected int $bowAttackDelay = 0;

    public function __construct(protected Location $initialSpawn, protected string $bossNametag = "", int $maxHealth = 10, protected float $bossScale = 1, protected int $movementRange = 50, protected float $attackRange = 1, protected float $speed = 0.34, protected array $commands = [], protected array $loot = [], ?CompoundTag $nbt = null)
    {
        parent::__construct($initialSpawn, $nbt);
        $this->setMaxHealth($maxHealth);
    }

    public static function getNetworkTypeId(): string
    {
        return EntityIds::SKELETON;
    }

    public function getName(): string
    {
        return "Skeleton";
    }

    public function onAttack(Entity $entity, Position $position): void
    {
        $event = $this->doMeleeAttack($entity, $position);
        $this->setMainAttackDelay($event->getAttackCooldown() * 2.2);
    }

    protected function sendSpawnPacket(Player $player): void
    {
        parent::sendSpawnPacket($player);

        $player->getNetworkSession()->sendDataPacket(MobEquipmentPacket::create($this->getId(), ItemStackWrapper::legacy(TypeConverter::getInstance()->coreItemStackToNet(VanillaItems::BOW())), 0, 0, ContainerIds::INVENTORY));
    }

    protected function handleUpdate(?Player $player): void
    {
        if ($player !== null && $this->getPosition()->distance($player->getPosition()) <= $this->getShootRange() && $this->getBowAttackDelay() <= 0) {
            $this->setBowAttackDelay(20 * 2);
            $this->doBowShoot($player);
        }

        if ($this->bowAttackDelay > 0) {
            $this->setBowAttackDelay($this->getBowAttackDelay() - 1);
        }
    }

    protected function getShootRange(): int
    {
        return 8;
    }

    public function getBowAttackDelay(): int
    {
        return $this->bowAttackDelay;
    }

    public function setBowAttackDelay(int $bowAttackDelay): void
    {
        $this->bowAttackDelay = $bowAttackDelay;
    }

    protected function getInitialSizeInfo(): EntitySizeInfo
    {
        return new EntitySizeInfo(1.9, 0.6, 1.9);
    }
}
