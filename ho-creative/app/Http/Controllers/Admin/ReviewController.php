<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('reviews.manage');
        $reviews = Review::with('product')->when($request->query('status', 'pending'), fn ($q, $s) => $s === 'all' ? $q : $q->where('status', $s))->latest()->paginate(40)->withQueryString();

        return view('admin.reviews', compact('reviews'));
    }

    public function moderate(Request $request, Review $review)
    {
        $this->authorize('reviews.manage');
        $status = $request->validate(['status' => 'required|in:approved,rejected,pending'])['status'];
        $old = $review->status;
        $review->update(['status' => $status]);
        AuditLogger::log('review.moderated', $review, ['status' => $old], ['status' => $status]);

        return back()->with('ok', 'Avis mis à jour.');
    }
}
