<?php

namespace Modules\Logs\Libraries;

class LogViewer
{
    private $logFolderPath = WRITEPATH . 'logs/';
    private $logFilePattern = "log-*.log";
    private $maxLogSize = 52428800; // 50MB
    private $maxContentLength = 300;

    private static $levelsIcon = [
        'CRITICAL'  => 'fas fa-exclamation-triangle',
        'EMERGENCY' => 'fas fa-exclamation-triangle',
        'ALERT'     => 'fas fa-exclamation-circle',
        'ERROR'     => 'fas fa-bug',
        'WARNING'   => 'fas fa-exclamation-circle',
        'NOTICE'    => 'fas fa-info-circle',
        'INFO'      => 'fas fa-info-circle',
        'DEBUG'     => 'fas fa-vial',
        'ALL'       => 'fas fa-list',
    ];

    private static $levelClasses = [
        'CRITICAL'  => 'danger',
        'EMERGENCY' => 'danger',
        'ALERT'     => 'warning',
        'ERROR'     => 'danger',
        'WARNING'   => 'warning',
        'NOTICE'    => 'info',
        'INFO'      => 'info',
        'DEBUG'     => 'secondary',
        'ALL'       => 'dark',
    ];

    private const LOG_LINE_PATTERN = '/^([A-Z]+)\s*-\s*([\d-]+\s+[\d:]+)\s*-->\s*(.*)$/Us';

    public function getFiles(): array
    {
        $files = glob($this->logFolderPath . $this->logFilePattern);
        if (!$files) return [];

        $files = array_reverse($files);
        return array_map('basename', $files);
    }

    public function getLogs(string $fileName): ?array
    {
        $filePath = realpath($this->logFolderPath . basename($fileName));

        if (!$filePath || !is_file($filePath)) {
            return [];
        }

        if (filesize($filePath) > $this->maxLogSize) {
            return null;
        }

        $content = file_get_contents($filePath);
        $lines = preg_split('/\r\n|\r|\n/', $content);

        $parsedLogs = [];
        $currentLog = null;

        foreach ($lines as $line) {
            if (empty(trim($line))) continue;

            if (preg_match(self::LOG_LINE_PATTERN, $line, $matches)) {
                if ($currentLog) {
                    $parsedLogs[] = $currentLog;
                }

                $level = strtoupper($matches[1]);
                $message = $matches[3];

                $currentLog = [
                    'level'   => $level,
                    'date'    => $matches[2],
                    'icon'    => self::$levelsIcon[$level] ?? 'fas fa-question',
                    'class'   => self::$levelClasses[$level] ?? 'dark',
                    'content' => strlen($message) > $this->maxContentLength ? substr($message, 0, $this->maxContentLength) : $message,
                    'extra'   => strlen($message) > $this->maxContentLength ? substr($message, $this->maxContentLength) : ''
                ];
            } elseif ($currentLog) {
                $currentLog['extra'] .= ($currentLog['extra'] ? "\n" : "") . $line;
            }
        }

        if ($currentLog) {
            $parsedLogs[] = $currentLog;
        }

        return array_reverse($parsedLogs);
    }

    public function deleteFile(string $fileName): bool
    {
        if ($fileName === 'all') {
            $files = glob($this->logFolderPath . $this->logFilePattern);
            foreach ($files as $file) {
                @unlink($file);
            }
            return true;
        }

        $filePath = realpath($this->logFolderPath . basename($fileName));
        if ($filePath && is_file($filePath)) {
            return @unlink($filePath);
        }
        return false;
    }
}
