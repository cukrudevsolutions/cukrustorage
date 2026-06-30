<?php
declare(strict_types=1);

namespace Cukru;

use DateTimeImmutable;

final class RateCard
{
    public static function calculateStorage(int $boxes): float
    {
        if ($boxes <= 0) {
            return 0.0;
        }

        $rate1 = Settings::getFloat('rate_box1', 30);
        $rate2 = Settings::getFloat('rate_box2', 55);
        $rate3 = Settings::getFloat('rate_box3', 80);
        $rateExtra = Settings::getFloat('rate_extra_box', 10);

        if ($boxes === 1) {
            return $rate1;
        }
        if ($boxes === 2) {
            return $rate2;
        }
        if ($boxes === 3) {
            return $rate3;
        }

        return $rate3 + ($boxes - 3) * $rateExtra;
    }

    /**
     * Kira bilangan hari overdue & jumlah caj, berdasarkan tarikh tamat return window,
     * tempoh grace (hari) dan kadar/hari. Tarikh rujukan = tarikh barang dipulangkan
     * (jika sudah RETURNED) atau hari ini (jika masih belum dipulangkan).
     */
    public static function calculateOverdue(DateTimeImmutable $returnWindowEnd, ?DateTimeImmutable $referenceDate = null): array
    {
        $graceDays = Settings::getInt('overdue_grace_days', 0);
        $perDay = Settings::getFloat('overdue_rate_per_day', 10);

        $cutoff = $returnWindowEnd->modify("+{$graceDays} days");
        $reference = $referenceDate ?? new DateTimeImmutable('today');

        $diff = $cutoff->diff($reference);
        $days = ($reference > $cutoff) ? (int) $diff->days : 0;

        return [
            'days' => $days,
            'amount' => round($days * $perDay, 2),
            'cutoff_date' => $cutoff,
        ];
    }
}
