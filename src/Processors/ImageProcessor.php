<?php

declare(strict_types=1);

namespace App\Processors;

/**
 * ImageMagick (Imagick) preferred; GD fallback. Imagick failures (policy, exceptions) fall back to GD.
 */
final class ImageProcessor
{
    public function hasImagick(): bool
    {
        return extension_loaded('imagick');
    }

    public function jpgToPng(string $input, string $output): void
    {
        if ($this->hasImagick()) {
            try {
                $this->jpgToPngImagick($input, $output);
                return;
            } catch (\Throwable) {
                // fall through to GD
            }
        }
        $img = @imagecreatefromjpeg($input);
        if ($img === false) {
            throw new \RuntimeException('Could not read JPEG image.');
        }
        if (!imagepng($img, $output, 6)) {
            imagedestroy($img);
            throw new \RuntimeException('Could not write PNG.');
        }
        imagedestroy($img);
    }

    public function pngToJpg(string $input, string $output, int $quality = 90): void
    {
        $quality = max(1, min(100, $quality));
        if ($this->hasImagick()) {
            try {
                $this->pngToJpgImagick($input, $output, $quality);
                return;
            } catch (\Throwable) {
            }
        }
        $img = @imagecreatefrompng($input);
        if ($img === false) {
            throw new \RuntimeException('Could not read PNG image.');
        }
        $w = imagesx($img);
        $h = imagesy($img);
        $canvas = imagecreatetruecolor($w, $h);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);
        imagecopy($canvas, $img, 0, 0, 0, 0, $w, $h);
        imagedestroy($img);
        if (!imagejpeg($canvas, $output, $quality)) {
            imagedestroy($canvas);
            throw new \RuntimeException('Could not write JPEG.');
        }
        imagedestroy($canvas);
    }

    public function compress(string $input, string $output, string $format, int $quality): void
    {
        $quality = max(1, min(100, $quality));
        if ($this->hasImagick()) {
            try {
                $this->compressImagick($input, $output, $format, $quality);
                return;
            } catch (\Throwable) {
            }
        }
        if (!extension_loaded('gd')) {
            throw new \RuntimeException(
                'Image processing failed: enable the PHP GD extension (extension=gd in php.ini), or fix Imagick errors.'
            );
        }
        $this->compressGd($input, $output, $quality);
    }

    /**
     * @return array{0: int, 1: int} new width, height
     */
    public function resizeMaxDimension(string $input, string $output, int $maxSide): array
    {
        $maxSide = max(32, min(8192, $maxSide));
        if ($this->hasImagick()) {
            try {
                return $this->resizeMaxDimensionImagick($input, $output, $maxSide);
            } catch (\Throwable) {
            }
        }
        if (!extension_loaded('gd')) {
            throw new \RuntimeException(
                'Image processing failed: enable the PHP GD extension (extension=gd in php.ini), or fix Imagick errors.'
            );
        }
        return $this->resizeMaxDimensionGd($input, $output, $maxSide);
    }

    private function jpgToPngImagick(string $input, string $output): void
    {
        $im = new \Imagick($input);
        $im->setImageFormat('png');
        $im->writeImage($output);
        $im->clear();
        $im->destroy();
    }

    private function pngToJpgImagick(string $input, string $output, int $quality): void
    {
        $im = new \Imagick($input);
        $im->setImageBackgroundColor(new \ImagickPixel('white'));
        if ($im->getImageAlphaChannel()) {
            $im->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
            $flat = $im->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
            $im->clear();
            $im->destroy();
            $im = $flat;
        }
        $im->setImageFormat('jpeg');
        $im->setImageCompressionQuality($quality);
        $im->writeImage($output);
        $im->clear();
        $im->destroy();
    }

    private function compressImagick(string $input, string $output, string $format, int $quality): void
    {
        $im = new \Imagick($input);
        $fmt = strtolower($format);
        if ($fmt === 'jpeg' || $fmt === 'jpg') {
            $im->setImageFormat('jpeg');
            $im->setImageCompressionQuality($quality);
        } elseif ($fmt === 'png') {
            $im->setImageFormat('png');
            $level = (int) max(0, min(9, round((100 - $quality) / 11)));
            if (method_exists($im, 'setOption')) {
                $im->setOption('png:compression-level', (string) $level);
            }
        } elseif ($fmt === 'gif') {
            $im->setImageFormat('gif');
        } elseif ($fmt === 'webp') {
            $im->setImageFormat('webp');
            $im->setImageCompressionQuality($quality);
        } else {
            $im->setImageFormat($im->getImageFormat());
        }

        try {
            $im->stripImage();
        } catch (\Throwable) {
            // Policy or format may disallow strip; not required for output
        }

        $this->applyOutputFormatFromPath($im, $output);
        $im->writeImage($output);
        $im->clear();
        $im->destroy();
    }

    private function compressGd(string $input, string $output, int $quality): void
    {
        $info = @getimagesize($input);
        if ($info === false) {
            throw new \RuntimeException('Unsupported or corrupt image.');
        }
        $img = match ($info[2]) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($input),
            IMAGETYPE_PNG => imagecreatefrompng($input),
            IMAGETYPE_GIF => imagecreatefromgif($input),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? imagecreatefromwebp($input) : false,
            default => false,
        };
        if ($img === false) {
            throw new \RuntimeException('Could not load image for compression.');
        }
        $pngLevel = (int) max(0, min(9, round((100 - $quality) / 11)));
        $ok = match ($info[2]) {
            IMAGETYPE_JPEG => imagejpeg($img, $output, $quality),
            IMAGETYPE_PNG => imagepng($img, $output, $pngLevel),
            IMAGETYPE_GIF => imagegif($img, $output),
            IMAGETYPE_WEBP => function_exists('imagewebp') ? imagewebp($img, $output, $quality) : false,
            default => false,
        };
        imagedestroy($img);
        if (!$ok) {
            throw new \RuntimeException('Could not compress image.');
        }
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function resizeMaxDimensionImagick(string $input, string $output, int $maxSide): array
    {
        $im = new \Imagick($input);
        $w = $im->getImageWidth();
        $h = $im->getImageHeight();
        if ($w <= $maxSide && $h <= $maxSide) {
            $this->applyOutputFormatFromPath($im, $output);
            $im->writeImage($output);
            $im->clear();
            $im->destroy();
            return [$w, $h];
        }
        $im->thumbnailImage($maxSide, $maxSide, true);
        $this->applyOutputFormatFromPath($im, $output);
        $im->writeImage($output);
        $nw = $im->getImageWidth();
        $nh = $im->getImageHeight();
        $im->clear();
        $im->destroy();
        return [$nw, $nh];
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function resizeMaxDimensionGd(string $input, string $output, int $maxSide): array
    {
        $info = @getimagesize($input);
        if ($info === false) {
            throw new \RuntimeException('Unsupported or corrupt image.');
        }
        $w = $info[0];
        $h = $info[1];
        if ($w <= $maxSide && $h <= $maxSide) {
            if (!copy($input, $output)) {
                throw new \RuntimeException('Could not copy image.');
            }
            return [$w, $h];
        }
        $ratio = min($maxSide / $w, $maxSide / $h);
        $nw = (int) max(1, round($w * $ratio));
        $nh = (int) max(1, round($h * $ratio));
        $img = match ($info[2]) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($input),
            IMAGETYPE_PNG => imagecreatefrompng($input),
            IMAGETYPE_GIF => imagecreatefromgif($input),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? imagecreatefromwebp($input) : false,
            default => false,
        };
        if ($img === false) {
            throw new \RuntimeException('Could not load image for resize.');
        }
        $res = imagescale($img, $nw, $nh, IMG_BILINEAR_FIXED);
        imagedestroy($img);
        if ($res === false) {
            throw new \RuntimeException('Resize failed.');
        }
        $ok = match ($info[2]) {
            IMAGETYPE_JPEG => imagejpeg($res, $output, 90),
            IMAGETYPE_PNG => imagepng($res, $output, 6),
            IMAGETYPE_GIF => imagegif($res, $output),
            IMAGETYPE_WEBP => function_exists('imagewebp') ? imagewebp($res, $output, 90) : false,
            default => false,
        };
        imagedestroy($res);
        if (!$ok) {
            throw new \RuntimeException('Could not save resized image.');
        }
        return [$nw, $nh];
    }

    private function applyOutputFormatFromPath(\Imagick $im, string $outputPath): void
    {
        $ext = strtolower(pathinfo($outputPath, PATHINFO_EXTENSION));
        $map = [
            'jpg' => 'jpeg',
            'jpeg' => 'jpeg',
            'png' => 'png',
            'gif' => 'gif',
            'webp' => 'webp',
        ];
        if (isset($map[$ext])) {
            $im->setImageFormat($map[$ext]);
        }
    }
}
