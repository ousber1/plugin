<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class IssuedCertificate extends Model
{
    protected $fillable = ['code', 'certificate_request_id', 'user_id', 'course_id', 'holder_name', 'course_title', 'issued_on', 'public_note', 'created_by'];

    protected $casts = ['issued_on' => 'date', 'revoked_at' => 'datetime'];

    public function request()
    {
        return $this->belongsTo(CertificateRequest::class, 'certificate_request_id');
    }

    public function isRevoked(): bool
    {
        return $this->status === 'revoked';
    }

    public function verifyUrl(): string
    {
        return route('verify.show', $this->code);
    }

    /** Unambiguous code such as NB-7KQ4-M2XP (no 0/O/1/I). */
    public static function generateCode(): string
    {
        $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        do {
            $part = fn () => collect(range(1, 4))->map(fn () => $alphabet[random_int(0, strlen($alphabet) - 1)])->implode('');
            $code = 'NB-'.$part().'-'.$part();
        } while (static::where('code', $code)->exists());

        return $code;
    }

    public static function normalizeCode(string $code): string
    {
        return Str::upper(preg_replace('/[^A-Za-z0-9\-]/', '', trim($code)));
    }
}
