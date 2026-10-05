<?php

namespace App\Enums;

enum MonitoringSource: string
{
    case Intsec = 'intsec';
    case HotelBooking = 'hotel-booking';

    public function label(): string
    {
        return match ($this) {
            self::Intsec => 'INTSEC',
            self::HotelBooking => 'Hotel Booking',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
