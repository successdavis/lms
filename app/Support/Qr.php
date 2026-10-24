<?php

namespace App\Support;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;

/**
 * QR codes as PNG data URIs. Drawn with GD from the encoder's module matrix,
 * because dompdf does not render SVG data URIs and Imagick is not assumed.
 */
final class Qr
{
    public static function png(string $data, int $moduleSize = 4, int $quietZone = 2): string
    {
        $matrix = Encoder::encode($data, ErrorCorrectionLevel::M())->getMatrix();
        $width = $matrix->getWidth();
        $pixels = ($width + 2 * $quietZone) * $moduleSize;

        $image = imagecreate($pixels, $pixels);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        imagefill($image, 0, 0, $white);

        for ($y = 0; $y < $width; $y++) {
            for ($x = 0; $x < $width; $x++) {
                if ($matrix->get($x, $y) === 1) {
                    $px = ($x + $quietZone) * $moduleSize;
                    $py = ($y + $quietZone) * $moduleSize;
                    imagefilledrectangle($image, $px, $py, $px + $moduleSize - 1, $py + $moduleSize - 1, $black);
                }
            }
        }

        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();
        imagedestroy($image);

        return $png;
    }

    /**
     * For <img src> in pages and in dompdf PDFs.
     */
    public static function dataUri(string $data, int $moduleSize = 4): string
    {
        return 'data:image/png;base64,'.base64_encode(self::png($data, $moduleSize));
    }
}
