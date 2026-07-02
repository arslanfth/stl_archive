<?php

$langFile = dirname(__DIR__) . '/lang/tr.php';
$translations = is_file($langFile) ? require $langFile : [];

if (!function_exists('t')) {
    function t(string $key, string $fallback = ''): string
    {
        global $translations;

        if (isset($translations[$key]) && is_string($translations[$key])) {
            return $translations[$key];
        }

        if ($fallback !== '') {
            return $fallback;
        }

        return $key;
    }
}
