<?php

namespace Core\Class;

use pocketmine\network\mcpe\protocol\SetDisplayObjectivePacket;
use pocketmine\network\mcpe\protocol\RemoveObjectivePacket;
use pocketmine\network\mcpe\protocol\SetScorePacket;
use pocketmine\network\mcpe\protocol\types\ScorePacketEntry;
use pocketmine\player\Player;
use Core\Main;
class ScoreboardManager {

    public static function sendScoreboard(Player $player): void {
        $title = "Scoreboard";

        $rm = new RemoveObjectivePacket();
        $rm->objectiveName = "objective";
        $player->getNetworkSession()->sendDataPacket($rm);

        $pk = new SetDisplayObjectivePacket();
        $pk->objectiveName = "objective";
        $pk->displayName = $title;
        $pk->criteriaName = "dummy";
        $pk->displaySlot = "sidebar";
        $pk->sortOrder = 0;
        $player->getNetworkSession()->sendDataPacket($pk);

        $name = $player->getName();
        $blocksBroken = Main::getInstance()->getBlocksBroken($name);
        $playtime = Main::getInstance()->getPlaytimeSeconds($name);
        $minutes = intdiv((int)$playtime, 60);
        if($minutes >= 1440){
            $playDisplay = intdiv($minutes, 1440) . " d";
        }elseif($minutes >= 60){
            $playDisplay = intdiv($minutes, 60) . " h";
        }else{
            $playDisplay = $minutes . " min";
        }

        $entryPlayer = new ScorePacketEntry();
        $entryPlayer->objectiveName = "objective";
        $entryPlayer->score = 4;
        $entryPlayer->scoreboardId = 1;
        $entryPlayer->type = ScorePacketEntry::TYPE_FAKE_PLAYER;
        $entryPlayer->customName = $player->getName();

        $entrySpacer = new ScorePacketEntry();
        $entrySpacer->objectiveName = "objective";
        $entrySpacer->score = 3;
        $entrySpacer->scoreboardId = 2;
        $entrySpacer->type = ScorePacketEntry::TYPE_FAKE_PLAYER;
        $entrySpacer->customName = " ";

        $entryBlocks = new ScorePacketEntry();
        $entryBlocks->objectiveName = "objective";
        $entryBlocks->score = 2;
        $entryBlocks->scoreboardId = 3;
        $entryBlocks->type = ScorePacketEntry::TYPE_FAKE_PLAYER;
        $entryBlocks->customName = "Blocs cassés: " . $blocksBroken;

        $entryPlaytime = new ScorePacketEntry();
        $entryPlaytime->objectiveName = "objective";
        $entryPlaytime->score = 1;
        $entryPlaytime->scoreboardId = 4;
        $entryPlaytime->type = ScorePacketEntry::TYPE_FAKE_PLAYER;
        $entryPlaytime->customName = "Playtime: " . $playDisplay;

        $scorePk = new SetScorePacket();
        $scorePk->type = SetScorePacket::TYPE_CHANGE;
        $scorePk->entries = [$entryPlayer, $entrySpacer, $entryBlocks, $entryPlaytime];

        $player->getNetworkSession()->sendDataPacket($scorePk);
    }
}
