<?php

declare(strict_types=1);

namespace cooldogedev\BetterBosses;

use cooldogedev\BetterBosses\entity\BossEntity;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\Listener;
use pocketmine\player\Player;

final class EventListener implements Listener
{
    /**
     * @param EntityDamageEvent $event
     * @priority MONITOR
     * @ignoreCancelled true
     */
    public function onEntityDamage(EntityDamageEvent $event): void
    {
        $entity = $event->getEntity();

        if ($entity instanceof BossEntity && $event->getFinalDamage() >= $entity->getHealth()) {
            $killer = null;

            if ($event instanceof EntityDamageByEntityEvent && $event->getDamager() instanceof Player) {
                $killer = $event->getDamager();
            }

            $entity->onBossDeath($killer);
        }
    }
}
