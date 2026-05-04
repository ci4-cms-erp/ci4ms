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

        $templine = '';
        $lineNum = 0;
        while (($line = fgets($file)) !== false) {
            $lineNum++;
            if (substr($line, 0, 2) == '--' || trim($line) == '' || substr($line, 0, 1) == '#') {
                continue;
            }

            $templine .= $line;
            if (substr(trim($line), -1, 1) == ';') {
                $trimmed = trim($templine);

                // Check for dangerous patterns
                foreach ($dangerousPatterns as $pattern) {
                    if (preg_match($pattern, $trimmed)) {
                        log_message('error', "DbBackup::restore — Dangerous SQL blocked at line {$lineNum}: " . mb_substr($trimmed, 0, 100));
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
                    log_message('warning', "DbBackup::restore — Unrecognized SQL skipped at line {$lineNum}: " . mb_substr($trimmed, 0, 100));
                    $templine = '';
                    continue;
                }

                $this->db->query($templine);
                $templine = '';
            }
        }

        $this->db->query('SET FOREIGN_KEY_CHECKS = 1');

        fclose($file);
        return true;
    }
}
