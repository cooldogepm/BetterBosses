<?php

declare(strict_types=1);

namespace cooldogedev\BetterBosses;

use pocketmine\math\Vector3;

final class Utils
{
    public static function getLowercaseName(string $name): string
    {
        return str_replace(" ", "_", strtolower($name));
    }

    public static function getOriginalName(string $name): string
    {
        return str_replace("_", " ", ucwords($name));
    }

    public static function vectorFromArray(array $positions): Vector3
    {
        return new Vector3($positions["x"], $positions["y"], $positions["z"]);
    }
}
