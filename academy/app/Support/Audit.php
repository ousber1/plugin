<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class Audit
{
    public static function log(string $action, ?Model $subject = null, array $meta = []): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $subject ? $subject->getMorphClass() : null,
            'subject_id' => $subject?->getKey(),
            'meta' => $meta ?: null,
            'ip' => request()?->ip(),
            'created_at' => now(),
        ]);
    }

    public static function label(string $action): string
    {
        return [
            'user.role_changed' => 'تغيير دور مستخدم',
            'user.status_changed' => 'تفعيل/تعطيل حساب',
            'user.courses_assigned' => 'تعيين دورات لمدرّس',
            'course.created' => 'إنشاء دورة',
            'course.published' => 'نشر دورة',
            'course.unpublished' => 'إلغاء نشر دورة',
            'course.deleted' => 'حذف دورة',
            'lesson.video_replaced' => 'رفع/استبدال فيديو درس',
            'certificate_request.status_changed' => 'تغيير حالة طلب شهادة',
            'certificate.issued' => 'تسجيل شهادة للتحقق',
            'certificate.revoked' => 'إلغاء شهادة',
            'certificate.restored' => 'إعادة تفعيل شهادة',
            'settings.updated' => 'تعديل الإعدادات',
            'builder.saved' => 'نشر تصميم صفحة بالمحرّر',
            'system.migrated' => 'تحديث قاعدة البيانات',
            'system.optimized' => 'تحسين الموقع',
            'theme.applied' => 'تطبيق ثيم',
            'order.created' => 'طلب جديد في المتجر',
            'order.receipt_uploaded' => 'رفع وصل دفع',
            'center.created' => 'إضافة مركز',
            'center.updated' => 'تعديل مركز',
            'center.deleted' => 'حذف مركز',
            'order.status_changed' => 'تغيير حالة طلب متجر',
            'review.approved' => 'اعتماد مراجعة',
        ][$action] ?? $action;
    }
}
