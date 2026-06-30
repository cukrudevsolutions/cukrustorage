<?php
declare(strict_types=1);

namespace Cukru;

final class Terms
{
    /** Render teks Terma & Syarat (plain text + **bold**) kepada HTML selamat. */
    public static function render(string $raw): string
    {
        $paragraphs = preg_split('/\n{2,}/', trim($raw)) ?: [];
        $html = '';

        foreach ($paragraphs as $para) {
            $escaped = e($para);
            $escaped = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $escaped);
            $escaped = nl2br($escaped);
            $html .= '<p>' . $escaped . '</p>' . "\n";
        }

        return $html;
    }
}
