@extends('layouts.admin')
@section('title', 'Avis')
@section('content')
<div class="page-head"><h1>Avis clients</h1>
  <div class="flex">@foreach(['pending' => 'En attente', 'approved' => 'Publiés', 'rejected' => 'Refusés', 'all' => 'Tous'] as $k => $v)<a class="btn btn-sm {{ request('status', 'pending') === $k ? '' : 'btn-ghost' }}" href="?status={{ $k }}">{{ $v }}</a>@endforeach</div></div>
<p class="small muted">Les notes affichées sur la boutique sont calculées uniquement à partir des avis publiés. Seuls les clients ayant commandé le produit peuvent déposer un avis.</p>
@foreach($reviews as $r)
  <div class="card"><div class="flex between"><div><span class="stars">{{ str_repeat('★', $r->rating) }}</span> <strong>{{ $r->title }}</strong> – {{ $r->author_name }} · {{ $r->product->t('name') }} @if($r->is_demo)<span class="pill pill-demo">DÉMO</span>@endif</div><span class="pill">{{ $r->status }}</span></div>
    <p>{{ $r->body }}</p>
    <form method="post" action="{{ route('admin.reviews.moderate', $r) }}" class="flex">@csrf<button class="btn btn-sm" name="status" value="approved">Publier</button><button class="btn btn-sm btn-ghost" name="status" value="rejected">Refuser</button></form></div>
@endforeach
{{ $reviews->links('partials.pagination') }}
@endsection
