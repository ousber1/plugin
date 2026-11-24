<?php

namespace App\Services\Pricing;

use App\Models\PricingRuleSet;
use App\Models\Product;
use App\Support\Money;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Deterministic, server-side price calculation for one configured line.
 *
 * Rule order (see docs/PRICING.md):
 *  1. Normalise quantity (preset or custom within min/max/step).
 *  2. Normalise dimensions: preset or custom; cm → mm; round UP to `rounding_mm`; validate min/max.
 *  3. Resolve options in definition order. `visible_if` / `available_if` may only reference
 *     options declared earlier (and `size`). Hidden options are dropped; an unavailable explicit
 *     choice is an error; a missing choice falls back to the default, then the first available.
 *  4. Base price: `tiers` (most specific matching tier set; exact quantity, else unit price of the
 *     largest tier ≤ quantity × quantity) or `area` (area per piece rounded up to `area_step_m2`,
 *     floored at `min_area_m2`, × quantity × rate of the largest band ≤ total area).
 *  5. Choice modifiers, in this order: per_m2, per_unit, per_thousand, fixed, setup; then product setup fee.
 *  6. Minimum charge: if production subtotal < min_charge, add the difference.
 *  7. Percentage surcharges (e.g. express) on the subtotal after step 6, non-compounding.
 *  8. Artwork service fee (design_service), fixed, never discounted by percentages.
 *  Every component is rounded to 2 decimals HALF_UP; the line total is the sum of rounded components.
 *  Cart-level steps (coupon → shipping → tax) are in CartTotals.
 */
class PricingEngine
{
    public const MODIFIER_ORDER = ['per_m2', 'per_unit', 'per_thousand', 'fixed', 'setup'];

    public function forProduct(Product $product, array $input, ?string $locale = null, ?PricingRuleSet $ruleSet = null): PriceResult
    {
        $ruleSet ??= $product->publishedRuleSet;
        if (! $ruleSet) {
            return PriceResult::invalid([__('Ce produit n\'a pas de grille tarifaire publiée.')]);
        }

        return $this->calculate($ruleSet->definition, $input, $locale, $product, $ruleSet);
    }

    public function calculate(array $def, array $input, ?string $locale = null, ?Product $product = null, ?PricingRuleSet $ruleSet = null): PriceResult
    {
        $locale ??= app()->getLocale();
        $L = fn ($v) => is_array($v) ? ($v[$locale] ?? $v['fr'] ?? '') : (string) $v;
        $r = new PriceResult;
        $r->ruleSetId = $ruleSet?->id;
        $r->ruleVersion = $ruleSet?->version;

        // ---- 1. Quantity --------------------------------------------------------------
        $q = $def['quantity'] ?? [];
        $presets = array_values(array_map('intval', $q['presets'] ?? []));
        $allowCustom = (bool) ($q['custom'] ?? false);
        $min = (int) ($q['min'] ?? ($presets ? min($presets) : 1));
        $max = (int) ($q['max'] ?? 1000000);
        $step = max(1, (int) ($q['step'] ?? 1));
        $qty = isset($input['quantity']) && $input['quantity'] !== '' ? (int) $input['quantity'] : ($presets[0] ?? $min);
        if (! in_array($qty, $presets, true)) {
            if (! $allowCustom) {
                $r->errors[] = __('Quantité non disponible pour ce produit.');
                $qty = $presets[0] ?? $min;
            } elseif ($qty < $min || $qty > $max || ($qty % $step) !== 0) {
                $r->errors[] = __('La quantité doit être comprise entre :min et :max (par multiples de :step).', ['min' => $min, 'max' => $max, 'step' => $step]);
            }
        }
        $qty = max(1, $qty);
        $r->quantity = $qty;
        $r->ui['quantity'] = ['presets' => $presets, 'custom' => $allowCustom, 'min' => $min, 'max' => $max, 'step' => $step, 'value' => $qty];

        // ---- 2. Dimensions ------------------------------------------------------------
        $dimDef = $def['dimensions'] ?? ['enabled' => false];
        $sizeCode = null;
        $areaPiece = null;
        if (! empty($dimDef['enabled'])) {
            $sizes = $dimDef['presets'] ?? [];
            $sizeCode = (string) ($input['size'] ?? ($sizes[0]['code'] ?? 'custom'));
            if ($sizeCode === 'custom') {
                if (empty($dimDef['custom'])) {
                    $r->errors[] = __('Les dimensions personnalisées ne sont pas disponibles.');
                    $sizeCode = $sizes[0]['code'] ?? null;
                }
            }
            if ($sizeCode === 'custom') {
                $unit = ($input['unit'] ?? $dimDef['unit'] ?? 'mm') === 'cm' ? 'cm' : 'mm';
                $factor = $unit === 'cm' ? 10 : 1;
                // Missing custom dimensions default to the minimum allowed (shown in mm).
                $w = isset($input['width']) && $input['width'] !== '' ? Money::of($input['width'])->multipliedBy($factor) : Money::of($dimDef['min_w'] ?? 0);
                $h = isset($input['height']) && $input['height'] !== '' ? Money::of($input['height'])->multipliedBy($factor) : Money::of($dimDef['min_h'] ?? 0);
                $roundTo = Money::of($dimDef['rounding_mm'] ?? 1);
                if ($roundTo->isPositive()) {
                    $w = $w->dividedBy($roundTo, 0, RoundingMode::Ceiling)->multipliedBy($roundTo);
                    $h = $h->dividedBy($roundTo, 0, RoundingMode::Ceiling)->multipliedBy($roundTo);
                }
                $bad = $w->isLessThan(Money::of($dimDef['min_w'] ?? 1)) || $w->isGreaterThan(Money::of($dimDef['max_w'] ?? 100000))
                    || $h->isLessThan(Money::of($dimDef['min_h'] ?? 1)) || $h->isGreaterThan(Money::of($dimDef['max_h'] ?? 100000));
                if ($bad) {
                    $r->errors[] = __('Dimensions hors limites : largeur :minw–:maxw mm, hauteur :minh–:maxh mm.', [
                        'minw' => $dimDef['min_w'] ?? 1, 'maxw' => $dimDef['max_w'] ?? 100000,
                        'minh' => $dimDef['min_h'] ?? 1, 'maxh' => $dimDef['max_h'] ?? 100000]);
                }
                $r->widthMm = (string) $w->toScale(1, RoundingMode::HalfUp);
                $r->heightMm = (string) $h->toScale(1, RoundingMode::HalfUp);
                $r->sizeLabel = $r->widthMm.' × '.$r->heightMm.' mm';
            } else {
                $preset = collect($sizes)->firstWhere('code', $sizeCode);
                if (! $preset) {
                    $r->errors[] = __('Format non disponible.');
                    $preset = $sizes[0] ?? ['code' => '', 'w' => 0, 'h' => 0, 'label' => ''];
                    $sizeCode = $preset['code'];
                }
                $r->widthMm = (string) Money::round($preset['w'], 1);
                $r->heightMm = (string) Money::round($preset['h'], 1);
                $r->sizeLabel = $L($preset['label'] ?? ($preset['w'].' × '.$preset['h'].' mm'));
            }
            $areaPiece = Money::of($r->widthMm)->multipliedBy($r->heightMm)->dividedBy(1000000, 6, RoundingMode::HalfUp);
            // m² rounding / minimum area only apply to area-priced products (or when explicitly configured)
            $isArea = ($def['mode'] ?? 'tiers') === 'area';
            $stepM2 = Money::of($def['area']['area_step_m2'] ?? ($isArea ? '0.01' : '0'));
            if ($stepM2->isPositive()) {
                $areaPiece = $areaPiece->dividedBy($stepM2, 0, RoundingMode::Ceiling)->multipliedBy($stepM2);
            }
            $minArea = Money::of($isArea ? ($def['area']['min_area_m2'] ?? 0) : 0);
            if ($areaPiece->isLessThan($minArea)) {
                $areaPiece = $minArea;
            }
            $r->size = $sizeCode;
            $r->areaM2 = (string) $areaPiece->multipliedBy($qty)->toScale(4, RoundingMode::HalfUp);
            $r->ui['size'] = [
                'value' => $sizeCode,
                'presets' => array_map(fn ($s) => ['code' => $s['code'], 'label' => $L($s['label'] ?? '')], $sizes),
                'custom' => (bool) ($dimDef['custom'] ?? false),
                'unit' => $dimDef['unit'] ?? 'mm',
                'limits' => array_intersect_key($dimDef, array_flip(['min_w', 'max_w', 'min_h', 'max_h', 'rounding_mm'])),
                'width' => $r->widthMm, 'height' => $r->heightMm,
            ];
        }

        // ---- 3. Options ---------------------------------------------------------------
        $resolved = ['size' => $sizeCode];
        $chosenChoices = [];
        foreach ($def['options'] ?? [] as $opt) {
            $code = $opt['code'];
            $visible = $this->conditionHolds($opt['visible_if'] ?? null, $resolved);
            $choicesUi = [];
            $available = [];
            foreach ($opt['choices'] ?? [] as $c) {
                $ok = $this->conditionHolds($c['available_if'] ?? null, $resolved);
                if ($ok) {
                    $available[$c['code']] = $c;
                }
                $choicesUi[] = ['code' => $c['code'], 'label' => $L($c['label'] ?? $c['code']), 'available' => $ok,
                    'image' => $c['image'] ?? null, 'hint' => $L($c['hint'] ?? '')];
            }
            $value = null;
            if ($visible) {
                $requested = $input['options'][$code] ?? null;
                if ($requested !== null && $requested !== '') {
                    if (isset($available[$requested])) {
                        $value = $requested;
                    } else {
                        $exists = collect($opt['choices'] ?? [])->firstWhere('code', $requested);
                        $r->errors[] = $exists
                            ? __('« :choice » n\'est pas compatible avec votre configuration (:option).', ['choice' => $L($exists['label']), 'option' => $L($opt['label'])])
                            : __('Choix invalide pour :option.', ['option' => $L($opt['label'])]);
                    }
                }
                if ($value === null) {
                    $default = $opt['default'] ?? null;
                    $value = ($default !== null && isset($available[$default])) ? $default : (array_key_first($available));
                    if ($value === null && ! empty($opt['required'])) {
                        $r->errors[] = __('Aucun choix disponible pour :option avec cette configuration.', ['option' => $L($opt['label'])]);
                    }
                }
                if ($value !== null && isset($available[$value])) {
                    $resolved[$code] = $value;
                    $chosenChoices[$code] = $available[$value];
                    $r->options[$code] = ['label' => $L($opt['label']), 'value' => $value, 'value_label' => $L($available[$value]['label'] ?? $value)];
                }
            }
            $r->ui['options'][] = ['code' => $code, 'label' => $L($opt['label']), 'visible' => $visible, 'display' => $opt['display'] ?? 'select',
                'help' => $L($opt['help'] ?? ''), 'value' => $resolved[$code] ?? null, 'choices' => $choicesUi];
        }
        $r->selection = ['quantity' => $qty, 'size' => $sizeCode, 'width' => $r->widthMm, 'height' => $r->heightMm, 'unit' => 'mm', 'options' => array_diff_key($resolved, ['size' => 1])];

        // ---- production days & sides metadata -------------------------------------------
        $days = (int) ($def['production']['days'] ?? 3);
        $extra = 0;
        foreach ($chosenChoices as $c) {
            if (isset($c['production_days'])) {
                $days = (int) $c['production_days'];
            }
            $extra += (int) ($c['extra_days'] ?? 0);
        }
        $r->productionDays = $days + $extra;
        $r->meta['mode'] = $def['mode'] ?? 'tiers';
        $r->meta['two_sided'] = $this->conditionHolds($def['artwork']['back_side_if'] ?? ['__never__' => ['x']], $resolved);

        if ($r->errors) {
            $r->valid = false;

            return $r;
        }

        // ---- 4. Base price --------------------------------------------------------------
        $mode = $def['mode'] ?? 'tiers';
        $base = BigDecimal::zero();
        if ($mode === 'area') {
            if ($areaPiece === null) {
                return PriceResult::invalid([__('Configuration de prix invalide : dimensions requises.')]);
            }
            $total = $areaPiece->multipliedBy($qty);
            $rates = $this->mostSpecific($def['area']['rates'] ?? [], $resolved);
            $rate = null;
            foreach (collect($rates)->sortBy(fn ($b) => (float) ($b['min_m2'] ?? 0)) as $band) {
                if ($total->isGreaterThanOrEqualTo(Money::of($band['min_m2'] ?? 0))) {
                    $rate = $band;
                }
            }
            $rate ??= collect($rates)->sortBy(fn ($b) => (float) ($b['min_m2'] ?? 0))->first();
            if (! $rate) {
                return PriceResult::invalid([__('Aucun tarif au m² ne correspond à cette configuration.')]);
            }
            $base = $total->multipliedBy(Money::of($rate['rate']));
            $r->addComponent('base', __('Impression :area m² × :rate/m²', ['area' => (string) $total->toScale(2, RoundingMode::HalfUp), 'rate' => Money::format($rate['rate'], $locale)]), $base);
        } else {
            $tiers = collect($this->mostSpecific($def['tiers'] ?? [], $resolved))->sortBy('qty')->values();
            if ($tiers->isEmpty()) {
                return PriceResult::invalid([__('Aucun tarif ne correspond à cette combinaison.')]);
            }
            $exact = $tiers->firstWhere('qty', $qty);
            if ($exact) {
                $base = Money::of($exact['price']);
            } else {
                $tier = $tiers->filter(fn ($t) => (int) $t['qty'] <= $qty)->last() ?? $tiers->first();
                $unit = Money::of($tier['price'])->dividedBy((int) $tier['qty'], 6, RoundingMode::HalfUp);
                $base = $unit->multipliedBy($qty);
            }
            $r->addComponent('base', __('Impression – :qty ex.', ['qty' => $qty]), $base);
        }

        // ---- 5. Modifiers ---------------------------------------------------------------
        $percentMods = [];
        foreach (self::MODIFIER_ORDER as $type) {
            foreach ($def['options'] ?? [] as $opt) {
                $c = $chosenChoices[$opt['code']] ?? null;
                if (! $c) {
                    continue;
                }
                foreach ($c['modifiers'] ?? [] as $i => $m) {
                    if (($m['type'] ?? '') !== $type) {
                        continue;
                    }
                    $amount = match ($type) {
                        'per_m2' => $areaPiece ? Money::of($m['amount'])->multipliedBy($areaPiece->multipliedBy($qty)) : BigDecimal::zero(),
                        'per_unit' => Money::of($m['amount'])->multipliedBy($qty),
                        'per_thousand' => Money::of($m['amount'])->multipliedBy($qty)->dividedBy(1000, 6, RoundingMode::HalfUp),
                        default => Money::of($m['amount']),
                    };
                    if (! $amount->isZero()) {
                        $r->addComponent("{$opt['code']}.{$c['code']}.{$type}", $L($opt['label']).' : '.$L($c['label']), $amount);
                    }
                }
            }
        }
        foreach ($def['options'] ?? [] as $opt) {
            $c = $chosenChoices[$opt['code']] ?? null;
            foreach (($c['modifiers'] ?? []) as $m) {
                if (($m['type'] ?? '') === 'percent') {
                    $percentMods[] = [$opt, $c, $m];
                }
            }
        }
        if (Money::of($def['setup_fee'] ?? 0)->isPositive()) {
            $r->addComponent('setup_fee', __('Frais de calage / préparation'), Money::of($def['setup_fee']));
        }

        // ---- 6. Minimum charge ----------------------------------------------------------
        $production = $r->totalOf();
        $minCharge = Money::of($def['min_charge'] ?? 0);
        if ($production->isLessThan($minCharge)) {
            $r->addComponent('min_charge', __('Ajustement minimum de facturation (:min)', ['min' => Money::format($minCharge, $locale)]), $minCharge->minus($production));
            $production = $r->totalOf();
        }

        // ---- 7. Percentage surcharges (non-compounding, on post-minimum subtotal) -------
        foreach ($percentMods as [$opt, $c, $m]) {
            $r->addComponent("{$opt['code']}.{$c['code']}.percent", $L($opt['label']).' : '.$L($c['label']).' (+'.$m['amount'].' %)', Money::percent($production, $m['amount']));
        }

        // ---- 8. Artwork service -----------------------------------------------------------
        $artwork = $input['artwork_mode'] ?? null;
        $r->artworkMode = $artwork;
        if ($artwork === 'design_service') {
            $fee = Money::of($product?->design_fee ?? ($def['artwork']['design_fee'] ?? 0));
            if ($fee->isPositive()) {
                $r->addComponent('design_service', __('Création / modification graphique par HO CREATIVE'), $fee);
            }
        }

        $r->finalize();

        return $r;
    }

    /**
     * Condition format: {"option_code": ["allowed", "values"], ...} — all keys must hold (AND).
     */
    public function conditionHolds(?array $cond, array $resolved): bool
    {
        if (! $cond) {
            return true;
        }
        foreach ($cond as $key => $allowed) {
            if (! array_key_exists($key, $resolved) || ! in_array($resolved[$key], (array) $allowed, true)) {
                return false;
            }
        }

        return true;
    }

    /** Keep only rows whose `match` holds, and among them the ones with the most match keys. */
    protected function mostSpecific(array $rows, array $resolved): array
    {
        $matching = array_filter($rows, fn ($t) => $this->conditionHolds(array_map(fn ($v) => (array) $v, $t['match'] ?? []), $resolved));
        if (! $matching) {
            return [];
        }
        $best = max(array_map(fn ($t) => count($t['match'] ?? []), $matching));

        return array_values(array_filter($matching, fn ($t) => count($t['match'] ?? []) === $best));
    }

    /**
     * Lowest valid total over preset sizes × preset quantities × option combinations
     * (bounded to 3000 evaluations; falls back to defaults beyond that).
     */
    public function fromPrice(Product $product, ?PricingRuleSet $rs = null): ?string
    {
        $rs ??= $product->publishedRuleSet;
        if (! $rs) {
            return null;
        }
        $def = $rs->definition;
        $sizes = ! empty($def['dimensions']['enabled']) ? array_column($def['dimensions']['presets'] ?? [], 'code') : [null];
        if (! $sizes) {
            $sizes = ['custom'];
        }
        $qtys = $def['quantity']['presets'] ?? [$def['quantity']['min'] ?? 1];
        $combos = [[]];
        foreach ($def['options'] ?? [] as $opt) {
            $next = [];
            foreach ($combos as $combo) {
                foreach ($opt['choices'] ?? [] as $c) {
                    $next[] = $combo + [$opt['code'] => $c['code']];
                }
            }
            $combos = $next;
            if (count($combos) * count($sizes) * count($qtys) > 3000) {
                $combos = [[]];
                break;
            }
        }
        $best = null;
        foreach ($sizes as $size) {
            foreach ($qtys as $qty) {
                foreach ($combos as $combo) {
                    $in = ['quantity' => $qty, 'options' => $combo];
                    if ($size === 'custom') {
                        $in += ['size' => 'custom', 'width' => $def['dimensions']['min_w'] ?? 100, 'height' => $def['dimensions']['min_h'] ?? 100, 'unit' => 'mm'];
                    } elseif ($size) {
                        $in['size'] = $size;
                    }
                    $res = $this->calculate($def, $in, 'fr', $product, $rs);
                    if ($res->valid && ($best === null || Money::of($res->total)->isLessThan($best))) {
                        $best = $res->total;
                    }
                }
            }
        }

        return $best;
    }
}
