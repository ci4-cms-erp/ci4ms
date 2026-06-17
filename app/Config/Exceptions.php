<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;
use CodeIgniter\Debug\ExceptionHandler;
use CodeIgniter\Debug\ExceptionHandlerInterface;
use Psr\Log\LogLevel;
use Throwable;

/**
 * Setup how the exception handler works.
 */
class Exceptions extends BaseConfig
{
    /**
     * --------------------------------------------------------------------------
     * LOG EXCEPTIONS?
     * --------------------------------------------------------------------------
     * If true, then exceptions will be logged
     * through Services::Log.
     *
     * Default: true
     */
    public bool $log = true;

    /**
     * --------------------------------------------------------------------------
     * DO NOT LOG STATUS CODES
     * --------------------------------------------------------------------------
     * Any status codes here will NOT be logged if logging is turned on.
     * By default, only 404 (Page Not Found) exceptions are ignored.
     *
     * @var list<int>
     */
    public array $ignoreCodes = [404];

    /**
     * --------------------------------------------------------------------------
     * Error Views Path
     * --------------------------------------------------------------------------
     * This is the path to the directory that contains the 'cli' and 'html'
     * directories that hold the views used to generate errors.
     *
     * Default: APPPATH.'Views/errors'
     */
    public string $errorViewPath = APPPATH . 'Views/errors';

    /**
     * --------------------------------------------------------------------------
     * HIDE FROM DEBUG TRACE
     * --------------------------------------------------------------------------
     * Any data that you would like to hide from the debug trace.
     * In order to specify 2 levels, use "/" to separate.
     * ex. ['server', 'setup/password', 'secret_token']
     *
     * @var list<string>
     */
    public array $sensitiveDataInTrace = [];

    /**
     * --------------------------------------------------------------------------
     * WHETHER TO THROW AN EXCEPTION ON DEPRECATED ERRORS
     * --------------------------------------------------------------------------
     * If set to `true`, DEPRECATED errors are only logged and no exceptions are
     * thrown. This option also works for user deprecations.
     */
    public bool $logDeprecations = true;

    /**
     * --------------------------------------------------------------------------
     * LOG LEVEL THRESHOLD FOR DEPRECATIONS
     * --------------------------------------------------------------------------
     * If `$logDeprecations` is set to `true`, this sets the log level
     * to which the deprecation will be logged. This should be one of the log
     * levels recognized by PSR-3.
     *
     * The related `Config\Logger::$threshold` should be adjusted, if needed,
     * to capture logging the deprecations.
     */
    public string $deprecationLogLevel = LogLevel::WARNING;

    /*
     * DEFINE THE HANDLERS USED
     * --------------------------------------------------------------------------
     * Given the HTTP status code, returns exception handler that
     * should be used to deal with this error. By default, it will run CodeIgniter's
     * default handler and display the error information in the expected format
     * for CLI, HTTP, or AJAX requests, as determined by is_cli() and the expected
     * response format.
     *
     * Custom handlers can be returned if you want to handle one or more specific
     * error codes yourself like:
     *
     *      if (in_array($statusCode, [400, 404, 500])) {
     *          return new \App\Libraries\MyExceptionHandler();
     *      }
     *      if ($exception instanceOf PageNotFoundException) {
     *          return new \App\Libraries\MyExceptionHandler();
     *      }
     */
    public function handler(int $statusCode, Throwable $exception): ExceptionHandlerInterface
    {
        // Backend modülü bağlamında ise kendi handler'ımızı kullan
        if ($this->isBackendContext($exception)) {
            return new \Modules\Backend\Exceptions\BackendExceptionHandler($this);
        }

        return new ExceptionHandler($this);
    }

    /**
     * Checks whether the exception is within the Backend module context.
     *
     * 3-layer check:
     * 1. Is the active controller in the Backend namespace?
     * 2. Was the exception thrown from a Backend module file?
     * 3. Is there a Backend module class in the call stack?
     */

    private function isBackendContext(Throwable $exception): bool
    {
        // 1. Aktif controller Backend namespace'inde mi?
        try {
            $controller = service('router')->controllerName();

            if ($controller && str_starts_with($controller, 'Modules\\Backend\\')) {
                return true;
            }
        } catch (\Throwable $e) {
            // Router henüz hazır değilse atla
        }

        // 2. Exception Backend modülü içindeki bir dosyadan mı fırlatıldı?
        if (str_contains($exception->getFile(), 'modules' . DIRECTORY_SEPARATOR . 'Backend' . DIRECTORY_SEPARATOR)) {
            return true;
        }

        // 3. Call stack'te Backend modülü sınıfı var mı?
        foreach ($exception->getTrace() as $frame) {
            if (!isset($frame['class'])) {
                continue;
            }
            // Doğrudan Backend namespace'inde mi?
            if (str_starts_with($frame['class'], 'Modules\\Backend\\')) {
                return true;
            }
            // BaseController'ı extend eden herhangi bir sınıf mı?
            // (Pages, Blog, Catalog vb. backend modülleri bunu karşılar)
            if (
                class_exists($frame['class'], false)
                && is_subclass_of($frame['class'], 'Modules\\Backend\\Controllers\\BaseController')
            ) {
                return true;
            }
        }

        return false;
    }
}
