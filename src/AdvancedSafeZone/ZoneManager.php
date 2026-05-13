<?php
namespace AdvancedSafeZone;

use pocketmine\utils\Config;
use pocketmine\world\Position;

class ZoneManager {
    private Main $plugin;
    private Config $config;
    private array $customZones = [];
    private array $globalZones = [];

    // In-memory storage for admin bypass status (resets on server restart)
    private array $adminBypass = [];

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
        $plugin->saveResource("config.yml");
        $this->config = new Config($plugin->getDataFolder() . "config.yml", Config::YAML);

        if (!$this->config->exists("messages")) {
            $this->config->set("messages", [
                "in_zone" => "§aYou are in a Safe Zone: §e{zone}",
                "leave_zone" => "§cYou have left the Safe Zone!"
            ]);
            $this->config->save();
        }
        $this->loadZones();
    }

    public function getConfig(): Config { return $this->config; }

    public function loadZones(): void {
        $this->customZones = $this->config->get("custom_zones", []);
        $this->globalZones = $this->config->get("global_zones", []);
    }

    public function saveZones(): void {
        $this->config->set("custom_zones", $this->customZones);
        $this->config->set("global_zones", $this->globalZones);
        $this->config->save();
    }

    // --- PERSONAL BYPASS LOGIC ---
    public function getBypass(string $playerName, string $flag): bool {
        // By default, all bypass flags are enabled for admins until manually changed
        if (!isset($this->adminBypass[$playerName])) {
            $this->adminBypass[$playerName] = ["pvp" => true, "pve" => true, "break" => true, "place" => true];
        }
        return $this->adminBypass[$playerName][$flag];
    }

    public function setBypass(string $playerName, string $flag, bool $value): void {
        if (!isset($this->adminBypass[$playerName])) {
            $this->adminBypass[$playerName] = ["pvp" => true, "pve" => true, "break" => true, "place" => true];
        }
        $this->adminBypass[$playerName][$flag] = $value;
    }

    // --- ZONE LOGIC ---
    public function isOverlapping(array $pos1, array $pos2, string $world): bool {
        $minX1 = min($pos1['x'], $pos2['x']); $maxX1 = max($pos1['x'], $pos2['x']);
        $minZ1 = min($pos1['z'], $pos2['z']); $maxZ1 = max($pos1['z'], $pos2['z']);

        foreach ($this->customZones as $name => $data) {
            if (!isset($data['world']) || $data['world'] !== $world) continue;

            $minX2 = min($data['pos1']['x'], $data['pos2']['x']); $maxX2 = max($data['pos1']['x'], $data['pos2']['x']);
            $minZ2 = min($data['pos1']['z'], $data['pos2']['z']); $maxZ2 = max($data['pos1']['z'], $data['pos2']['z']);

            if ($minX1 <= $maxX2 && $maxX1 >= $minX2 && $minZ1 <= $maxZ2 && $maxZ1 >= $minZ2) return true;
        }
        return false;
    }

    public function createCustomZone(string $name, array $pos1, array $pos2, string $world): void {
        $this->customZones[$name] = [
            "pos1" => ["x" => $pos1['x'], "z" => $pos1['z']],
            "pos2" => ["x" => $pos2['x'], "z" => $pos2['z']],
            "world" => $world,
            "flags" => ["pvp" => false, "pve" => false, "break" => false, "place" => false]
        ];
        $this->saveZones();
    }

    public function deleteCustomZone(string $name): bool {
        if (isset($this->customZones[$name])) {
            unset($this->customZones[$name]);
            $this->saveZones();
            return true;
        }
        return false;
    }

    public function setGlobalZone(string $world, bool $state): void {
        if ($state) {
            if (!isset($this->globalZones[$world])) $this->globalZones[$world] = ["flags" => ["pvp" => false, "pve" => false, "break" => false, "place" => false]];
        } else {
            unset($this->globalZones[$world]);
        }
        $this->saveZones();
    }

    public function getZoneAt(Position $pos): ?string {
        $x = $pos->getX(); $z = $pos->getZ(); $worldName = $pos->getWorld()->getFolderName();

        foreach ($this->customZones as $name => $data) {
            if (!isset($data['world']) || !isset($data['pos1']['x']) || !isset($data['pos1']['z']) || !isset($data['pos2']['x']) || !isset($data['pos2']['z'])) continue;

            if ($data['world'] === $worldName) {
                $minX = min($data['pos1']['x'], $data['pos2']['x']); $maxX = max($data['pos1']['x'], $data['pos2']['x']);
                $minZ = min($data['pos1']['z'], $data['pos2']['z']); $maxZ = max($data['pos1']['z'], $data['pos2']['z']);

                if ($x >= $minX && $x <= $maxX && $z >= $minZ && $z <= $maxZ) return "custom:" . $name;
            }
        }
        if (isset($this->globalZones[$worldName])) return "global:" . $worldName;
        return null;
    }

    public function updateFlag(string $zoneKey, string $flag, bool $value): void {
        $parts = explode(":", $zoneKey, 2);
        if ($parts[0] === "custom" && isset($this->customZones[$parts[1]])) {
            $this->customZones[$parts[1]]['flags'][$flag] = $value;
        } elseif ($parts[0] === "global" && isset($this->globalZones[$parts[1]])) {
            $this->globalZones[$parts[1]]['flags'][$flag] = $value;
        }
        $this->saveZones();
    }

    public function getZoneFlags(string $zoneKey): array {
        $parts = explode(":", $zoneKey, 2);
        if ($parts[0] === "custom") return $this->customZones[$parts[1]]['flags'];
        return $this->globalZones[$parts[1]]['flags'];
    }

    public function getCustomZones(): array { return $this->customZones; }
    public function getGlobalZones(): array { return $this->globalZones; }
}
