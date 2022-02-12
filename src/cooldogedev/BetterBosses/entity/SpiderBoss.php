<?php

declare(strict_types=1);

namespace cooldogedev\BetterBosses\entity;

use cooldogedev\BetterBosses\entity\traits\BossEntityTrait;
use pocketmine\block\Slab;
use pocketmine\block\Stair;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Living;
use pocketmine\entity\Location;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\world\Position;

final class SpiderBoss extends Living implements BossEntity
{
    use BossEntityTrait;

    public function __construct(protected Location $initialSpawn, protected string $bossNametag = "", int $maxHealth = 10, protected float $bossScale = 1, protected int $movementRange = 50, protected float $attackRange = 1, protected float $speed = 0.34, protected array $commands = [], protected array $loot = [], ?CompoundTag $nbt = null)
    {
        parent::__construct($initialSpawn, $nbt);
        $this->setMaxHealth($maxHealth);
    }

    public static function getNetworkTypeId(): string
    {
        return EntityIds::SPIDER;
    }

    public function doMovement(Position $targetPos): void
    {
        $location = $this->getLocation();

        $motion = $this->getMotion();

        $xDist = $targetPos->x - $location->x;
        $zDist = $targetPos->z - $location->z;
        $yaw = atan2($zDist, $xDist) / M_PI * 180 - 90;

        if ($yaw < 0) {
            $yaw += 360.0;
        }

        $this->setRotation($yaw, 0);

        $x = -1 * sin(deg2rad($yaw));
        $z = cos(deg2rad($yaw));
        $directionVector = (new Vector3($x, 0, $z))->normalize()->multiply($this->getSpeed());

        $motion->x = $directionVector->x;
        $motion->z = $directionVector->z;

        if ($this->isCollidedHorizontally) {

            $facing = $this->getHorizontalFacing();
            $frontBlock = $location->getWorld()->getBlock($location->add(0, 0.5, 0)->getSide($facing));

            if ($frontBlock instanceof Slab || $frontBlock instanceof Stair) {
                $motion->y = 0.3 + $this->gravity;
            } else {
                $motion->y = 0.42 + $this->gravity;
            }

            if ($motion->y > 0) {
                $motion->x /= 3;
                $motion->z /= 3;
            }
        }

        $this->setMotion($motion);
        $this->setLastTargetPos($targetPos);
    }

    public function onAttack(Entity $entity, Position $position): void
    {
        $event = $this->doMeleeAttack($entity, $position);
        $this->setMainAttackDelay($event->getAttackCooldown() * 2);
    }

    public function getName(): string
    {
        return "Spider";
    }

    protected function calculateFallDamage(float $fallDistance): float
    {
        return 0;
    }

    protected function getInitialSizeInfo(): EntitySizeInfo
    {
        return new EntitySizeInfo(0.9, 1.4);
    }
}
