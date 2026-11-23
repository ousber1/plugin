@extends('layouts.admin')
@section('title', 'Équipe')
@section('content')
<div class="page-head"><h1>Équipe & rôles</h1><a class="btn btn-sm" href="{{ route('admin.staff.create') }}">+ Ajouter</a></div>
<div class="card table-wrap"><table><thead><tr><th>Nom</th><th>E-mail</th><th>Rôles</th><th>Actif</th><th>Dernière connexion</th><th></th></tr></thead><tbody>
@foreach($users as $u)<tr><td>{{ $u->name }}</td><td>{{ $u->email }}</td><td>@foreach($u->roles as $r)<span class="pill">{{ __('role.'.$r->name) }}</span> @endforeach</td><td>{{ $u->is_active ? '✔' : '—' }}</td><td>{{ d($u->last_login_at, true) }}</td><td><a class="btn btn-sm btn-ghost" href="{{ route('admin.staff.edit', $u) }}">Modifier</a></td></tr>@endforeach
</tbody></table></div>
<div class="card"><h3>Permissions par rôle</h3><table class="small"><tbody>@foreach($roles as $r)<tr><th>{{ __('role.'.$r->name) }}</th><td>{{ $r->name === 'owner' ? 'Toutes les permissions' : $r->permissions->pluck('name')->implode(', ') }}</td></tr>@endforeach</tbody></table>
<p class="small muted">Chiffre d'affaires, marges, dépenses et paramètres sont réservés aux rôles disposant de finance.view / settings.manage (propriétaire, comptabilité).</p></div>
@endsection
