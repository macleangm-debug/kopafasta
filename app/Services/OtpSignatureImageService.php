<?php

namespace App\Services;

class OtpSignatureImageService
{
    /** Generate a blue-ink PNG data URI suitable for embedding in PDFs. */
    public function generateDataUri(string $signerName): string
    {
        $name = trim($signerName) ?: 'Borrower';
        $font = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Oblique.ttf');

        $width = 520;
        $height = 140;
        $image = imagecreatetruecolor($width, $height);

        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 255, 255, 255, 127);
        imagefill($image, 0, 0, $transparent);

        $blue = imagecolorallocate($image, 29, 78, 216);
        $light = imagecolorallocate($image, 191, 219, 254);

        if (is_file($font)) {
            $size = 30;
            $angle = -6;
            $box = imagettfbbox($size, $angle, $font, $name);
            $textWidth = abs($box[2] - $box[0]);
            $x = max(12, (int) (($width - $textWidth) / 2));
            imagettftext($image, $size, $angle, $x, 78, $blue, $font, $name);

            // Underline flourish
            imageline($image, $x, 92, min($width - 12, $x + $textWidth), 90, $light);
        } else {
            imagestring($image, 5, 12, 52, $name, $blue);
        }

        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);

        return 'data:image/png;base64,'.base64_encode($png ?: '');
    }
}
