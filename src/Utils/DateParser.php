<?php

namespace App\Utils;

final class DateParser
{
    private const FORMATS = [
        'd/m/Y H:i:s',
        'd/m/Y H:i',
        'd/m/Y',
        'd-m-Y H:i:s',
        'd-m-Y H:i',
        'd-m-Y',
        'Y-m-d H:i:s',
        'Y-m-d H:i',
        'Y-m-d',
        'Y/m/d H:i:s',
        'Y/m/d H:i',
        'Y/m/d',
        'H:i:s',
        'H:i',
    ];

    public static function parseDate(?string $value): ?\DateTimeImmutable
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        foreach (self::FORMATS as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, trim($value));
            if ($date !== false) {
                return $date;
            }
        }

        throw new \RuntimeException(
            sprintf('Format de date invalide : "%s"', $value)
        );
    }
}
