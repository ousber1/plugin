<?php

namespace App\Http\Controllers\Admin;

use App\Models\Course;
use App\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CurriculumController extends AdminController
{
    public function show(Course $course)
    {
        $this->authorizeCourse($course);
        $course->load(['sections.lessons.quiz' => fn ($q) => $q->withCount('questions')]);

        return view('admin.courses.curriculum', compact('course'));
    }

    public function storeSection(Request $request, Course $course)
    {
        $this->authorizeCourse($course);
        $request->validate($this->trRules('title', true, 190));
        $course->sections()->create([
            'title' => $this->trInput($request, 'title'),
            'sort_order' => (int) $course->sections()->max('sort_order') + 1,
        ]);

        return back()->with('success', 'تمت إضافة القسم.');
    }

    public function updateSection(Request $request, Section $section)
    {
        $this->authorizeCourse($section->course);
        $request->validate($this->trRules('title', true, 190));
        $section->update(['title' => $this->trInput($request, 'title')]);

        return back()->with('success', 'تم حفظ القسم.');
    }

    public function destroySection(Section $section)
    {
        $this->authorizeCourse($section->course);
        if ($section->lessons()->exists()) {
            return back()->with('error', 'احذف دروس القسم أو انقلها قبل حذفه.');
        }
        $section->delete();

        return back()->with('success', 'تم حذف القسم.');
    }

    public function moveSection(Request $request, Section $section)
    {
        $this->authorizeCourse($section->course);
        $request->validate(['direction' => ['required', 'in:up,down']]);
        $this->swap($section->course->sections()->get(), $section->id, $request->input('direction'));

        return back();
    }

    /** Swap an item with its neighbour and renumber sort_order. */
    public static function swap($items, int $id, string $direction): void
    {
        $items = $items->values();
        $i = $items->search(fn ($x) => $x->id === $id);
        $j = $direction === 'up' ? $i - 1 : $i + 1;
        if ($i === false || ! isset($items[$j])) {
            return;
        }
        $arr = $items->all();
        [$arr[$i], $arr[$j]] = [$arr[$j], $arr[$i]];
        DB::transaction(function () use ($arr) {
            foreach ($arr as $n => $item) {
                $item->forceFill(['sort_order' => $n + 1])->save();
            }
        });
    }
}
