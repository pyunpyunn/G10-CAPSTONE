<?php

namespace App\Support;

class NavigationPolyline
{
    public static function encode(array $points): string
    {
        $encoded = ''; $previous = [0, 0];
        foreach ($points as $point) {
            foreach ([(float) $point['latitude'], (float) $point['longitude']] as $axis => $value) {
                $current = (int) round($value * 1e6);
                $delta = $current - $previous[$axis]; $previous[$axis] = $current;
                $number = $delta < 0 ? ~(($delta) << 1) : $delta << 1;
                while ($number >= 0x20) { $encoded .= chr((0x20 | ($number & 0x1f)) + 63); $number >>= 5; }
                $encoded .= chr($number + 63);
            }
        }
        return $encoded;
    }
}
