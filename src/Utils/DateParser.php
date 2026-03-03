<?php

namespace App\Utils;

use DateTimeImmutable;
use RuntimeException;

/**
 * Parses date strings using a predefined list of accepted input formats.
 */
final class DateParser
{
    private const array FORMATS = [
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

    /**
     * Parses a date string into an immutable date object.
     *
     * @param string|null $value Raw date value.
     *
     * @return DateTimeImmutable|null Parsed date, or null for empty input.
     *
     * @throws RuntimeException When the input does not match any supported format.
     */
    public static function parseDate(?string $value): ?DateTimeImmutable
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        foreach (self::FORMATS as $format) {
            $date = DateTimeImmutable::createFromFormat($format, trim($value));
            if ($date !== false) {
                return $date;
            }
        }

        throw new RuntimeException(
            sprintf('Format de date invalide : "%s"', $value)
        );
    }
}
