@extends('layouts.admin')
@section('title', 'Rapports')
@section('content')
<div class="page-head"><h1>Rapports</h1><a class="btn btn-sm" href="{{ route('admin.reports.csv', ['report' => $report, 'from' => $from->toDateString(), 'to' => $to->toDateString()]) }}">⬇ Export CSV</a></div>
<div class="tabs">@foreach(\App\Services\ReportService::REPORTS as $k => $l)<a href="?report={{ $k }}&from={{ $from->toDateString() }}&to={{ $to->toDateString() }}" @class(['active' => $k === $report])>{{ $l }}</a>@endforeach</div>
<form class="toolbar"><input type="hidden" name="report" value="{{ $report }}"><div class="field"><label class="small">Du</label><input type="date" name="from" value="{{ $from->toDateString() }}"></div><div class="field"><label class="small">au</label><input type="date" name="to" value="{{ $to->toDateString() }}"></div><button class="btn btn-sm">Appliquer</button></form>
<div class="alert alert-info small"><strong>Calcul :</strong> {{ $data['definition'] }}</div>
<div class="card table-wrap"><table><thead><tr>@foreach($data['columns'] as $c)<th>{{ $c }}</th>@endforeach</tr></thead><tbody>
@forelse($data['rows'] as $row)<tr>@foreach($row as $i => $cell)<td @class(['num' => is_numeric($cell)])>{{ $cell }}</td>@endforeach</tr>@empty<tr><td colspan="{{ count($data['columns']) }}" class="muted">Aucune donnée sur la période.</td></tr>@endforelse
</tbody></table></div>
@endsection
