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

final class IronGolemBoss extends Living implements BossEntity
{
    use BossEntityTrait;

    public function __construct(protected Location $initialSpawn, protected string $bossNametag = "", int $maxHealth = 10, protected float $bossScale = 1, protected int $movementRange = 50, protected float $attackRange = 1, protected float $speed = 0.34, protected array $commands = [], protected array $loot = [], ?CompoundTag $nbt = null)
    {
        parent::__construct($initialSpawn, $nbt);
        $this->setMaxHealth($maxHealth);
    }

    public static function getNetworkTypeId(): string
    {
        return EntityIds::IRON_GOLEM;
    }

    public function onAttack(Entity $entity, Position $position): void
    {
        $event = $this->doMeleeAttack($entity, $position, 2.5);
        $this->setMainAttackDelay($event->getAttackCooldown() * 3.5);
    }

    public function getName(): string
    {
        return "Iron Golem";
    }

    protected function handleUpdate(?Player $player): void
    {
        if (!$this->isAbilityActivated() && $this->canActivateSpecialAbility()) {
            $this->setAbilityActivated(true);
            $this->setHealth(floor($this->getMaxHealth() / 2));
        }
    }

    protected function getInitialSizeInfo(): EntitySizeInfo
    {
        return new EntitySizeInfo(2.9, 1.4);
    }
}
