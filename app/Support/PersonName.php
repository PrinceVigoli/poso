<?php
namespace App\Support;

use Illuminate\Support\Str;

class PersonName
{
    // Commas explicitly separate the surname, including compound surnames.
    // Legacy names without commas use the last word and common surname particles.
    public static function display(?string $name): ?string
    {
        if ($name === null) return null;
        $name = Str::squish($name);
        if (str_contains($name, ',') || !str_contains($name, ' ')) return $name;
        $parts = explode(' ', $name);
        $surname = array_pop($parts);
        if (in_array(strtolower(rtrim($surname, '.')), ['jr', 'sr', 'ii', 'iii', 'iv'], true) && count($parts) > 1) {
            $surname = array_pop($parts).' '.$surname;
        }
        while (count($parts) > 1 && in_array(Str::lower(end($parts)), ['de', 'del', 'dela', 'la', 'los', 'las', 'da', 'dos', 'van', 'von', 'san', 'santa'], true)) {
            $surname = array_pop($parts).' '.$surname;
        }
        return $surname.', '.implode(' ', $parts);
    }

    public static function normalized(string $name): string
    {
        if (str_contains($name, ',')) {
            [$surname, $given] = explode(',', $name, 2);
            $name = $given.' '.$surname;
        }
        return Str::lower(Str::squish($name));
    }

    public static function search($query, string $column, string $term): void
    {
        foreach (preg_split('/[\s,]+/u', trim($term), -1, PREG_SPLIT_NO_EMPTY) as $word) {
            $query->where($column, 'like', '%'.addcslashes($word, '%_\\').'%');
        }
    }
}
