@extends('layouts.app')
@section('title', 'Dashboard — Aadi Marketing OS')
@section('content')
<h1>Marketing operations</h1><p class="muted">Four-brand operations hub. Publishing requires verified account permissions and approved content.</p>
<h2>Authorized brands</h2>
@forelse($brands as $brand)<div class="card"><h3><a href="{{ route('brands.show', $brand) }}">{{ $brand->name }}</a></h3><p>{{ $brand->brand_config['objective'] ?? 'No objective configured' }}</p><span class="muted">{{ $brand->is_active ? 'Active' : 'Inactive' }}</span></div>
@empty<p>No brands are assigned to your account.</p>@endforelse
@endsection
