<?php

namespace App\Enums;

enum GradeLevel: string
{
    case PREP_1 = 'prep_1';
    case PREP_2 = 'prep_2';
    case PREP_3 = 'prep_3';
    case SECONDARY_1 = 'secondary_1';
    case SECONDARY_2 = 'secondary_2';
    case SECONDARY_3 = 'secondary_3';

    public function label(): string
    {
        return match ($this) {
            self::PREP_1 => 'الأول الإعدادي',
            self::PREP_2 => 'الثاني الإعدادي',
            self::PREP_3 => 'الثالث الإعدادي',
            self::SECONDARY_1 => 'الأول الثانوي',
            self::SECONDARY_2 => 'الثاني الثانوي',
            self::SECONDARY_3 => 'الثالث الثانوي',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
