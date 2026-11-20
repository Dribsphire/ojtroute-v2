<?php

/**
 * Minimal .env loader (no Composer package needed).
 *
 * Reads KEY=VALUE lines from the project-root .env file once per request.
 * Real environment variables (set by the server / hosting panel) win over .env.
 * .env is gitignored - copy .env.example to .env and fill in your values.
 */
if (!function_exists('env')) {
    function env($key, $default = null)
    {
        static $loaded = false;
        if (!$loaded) {
            $loaded = true;
            $file = dirname(__DIR__) . '/.env';
            if (is_readable($file)) {
                foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                    $line = trim($line);
                    if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                        continue;
                    }
                    [$name, $value] = array_map('trim', explode('=', $line, 2));
                    // Strip optional surrounding quotes: KEY="value" or KEY='value'
                    if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && substr($value, -1) === $value[0]) {
                        $value = substr($value, 1, -1);
                    }
                    if (getenv($name) === false) {
                        putenv("$name=$value");
                        $_ENV[$name] = $value;
                    }
                }
            }
        }

        $value = getenv($key);
        return $value === false ? $default : $value;
    }
}
