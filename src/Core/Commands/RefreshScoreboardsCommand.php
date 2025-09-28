<?php

namespace Core\Commands;

use Core\Class\ScoreboardManager;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;

class RefreshScoreboardsCommand extends Command
{
    public function __construct()
    {
        parent::__construct("refreshscoreboards", "Rafraîchir les scoreboards de tous les joueurs", "/refreshscoreboards", ["rsb", "refreshsb"]);
        $this->setPermission("Player.use");
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): bool
    {
        if(!$this->testPermission($sender)){
            return false;
        }

        $count = 0;
        foreach(($sender->getServer()->getOnlinePlayers()) as $player){
            ScoreboardManager::sendScoreboard($player);
            $count++;
        }

        $msg = "Scoreboards rafraîchis pour {$count} joueur(s).";
        if($sender instanceof Player){
            $sender->sendMessage($msg);
        }else{
            $sender->getServer()->getLogger()->info($msg);
        }
        return true;
    }
}
