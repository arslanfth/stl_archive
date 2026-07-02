<?php

function normalizeFileSizeNumber(string $value): ?float
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    $lastDot = strrpos($value, '.');
    $lastComma = strrpos($value, ',');

    if ($lastDot !== false && $lastComma !== false) {
        $decimalSeparator = $lastDot > $lastComma ? '.' : ',';
        $thousandsSeparator = $decimalSeparator === '.' ? ',' : '.';
        $value = str_replace($thousandsSeparator, '', $value);
        if ($decimalSeparator === ',') {
            $value = str_replace(',', '.', $value);
        }
    } elseif ($lastComma !== false) {
        $value = str_replace('.', '', $value);
        $value = str_replace(',', '.', $value);
    } else {
        $value = str_replace(',', '', $value);
    }

    return is_numeric($value) ? (float) $value : null;
}

function trimFileSizeZeros(string $value): string
{
    $value = rtrim($value, '0');
    $value = rtrim($value, '.');
    return $value === '' ? '0' : $value;
}

function formatFileSizeNumber(float $value, int $precision = 2): string
{
    return trimFileSizeZeros(number_format($value, $precision, '.', ''));
}

function formatFileSize($value): string
{
    if ($value === null) {
        return '-';
    }

    if (is_string($value)) {
        $value = trim($value);
    }

    if ($value === '' || $value === '-') {
        return '-';
    }

    $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];

    if (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))) {
        $bytes = (float) $value;
        if ($bytes <= 0) {
            return '0 B';
        }

        $power = (int) floor(log($bytes, 1024));
        $power = max(0, min($power, count($units) - 1));
        $normalized = $bytes / pow(1024, $power);
        $precision = $power === 0 ? 0 : 2;

        return formatFileSizeNumber($normalized, $precision) . ' ' . $units[$power];
    }

    if (!is_string($value)) {
        return (string) $value;
    }

    if (preg_match('/^\s*([\d.,]+)\s*([kmgtp]?b?)\s*$/i', $value, $matches)) {
        $normalizedValue = normalizeFileSizeNumber($matches[1]);
        if ($normalizedValue === null) {
            return $value;
        }

        $unit = strtoupper($matches[2]);
        $unitMap = [
            '' => 'B',
            'B' => 'B',
            'K' => 'KB',
            'KB' => 'KB',
            'M' => 'MB',
            'MB' => 'MB',
            'G' => 'GB',
            'GB' => 'GB',
            'T' => 'TB',
            'TB' => 'TB',
            'P' => 'PB',
            'PB' => 'PB',
        ];

        if (!isset($unitMap[$unit])) {
            return $value;
        }

        $resolvedUnit = $unitMap[$unit];
        $precision = $resolvedUnit === 'B' ? 0 : 2;

        return formatFileSizeNumber($normalizedValue, $precision) . ' ' . $resolvedUnit;
    }

    return $value;
}
