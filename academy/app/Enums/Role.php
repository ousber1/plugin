<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Instructor = 'instructor';
    case Student = 'student';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'مدير',
            self::Instructor => 'مدرّس',
            self::Student => 'طالب',
        };
    }
}
