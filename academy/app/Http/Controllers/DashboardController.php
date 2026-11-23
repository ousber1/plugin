<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\ProgressService;
use App\Support\Settings;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request, ProgressService $progress)
    {
        $user = $request->user();
        $enrollments = $user->enrollments()->with(['course' => fn ($q) => $q->withCardData(), 'lastLesson'])
            ->orderByDesc('last_activity_at')->orderByDesc('created_at')->get()
            ->filter(fn ($e) => $e->course !== null);

        $rows = $enrollments->map(function ($e) use ($user, $progress) {
            return [
                'enrollment' => $e,
                'course' => $e->course,
                'progress' => $progress->progress($user, $e->course),
                'eligibility' => $progress->eligibility($user, $e->course),
            ];
        });

        return view('dashboard.index', [
            'rows' => $rows,
            'requests' => $user->certificateRequests()->with('course')->latest()->get(),
            'bookmarks' => $user->bookmarks()->with('lesson.course')->latest()->limit(8)->get()->filter(fn ($b) => $b->lesson?->course),
            'wishlist' => $user->wishlist()->published()->withCardData()->get(),
            'orders' => Settings::feature('store') ? Order::where('user_id', $user->id)->with('product')->latest()->get() : collect(),
        ]);
    }
}
