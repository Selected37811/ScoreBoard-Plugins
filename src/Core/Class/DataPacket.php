<?php

namespace Core\Class;

use pocketmine\network\mcpe\NetworkSession;

abstract class DataPacket {

    public const NETWORK_ID = 0;

    protected ?NetworkSession $session = null;

    abstract protected function encodePayload(): void;

    abstract protected function decodePayload(): void;

    protected string $buffer = "";

    public function encode(): string {
        $this->buffer = "";
        $this->putByte($this::NETWORK_ID);
        $this->encodePayload();
        return $this->buffer;
    }
    protected function putByte(int $value): void {
        $this->buffer .= chr($value & 0xFF);
    }
    protected function putString(string $value): void {
        $length = strlen($value);
        $this->putVarInt($length);
        $this->buffer .= $value;
    }
    protected function putVarInt(int $value): void {
        while (true) {
            if (($value & ~0x7F) === 0) {
                $this->putByte($value);
                break;
            }
            $this->putByte(($value & 0x7F) | 0x80);
            $value >>= 7;
        }
    }
    public function send(NetworkSession $session): void {
        $session->sendDataPacket($this);
    }
    public function sendToPlayer(\pocketmine\player\Player $player): void {
        $player->getNetworkSession()->sendDataPacket($this);
    }
}