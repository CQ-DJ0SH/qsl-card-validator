<?php
/**
 * Loads config.php (or config.example.php as a safe fallback) and
 * exposes a small accessor function used throughout the project.
 */

function qsl_config(string $key, $default = null)
{
    static $cfg = null;
    if ($cfg === null) {
        $root = dirname(__DIR__);
        $local = $root . '/config.php';
        $example = $root . '/config.example.php';

        if (file_exists($local)) {
            $cfg = require $local;
        } elseif (file_exists($example)) {
            $cfg = require $example;
        } else {
            $cfg = [];
        }

        if (!is_array($cfg)) {
            throw new RuntimeException('Configuration must return an array.');
        }
    }
    return $cfg[$key] ?? $default;
}
