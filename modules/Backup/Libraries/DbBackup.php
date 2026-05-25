<?php

namespace Modules\Backup\Libraries;

use CodeIgniter\Database\ConnectionInterface;
use Config\Database;

class DbBackup
{
    protected $db;

    public function __construct(ConnectionInterface $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    public function backup(array $params = [])
    {
        $prefs = [
            'tables'             => [],
            'ignore'             => [],
            'filename'           => '',
            'format'             => 'gzip', // gzip, zip, txt
            'add_drop'           => true,
            'add_insert'         => true,
            'newline'            => "\n",
            'foreign_key_checks' => true,
        ];

        $prefs = array_merge($prefs, $params);

        if (empty($prefs['tables'])) {
            $prefs['tables'] = $this->db->listTables();
        }

        if (! in_array($prefs['format'], ['gzip', 'zip', 'txt'], true)) {
            $prefs['format'] = 'txt';
        }

        $out = $this->generateSql($prefs);

        if ($prefs['format'] === 'gzip') {
            return gzencode($out);
        }

        if ($prefs['format'] === 'zip') {
            $filename = $prefs['filename'] ?: 'backup.sql';
            if (! preg_match('|.+?\.sql$|', $filename)) {
                $filename .= '.sql';
            }

            $zip = new \ZipArchive();
            $tmpFile = tempnam(sys_get_temp_dir(), 'backup');
            if ($zip->open($tmpFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE)) {
                $zip->addFromString($filename, $out);
                $zip->close();
                $zipData = file_get_contents($tmpFile);
                @unlink($tmpFile);
                return $zipData;
            }
        }

        return $out;
    }

    protected function generateSql(array $prefs)
    {
        $out = '';

        if ($prefs['foreign_key_checks'] === false) {
            $out .= 'SET foreign_key_checks = 0;' . $prefs['newline'];
        }

        foreach ($prefs['tables'] as $table) {
            if (in_array($table, $prefs['ignore'], true)) {
                continue;
            }

            if ($prefs['add_drop']) {
                $out .= 'DROP TABLE IF EXISTS ' . $this->db->escapeIdentifiers($table) . ';' . $prefs['newline'];
            }

            $query = $this->db->query('SHOW CREATE TABLE ' . $this->db->escapeIdentifiers($table));
            $row = $query->getRowArray();

            // Create Table column name can sometimes vary (e.g., Create View), taking the 2nd column
            $createSql = array_values($row)[1] ?? '';

            $out .= $createSql . ';' . $prefs['newline'] . $prefs['newline'];

            if ($prefs['add_insert']) {
                $rows = $this->db->table($table)->get()->getResultArray();
                foreach ($rows as $row) {
                    $vals = [];
                    foreach ($row as $v) {
                        $vals[] = $v === null ? 'NULL' : $this->db->escape($v);
                    }
                    $out .= 'INSERT INTO ' . $this->db->escapeIdentifiers($table) . ' VALUES (' . implode(', ', $vals) . ');' . $prefs['newline'];
                }
                $out .= $prefs['newline'];
            }
        }

        if ($prefs['foreign_key_checks'] === false) {
            $out .= 'SET foreign_key_checks = 1;' . $prefs['newline'];
        }

        return $out;
    }

    public function restore(string $filePath)
    {
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        if ($extension != 'sql') return false;

        // Security: Verify file is within writable directory
        $realPath = realpath($filePath);
        if (!$realPath || strpos($realPath, realpath(WRITEPATH)) !== 0) {
            log_message('error', 'DbBackup::restore — Path traversal attempt blocked: ' . $filePath);
            return false;
        }

        $file = fopen($realPath, 'r');
        if (! $file) {
            return false;
        }

        // Dangerous SQL patterns that should never appear in a legitimate backup
        $dangerousPatterns = [
            '/\bLOAD_FILE\s*\(/i',
            '/\bINTO\s+(OUTFILE|DUMPFILE)\b/i',
            '/\bGRANT\b/i',
            '/\bCREATE\s+USER\b/i',
            '/\bDROP\s+USER\b/i',
            '/\bSYSTEM\s*\(/i',
            '/\bEXEC\s*\(/i',
            '/\bxp_cmdshell\b/i',
            '/\bCREATE\s+(FUNCTION|PROCEDURE|TRIGGER|EVENT)\b/i',
            '/\bALTER\s+USER\b/i',
            '/\bSET\s+GLOBAL\b/i',
            '/\bSHUTDOWN\b/i',
        ];

        // Allowed SQL statement prefixes (whitelist approach)
        $allowedPrefixes = [
            'INSERT', 'CREATE TABLE', 'DROP TABLE', 'ALTER TABLE',
            'SET', 'UPDATE', 'DELETE', 'LOCK', 'UNLOCK',
            'START TRANSACTION', 'COMMIT', 'ROLLBACK',
        ];

        $this->db->query('SET FOREIGN_KEY_CHECKS = 0');

        $stmtNum = 0;
        foreach ($this->splitStatements($file) as $statement) {
            $stmtNum++;
            $trimmed = trim($statement);
            if ($trimmed === '') continue;

            // Check for dangerous patterns
            foreach ($dangerousPatterns as $pattern) {
                if (preg_match($pattern, $trimmed)) {
                    log_message('error', "DbBackup::restore — Dangerous SQL blocked at statement {$stmtNum}: " . mb_substr($trimmed, 0, 100));
                    fclose($file);
                    $this->db->query('SET FOREIGN_KEY_CHECKS = 1');
                    return false;
                }
            }

            // Verify statement starts with an allowed prefix
            $isAllowed = false;
            foreach ($allowedPrefixes as $prefix) {
                if (stripos($trimmed, $prefix) === 0) {
                    $isAllowed = true;
                    break;
                }
            }

            if (!$isAllowed) {
                log_message('warning', "DbBackup::restore — Unrecognized SQL skipped at statement {$stmtNum}: " . mb_substr($trimmed, 0, 100));
                continue;
            }

            $this->db->query($statement);
        }

        $this->db->query('SET FOREIGN_KEY_CHECKS = 1');

        fclose($file);
        return true;
    }

    /**
     * Stream a SQL file and yield one statement at a time, tracking quote and
     * comment state so a `;` inside a string literal, identifier backtick,
     * or comment does NOT terminate the statement prematurely.
     *
     * Replaces the previous "split on lines ending with ';'" heuristic, which
     * could chop an INSERT mid-value if a row contained `;\n` inside a string
     * and then process the malformed remainder against the whitelist.
     *
     * Handles:
     *  - single/double/backtick quotes with backslash escapes and SQL doubled
     *    quote escapes ('' inside '...', "" inside "..." etc.)
     *  - -- line comments
     *  - # line comments
     *  - / * ... * / block comments
     *  - bare semicolons inside string literals (preserved, not split)
     */
    private function splitStatements($fileHandle): \Generator
    {
        $buffer        = '';
        $quote         = null; // null | "'" | '"' | '`'
        $inLineComment = false;
        $inBlock       = false;
        $escapeNext    = false;

        while (!feof($fileHandle)) {
            $chunk = fread($fileHandle, 8192);
            if ($chunk === false || $chunk === '') break;

            for ($i = 0, $len = strlen($chunk); $i < $len; $i++) {
                $c    = $chunk[$i];
                $next = $i + 1 < $len ? $chunk[$i + 1] : '';

                // Inside a backslash-escape (only meaningful inside quotes).
                if ($escapeNext) {
                    $buffer .= $c;
                    $escapeNext = false;
                    continue;
                }

                // Inside /* ... */
                if ($inBlock) {
                    if ($c === '*' && $next === '/') {
                        $inBlock = false;
                        $i++; // skip '/'
                    }
                    continue;
                }

                // Inside -- or # line comment
                if ($inLineComment) {
                    if ($c === "\n") {
                        $inLineComment = false;
                        $buffer .= "\n"; // keep newline for readability
                    }
                    continue;
                }

                // Inside a quoted string / identifier
                if ($quote !== null) {
                    $buffer .= $c;
                    if ($c === '\\') {
                        $escapeNext = true;
                        continue;
                    }
                    if ($c === $quote) {
                        // SQL doubled-quote escape: '' inside '…' is a literal quote
                        if ($next === $quote) {
                            $buffer .= $next;
                            $i++;
                        } else {
                            $quote = null;
                        }
                    }
                    continue;
                }

                // Bare context — recognise quote starts, comments, and statement end
                if ($c === "'" || $c === '"' || $c === '`') {
                    $quote = $c;
                    $buffer .= $c;
                    continue;
                }
                if ($c === '-' && $next === '-') {
                    $inLineComment = true;
                    $i++; // skip second '-'
                    continue;
                }
                if ($c === '#') {
                    $inLineComment = true;
                    continue;
                }
                if ($c === '/' && $next === '*') {
                    $inBlock = true;
                    $i++; // skip '*'
                    continue;
                }
                if ($c === ';') {
                    yield $buffer;
                    $buffer = '';
                    continue;
                }
                $buffer .= $c;
            }
        }

        if (trim($buffer) !== '') {
            yield $buffer;
        }
    }
}
