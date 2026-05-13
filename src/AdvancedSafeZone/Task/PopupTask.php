<?php
namespace AdvancedSafeZone\Task;

use pocketmine\scheduler\Task;
use AdvancedSafeZone\Main;

class PopupTask extends Task {
    private Main $plugin;
    private array $inZone = [];

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

    public function onRun(): void {
        // Get messages from config
        $messages = $this->plugin->getZoneManager()->getConfig()->get("messages");

        foreach ($this->plugin->getServer()->getOnlinePlayers() as $player) {
            $name = $player->getName();
            $zone = $this->plugin->getZoneManager()->getZoneAt($player->getPosition());

            if ($zone !== null) {
                $zoneName = explode(":", $zone)[1];

                // Replace {zone} tag with the actual zone name
                $popupText = str_replace("{zone}", $zoneName, $messages["in_zone"]);
                $player->sendPopup($popupText);

                $this->inZone[$name] = true;
            } else {
                if (isset($this->inZone[$name]) && $this->inZone[$name] === true) {
                    $player->sendPopup($messages["leave_zone"]);
                    $this->inZone[$name] = false;
                }
            }
        }
    }
}
