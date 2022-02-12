<?php

declare(strict_types=1);

namespace cooldogedev\BetterBosses\entity\traits;

use cooldogedev\BetterBosses\BetterBosses;
use cooldogedev\BetterBosses\constants\BossNBTTags;
use cooldogedev\BetterBosses\entity\CustomBoss;
use cooldogedev\BetterBosses\translation\Translation;
use cooldogedev\BetterBosses\Utils;
use JsonException;
use pocketmine\block\Air;
use pocketmine\block\Slab;
use pocketmine\block\Stair;
use pocketmine\block\utils\SlabType;
use pocketmine\console\ConsoleCommandSender;
use pocketmine\entity\animation\ArmSwingAnimation;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\entity\Entity;
use pocketmine\entity\Location;
use pocketmine\entity\projectile\Arrow;
use pocketmine\event\entity\EntityDamageByChildEntityEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\Item;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector2;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use pocketmine\world\format\Chunk;
use pocketmine\world\Position;
use pocketmine\world\sound\BowShootSound;
use pocketmine\world\World;

trait BossEntityTrait
{
    protected int $mainAttackDelay = 0;

    protected bool $abilityActivated = false;

    protected int $jumpTicks = 0;

    protected ?Player $target = null;

    protected ?Position $lastTargetPos = null;

    protected Location $initialSpawn;
    protected Vector3 $initialSpawnCenter;
    protected AxisAlignedBB $initialSpawnBoundingBox;

    public function isAbilityActivated(): bool
    {
        return $this->abilityActivated;
    }

    public function setAbilityActivated(bool $abilityActivated): void
    {
        $this->abilityActivated = $abilityActivated;
    }

    public function attack(EntityDamageEvent $source): void
    {
        if ($this->noDamageTicks > 0) {
            $source->cancel();
        }

        if ($this->effectManager->has(VanillaEffects::FIRE_RESISTANCE()) and (
                $source->getCause() === EntityDamageEvent::CAUSE_FIRE
                or $source->getCause() === EntityDamageEvent::CAUSE_FIRE_TICK
                or $source->getCause() === EntityDamageEvent::CAUSE_LAVA
            )
        ) {
            $source->cancel();
        }

        $this->applyDamageModifiers($source);

        if ($source instanceof EntityDamageByEntityEvent and (
                $source->getCause() === EntityDamageEvent::CAUSE_BLOCK_EXPLOSION or
                $source->getCause() === EntityDamageEvent::CAUSE_ENTITY_EXPLOSION)
        ) {
            //TODO: knockback should not just apply for entity damage sources
            //this doesn't matter for TNT right now because the PrimedTNT entity is considered the source, not the block.
            $base = $source->getKnockBack();
            $source->setKnockBack($base - min($base, $base * $this->getHighestArmorEnchantmentLevel(VanillaEnchantments::BLAST_PROTECTION()) * 0.15));
        }

        $source->call();
        if ($source->isCancelled()) {
            return;
        }

        $this->setLastDamageCause($source);

        $this->setHealth($this->getHealth() - $source->getFinalDamage());

        $this->attackTime = $source->getAttackCooldown();

        if ($source instanceof EntityDamageByChildEntityEvent) {
            $e = $source->getChild();
            if ($e !== null) {
                $motion = $e->getMotion();
                $this->knockBack($motion->x, $motion->z, $source->getKnockBack());
            }
        } elseif ($source instanceof EntityDamageByEntityEvent) {
            $e = $source->getDamager();
            if ($e !== null) {
                $deltaX = $this->location->x - $e->location->x;
                $deltaZ = $this->location->z - $e->location->z;
                $this->knockBack($deltaX, $deltaZ, $source->getKnockBack());
            }
        }

        if ($this->isAlive()) {
            $this->applyPostDamageEffects($source);
            $this->doHitAnimation();
        }
    }

    public function getLastTargetPos(): ?Position
    {
        return $this->lastTargetPos;
    }

    public function setLastTargetPos(?Position $lastTargetPos): void
    {
        $this->lastTargetPos = $lastTargetPos;
    }

    public function getJumpTicks(): int
    {
        return $this->jumpTicks;
    }

    public function getInitialSpawnBoundingBox(): AxisAlignedBB
    {
        return $this->initialSpawnBoundingBox;
    }

    public function saveNBT(): CompoundTag
    {
        $nbt = parent::saveNBT();

        $loot = array_map(fn(Item $item): string => json_encode($item->jsonSerialize()), $this->getLoot());

        $nbt->setInt(BossNBTTags::MOVEMENT_RANGE_TAG, $this->movementRange);
        $nbt->setFloat(BossNBTTags::ATTACK_RANGE_TAG, $this->attackRange);
        $nbt->setFloat(BossNBTTags::BOSS_SCALE_TAG, $this->bossScale);
        $nbt->setFloat(BossNBTTags::BOSS_SPEED_TAG, $this->speed);
        $nbt->setString(BossNBTTags::REWARD_COMMANDS_TAG, json_encode($this->commands));
        $nbt->setString(BossNBTTags::REWARD_LOOT_TAG, json_encode($loot));
        $nbt->setString(BossNBTTags::BOSS_NAMETAG_TAG, $this->bossNametag);

        $nbt->setFloat(BossNBTTags::BOSS_X_TAG, $this->initialSpawn->x);
        $nbt->setFloat(BossNBTTags::BOSS_Y_TAG, $this->initialSpawn->y);
        $nbt->setFloat(BossNBTTags::BOSS_Z_TAG, $this->initialSpawn->z);
        $nbt->setString(BossNBTTags::BOSS_WORLD_TAG, $this->initialSpawn->world?->getFolderName());

        return $nbt;
    }

    public function getLoot(): array
    {
        return $this->loot;
    }

    public function setAttackRange(float $attackRange): void
    {
        $this->attackRange = $attackRange;
    }

    public function setBossScale(float $bossScale): void
    {
        $this->bossScale = $bossScale;

        $this->setScale($bossScale);
    }

    public function setMovementRange(int $movementRange): void
    {
        $this->movementRange = $movementRange;
    }

    public function setBossNametag(string $bossNametag): void
    {
        $this->bossNametag = $bossNametag;
    }

    public function setSpeed(float $speed): void
    {
        $this->speed = $speed;
    }

    public function setLoot(array $loot): void
    {
        $this->loot = $loot;
    }

    public function setCommands(array $commands): void
    {
        $this->commands = $commands;
    }

    /**
     * @throws JsonException
     */
    public function onBossDeath(?Entity $player = null): void
    {
        BetterBosses::getInstance()->removeBoss(Utils::getLowercaseName($this->getName()));

        if ($player instanceof Player) {

            $consoleCommandSender = new ConsoleCommandSender(Server::getInstance(), Server::getInstance()->getLanguage());

            foreach ($this->getCommands() as $command) {
                Server::getInstance()->dispatchCommand($consoleCommandSender, str_replace(Translation::PLAYER, $player->getName(), $command));
            }

            foreach ($this->getLoot() as $item) {
                $player->getInventory()->addItem($item);
            }

            Server::getInstance()->broadcastMessage(Translation::translate(Translation::get(Translation::MESSAGE_BOSS_KILL_BROADCAST), [
                    Translation::PLAYER => $player->getName(),
                    Translation::X => $this->getPosition()->getFloorX(),
                    Translation::Y => $this->getPosition()->getFloorY(),
                    Translation::Z => $this->getPosition()->getFloorZ(),
                    Translation::BOSS => $this->getName(),
                ]
            ));
        }

        $respawnDelay = BetterBosses::getInstance()->getConfig()->get("boss-respawn-time");

        $class = get_class($this);

        if ($respawnDelay !== -1) {
            BetterBosses::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(function () use ($class): void {
                if ($class === CustomBoss::class) {
                    $entity = new $class($this->getInitialSpawn(), $this->skinFile, $this->getBossNametag(), $this->getMaxHealth(), $this->getBossScale(), $this->getMovementRange(), $this->getAttackRange(), $this->getSpeed(), $this->getCommands(), $this->getLoot());
                } else {
                    $entity = new $class($this->getInitialSpawn(), $this->getBossNametag(), $this->getMaxHealth(), $this->getBossScale(), $this->getMovementRange(), $this->getAttackRange(), $this->getSpeed(), $this->getCommands(), $this->getLoot());
                }

                $entity->setHealth($entity->getMaxHealth());

                $entity->spawnToAll();

                BetterBosses::getInstance()->addBoss($entity);
            }), $respawnDelay * 20);
        }
    }

    public function getCommands(): array
    {
        return $this->commands;
    }

    public function getInitialSpawn(): Location
    {
        return $this->initialSpawn;
    }

    public function setInitialSpawn(Location $initialSpawn): void
    {
        $this->initialSpawn = $initialSpawn;
    }

    public function getBossNametag(): string
    {
        return $this->bossNametag;
    }

    public function getBossScale(): float
    {
        return $this->bossScale;
    }

    public function getMovementRange(): int
    {
        return $this->movementRange;
    }

    public function getAttackRange(): float
    {
        return $this->attackRange;
    }

    public function getSpeed(): float
    {
        return $this->speed;
    }

    protected function initEntity(CompoundTag $nbt): void
    {
        parent::initEntity($nbt);

        $this->setNameTagAlwaysVisible(true);
        $this->setNameTagVisible(true);

        $deserializeClosure = fn(string $items) => array_map(fn(string $data) => Item::jsonDeserialize(json_decode($data, true)), json_decode($items, true));

        $lootNBT = $nbt->getString(BossNBTTags::REWARD_LOOT_TAG, "[]");

        if ($lootNBT !== "[]") {
            $this->loot = $deserializeClosure($lootNBT);
        }

        $this->movementRange = $nbt->getInt(BossNBTTags::MOVEMENT_RANGE_TAG, $this->getMovementRange());
        $this->attackRange = $nbt->getFloat(BossNBTTags::ATTACK_RANGE_TAG, $this->getAttackRange());
        $this->speed = $nbt->getFloat(BossNBTTags::BOSS_SPEED_TAG, $this->getSpeed());
        $this->commands = json_decode($nbt->getString(BossNBTTags::REWARD_COMMANDS_TAG, json_encode($this->getCommands())), true);
        $this->bossNametag = $nbt->getString(BossNBTTags::BOSS_NAMETAG_TAG, $this->getBossNametag());
        $this->bossScale = $nbt->getFloat(BossNBTTags::BOSS_SCALE_TAG, $this->getBossScale());

        $this->setScale($nbt->getFloat(BossNBTTags::BOSS_SCALE_TAG, $this->bossScale));

        $location = $this->initialSpawn;

        $x = $nbt->getFloat(BossNBTTags::BOSS_X_TAG, $location->x);
        $y = $nbt->getFloat(BossNBTTags::BOSS_Y_TAG, $location->y);
        $z = $nbt->getFloat(BossNBTTags::BOSS_Z_TAG, $location->z);
        $world = $nbt->getString(BossNBTTags::BOSS_WORLD_TAG, $location->world?->getFolderName());

        Server::getInstance()->getWorldManager()->loadWorld($world);

        $this->initialSpawn = new Location($x, $z, $y, Server::getInstance()->getWorldManager()->getWorldByName($world), 0, 0);

        $this->initialSpawnBoundingBox = new AxisAlignedBB($location->x - $this->movementRange, $location->y - $this->movementRange, $location->z - $this->movementRange, $location->x + $this->movementRange, $location->y + $this->movementRange, $location->z + $this->movementRange);

        $this->initialSpawnCenter = new Vector3(
            $this->initialSpawnBoundingBox->maxX + (($this->initialSpawnBoundingBox->minX - $this->initialSpawnBoundingBox->maxX) / 2),
            $this->initialSpawnBoundingBox->maxY + (($this->initialSpawnBoundingBox->minY - $this->initialSpawnBoundingBox->maxY) / 2),
            $this->initialSpawnBoundingBox->maxZ + (($this->initialSpawnBoundingBox->minZ - $this->initialSpawnBoundingBox->maxZ) / 2),
        );
    }

    protected function canActivateSpecialAbility(): bool
    {
        return floor($this->getMaxHealth() / 4) >= floor($this->getHealth());
    }

    protected function entityBaseTick(int $tickDiff = 1): bool
    {
        $target = $this->getTarget();

        if ($this->jumpTicks > 0) {
            $this->jumpTicks--;
        }

        if ($target === null || !$target->isOnline() || !$this->initialSpawnBoundingBox->isVectorInXZ($target->getPosition()) || !$target->isSurvival() || !$target->isAlive()) {

            /**
             * @var Player|null $target
             */
            $target = $this->getNearestPlayer($this->getWorld(), $this->getInitialSpawnCenter(), $this->getMovementRange());

            $this->setTarget($target);
        }

        if ($this->mainAttackDelay > 0) {
            $this->setMainAttackDelay($this->getMainAttackDelay() - 1);
        }

        if ($target !== null) {

            $targetPos = $target->getPosition();

            $targetVec2 = new Vector2($targetPos->x, $targetPos->z);
            $currentVec2 = new Vector2($this->getPosition()->x, $this->getPosition()->z);

            if ($this->initialSpawnBoundingBox->isVectorInXZ($targetPos) && $targetVec2->distanceSquared($currentVec2) > $this->getClosestDistanceToTarget() ** 2) {
                $this->doMovement($targetPos);
            }

            if ($target->getPosition()->distance($this->getPosition()) <= $this->getAttackRange() && $this->getMainAttackDelay() <= 0) {
                $this->onAttack($target, $targetPos);
            }
        }

        $this->handleUpdate($target);
        $this->updateNametag();

        return parent::entityBaseTick($tickDiff);
    }

    public function getTarget(): ?Player
    {
        return $this->target;
    }

    public function setTarget(?Player $target): void
    {
        $this->target = $target;
    }

    public function getNearestPlayer(World $world, Vector3 $pos, float $maxDistance): ?Player
    {
        $minX = ((int)floor($pos->x - $maxDistance)) >> Chunk::COORD_BIT_SIZE;
        $maxX = ((int)floor($pos->x + $maxDistance)) >> Chunk::COORD_BIT_SIZE;
        $minZ = ((int)floor($pos->z - $maxDistance)) >> Chunk::COORD_BIT_SIZE;
        $maxZ = ((int)floor($pos->z + $maxDistance)) >> Chunk::COORD_BIT_SIZE;

        $currentTargetDistSq = $maxDistance ** 2;

        /**
         * @var Entity|null $currentTarget
         * @phpstan-var TEntity|null $currentTarget
         */
        $currentTarget = null;

        for ($x = $minX; $x <= $maxX; ++$x) {
            for ($z = $minZ; $z <= $maxZ; ++$z) {
                if (!$world->isChunkLoaded($x, $z)) {
                    continue;
                }
                foreach ($world->getChunkEntities($x, $z) as $entity) {
                    if (!$entity instanceof Player) {
                        continue;
                    }

                    if (!$entity->isSurvival()) {
                        continue;
                    }

                    $distSq = $entity->getPosition()->distanceSquared($pos);
                    if ($distSq < $currentTargetDistSq) {
                        $currentTargetDistSq = $distSq;
                        $currentTarget = $entity;
                    }
                }
            }
        }

        return $currentTarget;
    }

    public function getInitialSpawnCenter(): Vector3
    {
        return $this->initialSpawnCenter;
    }

    public function getMainAttackDelay(): int
    {
        return $this->mainAttackDelay;
    }

    public function setMainAttackDelay(float $mainAttackDelay): void
    {
        $this->mainAttackDelay = (int)round($mainAttackDelay); // HACK
    }

    protected function getClosestDistanceToTarget(): int
    {
        return 1;
    }

    public function doMovement(Position $targetPos): void
    {
        $facing = $this->getHorizontalFacing();

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

        if ($this->isOnGround() && $this->isCollidedHorizontally && $this->jumpTicks <= 0) {

            $isFullJump = null;

            $block = $location->getWorld()->getBlock($location);

            $aboveBlock = $location->getWorld()->getBlock($location->add(0, 1, 0));

            $frontBlock = $location->getWorld()->getBlock($location->add(0, 0.5, 0)->getSide($facing));

            $secondFrontBlock = $location->getWorld()->getBlock($frontBlock->getPosition()->add(0, 1, 0));

            if ($block instanceof Air && $aboveBlock instanceof Air && !$frontBlock instanceof Air && $secondFrontBlock instanceof Air) {
                if (($frontBlock instanceof Slab && !$frontBlock->getSlabType()->equals(SlabType::TOP()) && !$frontBlock->getSlabType()->equals(SlabType::DOUBLE())) || ($frontBlock instanceof Stair && !$frontBlock->isUpsideDown() && $frontBlock->getFacing() === $facing)) {
                    $isFullJump = false;
                } else {
                    $isFullJump = true;
                }
            } elseif ($block instanceof Stair || $block instanceof Slab && $frontBlock instanceof Air && $secondFrontBlock instanceof Air && $aboveBlock instanceof Air) {
                $isFullJump = false;
            }

            if ($isFullJump !== null) {
                $motion->y = ($isFullJump ? 0.42 : 0.3) + $this->gravity;
                $this->jumpTicks = $isFullJump ? 5 : 2;
            }

            if ($motion->y > 0) {
                $motion->x /= 3;
                $motion->z /= 3;
            }
        }

        $this->setMotion($motion);
        $this->setLastTargetPos($targetPos);
    }

    protected function handleUpdate(?Player $player): void
    {
    }

    public function updateNametag(): void
    {
        $bossNametag = $this->bossNametag;

        $bossNametag = str_replace([Translation::HEALTH, Translation::MAX_HEALTH], [round($this->getHealth()), $this->getMaxHealth()], $bossNametag);

        $healthPoints = (int)round($this->getHealth() > 100 ? $this->getHealth() / 10 : $this->getHealth());
        $defeatPoints = (int)round(($this->getMaxHealth() > 100 ? $this->getMaxHealth() / 100 : $this->getMaxHealth()) - $healthPoints);

        $health = str_repeat(TextFormat::RED . "❤", $healthPoints);
        $defeat = str_repeat(TextFormat::GRAY . "❤", $defeatPoints > 0 ? $defeatPoints : 0);

        $bossNametag = str_replace(Translation::HEALTH_BAR, $health . $defeat, $bossNametag);

        $this->setNameTag(TextFormat::colorize($bossNametag));
    }

    protected function doMeleeAttack(Entity $entity, Position $position, float $damage = 1): EntityDamageByEntityEvent
    {
        $this->lookAtLocation($entity->getLocation());
        $this->broadcastAnimation(new ArmSwingAnimation($this));

        $event = new EntityDamageByEntityEvent($this, $entity, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $damage);
        $entity->attack($event);

        return $event;
    }

    protected function lookAtLocation(Location $location): array
    {
        $angle = atan2($location->z - $this->getLocation()->z, $location->x - $this->getLocation()->x);
        $yaw = (($angle * 180) / M_PI) - 90;
        $angle = atan2((new Vector2($this->getLocation()->x, $this->getLocation()->z))->distance(new Vector2($location->x, $location->z)), $location->y - $this->getLocation()->y);
        $pitch = (($angle * 180) / M_PI) - 90;

        $this->setRotation($yaw, $pitch);

        return [$yaw, $pitch];
    }

    protected function doBowShoot(Player $player): void
    {
        $this->lookAt($player->getPosition()->add(0, 0.5, 0));

        $directionVector = $this->getDirectionVector();

        $location = $this->getLocation();

        $projectile = new Arrow(Location::fromObject($this->getEyePos(), $this->getWorld(), $location->yaw, $location->pitch), $this, mt_rand(1, 2) === 2);
        $projectile->setMotion($directionVector->multiply(1.5));

        $projectileEv = new ProjectileLaunchEvent($projectile);
        $projectileEv->call();

        if ($projectileEv->isCancelled()) {
            $projectile->flagForDespawn();
            return;
        }

        $projectile->spawnToAll();
        $location->getWorld()->addSound($location, new BowShootSound());
    }

    protected function onDispose(): void
    {
        parent::onDispose();

        BetterBosses::getInstance()->removeBoss(Utils::getLowercaseName($this->getName()));
    }
}
