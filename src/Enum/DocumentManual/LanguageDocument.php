<?php

namespace App\Enum\DocumentManual;

enum LanguageDocument: string
{
    case DE = 'DE';
    case EN = 'EN';
    case ES = 'ES';
    case FR = 'FR';
    case IT = 'IT';
    case NL = 'NL';
    case SE = 'SE';

    /**
     * Returns a list of language codes.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Returns the human-readable language name for each enum case.
     */
    public static function labels(): array
    {
        return [
            self::DE->value => 'German',
            self::EN->value => 'English',
            self::ES->value => 'Spanish',
            self::FR->value => 'French',
            self::IT->value => 'Italian',
            self::NL->value => 'Dutch',
            self::SE->value => 'Swedish',
        ];
    }

    /**
     * Get the label for a specific enum case.
     */
    public function label(): string
    {
        return self::labels()[$this->value] ?? $this->value;
    }
}
