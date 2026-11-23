<?php

namespace App\Models;

use App\Enums\CertificateStatus;
use Illuminate\Database\Eloquent\Model;

class CertificateRequest extends Model
{
    protected $fillable = ['reference', 'user_id', 'course_id', 'status', 'delivery_method', 'center_id'];

    protected $casts = [
        'status' => CertificateStatus::class,
        'show_tracking' => 'boolean',
        'shipped_on' => 'date',
        'received_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function center()
    {
        return $this->belongsTo(Center::class);
    }

    public function isPickup(): bool
    {
        return $this->delivery_method === 'pickup' && $this->center_id;
    }

    public function issuedCertificate()
    {
        return $this->hasOne(IssuedCertificate::class);
    }

    /** Tracking details the student is allowed to see. */
    public function visibleTracking(): ?array
    {
        if (! $this->show_tracking || (! $this->tracking_number && ! $this->carrier)) {
            return null;
        }

        return ['carrier' => $this->carrier, 'number' => $this->tracking_number, 'shipped_on' => $this->shipped_on];
    }
}
