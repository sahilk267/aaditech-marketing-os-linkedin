@extends('layouts.app')
@section('content')
<h1>{{ $brand->exists ? 'Edit brand' : 'Create brand' }}</h1>
<form class="card" method="post" action="{{ $brand->exists ? route('brands.update', $brand) : route('brands.store') }}">@csrf
@if($brand->exists)@method('PUT')@endif
<label>Name</label><input name="name" value="{{ old('name',$brand->name) }}" maxlength="120" required>
<label>Slug</label><input name="slug" value="{{ old('slug',$brand->slug) }}" maxlength="120" required>
<label>Website URL</label><input name="website_url" type="url" value="{{ old('website_url',$brand->website_url) }}">
<label>Description</label><textarea name="description">{{ old('description',$brand->description) }}</textarea>
<label>Objective</label><input name="objective" value="{{ old('objective',$brand->brand_config['objective'] ?? '') }}" required>
<label>Call to action</label><input name="cta" value="{{ old('cta',$brand->brand_config['cta'] ?? '') }}" required>
<label>Topics (one per line)</label><textarea name="topics_text">{{ old('topics_text',implode("\n",$brand->brand_config['topics'] ?? [])) }}</textarea>
<label>Audiences (one per line)</label><textarea name="audience_text">{{ old('audience_text',implode("\n",$brand->brand_config['audience'] ?? [])) }}</textarea>
<label>Prohibited claims (one per line; existing restrictions cannot be removed here)</label><textarea name="prohibited_claims_text">{{ old('prohibited_claims_text',implode("\n",$brand->brand_config['prohibited_claims'] ?? [])) }}</textarea>
<input type="hidden" name="is_active" value="0"><label><input type="checkbox" name="is_active" value="1" style="width:auto" @checked(old('is_active',$brand->is_active ?? true))> Active</label>
<p class="muted">Verified catalogs, source rules and high-risk evidence settings are preserved on edit.</p>
<button type="submit">Save</button></form>
@endsection
