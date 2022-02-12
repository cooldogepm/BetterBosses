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
use pocketmine\world\Explosion;
use pocketmine\world\Position;

final class CreeperBoss extends Living implements BossEntity
{
    use BossEntityTrait;

    public function __construct(protected Location $initialSpawn, protected string $bossNametag = "", int $maxHealth = 10, protected float $bossScale = 1, protected int $movementRange = 50, protected float $attackRange = 1, protected float $speed = 0.34, protected array $commands = [], protected array $loot = [], ?CompoundTag $nbt = null)
    {
        parent::__construct($initialSpawn, $nbt);
        $this->setMaxHealth($maxHealth);
    }

    public static function getNetworkTypeId(): string
    {
        return EntityIds::CREEPER;
    }

    public function onAttack(Entity $entity, Position $position): void
    {
        $event = $this->doMeleeAttack($entity, $position);
        $this->setMainAttackDelay($event->getAttackCooldown() * 1.5);
    }

    public function getName(): string
    {
        return "Creeper";
    }

    protected function getInitialSizeInfo(): EntitySizeInfo
    {
        return new EntitySizeInfo(1.8, 0.6);
    }

    protected function handleUpdate(?Player $player): void
    {
        if (!$this->isAbilityActivated() && $this->canActivateSpecialAbility()) {
            $this->setAbilityActivated(true);

            $explosion = new Explosion($this->getPosition(), $this->getExplosionSize(), $this);
            $explosion->explodeB();
        }
    }

    public function setAbilityActivated(bool $abilityActivated): void
    {
        $this->abilityActivated = $abilityActivated;
    }

    protected function getExplosionSize(): int
    {
        return 8;
    }
}
