@extends('layouts.app')
@section('content')<h1>{{ $brand->name }}</h1><p>{{ $brand->description }}</p>
<div class="card"><p><strong>Slug:</strong> {{ $brand->slug }}</p>
<p><strong>Objective:</strong> {{ $brand->brand_config['objective'] ?? 'Unspecified' }}</p>
<p><strong>CTA:</strong> {{ $brand->brand_config['cta'] ?? 'Unspecified' }}</p>
<p><strong>Status:</strong> {{ $brand->is_active ? 'Active' : 'Inactive' }}</p>
@if($brand->website_url)<p><a href="{{ $brand->website_url }}" rel="noopener noreferrer">Brand website</a></p>@endif</div>
@can('update', $brand)<a class="button" href="{{route('brands.edit',$brand)}}">Edit brand</a>@endcan
@endsection
