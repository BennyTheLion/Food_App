<?php
declare(strict_types=1);

/**
 * Safe-zone ranges for gauge fields (temp_slider / ppm_slider), keyed by
 * "formId:fieldKey". Numbers come from each form's regulatory description
 * in forms-data.json. Fields not listed fall back to the universal food
 * "danger zone" rule: safe outside 5-60C.
 */
function kl_gauge_config(int $formId, string $fieldKey, string $fieldType): array
{
    $key = $formId . ':' . $fieldKey;

    $table = [
        // R-100-04 / vegetable-fruit disinfection: safe 80-100 ppm
        '1:actual_concentration_ppm' => ['min' => 0, 'max' => 200, 'safeMin' => 80, 'safeMax' => 100, 'unit' => 'ppm', 'step' => 1],
        '10:actual_concentration_ppm' => ['min' => 0, 'max' => 200, 'safeMin' => 80, 'safeMax' => 100, 'unit' => 'ppm', 'step' => 1],
        // R-100-17: cook-out core temp, safe >= 75C
        '11:temp_exit_cooking' => ['min' => 0, 'max' => 100, 'safeMin' => 75, 'safeMax' => 100, 'unit' => '°C', 'step' => 1],
        // Serving stations (R-100-01): hot holding safe >= 70C, oven exit >= 70C
        '4:temp_oven' => ['min' => -5, 'max' => 100, 'safeMin' => 70, 'safeMax' => 100, 'unit' => '°C', 'step' => 1],
        '4:temp_serving' => ['min' => -5, 'max' => 100, 'safeMin' => 70, 'safeMax' => 100, 'unit' => '°C', 'step' => 1],
        '4:temp_receiving' => ['min' => -5, 'max' => 100, 'safeMin' => 0, 'safeMax' => 5, 'unit' => '°C', 'step' => 1],
    ];

    if (isset($table[$key])) {
        return $table[$key];
    }

    if ($fieldType === 'ppm_slider') {
        return ['min' => 0, 'max' => 200, 'safeMin' => 80, 'safeMax' => 100, 'unit' => 'ppm', 'step' => 1];
    }

    // Generic temp_slider fallback: universal danger zone is 5-60C
    return ['min' => -25, 'max' => 100, 'safeMin' => -25, 'safeMax' => 5, 'unit' => '°C', 'step' => 1, 'altSafeMin' => 60, 'altSafeMax' => 100];
}
