<?php

namespace App\Enum\DocumentManual;

enum SpecialDocument: string
{
    case ORG = 'ORG';
    case CE = 'CE';
    case SHB = 'SHB';

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
            self::ORG->value => 'Organizational Document',
            self::CE->value => 'Certificate Document',
            self::SHB->value => 'Safety Information Document',
        ];
    }

    /**
     * Get the label for a specific enum case.
     * Example usage: SpecialDocument::CE->label();
     */
    public function label(): string
    {
        return self::labels()[$this->value] ?? $this->value;
    }
}
