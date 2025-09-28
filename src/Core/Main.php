<?php

namespace Core;

use Core\Class\ScoreboardManager;
use Core\Commands\DiscordCommands;
use Core\Commands\StatsCommand;
use Core\Commands\RefreshScoreboardsCommand;
use Core\Tasks\MySqlTask;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;

class Main extends PluginBase implements Listener
{
    public static $instance;
    private Config $statsConfig;
    private array $statsCache = [];
    private array $dirty = [];
    private array $dbCreds;

    protected function onEnable(): void
    {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        self::$instance = $this;
        $commandMap = $this->getServer()->getCommandMap();
        $commandMap->register("core", new StatsCommand());
        $commandMap->register("core", new RefreshScoreboardsCommand());

        $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function(): void {
            foreach($this->getServer()->getOnlinePlayers() as $player){
                ScoreboardManager::sendScoreboard($player);
            }
        }), 1200);

        $this->dbCreds = [
            'host' => 'DB_URL',
            'user' => 'DB_USER',
            'password' => 'DB_PASSWORD',
            'database' => 'DB',
            'port' => DB_PORT,
        ];

        $this->getServer()->getAsyncPool()->submitTask(new MySqlTask('ensure_table', $this->dbCreds));

        $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function(): void {
            foreach($this->getServer()->getOnlinePlayers() as $player){
                $name = $player->getName();
                if(!isset($this->statsCache[$name])){
                    $this->statsCache[$name] = ['blocks' => 0, 'playtime' => 0];
                }
                $this->statsCache[$name]['playtime'] = (int)($this->statsCache[$name]['playtime'] ?? 0) + 60;
                $this->dirty[$name] = true;
            }
        }), 1200);

        $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function(): void {
            $entries = [];
            foreach($this->dirty as $name => $_){
                $blocks = (int)($this->statsCache[$name]['blocks'] ?? ($this->statsCache[$name] ?? 0));
                $play = (int)($this->statsCache[$name]['playtime'] ?? 0);
                $entries[$name] = ['blocks' => $blocks, 'playtime' => $play];
            }
            if(!empty($entries)){
                $this->dirty = [];
                $this->getServer()->getAsyncPool()->submitTask(new MySqlTask('flush_batch', $this->dbCreds, ['entries' => $entries]));
            }
        }), 200);
    }

    public function onPlayerJoin(PlayerJoinEvent $event): void
    {
        $player = $event->getPlayer();
        $name = $player->getName();
        if(!isset($this->statsCache[$name])){
            $this->statsCache[$name] = ['blocks' => 0, 'playtime' => 0];
        }
        $this->getServer()->getAsyncPool()->submitTask(new MySqlTask('load_player', $this->dbCreds, ['name' => $name]));
        ScoreboardManager::sendScoreboard($player);
    }

    public function onBlockBreak(BlockBreakEvent $event): void
    {
        $player = $event->getPlayer();
        $name = $player->getName();
        if(!isset($this->statsCache[$name])){
            $this->statsCache[$name] = ['blocks' => 0, 'playtime' => 0];
        }
        $this->statsCache[$name]['blocks'] = (int)($this->statsCache[$name]['blocks'] ?? 0) + 1;
        $this->dirty[$name] = true;
        ScoreboardManager::sendScoreboard($player);
    }

    public static function getInstance()
    {
        return self::$instance;
    }

    protected function onDisable(): void
    {
        $entries = [];
        foreach($this->dirty as $name => $_){
            $blocks = (int)($this->statsCache[$name]['blocks'] ?? ($this->statsCache[$name] ?? 0));
            $play = (int)($this->statsCache[$name]['playtime'] ?? 0);
            $entries[$name] = ['blocks' => $blocks, 'playtime' => $play];
        }
        if(!empty($entries)){
            $this->getServer()->getAsyncPool()->submitTask(new MySqlTask('flush_batch', $this->dbCreds, ['entries' => $entries]));
        }
        $this->getLogger()->alert("le core vient de s'éteindre");
    }

    public static function getConfigName(string $fileName) {
        return new Config(Main::getInstance()->getDataFolder() . $fileName . "yml" . Config::YAML);
    }

    public function getStatsConfig(): Config
    {
        return $this->statsConfig;
    }

    public function getBlocksBroken(string $name): int
    {
        if(isset($this->statsCache[$name]) && is_array($this->statsCache[$name])){
            return (int)($this->statsCache[$name]['blocks'] ?? 0);
        }
        return (int)($this->statsCache[$name] ?? 0);
    }

    public function getPlaytimeSeconds(string $name): int
    {
        if(isset($this->statsCache[$name]) && is_array($this->statsCache[$name])){
            return (int)($this->statsCache[$name]['playtime'] ?? 0);
        }
        return 0;
    }
    public function onDbTaskComplete(string $op, $result, array $payload): void
    {
        $res = is_array($result) ? $result : [];
        $ok = (bool)($res['ok'] ?? true);
        $err = $res['error'] ?? null;

        switch ($op){
            case 'load_player':
                if(!$ok){
                    $this->getLogger()->warning("MySqlTask load_player failed: " . ($err ?? 'unknown error'));
                    break;
                }
                $name = $res['name'] ?? ($payload['name'] ?? null);
                if(is_string($name)){
                    $blocks = isset($res['blocks']) && $res['blocks'] !== null ? (int)$res['blocks'] : 0;
                    $play = isset($res['playtime']) && $res['playtime'] !== null ? (int)$res['playtime'] : 0;
                    $this->statsCache[$name] = ['blocks' => $blocks, 'playtime' => $play];
                    $player = $this->getServer()->getPlayerExact($name);
                    if($player !== null){
                        ScoreboardManager::sendScoreboard($player);
                    }
                }
                break;

            case 'ensure_table':
                if(!$ok){
                    $this->getLogger()->error("DB ensure_table failed: " . ($err ?? 'unknown error'));
                }else{
                    $this->getLogger()->info("DB ensure_table ok");
                }
                break;

            case 'flush_batch':
                if(!$ok){
                    $this->getLogger()->warning("DB flush_batch failed: " . ($err ?? 'unknown error'));
                }
                break;
        }
    }
}