@extends('layouts.admin')
@section('title', 'Production')
@section('content')
<div class="page-head"><h1>Production</h1><a class="btn btn-sm btn-ghost" href="{{ route('admin.production.list') }}">Vue liste</a></div>
@include('admin.production._filters')
<p class="small muted">Glissez une carte pour changer d'étape. Le serveur refuse l'impression tant que le BAT courant n'est pas approuvé ou que la commande n'est pas confirmée.</p>
<div class="kanban">
  @foreach($stages as $stage)
    <section class="kcol" data-stage="{{ $stage }}" aria-label="{{ __('stage.'.$stage) }}">
      <h3><span>{{ __('stage.'.$stage) }}</span><span>{{ count($jobs[$stage] ?? []) }}</span></h3>
      @foreach($jobs[$stage] ?? [] as $j)
        <article class="kcard @if($j->isOverdue()) overdue @endif @if(in_array($j->priority, ['urgent', 'high'])) urgent @endif" data-url="{{ route('admin.production.transition', $j) }}">
          <a href="{{ route('admin.production.show', $j) }}"><strong>{{ $j->number }}</strong></a> <span class="pill">{{ $j->priority }}</span>
          <div>{{ \Illuminate\Support\Str::limit($j->item->description, 70) }}</div>
          <div class="meta">{{ $j->order->number }} · {{ $j->order->customer?->displayName() }}</div>
          <div class="meta">📅 {{ d($j->due_at) }} @if($j->assignee)· 👤 {{ $j->assignee->name }}@endif @if($j->item->requires_proof)· BAT {{ __('proof_status.'.($j->item->proof_status === 'none' ? 'none' : $j->item->proof_status)) }}@endif</div>
        </article>
      @endforeach
    </section>
  @endforeach
</div>
@endsection
