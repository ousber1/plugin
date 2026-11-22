<?php

namespace App\Http\Controllers\Admin;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategoryController extends AdminController
{
    private const TYPES = ['course' => 'تصنيفات الدورات', 'post' => 'تصنيفات المدونة', 'resource' => 'تصنيفات المكتبة'];

    public function index(Request $request)
    {
        $type = $this->type($request);

        return view('admin.categories.index', ['categories' => Category::ofType($type)->withCount(['courses', 'posts', 'resources'])->get(), 'type' => $type, 'types' => self::TYPES]);
    }

    public function create(Request $request)
    {
        return view('admin.categories.form', ['category' => new Category(['type' => $this->type($request), 'is_active' => true]), 'types' => self::TYPES]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $category = new Category(['type' => $data['type']]);
        $this->fill($category, $request, $data);
        $category->save();

        return redirect()->route('admin.categories.index', ['type' => $category->type])->with('success', 'تمت إضافة التصنيف.');
    }

    public function edit(Category $category)
    {
        return view('admin.categories.form', ['category' => $category, 'types' => self::TYPES]);
    }

    public function update(Request $request, Category $category)
    {
        $data = $this->validated($request, $category);
        $this->fill($category, $request, $data);
        $category->save();

        return redirect()->route('admin.categories.index', ['type' => $category->type])->with('success', 'تم حفظ التصنيف.');
    }

    public function destroy(Category $category)
    {
        $type = $category->type;
        $category->delete(); // related items keep existing with no category (nullOnDelete)

        return redirect()->route('admin.categories.index', ['type' => $type])->with('success', 'تم حذف التصنيف.');
    }

    private function type(Request $request): string
    {
        return array_key_exists($request->input('type'), self::TYPES) ? $request->input('type') : 'course';
    }

    private function validated(Request $request, ?Category $category = null): array
    {
        $type = $category?->type ?? $this->type($request);

        return $request->validate([
            ...$this->trRules('name', true, 120),
            ...$this->trRules('description', false, 500),
            'type' => [$category ? 'nullable' : 'required', Rule::in(array_keys(self::TYPES))],
            'slug' => ['nullable', 'alpha_dash', 'max:120', Rule::unique('categories')->where('type', $type)->ignore($category?->id)],
            'icon' => ['nullable', 'string', 'max:40', 'alpha_dash'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function fill(Category $category, Request $request, array $data): void
    {
        $category->fill([
            'name' => $this->trInput($request, 'name'),
            'description' => $this->trInput($request, 'description'),
            'icon' => $data['icon'] ?? null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => $request->boolean('is_active'),
        ]);
        $name = $data['name'];
        $source = ($name['en'] ?? '') ?: (($name['fr'] ?? '') ?: $name['ar']);
        $category->slug = ($data['slug'] ?? null) ?: ($category->slug ?: $this->uniqueCategorySlug($category->type, $source));
    }

    private function uniqueCategorySlug(string $type, string $source): string
    {
        $base = Str::slug(Str::ascii($source)) ?: 'category';
        $slug = $base;
        $i = 2;
        while (Category::where('type', $type)->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
