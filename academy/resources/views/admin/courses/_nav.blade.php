<nav class="tabs" aria-label="أقسام الدورة">
    <a href="{{ route('admin.courses.edit', $course) }}" class="{{ request()->routeIs('admin.courses.edit') ? 'is-active' : '' }}">المعلومات</a>
    <a href="{{ route('admin.courses.curriculum', $course) }}" class="{{ request()->routeIs('admin.courses.curriculum', 'admin.lessons.*', 'admin.quizzes.*') ? 'is-active' : '' }}">المحتوى والدروس</a>
    <a href="{{ route('admin.courses.enrollments', $course) }}" class="{{ request()->routeIs('admin.courses.enrollments') ? 'is-active' : '' }}">الطلاب والتقدّم</a>
    <a href="{{ route('courses.show', $course) }}" target="_blank">معاينة <x-icon name="eye" size="14"/></a>
</nav>
