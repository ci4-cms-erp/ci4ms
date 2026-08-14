<?php

namespace Modules\Backend\Exceptions;

use CodeIgniter\Debug\BaseExceptionHandler;
use CodeIgniter\Debug\ExceptionHandlerInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

/**
 * Custom Exception Handler specific to the Backend module.
 *
 * Renders its own themed error pages for exceptions thrown
 * within the Backend module context.
 */
class BackendExceptionHandler extends BaseExceptionHandler implements ExceptionHandlerInterface
{
    /**
     * Directory containing the Backend error views.
     */
    protected ?string $viewPath = ROOTPATH . 'modules/Backend/Views/errors/';

    /**
     * Catch the exception and display the Backend-themed error page.
     */
    public function handle(
        Throwable $exception,
        RequestInterface $request,
        ResponseInterface $response,
        int $statusCode,
        int $exitCode,
    ): void {
        // If it's a CLI request, use the default CLI error view
        if (is_cli()) {
            $this->render($exception, $statusCode, $this->viewPath . "cli/error_{$statusCode}.php");
            exit($exitCode);
        }

        // Is there a view specific to this HTTP status code?
        $viewFile = $this->viewPath . "html/error_{$statusCode}.php";

        if (is_file($viewFile)) {
            // A status-code-specific view exists — render it directly via BaseExceptionHandler
            $this->render($exception, $statusCode, $viewFile);
        } else {
            // Otherwise fall back based on the environment
            $fallback = (ENVIRONMENT === 'production')
                ? $this->viewPath . 'html/production.php'
                : $this->viewPath . 'html/error_exception.php';

            $this->render($exception, $statusCode, $fallback);
        }

        exit($exitCode);
    }
}
