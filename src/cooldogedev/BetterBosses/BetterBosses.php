<?php

declare(strict_types=1);

namespace cooldogedev\BetterBosses;

use cooldogedev\BetterBosses\command\BossesCommand;
use cooldogedev\BetterBosses\entity\BossEntity;
use cooldogedev\BetterBosses\entity\CreeperBoss;
use cooldogedev\BetterBosses\entity\CustomBoss;
use cooldogedev\BetterBosses\entity\EndermanBoss;
use cooldogedev\BetterBosses\entity\IronGolemBoss;
use cooldogedev\BetterBosses\entity\SkeletonBoss;
use cooldogedev\BetterBosses\entity\SpiderBoss;
use cooldogedev\BetterBosses\entity\ZombieBoss;
use cooldogedev\BetterBosses\translation\Translation;
use pocketmine\data\bedrock\EntityLegacyIds;
use pocketmine\entity\EntityDataHelper;
use pocketmine\entity\EntityFactory;
use pocketmine\entity\Living;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\SingletonTrait;
use pocketmine\world\World;

final class BetterBosses extends PluginBase
{
    /**
     * @var Living[]
     */
    protected array $bosses;

    use SingletonTrait {
        getInstance as protected getInstanceTrait;
    }

    public static function getInstance(): BetterBosses
    {
        return BetterBosses::getInstanceTrait();
    }

    public function getBoss(string $name): ?Living
    {
        return $this->bosses[Utils::getLowercaseName($name)] ?? null;
    }

    protected function onLoad(): void
    {
        BetterBosses::setInstance($this);
    }

    protected function onEnable(): void
    {
        $this->bosses = [];

        Translation::init($this->getConfig()->get("translations"));

        @mkdir($this->getDataFolder() . DIRECTORY_SEPARATOR . "skins");

        $this->saveResource("skins" . DIRECTORY_SEPARATOR . "default.png");

        $this->getServer()->getCommandMap()->register("betterbosses", new BossesCommand($this));

        $this->getServer()->getPluginManager()->registerEvents(new EventListener(), $this);

        $entityFactory = EntityFactory::getInstance();

        $entityFactory->register(ZombieBoss::class, function (World $world, CompoundTag $nbt): ZombieBoss {
            return new ZombieBoss(EntityDataHelper::parseLocation($nbt, $world), nbt: $nbt);
        }, ['Zombie Boss', 'betterbosses:zombie'], EntityLegacyIds::ZOMBIE);
        $entityFactory->register(SkeletonBoss::class, function (World $world, CompoundTag $nbt): SkeletonBoss {
            return new SkeletonBoss(EntityDataHelper::parseLocation($nbt, $world), nbt: $nbt);
        }, ['Skeleton Boss', 'betterbosses:skeleton'], EntityLegacyIds::SKELETON);
        $entityFactory->register(EndermanBoss::class, function (World $world, CompoundTag $nbt): EndermanBoss {
            return new EndermanBoss(EntityDataHelper::parseLocation($nbt, $world), nbt: $nbt);
        }, ['Enderman Boss', 'betterbosses:enderman'], EntityLegacyIds::ENDERMAN);
        $entityFactory->register(CreeperBoss::class, function (World $world, CompoundTag $nbt): CreeperBoss {
            return new CreeperBoss(EntityDataHelper::parseLocation($nbt, $world), nbt: $nbt);
        }, ['Creeper Boss', 'betterbosses:creeper'], EntityLegacyIds::CREEPER);
        $entityFactory->register(IronGolemBoss::class, function (World $world, CompoundTag $nbt): IronGolemBoss {
            return new IronGolemBoss(EntityDataHelper::parseLocation($nbt, $world), nbt: $nbt);
        }, ['Iron Golem Boss', 'betterbosses:iron_golem'], EntityLegacyIds::IRON_GOLEM);
        $entityFactory->register(SpiderBoss::class, function (World $world, CompoundTag $nbt): SpiderBoss {
            return new SpiderBoss(EntityDataHelper::parseLocation($nbt, $world), nbt: $nbt);
        }, ['Spider Boss', 'betterbosses:spider'], EntityLegacyIds::IRON_GOLEM);
        $entityFactory->register(CustomBoss::class, function (World $world, CompoundTag $nbt): CustomBoss {
            return new CustomBoss(EntityDataHelper::parseLocation($nbt, $world), nbt: $nbt);
        }, ['Custom Boss', 'betterbosses:custom'], EntityLegacyIds::PLAYER);

        foreach ($this->getServer()->getWorldManager()->getWorlds() as $world) {
            foreach ($world->getEntities() as $entity) {
                if ($entity instanceof BossEntity) {
                    /**
                     * @var Living $entity
                     */
                    $this->addBoss($entity);
                }
            }
        }
    }

    public function addBoss(Living $boss): bool
    {
        if ($this->isBossSpawned($boss->getName())) {
            $boss->close();
            return false;
        }

        $this->bosses[Utils::getLowercaseName($boss->getName())] = $boss;

        return true;
    }

    public function isBossSpawned(string $name): bool
    {
        return isset($this->bosses[Utils::getLowercaseName($name)]);
    }

    protected function onDisable(): void
    {
        FormsList::clearAllData();
        foreach ($this->getBosses() as $boss) {
            $this->removeBoss($boss->getName());
        }
    }

    public function getBosses(): array
    {
        return $this->bosses;
    }

    public function removeBoss(string $name): bool
    {
        if (!$this->isBossSpawned($name)) {
            return false;
        }

        unset($this->bosses[Utils::getLowercaseName($name)]);
        return true;
    }
}
