<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PricingRuleSet;
use App\Models\Product;
use App\Services\AuditLogger;
use App\Services\Pricing\DefinitionValidator;
use App\Services\Pricing\PricingEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Versioned pricing: edit a draft, preview it, then publish. Published versions are
 * immutable; quotes/orders reference (rule_set_id, version) and keep their own frozen amounts.
 */
class PricingController extends Controller
{
    protected function draft(Product $product): PricingRuleSet
    {
        $draft = $product->ruleSets()->where('status', 'draft')->orderByDesc('version')->first();
        if ($draft) {
            return $draft;
        }
        $base = $product->ruleSets()->where('status', 'published')->orderByDesc('version')->first();

        return $product->ruleSets()->create([
            'version' => (int) $product->ruleSets()->max('version') + 1, 'status' => 'draft',
            'definition' => $base?->definition ?? self::blank(),
        ]);
    }

    public static function blank(): array
    {
        return ['mode' => 'tiers', 'quantity' => ['presets' => [100, 250, 500], 'custom' => false, 'min' => 1, 'max' => 100000, 'step' => 1],
            'dimensions' => ['enabled' => false, 'unit' => 'mm', 'custom' => false, 'presets' => [], 'rounding_mm' => 1],
            'area' => ['area_step_m2' => '0.01', 'min_area_m2' => '0', 'rates' => []],
            'tiers' => [['qty' => 100, 'price' => '0.00']], 'setup_fee' => '0', 'min_charge' => '0',
            'production' => ['days' => 3], 'artwork' => ['back_side_if' => null], 'options' => []];
    }

    public function edit(Product $product)
    {
        $this->authorize('pricing.manage');
        $draft = $this->draft($product);
        $versions = $product->ruleSets()->orderByDesc('version')->with('publisher')->get();

        return view('admin.products.pricing', compact('product', 'draft', 'versions'));
    }

    protected function definition(Request $request): array
    {
        $raw = $request->input('definition');
        $def = is_array($raw) ? $raw : json_decode((string) $raw, true);
        if (! is_array($def)) {
            throw ValidationException::withMessages(['definition' => 'Définition JSON invalide.']);
        }

        return $def;
    }

    public function saveDraft(Request $request, Product $product, DefinitionValidator $validator)
    {
        $this->authorize('pricing.manage');
        $def = $this->definition($request);
        $errors = $validator->errors($def);
        $draft = $this->draft($product);
        $draft->update(['definition' => $def, 'notes' => $request->input('notes')]);
        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'errors' => $errors]);
        }

        return back()->with($errors ? 'warn' : 'ok', $errors ? 'Brouillon enregistré avec des erreurs : '.implode(' · ', $errors) : 'Brouillon enregistré.');
    }

    /** Price any input against the posted (unsaved) definition. */
    public function preview(Request $request, Product $product, PricingEngine $engine, DefinitionValidator $validator)
    {
        $this->authorize('pricing.manage');
        $def = $this->definition($request);
        $errors = $validator->errors($def);
        $result = $engine->calculate($def, (array) $request->input('input', []), 'fr', $product);
        $live = $product->publishedRuleSet ? $engine->forProduct($product, (array) $request->input('input', []), 'fr') : null;

        return response()->json($result->toArray() + ['definition_errors' => $errors, 'published_total' => $live?->valid ? money($live->total) : null]);
    }

    public function publish(Request $request, Product $product, DefinitionValidator $validator, PricingEngine $engine)
    {
        $this->authorize('pricing.manage');
        $draft = $this->draft($product);
        $def = $request->has('definition') ? $this->definition($request) : $draft->definition;
        if ($errors = $validator->errors($def)) {
            return back()->with('err', 'Publication impossible : '.implode(' · ', $errors));
        }
        DB::transaction(function () use ($product, $draft, $def, $request, $engine) {
            $old = $product->ruleSets()->where('status', 'published')->first();
            $product->ruleSets()->where('status', 'published')->update(['status' => 'archived']);
            $draft->update(['definition' => $def, 'status' => 'published', 'published_at' => now(), 'published_by' => auth()->id(), 'notes' => $request->input('notes', $draft->notes)]);
            $product->unsetRelation('publishedRuleSet');
            $product->update(['from_price' => $engine->fromPrice($product, $draft)]);
            AuditLogger::log('pricing.published', $product, ['version' => $old?->version], ['version' => $draft->version, 'from_price' => $product->from_price], $request->input('notes'));
        });

        return redirect()->route('admin.pricing.edit', $product)->with('ok', "Version {$draft->version} publiée. Les devis et commandes existants conservent leurs montants.");
    }
}
