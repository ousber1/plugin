<?php

namespace App\Http\Controllers\Admin;

use App\Models\Review;
use App\Support\Audit;
use Illuminate\Http\Request;

class ReviewController extends AdminController
{
    public function index(Request $request)
    {
        $reviews = Review::with(['user:id,name,email', 'course:id,slug,title'])
            ->when($request->input('status') === 'pending', fn ($q) => $q->where('is_approved', false))
            ->when($request->input('status') === 'approved', fn ($q) => $q->where('is_approved', true))
            ->orderBy('is_approved')->latest()->paginate(25)->withQueryString();

        return view('admin.reviews.index', compact('reviews'));
    }

    public function approve(Review $review)
    {
        $review->is_approved = ! $review->is_approved;
        $review->save();
        if ($review->is_approved) {
            Audit::log('review.approved', $review);
        }

        return back()->with('success', $review->is_approved ? 'تم اعتماد المراجعة.' : 'تم إخفاء المراجعة.');
    }

    public function destroy(Review $review)
    {
        $review->delete();

        return back()->with('success', 'تم حذف المراجعة.');
    }
}
