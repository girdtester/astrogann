<?php

namespace App\Support;

/**
 * Generates simple PNG placeholder images with GD (seed data and tests only).
 */
class PlaceholderImage
{
    /**
     * Write a PNG to $absolutePath showing the given lines of text.
     *
     * @param  list<string>  $lines
     */
    public static function make(string $absolutePath, array $lines, int $hue = 0): void
    {
        // Draw on a small canvas with GD's built-in font, then scale up 4x so the text is readable.
        $small = imagecreatetruecolor(200, 120);
        [$r, $g, $b] = self::hsl($hue, 0.55, 0.35);
        imagefill($small, 0, 0, imagecolorallocate($small, $r, $g, $b));
        $white = imagecolorallocate($small, 255, 255, 255);
        $font = 5;
        $lineHeight = imagefontheight($font) + 6;
        $y = (int) ((120 - count($lines) * $lineHeight) / 2);
        foreach ($lines as $line) {
            $x = (int) max(4, (200 - strlen($line) * imagefontwidth($font)) / 2);
            imagestring($small, $font, $x, $y, $line, $white);
            $y += $lineHeight;
        }

        $big = imagecreatetruecolor(800, 480);
        imagecopyresized($big, $small, 0, 0, 0, 0, 800, 480, 200, 120);

        if (! is_dir(dirname($absolutePath))) {
            mkdir(dirname($absolutePath), 0755, true);
        }
        imagepng($big, $absolutePath, 6);
        imagedestroy($small);
        imagedestroy($big);
    }

    /** @return array{int,int,int} */
    private static function hsl(int $h, float $s, float $l): array
    {
        $c = (1 - abs(2 * $l - 1)) * $s;
        $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
        $m = $l - $c / 2;
        [$r, $g, $b] = match (intdiv($h % 360, 60)) {
            0 => [$c, $x, 0], 1 => [$x, $c, 0], 2 => [0, $c, $x],
            3 => [0, $x, $c], 4 => [$x, 0, $c], default => [$c, 0, $x],
        };

        return [(int) (($r + $m) * 255), (int) (($g + $m) * 255), (int) (($b + $m) * 255)];
    }
}
