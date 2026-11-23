<?php

namespace App\Http\Controllers\Admin;

use App\Models\ProductReview;

class ProductReviewController extends AdminController
{
    public function index()
    {
        return view('admin.product-reviews.index', ['items' => ProductReview::with(['product:id,title,slug', 'user:id,name'])->orderBy('is_approved')->latest()->paginate(30)]);
    }

    public function approve(ProductReview $review)
    {
        $review->update(['is_approved' => true]);

        return back()->with('success', 'تم اعتماد المراجعة.');
    }

    public function destroy(ProductReview $review)
    {
        $review->delete();

        return back()->with('success', 'تم حذف المراجعة.');
    }
}
