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
<div class="card"><h2>Manual draft — works without AI or desktop worker</h2>
<p class="muted">Create an original draft immediately. No source or provider access required. Admin review and factual-evidence checks are mandatory before publication.</p>
<form data-action="/content/manual" id="manualDraftForm">
<label>Title <input name="title" maxlength="255" required></label>
<label>Topic <input name="topic" maxlength="255" required></label>
<label>Body <textarea name="body" minlength="40" maxlength="12000" rows="7" required></textarea></label>
<label>Platform <select name="platform"><option>linkedin</option><option>facebook</option><option>instagram</option><option>youtube</option><option>pinterest</option><option>threads</option><option>tiktok</option></select></label>
<label>Language <input name="language" value="en" required maxlength="12"></label>
<label>Content type <select name="content_type"><option>educational_post</option><option>text</option><option>image_post</option><option>visual_post</option><option>carousel_outline</option><option>blog_outline</option></select></label>
<label>Hashtags, comma-separated <input name="hashtags" placeholder="Marketing, ITTips"></label>
<button type="submit">Save draft for admin review</button>
</form></div>
<div class="card"><h2>Generate draft</h2>
<form data-action="/content/generate" id="generateForm">
<label>Topic<input name="topic" required maxlength="255"></label>
<label>Platform<select name="platform"><option>linkedin</option><option>facebook</option><option>instagram</option><option>youtube</option><option>pinterest</option><option>threads</option><option>tiktok</option></select></label>
<label>Language<input name="language" value="en" maxlength="12"></label>
<label>Content type<input name="content_type" value="educational_post" maxlength="48"></label>
<label>Verified research IDs, comma-separated (leave blank for non-factual educational content)<input name="source_ids" placeholder="1, 2"></label>
<button type="submit">Queue content draft</button></form></div>
@endif
@if($canEdit)
<div class="card"><h2>Research verification workflow</h2>
<p class="muted">Only approved public sources can be fetched. Employment/immigration sources require official Tier A verification by admin.</p>
<form id="sourceApproveForm" data-job-action="sourceApprove">
<label>Source ID<input type="number" name="source_id" min="1" required></label>
<button type="submit">Approve source (admin only)</button></form>
<form id="sourceFetchForm" data-job-action="sourceFetch">
<label>Approved source ID<input type="number" name="source_id" min="1" required></label>
<button type="submit">Queue source fetch</button></form>
<form id="researchVerifyForm" data-job-action="researchVerify">
<label>Research item ID<input type="number" name="item_id" min="1" required></label>
<button type="submit">Verify evidence after reading original</button></form></div>
<div class="card"><h2>Instant manual-ready package</h2>
<p class="muted">For approved content only: prepare a copyable native-platform post without AI, OAuth or the desktop worker. It never publishes automatically.</p>
<form data-job-action="manualNow">
<label>Approved content ID <input name="content_id" type="number" min="1" required></label>
<label>Platform <select name="platform"><option>linkedin</option><option>facebook</option><option>instagram</option><option>youtube</option><option>pinterest</option><option>threads</option><option>tiktok</option></select></label>
<button type="submit">Prepare manual publishing package now</button>
</form></div>
<div class="card"><h2>Content approval and creative rendering</h2>
<form data-job-action="contentApprove">
<label>Draft content ID<input type="number" name="content_id" min="1" required></label>
<button type="submit">Approve draft after review</button></form>
<form data-job-action="contentRender">
<label>Approved content ID<input type="number" name="content_id" min="1" required></label>
<button type="submit">Generate creative assets</button></form></div>
<div class="card"><h2>Schedule or prepare manual publishing</h2>
<form data-job-action="contentSchedule">
<label>Approved content ID<input name="content_id" type="number" min="1" required></label>
<label>Platform<select name="platform"><option>linkedin</option><option>facebook</option><option>instagram</option><option>youtube</option><option>pinterest</option><option>threads</option><option>tiktok</option></select></label>
<label>Social account ID (leave blank to prepare manual package)<input name="social_account_id" type="number" min="1"></label>
<label>Publish at (your browser time zone)<input name="scheduled_at" type="datetime-local" required></label>
<button type="submit">Queue / manual-ready package</button></form>
<form data-job-action="manualPackage">
<label>Manual-ready publication ID<input name="publication_id" type="number" min="1" required></label>
<button type="submit">Show copyable manual package</button></form>
<pre id="manualPackage" style="white-space:pre-wrap;overflow-wrap:anywhere"></pre>
</div>
<div class="card"><h2>Import verified analytics snapshot</h2>
<form data-job-action="analyticsImport">
<label>Published job ID<input name="publishing_job_id" type="number" min="1" required></label>
<label>Metric name<input name="metric_name" required maxlength="70" placeholder="impressions"></label>
<label>Value<input name="metric_value" type="number" step="any" min="0"></label>
<label>Metric definition<input name="metric_definition" required maxlength="1000" placeholder="Cumulative post impressions"></label>
<label>Captured at<input name="captured_at" type="datetime-local" required></label>
<button type="submit">Record measured value</button></form></div>
@endif
@if(auth()->user()->isAdmin())
<div class="card"><h2>LinkedIn organization connection</h2>
<p class="muted">Requires official app access and an organization you administer. OAuth never grants publication approval automatically.</p>
<form method="POST" action="{{ url('/brands/'.$brand->id.'/linkedin/connect') }}">@csrf
<label>LinkedIn organization numeric ID<input name="organization_id" required pattern="[1-9][0-9]*"></label>
<button type="submit">Connect through official LinkedIn OAuth</button></form>
<form data-job-action="accountApprove">
<label>Connected social account ID<input name="account_id" type="number" min="1" required></label>
<label>API review outcome<select name="approved"><option value="1">Confirmed approved</option><option value="0">Revoke approval</option></select></label>
<label>Independent app and organization access verification (min 30 chars)
<textarea name="verification_note" minlength="30" maxlength="1500" required></textarea></label>
<button type="submit">Record approval decision</button></form></div>
@endif
<div class="card"><h2>Research</h2><button type="button" data-load="sources">Sources</button> <button type="button" data-load="research">Research items</button>
<pre id="sources" style="white-space:pre-wrap;overflow-wrap:anywhere"></pre><pre id="research" style="white-space:pre-wrap;overflow-wrap:anywhere"></pre></div>
<div class="card"><h2>Content review and calendar</h2><button type="button" data-load="content">Refresh drafts</button> <button type="button" data-load="calendar">Calendar</button>
<pre id="content" style="white-space:pre-wrap;overflow-wrap:anywhere"></pre><pre id="calendar" style="white-space:pre-wrap;overflow-wrap:anywhere"></pre></div>
<div class="card"><h2>Reports and social connectors</h2><button type="button" data-load="reports/weekly" data-target="report">Weekly report</button> <button type="button" data-load="social-accounts" data-target="accounts">Connected accounts</button>
<pre id="report" style="white-space:pre-wrap;overflow-wrap:anywhere"></pre><pre id="accounts" style="white-space:pre-wrap;overflow-wrap:anywhere"></pre></div>
<div class="card"><h2>Publishing reconciliation (admin only)</h2>
<p class="muted">Provider timeouts must never auto-repost. Verify the result in the official account before resolving.</p>
<button type="button" data-load="publishing/reconciliation" data-target="reconciliation">Show uncertain posts</button>
<pre id="reconciliation" style="white-space:pre-wrap;overflow-wrap:anywhere"></pre>
@if(auth()->user()->isAdmin())
<form id="reconciliationForm">
<label>Publication ID<input name="publication_id" required type="number" min="1"></label>
<label>Verified outcome<select name="resolution"><option value="confirmed_published">Published (with platform post ID)</option><option value="confirmed_not_published">Not published (manual-ready only)</option></select></label>
<label>Provider post ID<input name="platform_post_id" maxlength="255"></label>
<label>Official permalink (optional)<input name="permalink" type="url"></label>
<label>Independent verification record (min 25 chars)<textarea name="verification_note" required minlength="25" maxlength="1800"></textarea></label>
<button type="submit">Record verified outcome</button></form>
@endif</div>
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
    if (form.id==='manualDraftForm')data.hashtags=String(data.hashtags||'').split(',').map(s=>s.trim().replace(/^#/,'')).filter(Boolean);
    try { const response=await call(form.dataset.action,data);feedback.textContent=JSON.stringify(response);load('overview','overview'); }
    catch(err){ feedback.textContent=err.message; }
  }));
  const jobRoutes={
    sourceApprove:d=>'/sources/'+d.source_id+'/approve',
    sourceFetch:d=>'/sources/'+d.source_id+'/fetch',
    researchVerify:d=>'/research/'+d.item_id+'/verify',
    contentApprove:d=>'/content/'+d.content_id+'/approve',
    contentRender:d=>'/content/'+d.content_id+'/render',
    contentSchedule:d=>'/content/'+d.content_id+'/schedule',
    manualNow:d=>'/content/'+d.content_id+'/manual-ready',
    manualPackage:d=>'/manual-package/'+d.publication_id,
    analyticsImport:d=>'/analytics/import',
    accountApprove:d=>'/social-accounts/'+d.account_id+'/approval',
  };
  document.querySelectorAll('form[data-job-action]').forEach(form=>form.addEventListener('submit',async e=>{
    e.preventDefault();const action=form.dataset.jobAction;
    const data=Object.fromEntries(new FormData(form).entries());
    const path=jobRoutes[action](data);
    // Server receives only fields in the explicit route contract.
    delete data.source_id;delete data.item_id;delete data.content_id;
    delete data.publication_id;delete data.account_id;
    if(action==='contentSchedule'){
      if(data.scheduled_at)data.scheduled_at=new Date(data.scheduled_at).toISOString();
      if(!data.social_account_id)delete data.social_account_id;
    }
    if(action==='analyticsImport'){
      if(!data.metric_value)delete data.metric_value;
      if(data.captured_at)data.captured_at=new Date(data.captured_at).toISOString();
    }
    try{
      const result=await call(path,action==='manualPackage'?undefined:(Object.keys(data).length?data:{}));
      feedback.textContent=JSON.stringify(result);
      if(action==='manualPackage')document.getElementById('manualPackage').textContent=JSON.stringify(result,null,2);
      load('overview','overview');
    }catch(error){feedback.textContent=error.message;}
  }));
  const reconciliationForm=document.getElementById('reconciliationForm');
  if(reconciliationForm)reconciliationForm.addEventListener('submit',async e=>{
    e.preventDefault();const data=Object.fromEntries(new FormData(reconciliationForm).entries());
    const id=data.publication_id;delete data.publication_id;
    try { const result=await call('/publishing/'+encodeURIComponent(id)+'/reconcile',data);
      feedback.textContent=JSON.stringify(result);load('publishing/reconciliation','reconciliation'); }
    catch(err){feedback.textContent=err.message;}
  });
  load('overview','overview');load('content','content');load('calendar','calendar');
})();
</script>
@endsection
