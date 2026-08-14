<?php

declare(strict_types=1);

namespace Tests\Support\Settings;

use Modules\Settings\Libraries\UpdateService;
use ReflectionProperty;

/**
 * UpdateService double that keeps every read path real and neuters only the write.
 *
 * resolveBackupDir() and listBackups() run their production implementations against
 * a scratch directory, so a controller test still exercises the real restore-source
 * selection. rollback() is the single overridden method: it records what it was
 * asked to do and returns a configurable result instead of copying files, because
 * the real one writes into ROOTPATH and no test may do that.
 */
final class SpyUpdateService extends UpdateService
{
    public int $rollbackCalls = 0;

    public ?string $rollbackDir = null;

    /** @var list<string> */
    public array $rollbackFiles = [];

    /** Value the stubbed rollback() hands back to the controller. */
    public bool $rollbackResult = true;

    /**
     * @param string $backupBaseDir Scratch directory standing in for WRITEPATH.'backups/'
     */
    public function __construct(string $backupBaseDir)
    {
        parent::__construct(new StubCurlRequest());

        (new ReflectionProperty(UpdateService::class, 'backupBaseDir'))
            ->setValue($this, rtrim($backupBaseDir, '/') . '/');
    }

    /**
     * @param string       $backupDir
     * @param list<string> $filesToRestore
     */
    public function rollback(string $backupDir, array $filesToRestore): bool
    {
        $this->rollbackCalls++;
        $this->rollbackDir   = $backupDir;
        $this->rollbackFiles = array_values($filesToRestore);

        return $this->rollbackResult;
    }
}
