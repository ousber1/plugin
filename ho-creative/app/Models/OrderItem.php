<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasTranslations;

    protected $table = 'order_items';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:4',
            'subtotal' => 'decimal:2',
            'manual_discount' => 'decimal:2',
            'total' => 'decimal:2',
            'width_mm' => 'decimal:2',
            'height_mm' => 'decimal:2',
            'selection' => 'array',
            'options_snapshot' => 'array',
            'breakdown' => 'array',
            'requires_proof' => 'boolean',
            'is_custom' => 'boolean',
        ];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function design()
    {
        return $this->belongsTo(Design::class);
    }

    public function template()
    {
        return $this->belongsTo(Template::class);
    }

    public function artworkFiles()
    {
        return $this->hasMany(ArtworkFile::class);
    }

    public function proofs()
    {
        return $this->hasMany(Proof::class);
    }

    public function job()
    {
        return $this->hasOne(ProductionJob::class);
    }

    public function remakeOf()
    {
        return $this->belongsTo(OrderItem::class, 'remake_of_id');
    }


    public function currentProof()
    {
        return $this->hasOne(Proof::class)->ofMany(['version' => 'max']);
    }

    public function latestArtwork(string $side = 'front'): ?ArtworkFile
    {
        return $this->artworkFiles()->where('side', $side)->orderByDesc('version')->first();
    }

    public function needsBackSide(): bool
    {
        return (bool) ($this->breakdown['meta']['two_sided'] ?? false);
    }

    /** True when production may start (proof approved or not required). */
    public function proofCleared(): bool
    {
        return ! $this->requires_proof || $this->proof_status === 'approved';
    }
}
