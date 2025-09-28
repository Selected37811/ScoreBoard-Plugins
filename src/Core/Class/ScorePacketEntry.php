<?php

namespace Core\Class;

class ScorePacketEntry {

    public const TYPE_FAKE_PLAYER = 3;

    public string $objectiveName = "";
    public int $score = 0;
    public int $scoreboardId = 0;
    public int $type = self::TYPE_FAKE_PLAYER;
    public string $customName = "";
}