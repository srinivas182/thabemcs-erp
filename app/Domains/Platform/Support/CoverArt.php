<?php

declare(strict_types=1);

namespace App\Domains\Platform\Support;

/**
 * Draws a cover illustration for a development: sky, sea, a headland, rows of low-rise homes and a dune.
 *
 * This is deliberately an illustration, not a photograph. Showing somebody else's buildings under a
 * developer's own name misleads buyers, so the demonstration site uses drawn artwork until the client
 * supplies real photography.
 */
final class CoverArt
{
    /** Palettes in the order: sky top, sky low, sun, sea, headland, front homes, back homes, roofs, dune. */
    public const array PALETTES = [
        'dawn' => [[32, 58, 78], [196, 146, 118], [247, 206, 150], [34, 72, 86], [26, 48, 58], [48, 72, 80], [36, 58, 66], [22, 38, 46], [24, 40, 44]],
        'dusk' => [[28, 36, 66], [172, 110, 118], [245, 190, 140], [30, 50, 78], [26, 32, 52], [52, 56, 82], [38, 42, 64], [22, 26, 42], [22, 28, 42]],
        'day' => [[64, 124, 150], [186, 206, 206], [250, 232, 190], [44, 112, 120], [38, 78, 72], [84, 102, 102], [62, 80, 82], [44, 56, 58], [52, 66, 58]],
        'evening' => [[48, 52, 62], [206, 150, 104], [250, 214, 160], [48, 78, 82], [40, 46, 44], [70, 66, 60], [52, 50, 46], [34, 30, 30], [40, 36, 32]],
    ];

    /**
     * Draws one cover and returns the path of a temporary JPEG.
     *
     * @param  string  $palette  one of the keys in PALETTES
     * @param  int  $seed  keeps a development's artwork the same each time it is generated
     */
    public function draw(string $palette, int $seed): string
    {
        mt_srand($seed);
        $width = 1600;
        $height = 900;
        $image = imagecreatetruecolor($width, $height);
        imageantialias($image, true);

        $colours = self::PALETTES[$palette] ?? self::PALETTES['dawn'];
        [$skyTop, $skyLow, $sun, $sea, $headland, $frontWall, $backWall, $roof, $dune] = $colours;

        $pen = function (array $rgb) use ($image): int {
            return (int) imagecolorallocate($image, $this->channel($rgb[0]), $this->channel($rgb[1]), $this->channel($rgb[2]));
        };
        $horizon = (int) ($height * 0.52);

        $this->sky($image, $pen, $skyTop, $skyLow, $width, $horizon);
        $sunX = (int) ($width * 0.72);
        $this->sun($image, $pen, $sun, $sunX, $horizon - 70);
        $this->sea($image, $pen, $sun, $sea, $width, $height, $horizon, $sunX);
        $this->headland($image, $pen, $headland, $width, $height);
        $this->homes($image, $pen, $frontWall, $backWall, $roof, $width, $height);
        $this->dune($image, $pen, $dune, $width, $height);

        $path = tempnam(sys_get_temp_dir(), 'cover').'.jpg';
        imagejpeg($image, $path, 88);
        imagedestroy($image);

        return $path;
    }

    /**
     * @param  callable(array{int, int, int}): int  $pen
     * @param  array{int, int, int}  $top
     * @param  array{int, int, int}  $low
     */
    private function sky(\GdImage $image, callable $pen, array $top, array $low, int $width, int $horizon): void
    {
        for ($y = 0; $y < $horizon; $y++) {
            $t = $y / max(1, $horizon);
            imageline($image, 0, $y, $width, $y, $pen([
                (int) ($top[0] + ($low[0] - $top[0]) * $t),
                (int) ($top[1] + ($low[1] - $top[1]) * $t),
                (int) ($top[2] + ($low[2] - $top[2]) * $t),
            ]));
        }
    }

    /**
     * @param  callable(array{int, int, int}): int  $pen
     * @param  array{int, int, int}  $sun
     */
    private function sun(\GdImage $image, callable $pen, array $sun, int $x, int $y): void
    {
        for ($r = 170; $r > 0; $r -= 2) {
            $glow = imagecolorallocatealpha(
                $image,
                $this->channel($sun[0]), $this->channel($sun[1]), $this->channel($sun[2]),
                $this->alpha((int) (120 - 120 * ($r / 170))),
            );
            imagefilledellipse($image, $x, $y, $r * 2, $r * 2, (int) $glow);
        }
        imagefilledellipse($image, $x, $y, 118, 118, $pen($sun));
    }

    /**
     * @param  callable(array{int, int, int}): int  $pen
     * @param  array{int, int, int}  $sun
     * @param  array{int, int, int}  $sea
     */
    private function sea(\GdImage $image, callable $pen, array $sun, array $sea, int $width, int $height, int $horizon, int $sunX): void
    {
        $bottom = (int) ($height * 0.64);
        for ($y = $horizon; $y < $bottom; $y++) {
            $t = ($y - $horizon) / max(1, $bottom - $horizon);
            imageline($image, 0, $y, $width, $y, $pen([
                (int) ($sea[0] * (1 - $t * 0.35)), (int) ($sea[1] * (1 - $t * 0.3)), (int) ($sea[2] * (1 - $t * 0.25)),
            ]));
        }

        // The sun's path on the water.
        for ($y = $horizon + 4; $y < $bottom - 4; $y += 7) {
            $spread = (int) (40 + ($y - $horizon) * 1.6);
            $shimmer = imagecolorallocatealpha(
                $image,
                $this->channel($sun[0]), $this->channel($sun[1]), $this->channel($sun[2]),
                $this->alpha(60 + mt_rand(0, 30)),
            );
            imagefilledrectangle($image, $sunX - $spread, $y, $sunX + $spread, $y + 2, (int) $shimmer);
        }
    }

    /** @return int<0, 255> */
    private function channel(int $value): int
    {
        return max(0, min(255, $value));
    }

    /** @return int<0, 127> */
    private function alpha(int $value): int
    {
        return max(0, min(127, $value));
    }

    /**
     * @param  callable(array{int, int, int}): int  $pen
     * @param  array{int, int, int}  $colour
     */
    private function headland(\GdImage $image, callable $pen, array $colour, int $width, int $height): void
    {
        $points = [0, (int) ($height * 0.56)];
        for ($x = 0; $x <= $width; $x += 80) {
            $points[] = $x;
            $points[] = (int) ($height * 0.54 - sin($x / 420) * 46 - mt_rand(0, 14));
        }
        array_push($points, $width, $height, 0, $height);
        imagefilledpolygon($image, $points, $pen($colour));
    }

    /**
     * @param  callable(array{int, int, int}): int  $pen
     * @param  array{int, int, int}  $front
     * @param  array{int, int, int}  $back
     * @param  array{int, int, int}  $roof
     */
    private function homes(\GdImage $image, callable $pen, array $front, array $back, array $roof, int $width, int $height): void
    {
        foreach ([[0.70, 1.0, $back], [0.80, 1.35, $front]] as [$baseFactor, $scale, $wall]) {
            $baseY = (int) ($height * $baseFactor);
            $x = -60;

            while ($x < $width + 80) {
                $homeWidth = (int) (mt_rand(120, 190) * $scale);
                $homeHeight = (int) (mt_rand(70, 110) * $scale);
                $top = $baseY - $homeHeight;

                imagefilledrectangle($image, $x, $top, $x + $homeWidth, $baseY + 40, $pen($wall));
                imagefilledpolygon($image, [
                    $x - 14, $top,
                    (int) ($x + $homeWidth / 2), $top - (int) (46 * $scale),
                    $x + $homeWidth + 14, $top,
                ], $pen($roof));

                $lit = $pen([min(255, $wall[0] + 85), min(255, $wall[1] + 74), min(255, $wall[2] + 46)]);
                for ($wx = $x + (int) (22 * $scale); $wx < $x + $homeWidth - (int) (26 * $scale); $wx += (int) (44 * $scale)) {
                    if (mt_rand(0, 100) < 58) {
                        imagefilledrectangle($image, $wx, $top + (int) (26 * $scale), $wx + (int) (18 * $scale), $top + (int) (52 * $scale), $lit);
                    }
                }

                $x += $homeWidth + mt_rand(18, 46);
            }
        }
    }

    /**
     * @param  callable(array{int, int, int}): int  $pen
     * @param  array{int, int, int}  $colour
     */
    private function dune(\GdImage $image, callable $pen, array $colour, int $width, int $height): void
    {
        $points = [0, $height];
        for ($x = 0; $x <= $width; $x += 60) {
            $points[] = $x;
            $points[] = (int) ($height * 0.88 - sin($x / 260 + 2) * 26);
        }
        array_push($points, $width, $height);
        imagefilledpolygon($image, $points, $pen($colour));
    }
}
