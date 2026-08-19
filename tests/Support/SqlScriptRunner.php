<?php

namespace Tests\Support;

use PDO;
use RuntimeException;

class SqlScriptRunner
{
    public static function run(PDO $pdo, string $path): void
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException("Unable to read SQL fixture: {$path}");
        }

        $delimiter = ';';
        $buffer = '';

        foreach (preg_split('/\R/', $contents) as $line) {
            if (preg_match('/^\s*DELIMITER\s+(\S+)\s*$/i', $line, $matches) === 1) {
                if (trim($buffer) !== '') {
                    throw new RuntimeException('DELIMITER encountered before SQL statement was complete.');
                }

                $delimiter = $matches[1];
                continue;
            }

            $buffer .= $line."\n";
            if (! str_ends_with(rtrim($buffer), $delimiter)) {
                continue;
            }

            $statement = rtrim(substr(rtrim($buffer), 0, -strlen($delimiter)));
            if ($statement !== '') {
                $pdo->exec($statement);
            }

            $buffer = '';
        }

        if (trim($buffer) !== '') {
            throw new RuntimeException('SQL fixture ended with an incomplete statement.');
        }
    }
}
