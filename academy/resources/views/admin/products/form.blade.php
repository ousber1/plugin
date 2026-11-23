@extends('layouts.admin')
@section('title', 'منتج')
@section('content')
@php $money = fn ($c) => $c ? $c / 100 : ''; @endphp
<div class="page-head"><h1>{{ $item->exists ? 'تعديل منتج' : 'منتج جديد' }}</h1>
    @if ($item->exists && $item->status === 'published')<a class="btn btn--ghost" href="{{ route('store.show', $item) }}" target="_blank"><x-icon name="eye" size="18"/> عرض في المتجر</a>@endif</div>
<form method="POST" enctype="multipart/form-data" action="{{ $item->exists ? route('admin.products.update', $item) : route('admin.products.store') }}" class="product-form">
    @csrf @if ($item->exists) @method('PUT') @endif
    <div class="dash-cols">
        <div class="stack">
            <section class="card card--pad">
                <h2 class="card__title">المعلومات الأساسية</h2>
                <x-admin.tr name="title" label="الاسم" :value="$item->title" required/>
                <x-admin.tr name="subtitle" label="وصف قصير (يظهر بعد الاسم)" :value="$item->subtitle" hint="مثال: التجربة الافتراضية للمنتجات"/>
                <x-admin.tr name="description" label="الوصف الكامل" :value="$item->description" type="textarea" rich :rows="8"/>
                <x-admin.tr name="features" label="ما سيحصل عليه المشتري" :value="$item->features" type="list" :rows="4"/>
                <x-admin.tr name="requirements" label="المتطلبات" :value="$item->requirements" type="list" :rows="3"/>
            </section>
            <section class="card card--pad">
                <h2 class="card__title">الصور والملف</h2>
                <div class="form-grid">
                    <div class="field"><label for="cover">صورة الغلاف (16:9)</label><input class="input" type="file" id="cover" name="cover" accept="image/*">
                        @if ($item->cover_path)<img src="{{ $item->coverUrl() }}" alt="" style="max-height:90px;border-radius:8px;margin-top:6px">@endif</div>
                    <div class="field"><label for="file">الملف الرقمي (خاص، يُحمَّل بعد الدفع) @if ($item->original_name)<span class="muted small">— {{ $item->original_name }}</span>@endif</label><input class="input" type="file" id="file" name="file">
                        <p class="hint">ZIP أو PDF أو EPUB… اتركه فارغًا لمنتج يُسلَّم يدويًا.</p></div>
                </div>
                <div class="field"><label for="gallery_add">صور المعاينة (حتى 12)</label><input class="input" type="file" id="gallery_add" name="gallery_add[]" accept="image/*" multiple>
                    @if ($item->gallery)
                        <div class="gallery-admin">@foreach ($item->gallery as $g)<label><img src="{{ \App\Support\Media::url($g) }}" alt=""><span class="check small"><input type="checkbox" name="gallery_remove[]" value="{{ $g }}"> حذف</span></label>@endforeach</div>
                    @endif</div>
            </section>
            <section class="card card--pad">
                <h2 class="card__title">سجل التحديثات</h2>
                <div data-repeater>
                    <div data-list>@foreach (old('changelog', $item->changelogEntries()) as $i => $e)@include('admin.products._log', ['i' => $i, 'e' => $e])@endforeach</div>
                    <template>@include('admin.products._log', ['i' => '__INDEX__', 'e' => []])</template>
                    <button class="btn btn--outline btn--sm" type="button" data-add><x-icon name="plus" size="16"/> إصدار جديد</button>
                </div>
            </section>
            <section class="card card--pad">
                <h2 class="card__title">محركات البحث (اختياري)</h2>
                <x-admin.tr name="seo_title" label="عنوان SEO" :value="$item->seo_title"/>
                <x-admin.tr name="seo_description" label="وصف SEO" :value="$item->seo_description" type="textarea" :rows="2"/>
            </section>
        </div>
        <div class="stack">
            <section class="card card--pad">
                <h2 class="card__title">النشر</h2>
                <div class="field"><label for="status">الحالة</label><select class="select" id="status" name="status"><option value="draft" @selected(old('status', $item->status) === 'draft')>مسودة</option><option value="published" @selected(old('status', $item->status) === 'published')>منشور</option></select></div>
                <div class="field"><label for="category_id">التصنيف</label><select class="select" id="category_id" name="category_id"><option value="">— بدون —</option>@foreach ($categories as $c)<option value="{{ $c->id }}" @selected(old('category_id', $item->category_id) == $c->id)>{{ $c->tr('name') }}</option>@endforeach</select>
                    <p class="hint"><a href="{{ route('admin.categories.create', ['type' => 'product']) }}">إضافة تصنيف</a> (مثل: إضافات ووردبريس، قوالب، كتب).</p></div>
                <label class="check field"><input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $item->is_featured))> منتج مميز (يظهر أولًا)</label>
                <div class="field"><label for="sort_order">الترتيب</label><input class="input" type="number" min="0" id="sort_order" name="sort_order" value="{{ old('sort_order', $item->sort_order ?? 0) }}"></div>
                <button class="btn btn--primary btn--lg btn--block" type="submit">حفظ المنتج</button>
            </section>
            <section class="card card--pad">
                <h2 class="card__title">السعر والترخيص</h2>
                <div class="form-grid">
                    <div class="field"><label for="price">السعر (درهم) <span class="req">*</span></label><input class="input ltr" type="number" min="1" step="0.01" id="price" name="price" value="{{ old('price', $money($item->price_cents)) }}" required></div>
                    <div class="field"><label for="compare_price">السعر قبل الخصم</label><input class="input ltr" type="number" min="0" step="0.01" id="compare_price" name="compare_price" value="{{ old('compare_price', $money($item->compare_price_cents)) }}"></div>
                    <div class="field"><label for="price_usd">السعر بالدولار</label><input class="input ltr" type="number" min="0" step="0.01" id="price_usd" name="price_usd" value="{{ old('price_usd', $money($item->price_usd_cents)) }}" placeholder="تلقائي"></div>
                    <div class="field"><label for="compare_price_usd">قبل الخصم ($)</label><input class="input ltr" type="number" min="0" step="0.01" id="compare_price_usd" name="compare_price_usd" value="{{ old('compare_price_usd', $money($item->compare_price_usd_cents)) }}"></div>
                </div>
                <p class="hint">اترك سعر الدولار فارغًا ليُحسب تلقائيًا من سعر الصرف في إعدادات المتجر.</p>
                <div class="form-grid">
                    <div class="field"><label for="billing">نوع البيع</label><select class="select" id="billing" name="billing">@foreach (['once' => 'شراء مرة واحدة', 'year' => 'اشتراك سنوي', 'month' => 'اشتراك شهري'] as $k => $l)<option value="{{ $k }}" @selected(old('billing', $item->billing) === $k)>{{ $l }}</option>@endforeach</select></div>
                    <div class="field"><label for="license_sites">عدد المواقع لكل ترخيص</label><input class="input" type="number" min="1" id="license_sites" name="license_sites" value="{{ old('license_sites', $item->license_sites ?? 1) }}"></div>
                </div>
                <div class="field"><label for="demo_url">رابط التجربة المباشرة (Demo)</label><input class="input ltr" type="url" id="demo_url" name="demo_url" value="{{ old('demo_url', $item->demo_url) }}" placeholder="https://"></div>
            </section>
            <section class="card card--pad">
                <h2 class="card__title">معلومات تقنية</h2>
                <div class="form-grid">
                    <div class="field"><label for="version">الإصدار</label><input class="input ltr" id="version" name="version" value="{{ old('version', $item->version) }}" placeholder="1.2.0"></div>
                    <div class="field"><label for="updated_on">آخر تحديث</label><input class="input ltr" type="date" id="updated_on" name="updated_on" value="{{ old('updated_on', $item->updated_on?->format('Y-m-d')) }}"></div>
                    <div class="field"><label for="requires">يتطلب</label><input class="input ltr" id="requires" name="requires" value="{{ old('requires', $item->requires) }}" placeholder="WordPress 6.0+"></div>
                    <div class="field"><label for="tested_up_to">مختبر حتى</label><input class="input ltr" id="tested_up_to" name="tested_up_to" value="{{ old('tested_up_to', $item->tested_up_to) }}" placeholder="6.8"></div>
                    <div class="field"><label for="file_format">الصيغة</label><input class="input ltr" id="file_format" name="file_format" value="{{ old('file_format', $item->file_format) }}" placeholder="ZIP"></div>
                    <div class="field"><label for="files_count">عدد الملفات</label><input class="input" type="number" min="0" id="files_count" name="files_count" value="{{ old('files_count', $item->files_count) }}"></div>
                </div>
            </section>
        </div>
    </div>
</form>
@endsection
