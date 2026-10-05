<?php
use exception\AWTException;
use exception\ExceptionBase;
use render\TemplateEngine\BladeOne;

// ─── CLI renderer ────────────────────────────────────────────────────────────

function render_cli_exception(\Throwable $e, array $trace): void
{
    $root = $e;
    while ($root->getPrevious()) {
        $root = $root->getPrevious();
    }

    $red    = "\e[1;31m";
    $yellow = "\e[1;33m";
    $cyan   = "\e[1;36m";
    $gray   = "\e[0;90m";
    $bold   = "\e[1m";
    $reset  = "\e[0m";

    $isDebug = defined('DEBUG') && DEBUG;

    fwrite(STDERR, "\n{$red}┌─ AWT Exception ────────────────────────────────────────────┐{$reset}\n");

    $class = get_class($e);
    fwrite(STDERR, "{$red}│{$reset} {$bold}{$class}{$reset}\n");
    fwrite(STDERR, "{$red}│{$reset} {$yellow}" . wordwrap($e->getMessage(), 62, "\n│   ", true) . "{$reset}\n");

    if ($isDebug) {
        fwrite(STDERR, "{$red}│{$reset}\n");
        fwrite(STDERR, "{$red}│{$reset} {$gray}File:{$reset}  {$root->getFile()}:{$root->getLine()}\n");

        if ($e instanceof ExceptionBase && isset($e->context)) {
            fwrite(STDERR, "{$red}│{$reset} {$gray}Ctx:{$reset}   {$e->context->contextName} ({$e->context->contextId})\n");
            fwrite(STDERR, "{$red}│{$reset} {$gray}Path:{$reset}  {$e->context->contextPath}\n");
        }

        fwrite(STDERR, "{$red}│{$reset}\n");
        fwrite(STDERR, "{$red}│{$reset} {$cyan}Stack trace:{$reset}\n");

        $limit = min(count($trace), 10);
        for ($i = 0; $i < $limit; $i++) {
            $f    = $trace[$i];
            $loc  = $f['file'] ? basename($f['file']) . ':' . $f['line'] : '[internal]';
            $call = ($f['class'] ? $f['class'] . $f['type'] : '') . $f['function'] . '()';
            fwrite(STDERR, "{$red}│{$reset}  {$gray}#{$i}{$reset} {$bold}{$call}{$reset}\n");
            fwrite(STDERR, "{$red}│{$reset}     {$gray}{$loc}{$reset}\n");
        }

        if (count($trace) > $limit) {
            fwrite(STDERR, "{$red}│{$reset}  {$gray}… " . (count($trace) - $limit) . " more frames{$reset}\n");
        }

        // Previous exception chain
        $prev = $e->getPrevious();
        if ($prev) {
            fwrite(STDERR, "{$red}│{$reset}\n");
            fwrite(STDERR, "{$red}│{$reset} {$gray}Caused by:{$reset} " . get_class($prev) . ": " . $prev->getMessage() . "\n");
            fwrite(STDERR, "{$red}│{$reset}            {$gray}{$prev->getFile()}:{$prev->getLine()}{$reset}\n");
        }
    }

    fwrite(STDERR, "{$red}└────────────────────────────────────────────────────────────┘{$reset}\n\n");
}

function render_cli_fatal(array $last): void
{
    $red   = "\e[1;31m";
    $gray  = "\e[0;90m";
    $bold  = "\e[1m";
    $reset = "\e[0m";

    $typeMap = [E_PARSE => 'Parse Error', E_CORE_ERROR => 'Core Error', E_COMPILE_ERROR => 'Compile Error'];
    $label   = $typeMap[$last['type']] ?? 'Fatal Error';

    fwrite(STDERR, "\n{$red}┌─ AWT Fatal: {$label} ───────────────────────────────────────┐{$reset}\n");
    fwrite(STDERR, "{$red}│{$reset} {$bold}" . wordwrap($last['message'], 62, "\n│ ", true) . "{$reset}\n");

    if (defined('DEBUG') && DEBUG) {
        fwrite(STDERR, "{$red}│{$reset} {$gray}{$last['file']}:{$last['line']}{$reset}\n");
    }

    fwrite(STDERR, "{$red}└────────────────────────────────────────────────────────────┘{$reset}\n\n");
}

// ─── Web renderer ────────────────────────────────────────────────────────────

function render_web_exception(\Throwable $e, array $trace): void
{
    $root = $e;
    while ($root->getPrevious()) {
        $root = $root->getPrevious();
    }

    $isDebug = defined('DEBUG') && DEBUG;

    try {
        http_response_code(500);
        $view   = $isDebug ? "debug" : "error";
        $engine = new BladeOne(ERRORS_DIRECTORY, COMPILED, BladeOne::MODE_AUTO);
        $engine->setFileExtension(".awt.php");

        $data = ['WEB_NAME' => defined('WEB_NAME') ? WEB_NAME : 'App'];

        if ($isDebug) {
            $data['error']       = $e->getMessage();
            $data['file']        = $root->getFile();
            $data['line']        = $root->getLine();
            $data['context']     = $e->context->contextName ?? '';
            $data['contextPath'] = $e->context->contextPath ?? '';
            $data['contextId']   = $e->context->contextId   ?? '';
            $data['trace']       = $trace;
        }

        echo $engine->run($view, $data);
    } catch (\Throwable $inner) {
        echo "<h1>500</h1>";
        if ($isDebug) echo "<pre>" . htmlspecialchars($inner->getMessage()) . "</pre>";
    }
}

// ─── Main handler ────────────────────────────────────────────────────────────

function handle_exception(\Throwable $e): void
{
    $root = $e;
    while ($root->getPrevious()) {
        $root = $root->getPrevious();
    }

    $trace = array_map(fn($f) => [
        'file'     => $f['file']     ?? '',
        'line'     => $f['line']     ?? 0,
        'class'    => $f['class']    ?? '',
        'type'     => $f['type']     ?? '',
        'function' => $f['function'] ?? '{closure}',
    ], $e->getTrace());

    if (!$e instanceof ExceptionBase) {
        $e = new AWTException($e);
    }

    if (PHP_SAPI === 'cli') {
        render_cli_exception($e, $trace);
        exit(1);
    }

    render_web_exception($e, $trace);
}

set_exception_handler('handle_exception');

register_shutdown_function(function () {
    $last = error_get_last();
    if (!$last || !in_array($last['type'], [E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        return;
    }

    if (PHP_SAPI === 'cli') {
        render_cli_fatal($last);
        exit(1);
    }

    // Web fatal
    http_response_code(500);
    try {
        $engine = new BladeOne(ERRORS_DIRECTORY, COMPILED, BladeOne::MODE_AUTO);
        $engine->setFileExtension(".awt.php");
        $data = ['WEB_NAME' => defined('WEB_NAME') ? WEB_NAME : 'App'];
        if (defined('DEBUG') && DEBUG) {
            $data['error'] = $last['message'];
            $data['file']  = $last['file'];
            $data['line']  = $last['line'];
            $data['trace'] = [];
        }
        echo $engine->run("error", $data);
    } catch (\Throwable $inner) {
        echo "<h1>500</h1>";
    }
});