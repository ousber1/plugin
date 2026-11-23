<div class="repeater-item card card--pad" data-item style="flex-wrap:wrap">
    <div class="field" style="flex:0 1 120px"><label>الإصدار</label><input class="input ltr" name="changelog[{{ $i }}][version]" value="{{ $e['version'] ?? '' }}" placeholder="1.2.0"></div>
    <div class="field" style="flex:0 1 150px"><label>التاريخ</label><input class="input ltr" name="changelog[{{ $i }}][date]" value="{{ $e['date'] ?? '' }}" placeholder="2026-11-10"></div>
    <div class="field" style="flex:1 1 260px"><label>ما الجديد</label><textarea class="textarea" rows="2" name="changelog[{{ $i }}][notes]" dir="auto">{{ $e['notes'] ?? '' }}</textarea></div>
    <button class="btn btn--ghost btn--sm" type="button" data-remove style="align-self:center">حذف</button>
</div>
