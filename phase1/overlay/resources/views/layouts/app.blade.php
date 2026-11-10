<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>@yield('title', 'Aadi Marketing OS')</title><style>
:root{font-family:system-ui,sans-serif;color:#192537;background:#f6f8fb}body{margin:0}header{background:#10253f;color:white;padding:15px 24px;display:flex;align-items:center;gap:24px}header a{color:white}main{max-width:980px;margin:30px auto;padding:0 20px}.card{background:white;border:1px solid #e0e5ed;border-radius:12px;padding:20px;margin:16px 0}label{display:block;margin:12px 0 4px}input,textarea{width:100%;box-sizing:border-box;padding:10px;border:1px solid #bec8d7;border-radius:5px}button,.button{background:#145ad5;color:white;border:0;border-radius:6px;padding:10px 15px;cursor:pointer;text-decoration:none;display:inline-block}.muted{color:#68768b}nav{flex:1}ul.errors{color:#b01c36}
</style></head><body><header><strong>Aadi Marketing OS</strong><nav><a href="{{ route('dashboard') }}">Dashboard</a> &nbsp; <a href="{{ route('brands.index') }}">Brands</a></nav>@auth<form action="{{ route('logout') }}" method="post">@csrf<button type="submit">Log out</button></form>@endauth</header>
<main>@if(session('status'))<p role="status">{{session('status')}}</p>@endif
@if($errors->any())<ul class="errors">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>@endif
@yield('content')</main></body></html>
