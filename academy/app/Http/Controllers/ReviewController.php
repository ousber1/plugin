<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Review;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    /** One editable review per enrolled student; edits return to moderation. */
    public function store(Request $request, Course $course)
    {
        abort_unless($course->isPublished() && $request->user()->isEnrolledIn($course), 403);
        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'body' => ['nullable', 'string', 'max:2000'],
        ]);

        $review = Review::firstOrNew(['user_id' => $request->user()->id, 'course_id' => $course->id]);
        $review->fill($data);
        $review->is_approved = false;
        $review->save();

        return back()->with('success', __('شكرًا! ستظهر مراجعتك بعد اعتمادها من فريق الأكاديمية.'));
    }
}
