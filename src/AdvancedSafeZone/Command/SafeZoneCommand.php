<?php
namespace AdvancedSafeZone\Command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use AdvancedSafeZone\Main;
use AdvancedSafeZone\Form\SimpleForm;
use AdvancedSafeZone\Form\CustomForm;

class SafeZoneCommand extends Command {
    private Main $plugin;

    public function __construct(Main $plugin) {
        parent::__construct("sz", "Advanced Safe Zone Menu", "/sz", ["safezone"]);
        $this->setPermission("sz.admin");
        $this->plugin = $plugin;
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): bool {
        if (!$sender instanceof Player) return false;

        if (!isset($args[0]) || strtolower($args[0]) === "ui") {
            $this->openMainMenu($sender);
            return true;
        }

        switch (strtolower($args[0])) {
            case "setup":
                $this->plugin->setupSessions[$sender->getName()] = ['step' => 1];
                $sender->sendMessage("§a[SafeZone] Setup mode is now active! Right-click a block with an empty hand to set Point 1.");
                break;

            case "create":
                if (!isset($args[1])) {
                    $sender->sendMessage("§cUsage: /sz create <zone_name>");
                    return false;
                }
                $name = $sender->getName();
                if (isset($this->plugin->setupSessions[$name]) && $this->plugin->setupSessions[$name]['step'] === 3) {
                    $data = $this->plugin->setupSessions[$name];
                    if ($this->plugin->getZoneManager()->isOverlapping($data['pos1'], $data['pos2'], $data['world'])) {
                        $sender->sendMessage("§c[Failed] This area overlaps with an existing Safe Zone!");
                        return false;
                    }
                    $this->plugin->getZoneManager()->createCustomZone($args[1], $data['pos1'], $data['pos2'], $data['world']);
                    unset($this->plugin->setupSessions[$name]);
                    $sender->sendMessage("§a[SafeZone] Zone '{$args[1]}' has been successfully created!");
                } else {
                    $sender->sendMessage("§cPlease set both points first before creating a zone!");
                }
                break;

            case "delete":
                if (!isset($args[1])) {
                    $sender->sendMessage("§cUsage: /sz delete <zone_name>");
                    return false;
                }
                if ($this->plugin->getZoneManager()->deleteCustomZone($args[1])) {
                    $sender->sendMessage("§a[SafeZone] Zone '{$args[1]}' has been successfully deleted!");
                } else {
                    $sender->sendMessage("§c[Failed] No zone with that name was found.");
                }
                break;

            case "bypass":
                $this->openBypassMenu($sender);
                break;

            default:
                $sender->sendMessage("§cType /sz to open the UI Menu, or /sz bypass to enter Admin Bypass mode.");
                break;
        }
        return true;
    }

    private function openMainMenu(Player $player): void {
        $form = new SimpleForm("Advanced Safe Zone", "Please select a configuration option:", function(Player $player, ?int $data) {
            if ($data === null) return;
            switch ($data) {
                case 0: $this->plugin->getServer()->dispatchCommand($player, "sz setup"); break;
                case 1: $this->openCreateMenu($player); break;
                case 2: $this->openSettingMenu($player); break;
                case 3: $this->openGlobalMenu($player); break;
                case 4: $this->openDeleteMenu($player); break;
                case 5: $this->openBypassMenu($player); break;
                case 6: $this->openListMenu($player); break;
            }
        });

        $form->addButton("Setup Zone");
        $form->addButton("Create Zone");
        $form->addButton("Current Zone Settings");
        $form->addButton("Global Zone Settings");
        $form->addButton("Delete Zone");
        $form->addButton("Personal Bypass (Admin Mode)");
        $form->addButton("Zone List");
        $player->sendForm($form);
    }

    // --- PERSONAL BYPASS MENU ---
    private function openBypassMenu(Player $player): void {
        $name = $player->getName();
        $zm = $this->plugin->getZoneManager();

        $form = new CustomForm("Personal Bypass Mode", function(Player $player, ?array $data) use ($zm, $name) {
            if ($data === null) return;
            $zm->setBypass($name, "pvp", $data[1]);
            $zm->setBypass($name, "pve", $data[2]);
            $zm->setBypass($name, "break", $data[3]);
            $zm->setBypass($name, "place", $data[4]);
            $player->sendMessage("§a[SafeZone] Your personal bypass settings have been updated successfully!");
        });

        $form->addLabel("Choose which restrictions you want to bypass inside a Safe Zone.");
        $form->addToggle("Bypass PVP (Allow dealing/receiving player damage)", $zm->getBypass($name, "pvp"));
        $form->addToggle("Bypass PVE (Allow receiving environmental damage)", $zm->getBypass($name, "pve"));
        $form->addToggle("Bypass Block Breaking (Allow breaking blocks)", $zm->getBypass($name, "break"));
        $form->addToggle("Bypass Block Placing (Allow placing blocks)", $zm->getBypass($name, "place"));

        $player->sendForm($form);
    }

    private function openGlobalMenu(Player $player): void {
        $worlds = $this->plugin->getServer()->getWorldManager()->getWorlds();
        $worldNames = [];
        foreach($worlds as $w) $worldNames[] = $w->getFolderName();

        $form = new SimpleForm("Global Zone Settings", "Select a world to configure as a Safe Zone:", function(Player $player, ?int $data) use ($worldNames) {
            if ($data === null) return;
            $selectedWorld = $worldNames[$data];
            $this->openGlobalWorldSetting($player, $selectedWorld);
        });

        foreach($worldNames as $name) {
            $isGlobal = isset($this->plugin->getZoneManager()->getGlobalZones()[$name]);
            $form->addButton($name . ($isGlobal ? " §a[ACTIVE]" : " §c[OFF]"));
        }
        $player->sendForm($form);
    }

    private function openGlobalWorldSetting(Player $player, string $worldName): void {
        $isGlobal = isset($this->plugin->getZoneManager()->getGlobalZones()[$worldName]);
        $form = new CustomForm("Global Setting: $worldName", function(Player $player, ?array $data) use ($worldName) {
            if ($data === null) return;
            $this->plugin->getZoneManager()->setGlobalZone($worldName, $data[0]);
            $player->sendMessage("§a[SafeZone] Global Safe Zone status for world '{$worldName}' has been updated!");
        });
        $form->addToggle("Enable Global Safe Zone", $isGlobal);
        $player->sendForm($form);
    }

    private function openCreateMenu(Player $player): void {
        $name = $player->getName();
        if (!isset($this->plugin->setupSessions[$name]) || $this->plugin->setupSessions[$name]['step'] !== 3) {
            $player->sendMessage("§cPlease set both points first before creating a zone!");
            return;
        }
        $form = new CustomForm("Create Safe Zone", function(Player $player, ?array $data) {
            if ($data === null || empty($data[0])) return;
            $this->plugin->getServer()->dispatchCommand($player, "sz create " . $data[0]);
        });
        $form->addInput("Enter a name for the new zone:");
        $player->sendForm($form);
    }

    private function openDeleteMenu(Player $player): void {
        $zones = array_keys($this->plugin->getZoneManager()->getCustomZones());
        if (empty($zones)) {
            $player->sendMessage("§cThere are no Safe Zones to delete.");
            return;
        }
        $form = new SimpleForm("Delete Safe Zone", "Select the zone you want to delete:", function(Player $player, ?int $data) use ($zones) {
            if ($data === null) return;
            $zoneName = $zones[$data];
            $this->plugin->getServer()->dispatchCommand($player, "sz delete " . $zoneName);
        });
        foreach ($zones as $zone) {
            $form->addButton($zone);
        }
        $player->sendForm($form);
    }

    private function openSettingMenu(Player $player): void {
        $zone = $this->plugin->getZoneManager()->getZoneAt($player->getPosition());
        if ($zone === null) {
            $player->sendMessage("§cYou must be standing inside a Safe Zone to change its settings!");
            return;
        }
        $flags = $this->plugin->getZoneManager()->getZoneFlags($zone);
        $form = new CustomForm("Active Zone Settings", function(Player $player, ?array $data) use ($zone) {
            if ($data === null) return;
            $this->plugin->getZoneManager()->updateFlag($zone, "pvp", $data[1]);
            $this->plugin->getZoneManager()->updateFlag($zone, "pve", $data[2]);
            $this->plugin->getZoneManager()->updateFlag($zone, "break", $data[3]);
            $this->plugin->getZoneManager()->updateFlag($zone, "place", $data[4]);
            $player->sendMessage("§a[SafeZone] Zone flags have been updated successfully!");
        });
        $form->addLabel("Zone: " . explode(":", $zone)[1]);
        $form->addToggle("PVP (Player vs Player Damage)", $flags['pvp']);
        $form->addToggle("PVE (Environmental Damage)", $flags['pve']);
        $form->addToggle("Block Breaking", $flags['break']);
        $form->addToggle("Block Placing", $flags['place']);
        $player->sendForm($form);
    }

    private function openListMenu(Player $player): void {
        $customs = $this->plugin->getZoneManager()->getCustomZones();
        $globals = $this->plugin->getZoneManager()->getGlobalZones();
        $content = "§e--- Custom Zones ---\n";
        foreach ($customs as $name => $z) $content .= "- $name ({$z['world']})\n";
        $content .= "\n§b--- Global Zones ---\n";
        foreach ($globals as $w => $z) $content .= "- $w\n";
        $form = new SimpleForm("Zone List", $content, function(Player $player, ?int $data) {});
        $form->addButton("Close");
        $player->sendForm($form);
    }
}
