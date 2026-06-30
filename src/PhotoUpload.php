<?php
declare(strict_types=1);

namespace Cukru;

final class PhotoUpload
{
    public const MAX_PHOTOS = 3;
    private const MAX_DIMENSION = 1280;
    private const JPEG_QUALITY = 75;
    private const MAX_UPLOAD_BYTES = 10 * 1024 * 1024;

    /** Proses fail upload (array $_FILES['x']), pulangkan data URI base64 JPEG yang dimampatkan. */
    public static function processUploadedFile(array $file): string
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException(self::uploadErrorMessage((int) $file['error']));
        }
        if ($file['size'] > self::MAX_UPLOAD_BYTES) {
            throw new \RuntimeException('File is too large (maximum 10MB).');
        }

        $imageInfo = @getimagesize($file['tmp_name']);
        if (!$imageInfo) {
            throw new \RuntimeException('File is not a valid image.');
        }

        $type = $imageInfo[2];
        $source = match ($type) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($file['tmp_name']),
            IMAGETYPE_PNG => imagecreatefrompng($file['tmp_name']),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? imagecreatefromwebp($file['tmp_name']) : false,
            default => false,
        };

        if (!$source) {
            throw new \RuntimeException('Unsupported image format. Please use JPEG, PNG, or WEBP.');
        }

        $source = self::autoRotate($source, $file['tmp_name']);

        [$newWidth, $newHeight] = self::scaledDimensions(imagesx($source), imagesy($source), self::MAX_DIMENSION);
        $resized = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled($resized, $source, 0, 0, 0, 0, $newWidth, $newHeight, imagesx($source), imagesy($source));
        imagedestroy($source);

        ob_start();
        imagejpeg($resized, null, self::JPEG_QUALITY);
        $data = (string) ob_get_clean();
        imagedestroy($resized);

        return 'data:image/jpeg;base64,' . base64_encode($data);
    }

    /** @return array{0: int, 1: int} */
    private static function scaledDimensions(int $width, int $height, int $maxDim): array
    {
        if ($width <= $maxDim && $height <= $maxDim) {
            return [$width, $height];
        }
        if ($width >= $height) {
            return [$maxDim, (int) round($height * ($maxDim / $width))];
        }
        return [(int) round($width * ($maxDim / $height)), $maxDim];
    }

    /** Betulkan orientasi gambar kamera telefon berdasarkan data EXIF. */
    private static function autoRotate(\GdImage $image, string $path): \GdImage
    {
        if (!function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($path);
        $orientation = $exif['Orientation'] ?? null;

        $rotated = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => null,
        };

        if ($rotated === false || $rotated === null) {
            return $image;
        }

        imagedestroy($image);
        return $rotated;
    }

    private static function uploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File size exceeds the server limit.',
            UPLOAD_ERR_PARTIAL => 'File upload was incomplete. Please try again.',
            UPLOAD_ERR_NO_FILE => 'No file selected.',
            default => 'An error occurred while uploading the file.',
        };
    }
}
