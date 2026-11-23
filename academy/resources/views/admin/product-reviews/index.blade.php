@extends('layouts.admin')
@section('title', 'تقييمات المنتجات')
@section('content')
<div class="page-head"><h1>تقييمات المنتجات</h1></div>
<p class="muted small">يستطيع التقييم فقط من اشترى المنتج. التقييمات الجديدة تحتاج اعتمادك قبل الظهور.</p>
<div class="table-wrap"><table class="table"><thead><tr><th>المنتج</th><th>الزبون</th><th>التقييم</th><th>التعليق</th><th>الحالة</th><th></th></tr></thead><tbody>
@forelse ($items as $r)
    <tr><td dir="auto">{{ $r->product?->tr('title') }}</td><td>{{ $r->user?->name }}</td><td>{{ str_repeat('★', $r->rating) }}</td><td class="small" dir="auto">{{ \Illuminate\Support\Str::limit($r->body, 140) }}</td>
        <td>@if ($r->is_approved)<span class="badge badge--green">معتمد</span>@else<span class="badge badge--amber">بانتظار</span>@endif</td>
        <td class="row">@unless ($r->is_approved)<form method="POST" action="{{ route('admin.product-reviews.approve', $r) }}">@csrf<button class="btn btn--primary btn--sm">اعتماد</button></form>@endunless
            <form method="POST" action="{{ route('admin.product-reviews.destroy', $r) }}" data-confirm="حذف المراجعة؟">@csrf @method('DELETE')<button class="btn btn--ghost btn--sm">حذف</button></form></td></tr>
@empty<tr><td colspan="6" class="muted" style="text-align:center;padding:24px">لا توجد تقييمات.</td></tr>@endforelse
</tbody></table></div>
{{ $items->links() }}
@endsection
