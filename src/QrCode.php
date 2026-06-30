<?php
declare(strict_types=1);

namespace Cukru;

use Endroid\QrCode\QrCode as EndroidQrCode;
use Endroid\QrCode\Writer\PngWriter;

final class QrCode
{
    public static function pngBinary(string $data, int $size = 300): string
    {
        $qrCode = new EndroidQrCode(data: $data, size: $size, margin: 10);
        $writer = new PngWriter();
        return $writer->write($qrCode)->getString();
    }

    public static function dataUri(string $data, int $size = 220): string
    {
        return 'data:image/png;base64,' . base64_encode(self::pngBinary($data, $size));
    }
}
