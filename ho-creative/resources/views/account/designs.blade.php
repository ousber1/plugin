@extends('account._layout')
@section('title', __('Mes créations'))
@section('account')
<h1>{{ __('Mes créations') }}</h1>
<div class="card table-wrap"><table>
  <thead><tr><th>{{ __('Création') }}</th><th>{{ __('Format') }}</th><th>{{ __('Modifiée le') }}</th><th></th></tr></thead>
  <tbody>@forelse($designs as $dsg)
    <tr><td>{{ $dsg->name ?: $dsg->product?->t('name') ?: __('Création') }}</td><td>{{ (float) $dsg->width_mm }} × {{ (float) $dsg->height_mm }} mm</td><td>{{ d($dsg->updated_at, true) }}</td>
      <td class="flex"><a class="btn btn-sm btn-ghost" href="{{ route('editor', ['design' => $dsg->uuid]) }}">{{ __('Modifier') }}</a>@if(! empty($dsg->exports['pdf']))<a class="btn btn-sm btn-ghost" href="{{ route('files.design', [$dsg, 'pdf']) }}" target="_blank">PDF</a>@endif</td></tr>
  @empty<tr><td colspan="4" class="muted">{{ __('Aucune création enregistrée.') }}</td></tr>@endforelse</tbody>
</table>{{ $designs->links('partials.pagination') }}</div>
@endsection
