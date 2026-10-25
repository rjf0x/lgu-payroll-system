<?php

namespace App\Enums;

enum PersonnelClassification: string
{
    case Permanent = 'permanent';
    case Coterminous = 'coterminous';
    case PartTime = 'part_time';

    public function label(): string
    {
        return match ($this) {
            self::Permanent => 'Permanent',
            self::Coterminous => 'Coterminous',
            self::PartTime => 'Part-time',
        };
    }
}
