<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\IssuedCertificate;
use App\Models\Lesson;
use App\Models\Partner;
use App\Models\Post;
use App\Models\Testimonial;
use App\Support\Settings;

class HomeController extends Controller
{
    public function index()
    {
        $sections = collect(Settings::homeSections())->where('enabled', true)->pluck('key')->all();
        $has = fn ($key) => in_array($key, $sections, true);

        $data = ['sections' => $sections];

        if ($has('categories')) {
            $data['categories'] = Category::ofType('course')->where('is_active', true)
                ->withCount(['courses' => fn ($q) => $q->published()])->get()->where('courses_count', '>', 0);
        }
        if ($has('featured')) {
            $data['featured'] = Course::published()->where('is_featured', true)->withCardData()
                ->orderBy('featured_order')->orderByDesc('published_at')->limit(8)->get();
        }
        if ($has('recent')) {
            $data['recent'] = Course::published()->withCardData()->latest('published_at')->limit(4)->get();
        }
        if ($has('stats')) {
            // Derived from real records only.
            $data['stats'] = [
                'courses' => Course::published()->count(),
                'students' => Enrollment::distinct('user_id')->count('user_id'),
                'lessons' => Lesson::where('status', 'published')->whereHas('course', fn ($q) => $q->published())->count(),
                'certificates' => IssuedCertificate::where('status', 'valid')->count(),
            ];
        }
        if ($has('testimonials')) {
            $data['testimonials'] = Testimonial::where('is_approved', true)->orderBy('sort_order')->limit(6)->get();
        }
        if ($has('articles') && Settings::feature('blog')) {
            $data['articles'] = Post::published()->with('category')->latest('published_at')->limit(3)->get();
        }
        if ($has('partners')) {
            $data['partners'] = Partner::where('is_active', true)->whereNotNull('logo_path')->orderBy('sort_order')->get();
        }

        return view('home', $data);
    }
}
