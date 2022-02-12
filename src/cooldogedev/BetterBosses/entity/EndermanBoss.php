<?php

declare(strict_types=1);

namespace cooldogedev\BetterBosses\entity;

use cooldogedev\BetterBosses\entity\traits\BossEntityTrait;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Living;
use pocketmine\entity\Location;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\player\Player;
use pocketmine\world\Position;

final class EndermanBoss extends Living implements BossEntity
{
    use BossEntityTrait;

    protected int $teleportationDelay = 0;

    public function __construct(protected Location $initialSpawn, protected string $bossNametag = "", int $maxHealth = 10, protected float $bossScale = 1, protected int $movementRange = 50, protected float $attackRange = 1, protected float $speed = 0.34, protected array $commands = [], protected array $loot = [], ?CompoundTag $nbt = null)
    {
        parent::__construct($initialSpawn, $nbt);
        $this->setMaxHealth($maxHealth);
    }

    public static function getNetworkTypeId(): string
    {
        return EntityIds::ENDERMAN;
    }

    public function onAttack(Entity $entity, Position $position): void
    {
        $event = $this->doMeleeAttack($entity, $position);
        $this->setMainAttackDelay($event->getAttackCooldown() * 1.5);
    }

    public function getName(): string
    {
        return "Enderman";
    }

    protected function getInitialSizeInfo(): EntitySizeInfo
    {
        return new EntitySizeInfo(2.9, 0.6);
    }

    protected function handleUpdate(?Player $player): void
    {
        if ($player !== null && $player->getPosition()->distance($this->getPosition()) <= $this->getTeleportRange() && $this->getTeleportationDelay() <= 0) {
            $this->teleport($player->getPosition());
            $this->setTeleportationDelay(20 * 10);
        }

        if ($this->teleportationDelay > 0) {
            $this->setTeleportationDelay($this->getTeleportationDelay() - 1);
        }
    }

    protected function getTeleportRange(): int
    {
        return 8;
    }

    public function getTeleportationDelay(): int
    {
        return $this->teleportationDelay;
    }

    public function setTeleportationDelay(int $teleportationDelay): void
    {
        $this->teleportationDelay = $teleportationDelay;
    }
}
