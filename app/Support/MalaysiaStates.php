<?php

namespace App\Support;

final class MalaysiaStates
{
    /**
     * Canonical state keys stored in DB.
     *
     * Keys are lowercase snake_case; labels are customer-facing.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            'johor' => 'Johor',
            'kedah' => 'Kedah',
            'kelantan' => 'Kelantan',
            'melaka' => 'Melaka',
            'negeri_sembilan' => 'Negeri Sembilan',
            'pahang' => 'Pahang',
            'perak' => 'Perak',
            'perlis' => 'Perlis',
            'pulau_pinang' => 'Pulau Pinang',
            'sabah' => 'Sabah',
            'sarawak' => 'Sarawak',
            'selangor' => 'Selangor',
            'terengganu' => 'Terengganu',
            'wp_kuala_lumpur' => 'W.P. Kuala Lumpur',
            'wp_labuan' => 'W.P. Labuan',
            'wp_putrajaya' => 'W.P. Putrajaya',
        ];
    }

    /**
     * @return string[]
     */
    public static function keys(): array
    {
        return array_keys(self::options());
    }

    public static function label(string $key): string
    {
        return self::options()[$key] ?? $key;
    }

    /**
     * Normalize legacy/free-text state values into a canonical key.
     */
    public static function normalize(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $value = trim(mb_strtolower($raw));
        if ($value === '') {
            return null;
        }

        // If it's already a canonical key, keep it.
        if (array_key_exists($value, self::options())) {
            return $value;
        }

        $value = preg_replace('/[^a-z0-9\\s]/', ' ', $value) ?? $value;
        $value = preg_replace('/\\s+/', ' ', $value) ?? $value;

        $aliases = [
            'penang' => 'pulau_pinang',
            'pulau pinang' => 'pulau_pinang',
            'p pinang' => 'pulau_pinang',
            'malacca' => 'melaka',
            'melacca' => 'melaka',
            'n sembilan' => 'negeri_sembilan',
            'negeri sembilan' => 'negeri_sembilan',
            'kuala lumpur' => 'wp_kuala_lumpur',
            'wp kuala lumpur' => 'wp_kuala_lumpur',
            'wilayah persekutuan kuala lumpur' => 'wp_kuala_lumpur',
            'labuan' => 'wp_labuan',
            'wp labuan' => 'wp_labuan',
            'wilayah persekutuan labuan' => 'wp_labuan',
            'putrajaya' => 'wp_putrajaya',
            'wp putrajaya' => 'wp_putrajaya',
            'wilayah persekutuan putrajaya' => 'wp_putrajaya',
        ];

        if (array_key_exists($value, $aliases)) {
            return $aliases[$value];
        }

        // Best-effort contains match (handles "Selangor Darul Ehsan", etc.)
        foreach (self::options() as $key => $label) {
            $labelNorm = mb_strtolower($label);
            $labelNorm = preg_replace('/[^a-z0-9\\s]/', ' ', $labelNorm) ?? $labelNorm;
            $labelNorm = preg_replace('/\\s+/', ' ', $labelNorm) ?? $labelNorm;

            if ($value === $labelNorm || str_contains($value, $labelNorm)) {
                return $key;
            }
        }

        return null;
    }
}

