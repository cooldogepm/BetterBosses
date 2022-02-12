<?php

declare(strict_types=1);

namespace cooldogedev\BetterBosses\translation;

use pocketmine\utils\TextFormat;

final class Translation
{
    // Messages
    public const MESSAGE_BOSS_SPAWN = "boss-spawn";
    public const MESSAGE_BOSS_DESPAWN = "boss-despawn";
    public const MESSAGE_BOSS_SPAWN_BROADCAST = "boss-spawn-broadcast";
    public const MESSAGE_BOSS_KILL_BROADCAST = "boss-kill-broadcast";

    // Variables
    public const PLAYER = "{player}";
    public const BOSS = "{boss}";
    public const X = "{x}";
    public const Y = "{y}";
    public const Z = "{z}";
    public const HEALTH_BAR = "{health_bar}";
    public const HEALTH = "{health}";
    public const MAX_HEALTH = "{max_health}";

    protected static array $translations = [];

    public static function init(array $translations)
    {
        Translation::$translations = $translations;
    }

    public static function get(string $message): ?string
    {
        $message = Translation::$translations[$message] ?? null;
        return $message ? TextFormat::colorize($message) : null;
    }

    public static function getArray(string $message): ?array
    {
        return Translation::$translations[$message] ?? null;
    }

    public static function translate(string $message, array $translations): string
    {
        return str_replace(array_keys($translations), array_values($translations), $message);
    }
}
