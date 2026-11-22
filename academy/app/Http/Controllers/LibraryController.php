<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Resource;
use App\Services\AccessService;
use App\Services\MediaService;
use Illuminate\Http\Request;

class LibraryController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['q' => ['nullable', 'string', 'max:120'], 'category' => ['nullable', 'string', 'max:120'], 'access' => ['nullable', 'in:public,enrolled']]);
        $categories = Category::ofType('resource')->where('is_active', true)->get();
        $category = $request->filled('category') ? $categories->firstWhere('slug', $request->input('category')) : null;

        $resources = Resource::published()->with(['category', 'course'])
            ->searchTranslated(['title', 'description'], $request->input('q'))
            ->when($request->filled('category'), fn ($q) => $category ? $q->where('category_id', $category->id) : $q->whereRaw('1 = 0'))
            ->when($request->input('access'), fn ($q, $a) => $q->where('access', $a))
            ->latest()->paginate(12)->withQueryString();

        $user = $request->user();
        $enrolledIds = $user ? $user->enrollments()->pluck('course_id')->all() : [];

        return view('library.index', compact('resources', 'categories', 'category', 'enrolledIds'));
    }

    public function download(Request $request, Resource $resource, AccessService $access, MediaService $media)
    {
        abort_unless($resource->path, 404);
        if (! $access->canDownloadResource($request->user(), $resource)) {
            abort_if($resource->status !== 'published', 404);
            if (! $request->user()) {
                return redirect()->route('login', ['intended' => route('library.index')])->with('info', __('هذا المورد متاح للطلاب المسجلين في الدورة المرتبطة به. سجّل الدخول أولًا.'));
            }
            abort(403, __('هذا المورد متاح فقط للطلاب المسجلين في الدورة المرتبطة به.'));
        }
        $resource->increment('downloads');

        return $media->respond($resource->disk, $resource->path, $resource->original_name, $resource->mime);
    }
}
