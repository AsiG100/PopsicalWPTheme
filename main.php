<?php
// Load environment variables from .env file
// Note: In a real WordPress environment, this might be handled differently,
// e.g., via wp-config.php or a plugin. For this context, we assume .env is readable.
if (file_exists(__DIR__ . '/.env')) {
    $dotenv = fopen(__DIR__ . '/.env', 'r');
    if ($dotenv) {
        while (($line = fgets($dotenv)) !== false) {
            $line = trim($line);
            if ($line && strpos($line, '=') !== false && substr($line, 0, 1) !== '#') {
                list($name, $value) = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value);
                // Remove surrounding quotes if any
                if (substr($value, 0, 1) == '"' && substr($value, -1) == '"') {
                    $value = substr($value, 1, -1);
                }
                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
            }
        }
        fclose($dotenv);
    }
}

// load required files
require_once __DIR__ . '/EB-SDK.php';
require_once __DIR__ . '/geo.php';