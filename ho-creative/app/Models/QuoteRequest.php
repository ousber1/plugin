<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;

class QuoteRequest extends Model
{
    use HasTranslations;

    protected $table = 'quote_requests';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'deadline' => 'date',
        ];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function quote()
    {
        return $this->belongsTo(Quote::class);
    }

    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }
}
