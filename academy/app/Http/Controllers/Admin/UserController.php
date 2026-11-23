<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Models\Course;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends AdminController
{
    public function index(Request $request)
    {
        $request->validate(['q' => ['nullable', 'string', 'max:120'], 'role' => ['nullable', Rule::enum(Role::class)], 'center' => ['nullable', 'integer']]);
        $users = User::query()->withCount('enrollments')->with('center')
            ->when($request->input('center'), fn ($q, $c) => $q->where('center_id', $c))
            ->when($request->input('q'), fn ($q, $t) => $q->where(fn ($w) => $w->where('name', 'like', "%$t%")->orWhere('email', 'like', "%$t%")->orWhere('phone', 'like', "%$t%")))
            ->when($request->input('role'), fn ($q, $r) => $q->where('role', $r))
            ->latest()->paginate(25)->withQueryString();

        $centers = \App\Models\Center::orderBy('sort_order')->get();

        return view('admin.users.index', compact('users', 'centers'));
    }

    public function create(Request $request)
    {
        $role = in_array($request->query('role'), ['admin', 'instructor', 'student'], true) ? $request->query('role') : 'student';

        return view('admin.users.create', [
            'role' => $role,
            'courses' => Course::orderBy('slug')->get(['id', 'title', 'slug']),
            'centers' => \App\Models\Center::orderBy('sort_order')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:40'],
            'role' => ['required', Rule::enum(Role::class)],
            'password' => ['nullable', 'string', 'min:8', 'max:100'],
            'center_id' => ['nullable', 'integer', 'exists:centers,id'],
            'headline' => ['nullable', 'string', 'max:190'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'courses' => ['nullable', 'array'], 'courses.*' => ['integer', 'exists:courses,id'],
            'enroll' => ['nullable', 'array'], 'enroll.*' => ['integer', 'exists:courses,id'],
        ]);
        $password = $data['password'] ?? \Illuminate\Support\Str::password(10, symbols: false);
        $user = new User([
            'name' => $data['name'], 'email' => mb_strtolower($data['email']), 'phone' => $data['phone'] ?? null,
            'password' => $password, 'locale' => 'ar', 'headline' => $data['headline'] ?? null, 'bio' => $data['bio'] ?? null,
        ]);
        $user->role = $data['role'];
        $user->center_id = $data['center_id'] ?? null;
        $user->email_verified_at = now(); // created by an admin
        $user->save();
        if ($user->role === Role::Instructor) {
            $user->assignedCourses()->sync($data['courses'] ?? []);
        }
        foreach ($data['enroll'] ?? [] as $courseId) {
            \App\Models\Enrollment::firstOrCreate(['user_id' => $user->id, 'course_id' => $courseId]);
        }
        Audit::log('user.created', $user, ['role' => $user->role->value]);

        return redirect()->route('admin.users.edit', $user)->with('success', 'تمت إضافة '.$user->role->label().' «'.$user->name.'».')
            ->with('new_credentials', ['email' => $user->email, 'password' => $password, 'phone' => $user->phone]);
    }

    public function edit(User $user)
    {
        return view('admin.users.edit', [
            'user' => $user->load('assignedCourses'),
            'courses' => Course::orderBy('slug')->get(['id', 'title', 'slug']),
            'centers' => \App\Models\Center::orderBy('sort_order')->get(),
            'enrollments' => $user->enrollments()->with('course:id,title,slug')->get(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'role' => ['required', Rule::enum(Role::class)],
            'is_active' => ['nullable', 'boolean'],
            'headline' => ['nullable', 'string', 'max:190'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'courses' => ['nullable', 'array'],
            'courses.*' => ['integer', 'exists:courses,id'],
            'verify_email' => ['nullable', 'boolean'],
            'center_id' => ['nullable', 'integer', 'exists:centers,id'],
            'new_password' => ['nullable', 'string', 'min:8', 'max:100'],
            'enroll' => ['nullable', 'array'], 'enroll.*' => ['integer', 'exists:courses,id'],
        ]);

        if ($user->is($request->user()) && ($data['role'] !== 'admin' || ! $request->boolean('is_active'))) {
            return back()->with('error', 'لا يمكنك إزالة صلاحية المدير أو تعطيل حسابك بنفسك.');
        }

        $oldRole = $user->role->value;
        $user->role = $data['role'];
        $wasActive = $user->is_active;
        $user->is_active = $request->boolean('is_active');
        $user->headline = $data['headline'] ?? null;
        $user->bio = $data['bio'] ?? null;
        $user->center_id = $data['center_id'] ?? null;
        if (! empty($data['new_password'])) {
            $user->password = $data['new_password'];
            $user->setRememberToken(\Illuminate\Support\Str::random(60));
            Audit::log('user.password_reset', $user);
            session()->flash('new_credentials', ['email' => $user->email, 'password' => $data['new_password'], 'phone' => $user->phone]);
        }
        foreach ($data['enroll'] ?? [] as $courseId) {
            \App\Models\Enrollment::firstOrCreate(['user_id' => $user->id, 'course_id' => $courseId]);
        }
        if ($request->boolean('verify_email') && ! $user->hasVerifiedEmail()) {
            $user->email_verified_at = now();
        }
        $user->save();

        if ($oldRole !== $user->role->value) {
            Audit::log('user.role_changed', $user, ['from' => $oldRole, 'to' => $user->role->value]);
        }
        if ($wasActive !== $user->is_active) {
            Audit::log('user.status_changed', $user, ['active' => $user->is_active]);
        }
        $courses = $user->role === Role::Instructor ? ($data['courses'] ?? []) : [];
        $changes = $user->assignedCourses()->sync($courses);
        if (array_filter($changes)) {
            Audit::log('user.courses_assigned', $user, ['courses' => $courses]);
        }

        return back()->with('success', 'تم حفظ المستخدم.');
    }
}
