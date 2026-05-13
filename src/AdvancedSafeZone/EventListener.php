<?php
namespace AdvancedSafeZone;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\player\Player;

class EventListener implements Listener {
    private Main $plugin;

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

    public function onInteract(PlayerInteractEvent $event): void {
        $player = $event->getPlayer();
        $name = $player->getName();

        if ($event->getAction() === PlayerInteractEvent::RIGHT_CLICK_BLOCK && $player->getInventory()->getItemInHand()->isNull()) {
            if (isset($this->plugin->setupSessions[$name]) && $this->plugin->setupSessions[$name]['step'] === 1) {
                $block = $event->getBlock();
                $this->plugin->setupSessions[$name]['pos1'] = ["x" => $block->getPosition()->getX(), "z" => $block->getPosition()->getZ()];
                $this->plugin->setupSessions[$name]['world'] = $block->getPosition()->getWorld()->getFolderName();
                $this->plugin->setupSessions[$name]['step'] = 2;
                $player->sendMessage("§a[SafeZone] Point 1 set at X: " . $block->getPosition()->getX() . ", Z: " . $block->getPosition()->getZ() . ". Now BREAK a block to set Point 2.");
                $event->cancel();
            }
        }
    }

    public function onBlockBreak(BlockBreakEvent $event): void {
        $player = $event->getPlayer();
        $name = $player->getName();

        if (isset($this->plugin->setupSessions[$name]) && $this->plugin->setupSessions[$name]['step'] === 2) {
            $block = $event->getBlock();
            $this->plugin->setupSessions[$name]['pos2'] = ["x" => $block->getPosition()->getX(), "z" => $block->getPosition()->getZ()];
            $this->plugin->setupSessions[$name]['step'] = 3;
            $player->sendMessage("§a[SafeZone] Point 2 set! Type §e/sz create <name> §aor open §e/sz ui§a to finish.");
            $event->cancel();
            return;
        }

        $zone = $this->plugin->getZoneManager()->getZoneAt($event->getBlock()->getPosition());
        if ($zone !== null) {
            $flags = $this->plugin->getZoneManager()->getZoneFlags($zone);
            if ($flags['break'] === false) {
                // CHECK PERSONAL BYPASS
                if ($player->hasPermission("sz.bypass") && $this->plugin->getZoneManager()->getBypass($name, "break")) {
                    return; // Admin has bypass enabled, allow breaking
                }
                $player->sendPopup("§cYou are inside a Safe Zone!");
                $event->cancel();
            }
        }
    }

    public function onBlockPlace(BlockPlaceEvent $event): void {
        $player = $event->getPlayer();
        $name = $player->getName();

        foreach ($event->getTransaction()->getBlocks() as [$x, $y, $z, $block]) {
            $zone = $this->plugin->getZoneManager()->getZoneAt($block->getPosition());
            if ($zone !== null) {
                $flags = $this->plugin->getZoneManager()->getZoneFlags($zone);
                if ($flags['place'] === false) {
                    // CHECK PERSONAL BYPASS
                    if ($player->hasPermission("sz.bypass") && $this->plugin->getZoneManager()->getBypass($name, "place")) {
                        return; // Admin has bypass enabled, allow placing
                    }
                    $player->sendPopup("§cYou are inside a Safe Zone!");
                    $event->cancel();
                    return;
                }
            }
        }
    }

    public function onDamage(EntityDamageEvent $event): void {
        $entity = $event->getEntity();
        if (!$entity instanceof Player) return;

        $zone = $this->plugin->getZoneManager()->getZoneAt($entity->getPosition());
        if ($zone !== null) {
            $flags = $this->plugin->getZoneManager()->getZoneFlags($zone);

            if ($event instanceof EntityDamageByEntityEvent) {
                $damager = $event->getDamager();
                if ($damager instanceof Player) {
                    if ($flags['pvp'] === false) {
                        // CHECK PERSONAL BYPASS (Admin forcing PVP inside Safe Zone)
                        if ($damager->hasPermission("sz.bypass") && $this->plugin->getZoneManager()->getBypass($damager->getName(), "pvp")) {
                            return; // Let it hit
                        }
                        $event->cancel();
                        $damager->sendPopup("§cPVP is disabled in this zone!");
                    }
                }
            } else {
                if ($flags['pve'] === false) {
                    // CHECK PERSONAL BYPASS (Admin testing environmental damage)
                    if ($entity->hasPermission("sz.bypass") && $this->plugin->getZoneManager()->getBypass($entity->getName(), "pve")) {
                        return; // Let admin take damage
                    }
                    $event->cancel();
                }
            }
        }
    }
}
