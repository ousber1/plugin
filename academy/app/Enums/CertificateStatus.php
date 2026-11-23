<?php

namespace App\Enums;

enum CertificateStatus: string
{
    case PendingContact = 'pending_contact';
    case Received = 'received';
    case Preparing = 'preparing';
    case Shipped = 'shipped';
    case Delivered = 'delivered';

    public function label(): string
    {
        return match ($this) {
            self::PendingContact => __('بانتظار التواصل'),
            self::Received => __('تم الاستلام'),
            self::Preparing => __('قيد التجهيز'),
            self::Shipped => __('تم الشحن'),
            self::Delivered => __('تم التسليم'),
        };
    }

    public function step(): int
    {
        return array_search($this, self::cases(), true) + 1;
    }

    public function color(): string
    {
        return match ($this) {
            self::PendingContact => 'amber',
            self::Received, self::Preparing => 'indigo',
            self::Shipped => 'blue',
            self::Delivered => 'green',
        };
    }
}
