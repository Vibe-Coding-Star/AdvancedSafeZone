<?php
namespace AdvancedSafeZone;

use pocketmine\plugin\PluginBase;
use AdvancedSafeZone\Command\SafeZoneCommand;
use AdvancedSafeZone\Task\PopupTask;

class Main extends PluginBase {
    private ZoneManager $zoneManager;

    public array $setupSessions = [];

    public function onEnable(): void {
        $this->zoneManager = new ZoneManager($this);

        $this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);
        $this->getServer()->getCommandMap()->register("advancedsafezone", new SafeZoneCommand($this));

        // Schedule popup task (runs every 20 ticks = 1 second)
        $this->getScheduler()->scheduleRepeatingTask(new PopupTask($this), 20);

        $this->getLogger()->info("§aAdvancedSafeZone is active! Ready to protect your worlds. (API 5)");
    }

    public function onDisable(): void {
        $this->zoneManager->saveZones();
    }

    public function getZoneManager(): ZoneManager {
        return $this->zoneManager;
    }
}
