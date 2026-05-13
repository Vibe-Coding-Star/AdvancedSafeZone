<?php
namespace AdvancedSafeZone\Form;

use pocketmine\form\Form;
use pocketmine\player\Player;

class SimpleForm implements Form {
    private array $data = [];
    private $callable;

    public function __construct(string $title, string $content, callable $callable) {
        $this->data = ["type" => "form", "title" => $title, "content" => $content, "buttons" => []];
        $this->callable = $callable;
    }

    public function addButton(string $text, string $imageType = "", string $imagePath = ""): void {
        $button = ["text" => $text];
        if($imageType !== "") $button["image"] = ["type" => $imageType, "data" => $imagePath];
        $this->data["buttons"][] = $button;
    }

    public function handleResponse(Player $player, $data): void {
        $callable = $this->callable;
        $callable($player, $data);
    }

    public function jsonSerialize(): array { return $this->data; }
}