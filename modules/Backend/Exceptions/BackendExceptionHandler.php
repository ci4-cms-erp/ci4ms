<?php

namespace Modules\Backend\Exceptions;

use CodeIgniter\Debug\BaseExceptionHandler;
use CodeIgniter\Debug\ExceptionHandlerInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

/**
 * Backend modülüne özel Custom Exception Handler.
 *
 * Backend modülü bağlamında fırlatılan exception'lar için
 * kendi temalı hata sayfalarını render eder.
 */
class BackendExceptionHandler extends BaseExceptionHandler implements ExceptionHandlerInterface
{
    /**
     * Backend error view'larının bulunduğu dizin.
     */
    protected ?string $viewPath = ROOTPATH . 'modules/Backend/Views/errors/';

    /**
     * Exception'ı yakala ve Backend temalı hata sayfasını göster.
     */
    public function handle(
        Throwable $exception,
        RequestInterface $request,
        ResponseInterface $response,
        int $statusCode,
        int $exitCode,
    ): void {
        // CLI isteğiyse varsayılan CLI error view'ını kullan
        if (is_cli()) {
            $this->render($exception, $statusCode, $this->viewPath . "cli/error_{$statusCode}.php");
            exit($exitCode);
        }

        // HTTP status code'a özel view var mı?
        $viewFile = $this->viewPath . "html/error_{$statusCode}.php";

        if (is_file($viewFile)) {
            // Status code'a özel view mevcut — doğrudan BaseExceptionHandler render
            $this->render($exception, $statusCode, $viewFile);
        } else {
            // Yoksa environment'a göre fallback
            $fallback = (ENVIRONMENT === 'production')
                ? $this->viewPath . 'html/production.php'
                : $this->viewPath . 'html/error_exception.php';

            $this->render($exception, $statusCode, $fallback);
        }

        exit($exitCode);
    }
}
