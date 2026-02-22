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

            // Create Table sütunu bazen farklı isimde olabilir (örn: Create View), 2. sütunu alıyoruz
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
        $file = fopen($filePath, 'r');
        if (! $file) {
            return false;
        }

        $this->db->query('SET FOREIGN_KEY_CHECKS = 0');

        $templine = '';
        while (($line = fgets($file)) !== false) {
            if (substr($line, 0, 2) == '--' || trim($line) == '' || substr($line, 0, 1) == '#') {
                continue;
            }

            $templine .= $line;
            if (substr(trim($line), -1, 1) == ';') {
                $this->db->query($templine);
                $templine = '';
            }
        }

        $this->db->query('SET FOREIGN_KEY_CHECKS = 1');

        fclose($file);
        return true;
    }
}
