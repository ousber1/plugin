<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Post;
use Illuminate\Http\Request;

class BlogController extends Controller
{
    public function index(Request $request, ?string $slug = null)
    {
        $request->validate(['q' => ['nullable', 'string', 'max:120']]);
        $categories = Category::ofType('post')->where('is_active', true)
            ->withCount(['posts' => fn ($q) => $q->published()])->get();
        $category = $slug ? $categories->firstWhere('slug', $slug) : null;
        abort_if($slug && ! $category, 404);

        $posts = Post::published()->with(['category', 'author:id,name'])
            ->searchTranslated(['title', 'excerpt', 'body'], $request->input('q'))
            ->when($category, fn ($q) => $q->where('category_id', $category->id))
            ->latest('published_at')->paginate(9)->withQueryString();

        return view('blog.index', compact('posts', 'categories', 'category'));
    }

    public function show(Post $post)
    {
        abort_unless(($post->status === 'published' && $post->published_at?->lte(now())) || auth()->user()?->isAdmin(), 404);
        $post->load(['category', 'author:id,name,headline,bio']);
        $related = Post::published()->whereKeyNot($post->id)
            ->when($post->category_id, fn ($q) => $q->where('category_id', $post->category_id))
            ->latest('published_at')->limit(3)->get();

        return view('blog.show', compact('post', 'related'));
    }
}
