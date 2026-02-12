<?php

declare(strict_types=1);

namespace SOFe\Capital\Migration;

use FilesystemIterator;
use Generator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SOFe\Capital\AccountLabels;
use SOFe\Capital\Config;
use SplFileInfo;
use pocketmine\Server;
use function is_array;
use function is_int;
use function pathinfo;
use function strtolower;
use function yaml_parse;
use const PATHINFO_FILENAME;

/**
 * Imports player data from SimpleEconomy (SimpleSQL-based YAML storage).
 *
 * SimpleEconomy stores per-player YAML files in:
 *   plugin_data/SimpleEconomy/players/{playername}.yml
 *
 * Each file has the structure:
 *   data:
 *     balance: <int>
 */
final class SimpleEconomySource implements Source {
    public function __construct(
        private string $path,
    ) {
    }

    public function generateEntries() : Generator {
        if (!is_dir($this->path)) {
            throw new ImportException("SimpleEconomy data directory not found: {$this->path}");
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->path, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            /** @var SplFileInfo $file */
            if ($file->getExtension() !== "yml") {
                continue;
            }

            $content = @file_get_contents($file->getPathname());
            if ($content === false) {
                continue;
            }

            $data = @yaml_parse($content);
            if (!is_array($data) || !isset($data["data"]["balance"])) {
                continue;
            }

            $balance = $data["data"]["balance"];
            if (!is_int($balance)) {
                $balance = (int) $balance;
            }

            $playerName = strtolower(pathinfo($file->getFilename(), PATHINFO_FILENAME));

            yield new Entry($balance, [
                AccountLabels::MIGRATION_SOURCE => "simpleeconomy",
                AccountLabels::PLAYER_NAME => $playerName,
            ]);
        }
    }

    public static function parse(Config\Parser $config) : self {
        return new self(
            path: $config->expectString("path", Server::getInstance()->getDataPath() . "plugin_data/SimpleEconomy/players", <<<'EOT'
                The path to the SimpleEconomy players data directory.
                SimpleEconomy stores per-player YAML files in this directory.
                Each file is named {playername}.yml and contains the player's balance.
                EOT,
            ),
        );
    }
}
