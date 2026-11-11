<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        abort_unless($request->user()->is_active, 403);
        $brands = Brand::query()->visibleTo($request->user())->orderBy('name')->get();
        return view('dashboard', compact('brands'));
    }
}
