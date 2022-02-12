<?php

declare(strict_types=1);

namespace cooldogedev\BetterBosses\entity;

use pocketmine\entity\Entity;
use pocketmine\entity\Location;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\Position;
use pocketmine\world\World;

interface BossEntity
{
    public function getInitialSpawn(): Location;

    public function setInitialSpawn(Location $initialSpawn): void;

    public function getSpeed(): float;

    public function setSpeed(float $speed): void;

    public function isAbilityActivated(): bool;

    public function setAbilityActivated(bool $abilityActivated): void;

    public function doMovement(Position $targetPos): void;

    public function attack(EntityDamageEvent $source): void;

    public function getTarget(): ?Player;

    public function setTarget(Player $target): void;

    public function setLastTargetPos(Position $lastTargetPos): void;

    public function getLastTargetPos(): ?Position;

    public function getNearestPlayer(World $world, Vector3 $pos, float $maxDistance): ?Player;

    public function getInitialSpawnCenter(): Vector3;

    public function getMovementRange(): int;

    public function setMovementRange(int $movementRange): void;

    public function getMainAttackDelay(): int;

    public function setMainAttackDelay(float $mainAttackDelay): void;

    public function onBossDeath(Entity $killer): void;
}
