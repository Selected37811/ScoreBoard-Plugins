<?php

namespace Core\Commands;

use Core\Main;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;

class StatsCommand extends Command
{
    public function __construct()
    {
        parent::__construct("stats", "Ouvre le menu des statistiques", "/stats", ["sts"]);
        $this->setPermission("Player.use");
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): bool
    {
        if(!$this->testPermission($sender)){
            return false;
        }

        if(!$sender instanceof Player){
            $sender->sendMessage("Cette commande doit être exécutée en jeu.");
            return false;
        }

        $name = $sender->getName();
        $blocksBroken = Main::getInstance()->getBlocksBroken($name);
        $playtime = Main::getInstance()->getPlaytimeSeconds($name);
        $playMin = intdiv((int)$playtime, 60);

        if(class_exists('jojoe77777\FormAPI\SimpleForm')){
            $form = new \jojoe77777\FormAPI\SimpleForm(function(Player $player, ?int $data){});
            $form->setTitle("Statistiques");
            $form->setContent("§7Pseudo: §f" . $name . "\n\n§7Blocs cassés: §a" . $blocksBroken . "\n§7Playtime: §a" . $playMin . " min");
            $form->addButton("Fermer");
            $sender->sendForm($form);
            return true;
        }

        $sender->sendMessage("§6—— Stats ——\n§7Pseudo: §f{$name}\n§7Blocs cassés: §a{$blocksBroken}\n§7Playtime: §a{$playMin} min");
        return true;
    }
}
