<?php
/**
 * error_exception.php — development/testing ortamında detaylı hata raporu.
 * BaseExceptionHandler bu dosyayı include ederek render eder.
 * Erişilebilir değişkenler: $title, $exception, $message, $file, $line, $trace
 * Erişilebilir static metotlar: static::cleanPath(), static::highlightFile(), static::describeMemory()
 */
$error_id = uniqid('error', true);
?>
<!DOCTYPE html>
<html lang="<?= service('request')->getLocale() ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">

    <title><?= lang('Backend.errExcTitle') ?>: <?= htmlspecialchars($title ?? 'Exception', ENT_SUBSTITUTE, 'UTF-8') ?></title>

    <link rel="shortcut icon" href="/be-assets/img/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link href="/be-assets/plugins/fontawesome-free/css/all.min.css" rel="stylesheet">
    <link href="/be-assets/css/adminlte.min.css" rel="stylesheet">
    <link href="/be-assets/css/ci4ms-premium.css" rel="stylesheet">

    <style>
        body { background: #f4f6f9; font-family: 'Source Sans Pro', sans-serif; }
        .error-header {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: #fff;
            padding: 1.5rem 2rem;
        }
        .error-header h1 { font-size: 1.4rem; margin: 0 0 0.25rem; font-weight: 700; }
        .error-header p { margin: 0; opacity: 0.9; font-size: 0.95rem; }
        .error-header .search-link {
            display: inline-block;
            margin-top: 0.5rem;
            color: #ffc107;
            font-size: 0.85rem;
            text-decoration: none;
        }
        .error-header .search-link:hover { text-decoration: underline; }

        .source-block {
            background: #1e1e2e;
            border-radius: 6px;
            overflow: auto;
            margin: 0;
        }
        .source-block table { width: 100%; border-collapse: collapse; font-family: 'Courier New', monospace; font-size: 0.8rem; }
        .source-block td { padding: 2px 12px; white-space: pre; }
        .source-block td:first-child { color: #6272a4; user-select: none; text-align: right; min-width: 3em; border-right: 1px solid #44475a; }
        .source-block td:last-child { color: #f8f8f2; }
        .source-block tr.highlight td { background: #44475a; }
        .source-block tr.highlight td:first-child { color: #ff79c6; }

        .nav-tabs .nav-link { color: #495057; font-size: 0.85rem; }
        .nav-tabs .nav-link.active { color: #dc3545; border-bottom-color: #dc3545; font-weight: 600; }

        .trace-list { list-style: none; padding: 0; margin: 0; }
        .trace-list li { border-bottom: 1px solid #dee2e6; padding: 0.75rem 1rem; }
        .trace-list li:last-child { border-bottom: none; }
        .trace-list .trace-file { font-size: 0.85rem; color: #495057; }
        .trace-list .trace-method { font-size: 0.8rem; color: #dc3545; font-family: monospace; }

        .args-table td { padding: 0.3rem 0.6rem; font-size: 0.8rem; }
        .args-table code { background: #f8f9fa; padding: 2px 6px; border-radius: 3px; color: #e83e8c; }
        .args-table pre { margin: 0; font-size: 0.75rem; white-space: pre-wrap; }

        .footer-bar {
            background: #343a40;
            color: #adb5bd;
            padding: 0.75rem 1.5rem;
            font-size: 0.8rem;
            text-align: center;
            margin-top: 2rem;
        }
    </style>

    <script>
        function toggleArgs(id) {
            var el = document.getElementById(id);
            if (el) el.style.display = el.style.display === 'none' ? 'block' : 'none';
            return false;
        }
    </script>
</head>

<body>

<!-- Minimal top bar -->
<nav class="main-header navbar navbar-expand navbar-dark" style="background: linear-gradient(135deg, #343a40 0%, #495057 100%); padding: 0.5rem 1.5rem;">
    <a href="<?= base_url('backend') ?>">
        <img src="/be-assets/img/logo-w.png" alt="Kun-CMS" height="24">
    </a>
    <span class="ml-3 badge badge-danger">Development</span>
</nav>

<!-- Error Header -->
<div class="error-header">
    <div class="container-fluid">
        <h1>
            <i class="fas fa-bug mr-2"></i>
            <?= htmlspecialchars($title ?? 'Exception', ENT_SUBSTITUTE, 'UTF-8') ?>
            <?php if (!empty($exception) && $exception->getCode()) : ?>
                <small class="badge badge-light" style="font-size: 0.7rem;">#<?= $exception->getCode() ?></small>
            <?php endif; ?>
        </h1>
        <p>
            <?= isset($exception)
                ? htmlspecialchars($exception->getMessage(), ENT_SUBSTITUTE, 'UTF-8')
                : htmlspecialchars($message ?? '', ENT_SUBSTITUTE, 'UTF-8') ?>
        </p>
        <?php if (!empty($exception)) : ?>
            <a class="search-link"
               href="https://www.google.com/search?q=<?= urlencode(($title ?? '') . ' ' . preg_replace('#\'.*\'|".*"#Us', '', $exception->getMessage())) ?>"
               rel="noreferrer" target="_blank">
                <i class="fas fa-search mr-1"></i> <?= lang('Backend.errExcSearchGoogle') ?>
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="container-fluid py-3">

    <!-- Source File -->
    <?php if (!empty($file)) : ?>
        <div class="card mb-3">
            <div class="card-header py-2">
                <i class="fas fa-file-code mr-2 text-danger"></i>
                <strong><?= htmlspecialchars(static::cleanPath($file), ENT_SUBSTITUTE, 'UTF-8') ?></strong>
                <span class="badge badge-danger ml-2"><?= lang('Backend.errExcLine') ?> <?= $line ?? '' ?></span>
            </div>
            <?php if (is_file($file)) : ?>
                <div class="card-body p-0">
                    <div class="source-block">
                        <?= static::highlightFile($file, $line, 15) ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Tabs -->
    <div class="card">
        <div class="card-header p-0">
            <ul class="nav nav-tabs" id="errorTabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" data-toggle="tab" href="#tab-backtrace">
                        <i class="fas fa-list-ol mr-1"></i> <?= lang('Backend.errExcTabBacktrace') ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-toggle="tab" href="#tab-server">
                        <i class="fas fa-server mr-1"></i> <?= lang('Backend.errExcTabServer') ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-toggle="tab" href="#tab-request">
                        <i class="fas fa-paper-plane mr-1"></i> <?= lang('Backend.errExcTabRequest') ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-toggle="tab" href="#tab-response">
                        <i class="fas fa-reply mr-1"></i> <?= lang('Backend.errExcTabResponse') ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-toggle="tab" href="#tab-memory">
                        <i class="fas fa-memory mr-1"></i> <?= lang('Backend.errExcTabMemory') ?>
                    </a>
                </li>
            </ul>
        </div>

        <div class="card-body p-0">
            <div class="tab-content">

                <!-- Backtrace -->
                <div class="tab-pane fade show active" id="tab-backtrace">
                    <ol class="trace-list">
                        <?php foreach ($trace as $index => $row) : ?>
                            <li>
                                <div class="trace-file">
                                    <?php if (isset($row['file']) && is_file($row['file'])) : ?>
                                        <?php if (isset($row['function']) && in_array($row['function'], ['include', 'include_once', 'require', 'require_once'])) : ?>
                                            <?= htmlspecialchars($row['function'] . ' ' . static::cleanPath($row['file']), ENT_SUBSTITUTE, 'UTF-8') ?>
                                        <?php else : ?>
                                            <i class="fas fa-file-code mr-1 text-muted"></i>
                                            <?= htmlspecialchars(static::cleanPath($row['file']), ENT_SUBSTITUTE, 'UTF-8') ?>
                                            <span class="badge badge-secondary ml-1">:<?= $row['line'] ?? '' ?></span>
                                        <?php endif; ?>
                                    <?php else : ?>
                                        <span class="text-muted"><?= lang('Backend.errExcInternalCode') ?></span>
                                    <?php endif; ?>
                                </div>

                                <?php if (isset($row['class'])) : ?>
                                    <div class="trace-method mt-1">
                                        <i class="fas fa-chevron-right mr-1"></i>
                                        <?= htmlspecialchars($row['class'] . $row['type'] . $row['function'], ENT_SUBSTITUTE, 'UTF-8') ?>
                                        <?php if (!empty($row['args'])) : ?>
                                            <?php $args_id = $error_id . 'args' . $index ?>
                                            <a href="#" onclick="return toggleArgs('<?= $args_id ?>')"
                                               class="badge badge-light text-muted ml-1"><?= lang('Backend.errExcArguments') ?></a>
                                            <div id="<?= $args_id ?>" style="display:none; margin-top: 0.5rem;">
                                                <table class="table table-sm args-table">
                                                    <?php
                                                    $params = null;
                                                    if (substr($row['function'], -1) !== '}') {
                                                        try {
                                                            $mirror = isset($row['class'])
                                                                ? new \ReflectionMethod($row['class'], $row['function'])
                                                                : new \ReflectionFunction($row['function']);
                                                            $params = $mirror->getParameters();
                                                        } catch (\Throwable $e) {}
                                                    }
                                                    foreach ($row['args'] as $key => $value) :
                                                    ?>
                                                        <tr>
                                                            <td><code><?= htmlspecialchars(isset($params[$key]) ? '$' . $params[$key]->name : "#$key", ENT_SUBSTITUTE, 'UTF-8') ?></code></td>
                                                            <td><pre><?= htmlspecialchars(print_r($value, true), ENT_SUBSTITUTE, 'UTF-8') ?></pre></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </table>
                                            </div>
                                        <?php else : ?>
                                            <span class="text-muted">()</span>
                                        <?php endif; ?>
                                    </div>
                                <?php elseif (isset($row['function'])) : ?>
                                    <div class="trace-method mt-1">
                                        <i class="fas fa-chevron-right mr-1"></i>
                                        <?= htmlspecialchars($row['function'], ENT_SUBSTITUTE, 'UTF-8') ?>()
                                    </div>
                                <?php endif; ?>

                                <?php if (isset($row['file']) && is_file($row['file']) && isset($row['class'])) : ?>
                                    <div class="source-block mt-2" style="max-height: 200px; overflow: auto;">
                                        <?= static::highlightFile($row['file'], $row['line']) ?>
                                    </div>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </div>

                <!-- Server -->
                <div class="tab-pane fade" id="tab-server">
                    <div class="p-3">
                        <?php foreach (['_SERVER', '_SESSION'] as $var) : ?>
                            <?php if (empty($GLOBALS[$var]) || !is_array($GLOBALS[$var])) { continue; } ?>
                            <h6 class="text-muted mb-2"><code>$<?= $var ?></code></h6>
                            <table class="table table-sm table-bordered mb-4">
                                <thead class="thead-light">
                                    <tr><th>Key</th><th>Value</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($GLOBALS[$var] as $key => $value) : ?>
                                        <tr>
                                            <td><code><?= htmlspecialchars((string) $key, ENT_IGNORE, 'UTF-8') ?></code></td>
                                            <td><?= is_string($value)
                                                ? htmlspecialchars($value, ENT_SUBSTITUTE, 'UTF-8')
                                                : '<pre>' . htmlspecialchars(print_r($value, true), ENT_SUBSTITUTE, 'UTF-8') . '</pre>' ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>

                            <?php
                            // Constants block
                            if ($var === '_SESSION') :
                                $constants = get_defined_constants(true);
                                if (!empty($constants['user'])) :
                            ?>
                                <h6 class="text-muted mb-2"><?= lang('Backend.errExcConstants') ?></h6>
                                <table class="table table-sm table-bordered mb-4">
                                    <thead class="thead-light"><tr><th>Key</th><th>Value</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($constants['user'] as $key => $value) : ?>
                                            <tr>
                                                <td><?= htmlspecialchars((string) $key, ENT_IGNORE, 'UTF-8') ?></td>
                                                <td><?= (!is_array($value) && !is_object($value))
                                                    ? htmlspecialchars((string) $value, ENT_SUBSTITUTE, 'UTF-8')
                                                    : '<pre>' . htmlspecialchars(print_r($value, true), ENT_SUBSTITUTE, 'UTF-8') . '</pre>' ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Request -->
                <div class="tab-pane fade" id="tab-request">
                    <div class="p-3">
                        <?php $req = \Config\Services::request(); ?>
                        <table class="table table-sm table-bordered mb-3">
                            <tbody>
                                <tr>
                                    <td class="font-weight-bold" style="width:14em"><?= lang('Backend.errExcPath') ?></td>
                                    <td><?= esc((string) $req->getUri(), 'html') ?></td>
                                </tr>
                                <tr>
                                    <td class="font-weight-bold"><?= lang('Backend.errExcHttpMethod') ?></td>
                                    <td><?= esc(strtoupper($req->getMethod()), 'html') ?></td>
                                </tr>
                                <tr>
                                    <td class="font-weight-bold"><?= lang('Backend.errExcIpAddress') ?></td>
                                    <td><?= esc($req->getIPAddress(), 'html') ?></td>
                                </tr>
                                <tr>
                                    <td class="font-weight-bold"><?= lang('Backend.errExcIsAjax') ?></td>
                                    <td><?= $req->isAJAX()
                                        ? '<span class="badge badge-success">' . lang('Backend.errExcYes') . '</span>'
                                        : '<span class="badge badge-secondary">' . lang('Backend.errExcNo') . '</span>' ?></td>
                                </tr>
                                <tr>
                                    <td class="font-weight-bold"><?= lang('Backend.errExcIsSecure') ?></td>
                                    <td><?= $req->isSecure()
                                        ? '<span class="badge badge-success">' . lang('Backend.errExcYes') . '</span>'
                                        : '<span class="badge badge-secondary">' . lang('Backend.errExcNo') . '</span>' ?></td>
                                </tr>
                                <tr>
                                    <td class="font-weight-bold"><?= lang('Backend.errExcUserAgent') ?></td>
                                    <td><?= esc($req->getUserAgent()->getAgentString(), 'html') ?></td>
                                </tr>
                            </tbody>
                        </table>

                        <?php $empty = true; ?>
                        <?php foreach (['_GET', '_POST', '_COOKIE'] as $var) : ?>
                            <?php if (empty($GLOBALS[$var]) || !is_array($GLOBALS[$var])) { continue; } ?>
                            <?php $empty = false; ?>
                            <h6 class="text-muted mb-2"><code>$<?= $var ?></code></h6>
                            <table class="table table-sm table-bordered mb-4">
                                <thead class="thead-light"><tr><th>Key</th><th>Value</th></tr></thead>
                                <tbody>
                                    <?php foreach ($GLOBALS[$var] as $key => $value) : ?>
                                        <tr>
                                            <td><?= htmlspecialchars((string) $key, ENT_IGNORE, 'UTF-8') ?></td>
                                            <td><?= is_string($value)
                                                ? htmlspecialchars($value, ENT_SUBSTITUTE, 'UTF-8')
                                                : '<pre>' . htmlspecialchars(print_r($value, true), ENT_SUBSTITUTE, 'UTF-8') . '</pre>' ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endforeach; ?>

                        <?php if ($empty) : ?>
                            <div class="alert alert-secondary">
                                <?= lang('Backend.errExcNoGetPost') ?>
                            </div>
                        <?php endif; ?>

                        <!-- Request Headers -->
                        <?php $headers = $req->getHeaders(); ?>
                        <?php if (!empty($headers)) : ?>
                            <h6 class="text-muted mb-2"><?= lang('Backend.errExcHeaders') ?></h6>
                            <table class="table table-sm table-bordered">
                                <thead class="thead-light"><tr><th>Header</th><th>Value</th></tr></thead>
                                <tbody>
                                    <?php foreach ($headers as $headerList) : ?>
                                        <?php if (empty($headerList)) { continue; } ?>
                                        <?php $headerList = is_array($headerList) ? $headerList : [$headerList]; ?>
                                        <?php foreach ($headerList as $h) : ?>
                                            <tr>
                                                <td><?= esc($h->getName(), 'html') ?></td>
                                                <td><?= esc($h->getValueLine(), 'html') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Response -->
                <div class="tab-pane fade" id="tab-response">
                    <div class="p-3">
                        <?php
                        $res = \Config\Services::response();
                        $currentCode = http_response_code();
                        if ($currentCode !== false) {
                            $res->setStatusCode((int) $currentCode);
                        }
                        ?>
                        <table class="table table-sm table-bordered mb-3">
                            <tbody>
                                <tr>
                                    <td class="font-weight-bold" style="width:14em"><?= lang('Backend.errExcResponseStatus') ?></td>
                                    <td>
                                        <span class="badge badge-danger"><?= $res->getStatusCode() ?></span>
                                        <?= esc($res->getReasonPhrase(), 'html') ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>

                        <?php $resHeaders = $res->getHeaders(); ?>
                        <?php if (!empty($resHeaders)) : ?>
                            <h6 class="text-muted mb-2"><?= lang('Backend.errExcHeaders') ?></h6>
                            <table class="table table-sm table-bordered">
                                <thead class="thead-light"><tr><th>Header</th><th>Value</th></tr></thead>
                                <tbody>
                                    <?php foreach ($resHeaders as $name => $value) : ?>
                                        <tr>
                                            <td><?= esc((string) $name, 'html') ?></td>
                                            <td><?= esc($res->getHeaderLine((string) $name), 'html') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Memory -->
                <div class="tab-pane fade" id="tab-memory">
                    <div class="p-3">
                        <table class="table table-sm table-bordered">
                            <tbody>
                                <tr>
                                    <td class="font-weight-bold" style="width:14em"><?= lang('Backend.errExcMemoryUsage') ?></td>
                                    <td><?= static::describeMemory(memory_get_usage(true)) ?></td>
                                </tr>
                                <tr>
                                    <td class="font-weight-bold"><?= lang('Backend.errExcMemoryPeak') ?></td>
                                    <td><?= static::describeMemory(memory_get_peak_usage(true)) ?></td>
                                </tr>
                                <tr>
                                    <td class="font-weight-bold"><?= lang('Backend.errExcMemoryLimit') ?></td>
                                    <td><?= ini_get('memory_limit') ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div><!-- /.tab-content -->
        </div>
    </div><!-- /.card -->

</div><!-- /.container-fluid -->

<div class="footer-bar">
    <?= date('H:i:s') ?> &mdash; PHP: <?= phpversion() ?> &mdash; CodeIgniter: <?= \CodeIgniter\CodeIgniter::CI_VERSION ?>
</div>

<script src="/be-assets/plugins/jquery/jquery.min.js"></script>
<script src="/be-assets/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script>
    $(function () {
        $('#errorTabs a').on('click', function (e) {
            e.preventDefault();
            $(this).tab('show');
        });
    });
</script>
</body>
</html>
