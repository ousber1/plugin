<?php

namespace App\Services\Pricing;

/**
 * Structural validation of a pricing definition before it can be saved or published.
 * Returns a list of human-readable problems (empty = valid).
 */
class DefinitionValidator
{
    public const MODIFIER_TYPES = ['per_unit', 'per_thousand', 'per_m2', 'fixed', 'setup', 'percent'];

    public function errors(array $d): array
    {
        $e = [];
        $mode = $d['mode'] ?? 'tiers';
        if (! in_array($mode, ['tiers', 'area'], true)) {
            $e[] = 'Mode de tarification inconnu.';
        }
        $q = $d['quantity'] ?? [];
        foreach ($q['presets'] ?? [] as $p) {
            if (! is_numeric($p) || (int) $p < 1) {
                $e[] = "Quantité prédéfinie invalide : {$p}.";
            }
        }
        if (empty($q['presets']) && empty($q['custom'])) {
            $e[] = 'Définissez des quantités prédéfinies ou autorisez une quantité libre.';
        }
        if (isset($q['min'], $q['max']) && (int) $q['min'] > (int) $q['max']) {
            $e[] = 'Quantité minimum supérieure au maximum.';
        }
        $dim = $d['dimensions'] ?? [];
        $known = ['size' => array_merge(array_column($dim['presets'] ?? [], 'code'), ! empty($dim['custom']) ? ['custom'] : [])];
        if (! empty($dim['enabled'])) {
            if (empty($dim['presets']) && empty($dim['custom'])) {
                $e[] = 'Dimensions activées : ajoutez des formats ou autorisez le sur-mesure.';
            }
            foreach ($dim['presets'] ?? [] as $p) {
                if (empty($p['code']) || ! is_numeric($p['w'] ?? null) || ! is_numeric($p['h'] ?? null) || $p['w'] <= 0 || $p['h'] <= 0) {
                    $e[] = 'Format invalide : '.json_encode($p, JSON_UNESCAPED_UNICODE);
                }
            }
            if (! empty($dim['custom']) && (! is_numeric($dim['min_w'] ?? null) || ! is_numeric($dim['max_w'] ?? null) || ! is_numeric($dim['min_h'] ?? null) || ! is_numeric($dim['max_h'] ?? null))) {
                $e[] = 'Sur-mesure : renseignez largeur/hauteur minimum et maximum (mm).';
            }
        } elseif ($mode === 'area') {
            $e[] = 'Le mode « surface » nécessite des dimensions.';
        }
        $codes = [];
        foreach ($d['options'] ?? [] as $i => $o) {
            $c = $o['code'] ?? '';
            if (! preg_match('/^[a-z0-9_]+$/', $c)) {
                $e[] = "Option n°".($i + 1)." : code invalide (minuscules, chiffres, _).";
            }
            if (isset($known[$c]) || in_array($c, $codes, true)) {
                $e[] = "Code d'option en double : {$c}.";
            }
            if (empty($o['label']['fr'])) {
                $e[] = "Option {$c} : libellé français requis.";
            }
            if (empty($o['choices'])) {
                $e[] = "Option {$c} : au moins un choix.";
            }
            $this->checkCond($o['visible_if'] ?? null, $known, "Option {$c} (visible si)", $e);
            $choiceCodes = [];
            foreach ($o['choices'] ?? [] as $ch) {
                $cc = $ch['code'] ?? '';
                if (! preg_match('/^[a-z0-9_]+$/', $cc) || in_array($cc, $choiceCodes, true)) {
                    $e[] = "Option {$c} : code de choix invalide ou en double ({$cc}).";
                }
                $choiceCodes[] = $cc;
                $this->checkCond($ch['available_if'] ?? null, $known, "Choix {$c}.{$cc} (disponible si)", $e);
                foreach ($ch['modifiers'] ?? [] as $m) {
                    if (! in_array($m['type'] ?? '', self::MODIFIER_TYPES, true) || ! is_numeric($m['amount'] ?? null)) {
                        $e[] = "Choix {$c}.{$cc} : modificateur invalide ".json_encode($m);
                    }
                    if (($m['type'] ?? '') === 'per_m2' && empty($dim['enabled'])) {
                        $e[] = "Choix {$c}.{$cc} : un supplément au m² nécessite des dimensions.";
                    }
                }
            }
            if (! empty($o['default']) && ! in_array($o['default'], $choiceCodes, true)) {
                $e[] = "Option {$c} : choix par défaut inexistant.";
            }
            $known[$c] = $choiceCodes;
            $codes[] = $c;
        }
        if ($mode === 'tiers') {
            if (empty($d['tiers'])) {
                $e[] = 'Ajoutez au moins un palier de prix.';
            }
            foreach ($d['tiers'] ?? [] as $t) {
                if (! is_numeric($t['qty'] ?? null) || (int) $t['qty'] < 1 || ! is_numeric($t['price'] ?? null) || $t['price'] < 0) {
                    $e[] = 'Palier invalide : '.json_encode($t);
                }
                $this->checkCond($t['match'] ?? null, $known, 'Palier '.($t['qty'] ?? '?'), $e, true);
            }
        } else {
            if (empty($d['area']['rates'])) {
                $e[] = 'Ajoutez au moins un tarif au m².';
            }
            foreach ($d['area']['rates'] ?? [] as $r) {
                if (! is_numeric($r['min_m2'] ?? null) || ! is_numeric($r['rate'] ?? null) || $r['rate'] < 0) {
                    $e[] = 'Tarif m² invalide : '.json_encode($r);
                }
                $this->checkCond($r['match'] ?? null, $known, 'Tarif m²', $e, true);
            }
        }
        foreach (['setup_fee', 'min_charge'] as $k) {
            if (isset($d[$k]) && $d[$k] !== '' && (! is_numeric($d[$k]) || $d[$k] < 0)) {
                $e[] = "{$k} doit être un montant positif.";
            }
        }
        $this->checkCond($d['artwork']['back_side_if'] ?? null, $known, 'Verso requis si', $e, true);

        return $e;
    }

    /** Conditions may only reference `size` or options declared earlier, with existing choice codes. */
    protected function checkCond(?array $cond, array $known, string $where, array &$e, bool $allowAll = false): void
    {
        foreach ((array) $cond as $key => $vals) {
            if (! array_key_exists($key, $known)) {
                $e[] = "{$where} : « {$key} » doit être « size » ou une option définie AVANT.";

                continue;
            }
            foreach ((array) $vals as $v) {
                if (! in_array($v, $known[$key], true)) {
                    $e[] = "{$where} : valeur « {$v} » inconnue pour « {$key} ».";
                }
            }
        }
    }
}
