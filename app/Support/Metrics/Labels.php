<?php

namespace App\Support\Metrics;

final class Labels
{
    /**
     * @param  array<string, string>  $labels
     */
    public static function encode(array $labels): string
    {
        ksort($labels);

        return (string) json_encode($labels, JSON_UNESCAPED_SLASHES);
    }

    /**
     * @return array<string, string>
     */
    public static function decode(string $key): array
    {
        /** @var array<string, string> $labels */
        $labels = json_decode($key, true) ?: [];

        return $labels;
    }

    /**
     * Render labels in Prometheus text exposition format: {a="1",b="2"}.
     *
     * @param  array<string, string>  $labels
     */
    public static function render(array $labels): string
    {
        if ($labels === []) {
            return '';
        }

        $pairs = array_map(
            fn (string $name, string $value) => $name.'="'.addcslashes($value, "\\\"\n").'"',
            array_keys($labels),
            $labels,
        );

        return '{'.implode(',', $pairs).'}';
    }
}
