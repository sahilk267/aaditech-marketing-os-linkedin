@extends('layouts.app')
@section('content')<h1>Brands</h1>
@can('create', \App\Models\Brand::class)<p><a class="button" href="{{ route('brands.create') }}">Create brand</a></p>@endcan
@forelse($brands as $brand)<div class="card"><a href="{{ route('brands.show', $brand) }}">{{ $brand->name }}</a><p class="muted">{{ $brand->slug }}</p></div>@empty<p>No accessible brands.</p>@endforelse
{{ $brands->links() }}
@endsection
