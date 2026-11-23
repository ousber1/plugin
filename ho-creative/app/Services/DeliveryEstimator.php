<?php

namespace App\Services;

use App\Models\Holiday;
use App\Models\ShippingZone;
use App\Support\Settings;
use Carbon\CarbonImmutable;

/**
 * Business-date estimates in Africa/Casablanca:
 *  - orders after the cutoff time, or on a non-working day, start on the next working day;
 *  - production = N working days after the start day;
 *  - delivery = ready date + zone delivery days (working days); pickup = ready date.
 * When artwork approval is required the estimate counts from approval, which is flagged.
 */
class DeliveryEstimator
{
    protected ?array $holidays = null;

    public function __construct(protected Settings $settings) {}

    public function isWorkingDay(CarbonImmutable $d): bool
    {
        $days = array_map('intval', (array) $this->settings->get('hours.working_days', [1, 2, 3, 4, 5, 6]));
        $this->holidays ??= Holiday::query()->pluck('date')->map(fn ($x) => $x->format('Y-m-d'))->all();

        return in_array($d->isoWeekday(), $days, true) && ! in_array($d->format('Y-m-d'), $this->holidays, true);
    }

    public function addWorkingDays(CarbonImmutable $d, int $n): CarbonImmutable
    {
        while ($n > 0) {
            $d = $d->addDay();
            if ($this->isWorkingDay($d)) {
                $n--;
            }
        }

        return $d;
    }

    public function startDate(?CarbonImmutable $now = null): CarbonImmutable
    {
        $now ??= CarbonImmutable::now('Africa/Casablanca');
        [$h, $m] = array_map('intval', explode(':', (string) $this->settings->get('hours.cutoff', '13:00')) + [0, 0]);
        $start = $now->startOfDay();
        if (! $this->isWorkingDay($start) || $now->gt($now->setTime($h, $m))) {
            $start = $this->addWorkingDays($start, 1);
        }

        return $start;
    }

    /** @return array{ready: CarbonImmutable, delivery: CarbonImmutable, start: CarbonImmutable} */
    public function estimate(int $productionDays, ?ShippingZone $zone, string $method = 'pickup', ?CarbonImmutable $from = null): array
    {
        $start = $this->startDate($from);
        $ready = $this->addWorkingDays($start, max(0, $productionDays));
        $delivery = $method === 'delivery' ? $this->addWorkingDays($ready, (int) ($zone?->delivery_days ?? 2)) : $ready;

        return compact('start', 'ready', 'delivery');
    }
}
