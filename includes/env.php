<?php
/**
 * Minimal .env loader for FoodSave.
 *
 * This file intentionally supports only simple KEY=value entries.
 * Environment variables already supplied by Apache/PHP take precedence.
 */
function loadFoodSaveEnv($file)
{
    if (!is_file($file) || !is_readable($file)) {
        return;
    }

    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) {
            continue;
        }

        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);

        if ($name === '') {
            continue;
        }

        // Remove matching single/double quotes.
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last = $value[strlen($value) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        if (getenv($name) === false) {
            putenv($name . '=' . $value);
        }

        if (!isset($_ENV[$name])) {
            $_ENV[$name] = $value;
        }
    }
}

loadFoodSaveEnv(__DIR__ . '/../.env');
?>
