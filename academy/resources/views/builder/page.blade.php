@extends('layouts.app')
@if (! empty($pageTitle))
    @section('title', $pageTitle)
@endif
@if (! empty($pageDescription))
    @section('description', $pageDescription)
@endif
@if (! empty($canonical))
    @section('canonical', $canonical)
@endif
@if (! empty($editing))
    @section('noindex', '1')
    @push('head')<style>.bk[data-block-id]{cursor:pointer}.bk[data-block-id]:hover{outline:2px dashed var(--indigo-500);outline-offset:-4px}.bk.is-selected{outline:3px solid var(--amber-600)!important;outline-offset:-5px}.bk--anim{opacity:1!important;transform:none!important}.wa-float,.cookie-bar{display:none!important}</style>@endpush
@endif
@section('content')
    @include('builder.render', ['blocks' => $blocks, 'editing' => $editing ?? false])
@endsection
