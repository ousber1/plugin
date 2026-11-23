<?php

use App\Http\Controllers\Admin as A;
use App\Http\Controllers as C;
use App\Http\Controllers\Auth;
use App\Models\Page;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public site
|--------------------------------------------------------------------------
*/
Route::get('/', [C\HomeController::class, 'index'])->name('home');
Route::get('/locale/{locale}', [C\LocaleController::class, 'switch'])->name('locale.switch');
Route::get('/robots.txt', [C\SeoController::class, 'robots'])->name('robots');
Route::get('/sitemap.xml', [C\SeoController::class, 'sitemap'])->name('sitemap');

// Uploaded images when the hosting does not allow the public/storage symlink.
Route::get('/storage/{path}', [C\PublicFileController::class, 'show'])->where('path', '.*')->name('storage.public');

// Generated original artwork for items without an uploaded image.
Route::get('/covers/course/{course:id}.svg', [C\CoverController::class, 'course'])->name('covers.course');
Route::get('/covers/post/{post:id}.svg', [C\CoverController::class, 'post'])->name('covers.post');
Route::get('/covers/product/{product:id}.svg', [C\CoverController::class, 'product'])->name('covers.product');

// Free course catalog
Route::get('/courses', [C\CourseController::class, 'index'])->name('courses.index');
Route::get('/courses/{course}', [C\CourseController::class, 'show'])->name('courses.show');

// Lesson player (preview lessons are public; others need enrollment)
Route::get('/learn/{course}/{lesson?}', [C\LearnController::class, 'show'])->name('learn.show');

// Protected media (authorization is enforced in the controller)
Route::get('/media/lessons/{lesson}/video', [C\MediaController::class, 'video'])->name('media.video');
Route::get('/media/attachments/{attachment}', [C\MediaController::class, 'attachment'])->name('media.attachment');

// Static pages, contact
Route::get('/p/{page}', [C\PageController::class, 'show'])->name('pages.show');
Route::get('/about', fn () => app(C\PageController::class)->show(Page::where('slug', 'about')->firstOrFail()))->name('about');
Route::get('/privacy', fn () => app(C\PageController::class)->show(Page::where('slug', 'privacy')->firstOrFail()))->name('privacy');
Route::get('/contact', [C\ContactController::class, 'create'])->name('contact');
Route::post('/contact', [C\ContactController::class, 'store'])->middleware('throttle:forms')->name('contact.store');

// Training centres
Route::middleware('feature:centers')->group(function () {
    Route::get('/centers', [C\CenterController::class, 'index'])->name('centers.index');
    Route::get('/centers/{center}', [C\CenterController::class, 'show'])->name('centers.show');
});

// Blog
Route::middleware('feature:blog')->group(function () {
    Route::get('/blog', [C\BlogController::class, 'index'])->name('blog.index');
    Route::get('/blog/category/{slug}', [C\BlogController::class, 'index'])->name('blog.category');
    Route::get('/blog/{post}', [C\BlogController::class, 'show'])->name('blog.show');
});

// Library
Route::middleware('feature:library')->group(function () {
    Route::get('/library', [C\LibraryController::class, 'index'])->name('library.index');
    Route::get('/library/{resource}/download', [C\LibraryController::class, 'download'])->name('library.download');
});

// Certificate verification
Route::middleware('feature:verification')->group(function () {
    Route::get('/verify', [C\VerificationController::class, 'index'])->middleware('throttle:verify')->name('verify.index');
    Route::get('/verify/{code}', [C\VerificationController::class, 'show'])->middleware('throttle:verify')->name('verify.show');
});

// Browser tools
Route::middleware('feature:tools')->group(function () {
    Route::get('/tools', [C\ToolController::class, 'index'])->name('tools.index');
    Route::post('/tools/suggest', [C\ToolController::class, 'suggest'])->middleware('throttle:forms')->name('tools.suggest');
    Route::post('/tools/quiz-generator/generate', [C\ToolController::class, 'generateQuiz'])->middleware(['auth', 'throttle:ai'])->name('tools.quiz.generate');
    Route::get('/tools/{tool}', [C\ToolController::class, 'show'])->name('tools.show');
});

// Optional digital store (disabled by default; independent from learning)
Route::middleware('feature:store')->group(function () {
    Route::get('/store', [C\StoreController::class, 'index'])->name('store.index');
    Route::get('/store/{product}', [C\StoreController::class, 'show'])->name('store.show');
    Route::middleware(['auth', 'verified'])->group(function () {
        Route::post('/store/{product}/checkout', [C\StoreController::class, 'checkout'])->middleware('throttle:forms')->name('store.checkout');
        Route::get('/orders/{order:reference}', [C\StoreController::class, 'order'])->name('store.order');
        Route::get('/orders/{order:reference}/download', [C\StoreController::class, 'download'])->name('store.download');
    });
});

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/
Route::middleware('guest')->group(function () {
    Route::get('/register', [Auth\RegisterController::class, 'create'])->name('register');
    Route::post('/register', [Auth\RegisterController::class, 'store'])->middleware('throttle:forms');
    Route::get('/login', [Auth\LoginController::class, 'create'])->name('login');
    Route::post('/login', [Auth\LoginController::class, 'store'])->middleware('throttle:login');
    Route::get('/forgot-password', [Auth\PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [Auth\PasswordResetController::class, 'email'])->middleware('throttle:forms')->name('password.email');
    Route::get('/reset-password/{token}', [Auth\PasswordResetController::class, 'reset'])->name('password.reset');
    Route::post('/reset-password', [Auth\PasswordResetController::class, 'update'])->middleware('throttle:forms')->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [Auth\LoginController::class, 'destroy'])->name('logout');
    Route::get('/email/verify', [Auth\VerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [Auth\VerificationController::class, 'verify'])->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/email/verification-notification', [Auth\VerificationController::class, 'send'])->middleware('throttle:3,1')->name('verification.send');

    // Student area
    Route::get('/dashboard', [C\DashboardController::class, 'index'])->name('dashboard');
    Route::get('/profile', [C\ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [C\ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [C\ProfileController::class, 'password'])->name('profile.password');

    Route::post('/courses/{course}/enroll', [C\EnrollmentController::class, 'store'])->name('courses.enroll');
    Route::post('/courses/{course}/wishlist', [C\CourseController::class, 'wishlist'])->name('courses.wishlist');
    Route::post('/courses/{course}/review', [C\ReviewController::class, 'store'])->middleware('throttle:forms')->name('courses.review');
    Route::post('/courses/{course}/certificate', [C\CertificateRequestController::class, 'store'])->name('certificates.request');

    Route::post('/learn/{course}/{lesson}/complete', [C\LearnController::class, 'complete'])->name('learn.complete');
    Route::post('/learn/{course}/{lesson}/bookmark', [C\LearnController::class, 'bookmark'])->name('learn.bookmark');
    Route::post('/learn/{course}/{lesson}/quiz', [C\LearnController::class, 'quiz'])->middleware('throttle:20,1')->name('learn.quiz');
});

/*
|--------------------------------------------------------------------------
| Admin dashboard (Arabic RTL). Instructors only see their assigned courses.
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('admin.')->middleware(['auth', 'staff'])->group(function () {
    Route::get('/', [A\DashboardController::class, 'index'])->name('dashboard');

    // Courses & curriculum (admins: all courses; instructors: assigned only — enforced by gate)
    Route::get('courses', [A\CourseController::class, 'index'])->name('courses.index');
    Route::get('courses/create', [A\CourseController::class, 'create'])->middleware('admin')->name('courses.create');
    Route::post('courses', [A\CourseController::class, 'store'])->middleware('admin')->name('courses.store');
    Route::get('courses/{course:id}/edit', [A\CourseController::class, 'edit'])->name('courses.edit');
    Route::put('courses/{course:id}', [A\CourseController::class, 'update'])->name('courses.update');
    Route::post('courses/{course:id}/publish', [A\CourseController::class, 'publish'])->name('courses.publish');
    Route::delete('courses/{course:id}', [A\CourseController::class, 'destroy'])->middleware('admin')->name('courses.destroy');
    Route::get('courses/{course:id}/curriculum', [A\CurriculumController::class, 'show'])->name('courses.curriculum');
    Route::post('courses/{course:id}/sections', [A\CurriculumController::class, 'storeSection'])->name('sections.store');
    Route::put('sections/{section}', [A\CurriculumController::class, 'updateSection'])->name('sections.update');
    Route::delete('sections/{section}', [A\CurriculumController::class, 'destroySection'])->name('sections.destroy');
    Route::post('sections/{section}/move', [A\CurriculumController::class, 'moveSection'])->name('sections.move');
    Route::get('courses/{course:id}/enrollments', [A\EnrollmentController::class, 'index'])->name('courses.enrollments');

    Route::get('sections/{section}/lessons/create', [A\LessonController::class, 'create'])->name('lessons.create');
    Route::post('sections/{section}/lessons', [A\LessonController::class, 'store'])->name('lessons.store');
    Route::get('lessons/{lesson}/edit', [A\LessonController::class, 'edit'])->name('lessons.edit');
    Route::put('lessons/{lesson}', [A\LessonController::class, 'update'])->name('lessons.update');
    Route::delete('lessons/{lesson}', [A\LessonController::class, 'destroy'])->name('lessons.destroy');
    Route::post('lessons/{lesson}/move', [A\LessonController::class, 'move'])->name('lessons.move');
    Route::post('lessons/{lesson}/attachments', [A\LessonController::class, 'storeAttachment'])->name('lessons.attachments.store');
    Route::delete('attachments/{attachment}', [A\LessonController::class, 'destroyAttachment'])->name('attachments.destroy');
    Route::delete('lessons/{lesson}/video', [A\LessonController::class, 'destroyVideo'])->name('lessons.video.destroy');

    // Resumable chunked video upload
    Route::middleware('throttle:uploads')->group(function () {
        Route::post('lessons/{lesson}/uploads', [A\VideoUploadController::class, 'start'])->name('uploads.start');
        Route::post('uploads/{upload}/chunk', [A\VideoUploadController::class, 'chunk'])->name('uploads.chunk');
        Route::get('uploads/{upload}', [A\VideoUploadController::class, 'status'])->name('uploads.status');
        Route::post('uploads/{upload}/complete', [A\VideoUploadController::class, 'complete'])->name('uploads.complete');
        Route::delete('uploads/{upload}', [A\VideoUploadController::class, 'cancel'])->name('uploads.cancel');
    });

    // Quizzes
    Route::get('lessons/{lesson}/quiz', [A\QuizController::class, 'edit'])->name('quizzes.edit');
    Route::put('lessons/{lesson}/quiz', [A\QuizController::class, 'update'])->name('quizzes.update');
    Route::post('lessons/{lesson}/quiz/generate', [A\QuizController::class, 'generate'])->middleware('throttle:ai')->name('quizzes.generate');

    // Admin-only management
    Route::middleware('admin')->group(function () {
        Route::get('users', [A\UserController::class, 'index'])->name('users.index');
        Route::get('users/{user}/edit', [A\UserController::class, 'edit'])->name('users.edit');
        Route::put('users/{user}', [A\UserController::class, 'update'])->name('users.update');

        Route::resource('categories', A\CategoryController::class)->except(['show']);
        Route::get('reviews', [A\ReviewController::class, 'index'])->name('reviews.index');
        Route::post('reviews/{review}/approve', [A\ReviewController::class, 'approve'])->name('reviews.approve');
        Route::delete('reviews/{review}', [A\ReviewController::class, 'destroy'])->name('reviews.destroy');

        Route::resource('testimonials', A\TestimonialController::class)->except(['show']);
        Route::resource('partners', A\PartnerController::class)->except(['show']);
        Route::resource('centers', A\CenterController::class)->except(['show']);

        // Site design: header/footer, themes; optimization tools
        Route::get('layout', [A\LayoutController::class, 'edit'])->name('layout.edit');
        Route::put('layout', [A\LayoutController::class, 'update'])->name('layout.update');
        Route::post('themes/{theme}', [A\LayoutController::class, 'applyTheme'])->name('themes.apply');
        Route::get('optimize', [A\OptimizeController::class, 'index'])->name('optimize.index');
        Route::put('optimize', [A\OptimizeController::class, 'update'])->name('optimize.update');
        Route::post('optimize/{action}', [A\OptimizeController::class, 'run'])->name('optimize.run');
        Route::resource('posts', A\PostController::class)->except(['show']);
        Route::resource('resources', A\ResourceController::class)->except(['show']);
        Route::resource('pages', A\PageController::class)->except(['show']);
        Route::resource('products', A\ProductController::class)->except(['show']);

        Route::get('orders', [A\OrderController::class, 'index'])->name('orders.index');
        Route::put('orders/{order}', [A\OrderController::class, 'update'])->name('orders.update');

        Route::get('tools', [A\ToolController::class, 'index'])->name('tools.index');
        Route::put('tools', [A\ToolController::class, 'update'])->name('tools.update');
        Route::put('tool-suggestions/{suggestion}', [A\ToolController::class, 'updateSuggestion'])->name('tool-suggestions.update');
        Route::delete('tool-suggestions/{suggestion}', [A\ToolController::class, 'destroySuggestion'])->name('tool-suggestions.destroy');

        Route::get('messages', [A\ContactMessageController::class, 'index'])->name('messages.index');
        Route::get('messages/{message}', [A\ContactMessageController::class, 'show'])->name('messages.show');
        Route::delete('messages/{message}', [A\ContactMessageController::class, 'destroy'])->name('messages.destroy');

        Route::get('certificate-requests', [A\CertificateRequestController::class, 'index'])->name('certificate-requests.index');
        Route::get('certificate-requests/{certificateRequest}', [A\CertificateRequestController::class, 'show'])->name('certificate-requests.show');
        Route::put('certificate-requests/{certificateRequest}', [A\CertificateRequestController::class, 'update'])->name('certificate-requests.update');

        Route::get('certificates', [A\IssuedCertificateController::class, 'index'])->name('certificates.index');
        Route::get('certificates/create', [A\IssuedCertificateController::class, 'create'])->name('certificates.create');
        Route::post('certificates', [A\IssuedCertificateController::class, 'store'])->name('certificates.store');
        Route::get('certificates/{certificate}', [A\IssuedCertificateController::class, 'show'])->name('certificates.show');
        Route::post('certificates/{certificate}/revoke', [A\IssuedCertificateController::class, 'revoke'])->name('certificates.revoke');
        Route::post('certificates/{certificate}/restore', [A\IssuedCertificateController::class, 'restore'])->name('certificates.restore');
        Route::get('certificates/{certificate}/qr.svg', [A\IssuedCertificateController::class, 'qr'])->name('certificates.qr');

        Route::get('settings/{group?}', [A\SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('settings/{group}', [A\SettingsController::class, 'update'])->name('settings.update');

        Route::get('audit-log', [A\AuditLogController::class, 'index'])->name('audit.index');

        Route::post('system/migrate', [A\SystemController::class, 'migrate'])->name('system.migrate');

        // Visual page builder (target = "home" or a page id)
        Route::post('builder/upload', [A\BuilderController::class, 'upload'])->middleware('throttle:uploads')->name('builder.upload');
        Route::get('builder/{target}', [A\BuilderController::class, 'edit'])->name('builder.edit');
        Route::put('builder/{target}', [A\BuilderController::class, 'save'])->name('builder.save');
        Route::post('builder/{target}/preview', [A\BuilderController::class, 'preview'])->name('builder.preview');
        Route::post('builder/{target}/disable', [A\BuilderController::class, 'disable'])->name('builder.disable');
    });
});
