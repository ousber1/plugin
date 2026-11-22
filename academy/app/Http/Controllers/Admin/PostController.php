<?php

namespace App\Http\Controllers\Admin;

use App\Models\Category;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PostController extends AdminController
{
    public function index(Request $request)
    {
        $posts = Post::with(['category', 'author:id,name'])
            ->searchTranslated(['title', 'slug'], $request->input('q'))
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->latest()->paginate(20)->withQueryString();

        return view('admin.posts.index', compact('posts'));
    }

    public function create()
    {
        return view('admin.posts.form', ['post' => new Post(['status' => 'draft']), 'categories' => Category::ofType('post')->get()]);
    }

    public function store(Request $request)
    {
        $post = new Post(['author_id' => $request->user()->id]);
        $this->save($post, $request);

        return redirect()->route('admin.posts.edit', $post)->with('success', 'تم حفظ المقال.');
    }

    public function edit(Post $post)
    {
        return view('admin.posts.form', ['post' => $post, 'categories' => Category::ofType('post')->get()]);
    }

    public function update(Request $request, Post $post)
    {
        $this->save($post, $request);

        return back()->with('success', 'تم حفظ المقال.');
    }

    public function destroy(Post $post)
    {
        if ($post->cover_path) {
            Storage::disk('public')->delete($post->cover_path);
        }
        $post->delete();

        return redirect()->route('admin.posts.index')->with('success', 'تم حذف المقال.');
    }

    private function save(Post $post, Request $request): void
    {
        $data = $request->validate([
            ...$this->trRules('title', true, 190),
            ...$this->trRules('excerpt', false, 400),
            ...$this->trRules('body', false, 200000),
            ...$this->trRules('seo_title', false, 190),
            ...$this->trRules('seo_description', false, 320),
            'slug' => ['nullable', 'alpha_dash', 'max:120', Rule::unique('posts')->ignore($post->id)],
            'category_id' => ['nullable', Rule::exists('categories', 'id')->where('type', 'post')],
            'status' => ['required', 'in:draft,published'],
            'published_at' => ['nullable', 'date'],
            'cover' => ['nullable', 'image', 'mimes:'.config('academy.images.mimes'), 'max:'.config('academy.images.max_kb')],
        ]);
        $post->fill([
            'title' => $this->trInput($request, 'title'), 'excerpt' => $this->trInput($request, 'excerpt'),
            'body' => $this->trInput($request, 'body', 'rich'), 'seo_title' => $this->trInput($request, 'seo_title'),
            'seo_description' => $this->trInput($request, 'seo_description'), 'category_id' => $data['category_id'] ?? null,
            'status' => $data['status'],
            'published_at' => $data['published_at'] ?? ($data['status'] === 'published' ? ($post->published_at ?? now()) : $post->published_at),
        ]);
        $t = $data['title'];
        $post->slug = ($data['slug'] ?? null) ?: ($post->slug ?: $this->uniqueSlug(Post::class, ($t['en'] ?? '') ?: (($t['fr'] ?? '') ?: $t['ar']), $post->id));
        if ($request->hasFile('cover')) {
            if ($post->cover_path) {
                Storage::disk('public')->delete($post->cover_path);
            }
            $post->cover_path = $request->file('cover')->store('posts', 'public');
        }
        $post->save();
    }
}
