<?php

declare(strict_types=1);

namespace cooldogedev\BetterBosses;

use cooldogedev\BetterBosses\entity\CreeperBoss;
use cooldogedev\BetterBosses\entity\CustomBoss;
use cooldogedev\BetterBosses\entity\EndermanBoss;
use cooldogedev\BetterBosses\entity\IronGolemBoss;
use cooldogedev\BetterBosses\entity\SkeletonBoss;
use cooldogedev\BetterBosses\entity\SpiderBoss;
use cooldogedev\BetterBosses\entity\traits\BossEntityTrait;
use cooldogedev\BetterBosses\entity\ZombieBoss;
use cooldogedev\BetterBosses\translation\Translation;
use jojoe77777\FormAPI\Form;
use jojoe77777\FormAPI\ModalForm;
use jojoe77777\FormAPI\SimpleForm;
use pocketmine\entity\Living;
use pocketmine\entity\Location;
use pocketmine\item\ItemFactory;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;

final class FormsList
{
    protected const BOSSES_CLASS_MAP = [
        "zombie" => ZombieBoss::class,
        "creeper" => CreeperBoss::class,
        "enderman" => EndermanBoss::class,
        "skeleton" => SkeletonBoss::class,
        "spider" => SpiderBoss::class,
        "iron_golem" => IronGolemBoss::class,
        "custom" => CustomBoss::class,
    ];

    protected static array $formData = [];

    public static function getMainForm(): Form
    {
        $closure = function (Player $player, ?int $data): void {
            if ($data === null) {
                return;
            }

            $form = match ($data) {
                0 => FormsList::getSpawnForm($player),
                1 => FormsList::getDespawnForm($player),
                default => null
            };

            if ($form !== null) {
                $player->sendForm($form);
            }
        };

        $formConfiguration = Translation::getArray("main-form");

        $form = new SimpleForm($closure);
        $form->setTitle($formConfiguration["title"]);
        $form->setContent($formConfiguration["content"]);

        foreach ($formConfiguration["buttons"] as $button) {
            $form->addButton(TextFormat::colorize($button));
        }

        return $form;
    }

    public static function getSpawnForm(Player $player): Form
    {
        $buttonsMap = [];

        $closure = function (Player $player, ?int $data): void {
            if ($data === null) {
                FormsList::removeFormData($player->getName());

                $player->sendForm(FormsList::getMainForm());

                return;
            }

            $buttonsMap = FormsList::$formData[$player->getName()];

            $bossType = $buttonsMap[$data] ?? null;

            if ($bossType === null) {
                FormsList::removeFormData($player->getName());
                $player->sendForm(FormsList::getMainForm());
                return;
            }

            $bossClass = FormsList::BOSSES_CLASS_MAP[$bossType];
            $config = BetterBosses::getInstance()->getConfig()->get("bosses")[$bossType];
            $pos = Utils::vectorFromArray($config["spawn-position"]);

            if ($config !== false && $bossClass !== null) {

                $location = Location::fromObject(
                    $pos->add(0.5, 0, 0.5),
                    $player->getWorld()
                );

                /**
                 * @var Living|BossEntityTrait $entity
                 */
                $entity = new $bossClass($location);

                $entity->setCommands($config["commands"]);

                $loot = [];

                $factory = ItemFactory::getInstance();

                foreach ($config["drops"] as $item) {
                    $item = explode(":", $item);

                    $loot[] = $factory->get((int)$item[0] ?? 0, (int)$item[1] ?? 0, (int)$item[2] ?? 1);
                }

                $entity->setLoot($loot);

                $entity->setBossScale($config["scale"]);
                $entity->setAttackRange($config["attack-range"]);
                $entity->setMaxHealth($config["max-health"]);
                $entity->setSpeed($config["speed"]);
                $entity->setMovementRange($config["movement-range"]);
                $entity->setBossNametag($config["nametag"]);
                $entity->setHealth($entity->getMaxHealth());

                if ($entity instanceof CustomBoss) {
                    $entity->setSkinFile($config["skin"]);
                }

                $player->sendMessage(Translation::translate(Translation::get(Translation::MESSAGE_BOSS_SPAWN), [
                        Translation::PLAYER => $player->getName(),
                        Translation::BOSS => $entity->getName(),
                    ]
                ));

                Server::getInstance()->broadcastMessage(Translation::translate(Translation::get(Translation::MESSAGE_BOSS_SPAWN_BROADCAST), [
                        Translation::PLAYER => $player->getName(),
                        Translation::X => $entity->getPosition()->getFloorX(),
                        Translation::Y => $entity->getPosition()->getFloorY(),
                        Translation::Z => $entity->getPosition()->getFloorZ(),
                        Translation::BOSS => $entity->getName(),
                    ]
                ));

                $entity->spawnToAll();
            }

            FormsList::removeFormData($player->getName());
        };

        $formData = Translation::getArray("spawn-form");

        $form = new SimpleForm($closure);
        $form->setTitle(TextFormat::colorize($formData["title"]));
        $form->setContent(TextFormat::colorize($formData["content"]));

        $i = 0;
        foreach ($formData["buttons"] as $button) {
            if (isset($button["type"]) && BetterBosses::getInstance()->isBossSpawned($button["type"])) {
                continue;
            }

            if (isset($button["type"])) {
                $buttonsMap[$i] = $button["type"];
                $i++;
            }

            $form->addButton(TextFormat::colorize($button["text"]));
        }

        FormsList::setFormData($player->getName(), $buttonsMap);

        return $form;
    }

    protected static function removeFormData(string $player): void
    {
        if (!self::hasFormData($player)) {
            return;
        }
        unset(FormsList::$formData[$player]);
    }

    protected static function hasFormData(string $player): bool
    {
        return isset(FormsList::$formData[$player]);
    }

    protected static function setFormData(string $player, array $data): void
    {
        FormsList::$formData[$player] = $data;
    }

    public static function getDespawnForm(Player $player): Form
    {
        $buttonsMap = [];

        $closure = function (Player $player, ?int $data): void {
            if ($data === null) {
                FormsList::removeFormData($player->getName());

                $player->sendForm(FormsList::getMainForm());

                return;
            }

            $buttonsMap = FormsList::$formData[$player->getName()];

            $bossType = $buttonsMap[$data] ?? null;

            if ($bossType === null) {
                FormsList::removeFormData($player->getName());
                $player->sendForm(FormsList::getMainForm());
                return;
            }

            $entity = BetterBosses::getInstance()->getBoss($bossType);

            if ($entity !== null && $entity->isAlive()) {

                $player->sendForm(FormsList::getDespawnFormConfirmation($entity));
            }

            FormsList::removeFormData($player->getName());
        };

        $formData = Translation::getArray("despawn-form");

        $form = new SimpleForm($closure);
        $form->setTitle(TextFormat::colorize($formData["title"]));
        $form->setContent(TextFormat::colorize($formData["content"]));

        $i = 0;
        foreach ($formData["buttons"] as $button) {
            if (isset($button["type"]) && !BetterBosses::getInstance()->isBossSpawned($button["type"])) {
                continue;
            }

            if (isset($button["type"])) {
                $buttonsMap[$i] = $button["type"];
                $i++;
            }

            $form->addButton(TextFormat::colorize($button["text"]));
        }

        FormsList::setFormData($player->getName(), $buttonsMap);

        return $form;
    }

    public static function getDespawnFormConfirmation(Living $entity): Form
    {
        $closure = function (Player $player, ?bool $data) use ($entity): void {
            if ($data === null || $data === false) {
                $player->sendForm(FormsList::getDespawnForm($player));

                return;
            }

            if ($entity->isAlive()) {

                $player->sendMessage(Translation::translate(Translation::get(Translation::MESSAGE_BOSS_DESPAWN), [
                        Translation::PLAYER => $player->getName(),
                        Translation::BOSS => $entity->getName(),
                    ]
                ));

                $entity->close();
            }
        };

        $formData = Translation::getArray("despawn-form-confirmation");

        $form = new ModalForm($closure);
        $form->setTitle(
            Translation::translate(TextFormat::colorize($formData["title"]),
                [
                    Translation::BOSS => $entity->getName(),
                ]
            )
        );
        $form->setContent(
            Translation::translate(TextFormat::colorize($formData["content"]),
                [
                    Translation::BOSS => $entity->getName(),
                ]
            )
        );

        $form->setButton1(TextFormat::colorize($formData["yes"]));
        $form->setButton2(TextFormat::colorize($formData["no"]));

        return $form;
    }

    public static function clearAllData(): void
    {
        FormsList::$formData = [];
    }
}
