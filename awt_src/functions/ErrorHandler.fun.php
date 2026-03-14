<?php

use exception\AWTException;
use exception\ExceptionBase;
use render\TemplateEngine\BladeOne;

function handle_exception(\Throwable $e): void
{
    // Walk chain to the root cause
    $root = $e;
    while ($root->getPrevious()) {
        $root = $root->getPrevious();
    }

    // Build trace from the outermost exception (has the full call stack)
    $trace = array_map(fn($f) => [
        'file'     => $f['file']     ?? '',
        'line'     => $f['line']     ?? 0,
        'class'    => $f['class']    ?? '',
        'type'     => $f['type']     ?? '',
        'function' => $f['function'] ?? '{closure}',
    ], $e->getTrace());

    try {
        http_response_code(500);

        $view = "error";
        if(DEBUG)
            $view = "debug";

        $engine = new BladeOne(ERRORS_DIRECTORY, COMPILED, BladeOne::MODE_AUTO);
        $engine->setFileExtension(".awt.php");

        $data = ['WEB_NAME' => defined('WEB_NAME') ? WEB_NAME : 'App'];

        if(!$e instanceof ExceptionBase)
            $e = new AWTException($e);

        if (defined('DEBUG') && DEBUG) {
            $data['error'] = $e->getMessage();
            $data['file']  = $root->getFile();
            $data['line']  = $root->getLine();
            $data['context'] = $e->context->contextName;
            $data['contextPath'] = $e->context->contextPath;
            $data['contextId'] = $e->context->contextId;
            $data['trace'] = $trace;
        }

        echo $engine->run($view, $data);
    } catch (\Throwable $inner) {
        echo "<h1>500</h1>";
        if (defined('DEBUG') && DEBUG) echo "<pre>" . $inner->getMessage() . "</pre>";
    }
}

// Handles uncaught exceptions — gets the full object with getPrevious()
set_exception_handler('handle_exception');

// Keep shutdown ONLY for things set_exception_handler can't catch (parse/compile errors)
register_shutdown_function(function () {
    $last = error_get_last();
    if ($last && in_array($last['type'], [E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        http_response_code(500);
        // Parse errors have no chain, direct render
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
    }
});

register_shutdown_function(function () {
    $error = error_get_last();
    handle_exception(new \Exception($error['message'], $error['type']));
});

