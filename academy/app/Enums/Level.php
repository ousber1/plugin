<?php

namespace App\Enums;

enum Level: string
{
    case Beginner = 'beginner';
    case Intermediate = 'intermediate';
    case Advanced = 'advanced';
    case All = 'all';

    public function label(): string
    {
        return match ($this) {
            self::Beginner => __('مبتدئ'),
            self::Intermediate => __('متوسط'),
            self::Advanced => __('متقدم'),
            self::All => __('كل المستويات'),
        };
    }
}
