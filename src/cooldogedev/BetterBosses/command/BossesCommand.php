<?php

declare(strict_types=1);

namespace cooldogedev\BetterBosses\command;

use cooldogedev\BetterBosses\BetterBosses;
use cooldogedev\BetterBosses\FormsList;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;

final class BossesCommand extends Command
{
    public function __construct(protected BetterBosses $plugin)
    {
        parent::__construct("betterbosses", "Spawn bosses", "", ["bosses"]);
        $this->setPermission("betterbosses.manage");
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): void
    {
        if (!$this->testPermission($sender)) {
            return;
        }

        if (!$sender instanceof Player) {
            $sender->sendMessage(TextFormat::RED . "Please run this command in-game.");
            return;
        }

        $sender->sendForm(FormsList::getMainForm());
    }
}
