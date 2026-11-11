<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use Illuminate\Http\Request;

class MarketingDashboardController extends Controller
{
    public function __invoke(Request $request,Brand $brand)
    {
        if (!$request->user()?->hasBrandAccess($brand)) abort(403);
        return view('marketing.index',['brand'=>$brand,'canEdit'=>in_array($request->user()->role,['admin','editor'],true)]);
    }
}
