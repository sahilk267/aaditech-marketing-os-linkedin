<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BrandController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Brand::class);
        $brands = Brand::query()->visibleTo($request->user())->orderBy('name')->paginate(20);
        return view('brands.index', compact('brands'));
    }

    public function show(Brand $brand): View
    {
        Gate::authorize('view', $brand);
        return view('brands.show', compact('brand'));
    }

    public function create(): View
    {
        Gate::authorize('create', Brand::class);
        return view('brands.form', ['brand'=>new Brand()]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Brand::class);
        $input = $this->validated($request);
        $brand = DB::transaction(fn (): Brand => Brand::create($input));
        return redirect()->route('brands.show', $brand)->with('status','Brand created.');
    }

    public function edit(Brand $brand): View
    {
        Gate::authorize('update', $brand);
        return view('brands.form', compact('brand'));
    }

    public function update(Request $request, Brand $brand): RedirectResponse
    {
        Gate::authorize('update', $brand);
        $input = $this->validated($request, $brand);
        DB::transaction(fn () => $brand->update($input));
        return redirect()->route('brands.show', $brand)->with('status','Brand updated.');
    }

    private function validated(Request $request, ?Brand $brand = null): array
    {
        $input = $request->validate([
            'name'=>['required','string','max:120'],
            'slug'=>['required','alpha_dash','max:120',Rule::unique('brands','slug')->ignore($brand?->getKey())],
            'website_url'=>['nullable','url','max:2048'],
            'description'=>['nullable','string','max:5000'],
            'objective'=>['required','string','max:1000'],
            'cta'=>['required','string','max:250'],
            'topics_text'=>['nullable','string','max:6000'],
            'audience_text'=>['nullable','string','max:6000'],
            'prohibited_claims_text'=>['nullable','string','max:8000'],
            'is_active'=>['required','boolean'],
        ]);
        $original = $brand?->brand_config ?? [];
        $submittedClaims = $this->lines($input['prohibited_claims_text'] ?? '', 50);
        $input['brand_config'] = array_replace($original, [
            'objective'=>$input['objective'],
            'cta'=>$input['cta'],
            'topics'=>$this->lines($input['topics_text'] ?? '', 40),
            'audience'=>$this->lines($input['audience_text'] ?? '', 30),
            // Existing prohibited claims cannot be silently removed through this basic editor.
            'prohibited_claims'=>array_values(array_unique(array_merge($original['prohibited_claims'] ?? [], $submittedClaims))),
        ]);
        unset($input['objective'], $input['cta'], $input['topics_text'], $input['audience_text'], $input['prohibited_claims_text']);
        return $input;
    }

    private function lines(string $text, int $max): array
    {
        $lines = preg_split('/\r?\n/', $text) ?: [];
        $lines = array_values(array_unique(array_filter(array_map('trim', $lines), static fn ($s) => $s !== '')));
        abort_if(count($lines) > $max || count(array_filter($lines, static fn ($s) => mb_strlen($s) > 250)) > 0, 422, 'Too many or too-long entries.');
        return $lines;
    }
}
