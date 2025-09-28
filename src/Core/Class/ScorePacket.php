<?php

namespace Core\Class;

class ScorePacket extends ClientboundPacket {

    public const NETWORK_ID = 0x8F;

    public const TYPE_REMOVE = 0;
    public const TYPE_CHANGE = 1;

    public int $type = self::TYPE_CHANGE;
    public array $entries = [];

}

protected function encodePayload(): void {
    $this->putByte($this->type);
    $this->putVarInt(count($this->entries));
    foreach ($this->entries as $entry) {
        $this->putString($entry->objectiveName);
        $this->putVarInt($entry->score);
        $this->putVarInt($entry->scoreboardId);
        $this->putByte($entry->type);
        if ($entry->type === ScorePacketEntry::TYPE_FAKE_PLAYER) {
            $this->putString($entry->customName);
        }
    }
}