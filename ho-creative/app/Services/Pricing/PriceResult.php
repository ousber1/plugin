<?php

namespace App\Services\Pricing;

use App\Support\Money;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

class PriceResult
{
    public bool $valid = true;

    public array $errors = [];

    public int $quantity = 1;

    public ?string $size = null;

    public ?string $sizeLabel = null;

    public ?string $widthMm = null;

    public ?string $heightMm = null;

    public ?string $areaM2 = null;

    /** @var array<string, array{label:string,value:string,value_label:string}> */
    public array $options = [];

    public array $selection = [];

    /** @var array<int, array{key:string,label:string,amount:string}> */
    public array $components = [];

    public string $total = '0.00';

    public string $unitPrice = '0.0000';

    public int $productionDays = 0;

    public ?string $artworkMode = null;

    public ?int $ruleSetId = null;

    public ?int $ruleVersion = null;

    public array $meta = [];

    /** State for rendering the configurator (visibility/availability). */
    public array $ui = ['options' => []];

    public static function invalid(array $errors): self
    {
        $r = new self;
        $r->valid = false;
        $r->errors = $errors;

        return $r;
    }

    public function addComponent(string $key, string $label, BigDecimal $amount): void
    {
        $this->components[] = ['key' => $key, 'label' => $label, 'amount' => Money::str($amount)];
    }

    public function totalOf(): BigDecimal
    {
        return Money::sum(array_column($this->components, 'amount'));
    }

    public function finalize(): void
    {
        $total = $this->totalOf();
        $this->total = Money::str($total);
        $this->unitPrice = (string) $total->dividedBy(max(1, $this->quantity), 4, RoundingMode::HalfUp);
    }

    public function describe(): string
    {
        $parts = [];
        if ($this->sizeLabel) {
            $parts[] = $this->sizeLabel;
        }
        foreach ($this->options as $o) {
            $parts[] = $o['value_label'];
        }

        return implode(' · ', $parts);
    }

    /** Frozen breakdown stored on quote/order lines. */
    public function snapshot(): array
    {
        return [
            'components' => $this->components,
            'total' => $this->total,
            'unit_price' => $this->unitPrice,
            'quantity' => $this->quantity,
            'size' => $this->sizeLabel,
            'width_mm' => $this->widthMm,
            'height_mm' => $this->heightMm,
            'area_m2' => $this->areaM2,
            'production_days' => $this->productionDays,
            'rule_set_id' => $this->ruleSetId,
            'rule_version' => $this->ruleVersion,
            'meta' => $this->meta,
            'calculated_at' => now()->toIso8601String(),
        ];
    }

    public function toArray(): array
    {
        return [
            'valid' => $this->valid,
            'errors' => $this->errors,
            'quantity' => $this->quantity,
            'size_label' => $this->sizeLabel,
            'width_mm' => $this->widthMm,
            'height_mm' => $this->heightMm,
            'area_m2' => ($this->meta['mode'] ?? 'tiers') === 'area' ? $this->areaM2 : null,
            'options' => $this->options,
            'components' => array_map(fn ($c) => $c + ['formatted' => Money::format($c['amount'])], $this->components),
            'total' => $this->total,
            'total_formatted' => Money::format($this->total),
            'unit_price' => $this->unitPrice,
            'unit_formatted' => Money::format(Money::round($this->unitPrice, 2)),
            'production_days' => $this->productionDays,
            'two_sided' => $this->meta['two_sided'] ?? false,
            'ui' => $this->ui,
            'rule_version' => $this->ruleVersion,
        ];
    }
}
