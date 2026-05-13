<?php
namespace AdvancedSafeZone\Form;

use pocketmine\form\Form;
use pocketmine\player\Player;

class CustomForm implements Form {
    private array $data = [];
    private $callable;

    public function __construct(string $title, callable $callable) {
        $this->data = ["type" => "custom_form", "title" => $title, "content" => []];
        $this->callable = $callable;
    }

    public function addInput(string $text, string $placeholder = "", string $default = ""): void {
        $this->data["content"][] = ["type" => "input", "text" => $text, "placeholder" => $placeholder, "default" => $default];
    }

    public function addToggle(string $text, bool $default = false): void {
        $this->data["content"][] = ["type" => "toggle", "text" => $text, "default" => $default];
    }

    public function addLabel(string $text): void {
        $this->data["content"][] = ["type" => "label", "text" => $text];
    }

    public function handleResponse(Player $player, $data): void {
        $callable = $this->callable;
        $callable($player, $data);
    }

    public function jsonSerialize(): array { return $this->data; }
}