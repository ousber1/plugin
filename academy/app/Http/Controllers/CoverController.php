<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Post;
use App\Models\Product;
use App\Support\CoverArt;

class CoverController extends Controller
{
    public function course(Course $course)
    {
        // Covers of unpublished courses are generic to avoid leaking titles.
        $title = $course->isPublished() || auth()->user()?->isStaff() ? $course->tr('title') : '';

        return $this->svg(CoverArt::svg($title, $course->category?->tr('name') ?? '', $course->id * 7919, $this->dirFor($course->trLocale('title'))));
    }

    public function post(Post $post)
    {
        $title = $post->status === 'published' ? $post->tr('title') : '';

        return $this->svg(CoverArt::svg($title, $post->category?->tr('name') ?? '', $post->id * 104729 + 3, $this->dirFor($post->trLocale('title'))));
    }

    public function product(Product $product)
    {
        return $this->svg(CoverArt::svg($product->status === 'published' ? $product->tr('title') : '', '', $product->id * 31 + 5));
    }

    private function dirFor(string $locale): string
    {
        return $locale === 'ar' ? 'rtl' : 'ltr';
    }

    private function svg(string $svg)
    {
        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=604800',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'",
        ]);
    }
}
