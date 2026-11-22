@extends('layouts.admin')
@section('title', 'المنتجات')
@section('content')
<div class="page-head"><h1>منتجات المتجر الرقمي</h1><a class="btn btn--primary" href="{{ route('admin.products.create') }}"><x-icon name="plus" size="18"/> منتج جديد</a></div>
@unless (\App\Support\Settings::feature('store'))<div class="alert alert--info"><x-icon name="info"/> <span>المتجر معطّل حاليًا ولا يظهر للزوار. فعّله من <a href="{{ route('admin.settings.edit', 'features') }}">الإعدادات ← الوحدات</a>.</span></div>@endunless
<div class="table-wrap"><table class="table"><thead><tr><th>المنتج</th><th>السعر</th><th>الحالة</th><th></th></tr></thead><tbody>
@forelse ($items as $p)
    <tr><td dir="auto">{{ $p->tr('title') }}</td><td class="ltr">{{ $p->priceLabel() }}</td><td><x-admin.status :status="$p->status"/></td>
        <td class="row"><a class="btn btn--ghost btn--sm" href="{{ route('admin.products.edit', $p) }}">تعديل</a><form method="POST" action="{{ route('admin.products.destroy', $p) }}" data-confirm="حذف المنتج؟">@csrf @method('DELETE')<button class="btn btn--ghost btn--sm">حذف</button></form></td></tr>
@empty<tr><td colspan="4" class="muted" style="text-align:center;padding:24px">لا توجد منتجات.</td></tr>@endforelse
</tbody></table></div>
{{ $items->links() }}
@endsection
