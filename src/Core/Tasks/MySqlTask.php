<?php

namespace Core\Tasks;

use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use Core\Main;

class MySqlTask extends AsyncTask
{
    private string $op;
    private string $host;
    private string $user;
    private string $password;
    private string $database;
    private int $port;
    private string $payloadJson;

    public function __construct(string $op, array $creds, array $payload = [])
    {
        $this->op = $op;
        $this->host = (string)($creds['host'] ?? 'localhost');
        $this->user = (string)($creds['user'] ?? '');
        $this->password = (string)($creds['password'] ?? '');
        $this->database = (string)($creds['database'] ?? '');
        $this->port = (int)($creds['port'] ?? 3306);
        $this->payloadJson = json_encode($payload, JSON_THROW_ON_ERROR);
    }

    public function onRun(): void
    {
        try{
            if(!class_exists(\mysqli::class)){
                $this->setResult(['ok' => false, 'op' => $this->op, 'error' => 'mysqli extension not available in async worker']);
                return;
            }

            $host = $this->host;
            $user = $this->user;
            $pass = $this->password;
            $db   = $this->database;
            $port = $this->port;

            $payload = [];
            try{
                $decoded = json_decode($this->payloadJson, true, 512, JSON_THROW_ON_ERROR);
                if(is_array($decoded)){
                    $payload = $decoded;
                }
            }catch(\Throwable $e){
            }

            $mysqli = @new \mysqli($host, $user, $pass, $db, $port);
            if($mysqli->connect_error){
                $this->setResult(['ok' => false, 'error' => $mysqli->connect_error, 'op' => $this->op]);
                return;
            }

            $mysqli->set_charset('utf8mb4');

            switch ($this->op){
                case 'ensure_table':
                    $sql = "CREATE TABLE IF NOT EXISTS player_stats (\n                    name VARCHAR(16) NOT NULL PRIMARY KEY,\n                    blocks_broken INT NOT NULL DEFAULT 0\n                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
                    $ok = $mysqli->query($sql) === true;
                    $error = $ok ? null : $mysqli->error;
                    if($ok){
                        $alterOk = true;
                        $res = $mysqli->query("SHOW COLUMNS FROM player_stats LIKE 'playtime_seconds'");
                        if($res instanceof \mysqli_result){
                            $hasCol = $res->num_rows > 0;
                            $res->free();
                        }else{
                            $hasCol = false;
                        }
                        if(!$hasCol){
                            $alterOk = ($mysqli->query("ALTER TABLE player_stats ADD COLUMN playtime_seconds INT NOT NULL DEFAULT 0") === true);
                            if(!$alterOk){
                                $error = $mysqli->error;
                            }
                        }
                        $ok = $ok && $alterOk;
                    }
                    $this->setResult(['ok' => $ok, 'op' => $this->op, 'error' => $ok ? null : $error]);
                    break;

                case 'load_player':
                    $name = (string)($payload['name'] ?? '');
                    $stmt = $mysqli->prepare('SELECT blocks_broken, playtime_seconds FROM player_stats WHERE name = ? LIMIT 1');
                    if(!$stmt){
                        $this->setResult(['ok' => false, 'op' => $this->op, 'error' => $mysqli->error]);
                        break;
                    }
                    $stmt->bind_param('s', $name);
                    $stmt->execute();
                    $res = $stmt->get_result();
                    $row = $res ? $res->fetch_assoc() : null;
                    $blocks = $row ? (int)$row['blocks_broken'] : null;
                    $playtime = $row ? (int)$row['playtime_seconds'] : null;
                    $this->setResult(['ok' => true, 'op' => $this->op, 'name' => $name, 'blocks' => $blocks, 'playtime' => $playtime]);
                    $stmt->close();
                    break;

                case 'flush_batch':
                    $entries = $payload['entries'] ?? [];
                    if(empty($entries)){
                        $this->setResult(['ok' => true, 'op' => $this->op, 'count' => 0]);
                        break;
                    }
                    $stmt = $mysqli->prepare('INSERT INTO player_stats (name, blocks_broken, playtime_seconds) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE blocks_broken = VALUES(blocks_broken), playtime_seconds = VALUES(playtime_seconds)');
                    if(!$stmt){
                        $this->setResult(['ok' => false, 'op' => $this->op, 'error' => $mysqli->error]);
                        break;
                    }
                    $count = 0;
                    foreach($entries as $name => $vals){
                        $b = (int)($vals['blocks'] ?? 0);
                        $p = (int)($vals['playtime'] ?? 0);
                        $stmt->bind_param('sii', $name, $b, $p);
                        if($stmt->execute()){
                            $count++;
                        }
                    }
                    $stmt->close();
                    $this->setResult(['ok' => true, 'op' => $this->op, 'count' => $count]);
                    break;
            }
            $mysqli->close();
        }catch(\Throwable $e){
            $this->setResult(['ok' => false, 'op' => $this->op, 'error' => 'Unhandled exception: ' . $e->getMessage()]);
        }
    }

    public function onCompletion(): void
    {
        $plugin = Main::getInstance();
        if($plugin !== null){
            $plugin->onDbTaskComplete($this->op, $this->getResult(), []);
        }
    }
}
