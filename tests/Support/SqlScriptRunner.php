<?php

namespace Tests\Support;

use PDO;
use RuntimeException;

class SqlScriptRunner
{
    public static function run(PDO $pdo, string $path): void
    {
        // Fixtures contain Vietnamese literals. Make the client encoding explicit
        // even when a caller supplies a PDO created outside Laravel's connector.
        $pdo->exec('SET NAMES utf8mb4');
        if (defined('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY')) {
            $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
        }
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException("Unable to read SQL fixture: {$path}");
        }

        $delimiter = ';';
        $buffer = '';

        // Do not use PCRE \R here: without a UTF-8 mode it can treat the
        // 0x85 byte in Vietnamese characters such as "Nguyễn" as a newline.
        foreach (preg_split('/\r\n|\r|\n/', $contents) as $line) {
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
                $statementForClassification = preg_replace(
                    '/\A(?:\s*(?:--|#)[^\r\n]*(?:\r\n|\r|\n))+/',
                    '',
                    $statement,
                );
                if (preg_match('/^\s*(?:SELECT|SHOW|DESCRIBE|EXPLAIN|WITH)\b/i', $statementForClassification ?? $statement) === 1) {
                    // MariaDB keeps a result set open on the same connection;
                    // drain every read statement before the next DDL/DML.
                    $result = $pdo->query($statement);
                    $result->fetchAll(PDO::FETCH_ASSOC);
                    $result->closeCursor();
                } else {
                    $pdo->exec($statement);
                }
            }

            $buffer = '';
        }

        if (trim($buffer) !== '') {
            throw new RuntimeException('SQL fixture ended with an incomplete statement.');
        }
    }
}
