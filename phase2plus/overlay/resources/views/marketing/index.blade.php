@extends('layouts.app')
@section('title', 'Marketing – '.$brand->name)
@section('content')
<h1>{{ $brand->name }} — Marketing</h1>
<p class="muted">Research → Draft → Human review → Media → Schedule → Official publish or manual package. Brand-scoped access is enforced server-side.</p>
<meta name="csrf-token" content="{{ csrf_token() }}">
<div class="card"><h2>Overview</h2><div id="overview" aria-live="polite">Loading…</div></div>
@if($canEdit)
<div class="card"><h2>Register source (admin approval required)</h2>
<form data-action="/sources" id="sourceForm">
<label>Public HTTPS source URL <input name="url" type="url" placeholder="https://example.org/feed" required></label>
<label>Type <select name="source_type"><option value="rss">RSS</option><option value="official_blog">Official blog</option><option value="public_page">Public page</option><option value="manual">Manual</option></select></label>
<label>Trust tier <select name="trust_tier"><option>A</option><option>B</option><option selected>C</option><option>D</option></select></label>
<button type="submit">Register source</button></form></div>
<div class="card"><h2>Generate draft</h2>
<form data-action="/content/generate" id="generateForm">
<label>Topic<input name="topic" required maxlength="255"></label>
<label>Platform<select name="platform"><option>linkedin</option><option>facebook</option><option>instagram</option><option>youtube</option><option>pinterest</option><option>threads</option><option>tiktok</option></select></label>
<label>Language<input name="language" value="en" maxlength="12"></label>
<label>Content type<input name="content_type" value="educational_post" maxlength="48"></label>
<label>Verified research IDs, comma-separated (leave blank for non-factual educational content)<input name="source_ids" placeholder="1, 2"></label>
<button type="submit">Queue content draft</button></form></div>
@endif
<div class="card"><h2>Research</h2><button type="button" data-load="sources">Sources</button> <button type="button" data-load="research">Research items</button>
<pre id="sources" style="white-space:pre-wrap;overflow-wrap:anywhere"></pre><pre id="research" style="white-space:pre-wrap;overflow-wrap:anywhere"></pre></div>
<div class="card"><h2>Content review and calendar</h2><button type="button" data-load="content">Refresh drafts</button> <button type="button" data-load="calendar">Calendar</button>
<pre id="content" style="white-space:pre-wrap;overflow-wrap:anywhere"></pre><pre id="calendar" style="white-space:pre-wrap;overflow-wrap:anywhere"></pre></div>
<div class="card"><h2>Reports and social connectors</h2><button type="button" data-load="reports/weekly" data-target="report">Weekly report</button> <button type="button" data-load="social-accounts" data-target="accounts">Connected accounts</button>
<pre id="report" style="white-space:pre-wrap;overflow-wrap:anywhere"></pre><pre id="accounts" style="white-space:pre-wrap;overflow-wrap:anywhere"></pre></div>
<p id="feedback" role="status" aria-live="polite"></p>
<script>
(() => {
  const prefix = @json('/api/v1/brands/'.$brand->id);
  const token = document.querySelector('meta[name="csrf-token"]').content;
  const feedback = document.getElementById('feedback');
  async function call(path, data) {
    const opts = { credentials: 'same-origin',headers:{'Accept':'application/json','X-CSRF-TOKEN':token}};
    if (data) { opts.method='POST'; opts.headers['Content-Type']='application/json';opts.body=JSON.stringify(data); }
    const res=await fetch(prefix+path,opts);
    const json=await res.json().catch(()=>({message:'Unexpected response'}));
    if (!res.ok) throw new Error(json.message||'Request failed '+res.status);
    return json;
  }
  async function load(path,target) {
    try { document.getElementById(target).textContent = JSON.stringify(await call('/'+path),null,2); }
    catch(e) {feedback.textContent=e.message;}
  }
  document.querySelectorAll('[data-load]').forEach(b=>b.addEventListener('click',()=>load(b.dataset.load,b.dataset.target||b.dataset.load)));
  document.querySelectorAll('form[data-action]').forEach(form=>form.addEventListener('submit',async e=>{
    e.preventDefault();const fd=new FormData(form);const data=Object.fromEntries(fd.entries());
    if (form.id==='generateForm')data.source_ids=String(data.source_ids||'').split(',').map(s=>Number(s.trim())).filter(Number.isInteger).filter(n=>n>0);
    try { const response=await call(form.dataset.action,data);feedback.textContent=JSON.stringify(response);load('overview','overview'); }
    catch(err){ feedback.textContent=err.message; }
  }));
  load('overview','overview');load('content','content');load('calendar','calendar');
})();
</script>
@endsection
