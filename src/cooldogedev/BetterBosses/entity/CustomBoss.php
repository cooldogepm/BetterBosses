<?php

declare(strict_types=1);

namespace cooldogedev\BetterBosses\entity;

use cooldogedev\BetterBosses\BetterBosses;
use cooldogedev\BetterBosses\entity\traits\BossEntityTrait;
use JsonException;
use pocketmine\entity\Entity;
use pocketmine\entity\Human;
use pocketmine\entity\Location;
use pocketmine\entity\Skin;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\world\Position;

final class CustomBoss extends Human implements BossEntity
{
    use BossEntityTrait;

    /**
     * @throws JsonException
     */
    public function __construct(protected Location $initialSpawn, protected string $skinFile = "default", protected string $bossNametag = "", int $maxHealth = 10, protected float $bossScale = 1, protected int $movementRange = 50, protected float $attackRange = 1, protected float $speed = 0.34, protected array $commands = [], protected array $loot = [], ?CompoundTag $nbt = null)
    {
        parent::__construct(
            $initialSpawn,
            $this->generateSkinFromPath($skinFile),
            $nbt
        );

        $this->setMaxHealth($maxHealth);
    }

    /**
     * @throws JsonException
     */
    protected function generateSkinFromPath(string $file): Skin
    {
        $path = BetterBosses::getInstance()->getDataFolder() . "skins" . DIRECTORY_SEPARATOR . $file . ".png";

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

        return new Skin($this->getName() . mt_rand(1, 100), $bytes);
    }

    public function getSkinFile(): string
    {
        return $this->skinFile;
    }

    /**
     * @throws JsonException
     */
    public function setSkinFile(string $skinFile): void
    {
        $this->skinFile = $skinFile;

        $this->setSkin($this->generateSkinFromPath($skinFile));
        $this->sendSkin();
    }

    public function onAttack(Entity $entity, Position $position): void
    {
        $event = $this->doMeleeAttack($entity, $position);
        $this->setMainAttackDelay($event->getAttackCooldown() * 1.8);
    }
}
