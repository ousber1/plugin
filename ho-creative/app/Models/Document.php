<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    use HasTranslations;

    protected $table = 'documents';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
            'snapshot' => 'array',
            'issued_at' => 'datetime',
        ];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function quote()
    {
        return $this->belongsTo(Quote::class);
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function related()
    {
        return $this->belongsTo(Document::class, 'related_document_id');
    }

    public function issuer()
    {
        return $this->belongsTo(User::class, 'issued_by');
    }


    public const TYPES = ['quote', 'proforma', 'invoice', 'credit_note', 'purchase_order', 'delivery_note', 'receipt'];

    public function retailSale()
    {
        return $this->belongsTo(RetailSale::class);
    }
}
