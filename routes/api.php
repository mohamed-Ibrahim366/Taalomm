<?php

use App\Http\Controllers\Api\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\Auth\EmailVerificationController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\LessonController;
use App\Http\Controllers\Api\ResourceController;
use App\Http\Controllers\Api\QuizController;
use App\Http\Controllers\Api\ExamController;
use App\Http\Controllers\Api\AssignmentController;
use App\Http\Controllers\Api\RevisionMaterialController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\TeacherController;
use App\Http\Controllers\Api\CertificateController;
use App\Http\Controllers\Api\MeetingController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\InstallmentController;
use App\Http\Controllers\Api\GroupController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\ParentController;
use App\Http\Controllers\Api\CommunicationLogController;
use App\Http\Controllers\Api\StudentNoteController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\Teacher\CourseController as TeacherCourseController;
use App\Http\Controllers\Api\Teacher\CourseSectionController as TeacherCourseSectionController;
use Illuminate\Support\Facades\Route;

/* ───────────────────────── AUTH ───────────────────────── */

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);

    Route::prefix('email')->group(function () {
        Route::post('send-otp', [EmailVerificationController::class, 'send']);
        Route::post('verify-otp', [EmailVerificationController::class, 'verify']);
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('logout-all', [AuthController::class, 'logoutAll']);
    });
});

/* ─────────────────────── PROFILE ──────────────────────── */

Route::middleware('auth:sanctum')->group(function () {
    Route::get('profile', [ProfileController::class, 'show']);
    Route::put('profile', [ProfileController::class, 'update']);
    Route::post('profile/photo', [ProfileController::class, 'uploadPhoto']);
    Route::delete('profile/photo', [ProfileController::class, 'deletePhoto']);
    Route::put('profile/password', [ProfileController::class, 'changePassword']);
    Route::put('profile/email', [ProfileController::class, 'initiateEmailChange']);
    Route::post('profile/email/verify', [ProfileController::class, 'confirmEmailChange']);
});

/* ─────────────────────── CATEGORIES ───────────────────── */

Route::middleware(['auth:sanctum', 'role:admin'])
    ->prefix('admin')
    ->group(function () {

        Route::get('overview', [AdminDashboardController::class, 'overview']);
        Route::get('teachers', [AdminDashboardController::class, 'teachers']);
        Route::post('teachers', [AdminDashboardController::class, 'storeTeacher']);
        Route::get('students', [AdminDashboardController::class, 'students']);
        Route::get('courses', [AdminDashboardController::class, 'courses']);

        Route::apiResource(
            'categories',
            AdminCategoryController::class
        );

    });

Route::get('categories', [CategoryController::class, 'index']);
Route::get('categories/{category}', [CategoryController::class, 'show']);

Route::middleware(['auth:sanctum', 'role:teacher'])->group(function () {
    Route::post('categories', [CategoryController::class, 'store']);
    Route::put('categories/{category}', [CategoryController::class, 'update']);
    Route::delete('categories/{category}', [CategoryController::class, 'destroy']);
});

/* ───────────────────────── COURSES ────────────────────── */
// Public browsing (catalog) is open; mutation is teacher/admin only.

Route::get('courses', [CourseController::class, 'index']);
Route::get('courses/{course}', [CourseController::class, 'show']);
Route::get('courses/{course}/sections', [CourseController::class, 'sections']);
// Route::get('courses/{course}/lessons', [LessonController::class, 'byCourse']);
 Route::get('courses/{course}/quizzes', [QuizController::class, 'byCourse']);
 Route::get('courses/{course}/exams', [ExamController::class, 'byCourse']);
// Route::get('courses/{course}/assignments', [AssignmentController::class, 'byCourse']);
// Route::get('courses/{course}/revision-materials', [RevisionMaterialController::class, 'byCourse']);

Route::middleware([
    'auth:sanctum',
    'role:teacher,admin',
])->prefix('teacher')->group(function () {

    Route::get( 'courses/statistics', [TeacherCourseController::class,'statistics'] );
    Route::patch('courses/{course}/publish', [TeacherCourseController::class,'publish'] );
    Route::patch('courses/{course}/unpublish', [TeacherCourseController::class,'unpublish'] );
    Route::apiResource('courses', TeacherCourseController::class);

    // Sections CRUD
    Route::post('courses/{course}/sections', [TeacherCourseSectionController::class, 'store']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('courses/{course:slug}/meetings', [MeetingController::class, 'index']);
});

Route::middleware(['auth:sanctum', 'role:teacher,admin'])->group(function () {
    Route::post('courses/{course:slug}/meetings', [MeetingController::class, 'store']);
    Route::delete('meetings/{meeting}', [MeetingController::class, 'destroy']);
});


Route::middleware(['auth:sanctum', 'role:teacher,admin'])->group(function () {
    Route::post('courses', [CourseController::class, 'store']);
    Route::put('courses/{course}', [CourseController::class, 'update']);
    Route::delete('courses/{course}', [CourseController::class, 'destroy']);
    Route::post('courses/{course}/revision-materials', [RevisionMaterialController::class, 'store']);

    // Sections CRUD & Reordering
    Route::put('sections/reorder', [TeacherCourseSectionController::class, 'reorder']);
    Route::put('sections/{section}', [TeacherCourseSectionController::class, 'update']);
    Route::delete('sections/{section}', [TeacherCourseSectionController::class, 'destroy']);
});



Route::middleware(['auth:sanctum', 'role:teacher,admin'])->group(function () {
    Route::delete('revision-materials/{revisionMaterial}', [RevisionMaterialController::class, 'destroy']);
});

/* ───────────────────────── LESSONS ────────────────────── */

Route::middleware('auth:sanctum')->group(function () {
    Route::get('lessons/{lesson}', [LessonController::class, 'show']);

    Route::middleware('role:teacher,admin')->group(function () {
        Route::post('lessons', [LessonController::class, 'store']);
        Route::put('lessons/{lesson}', [LessonController::class, 'update']);
        Route::delete('lessons/{lesson}', [LessonController::class, 'destroy']);
        Route::put('lessons/reorder', [LessonController::class, 'reorder']);

        // Video / attachments upload — multipart/form-data
        Route::post('lessons/{lesson}/video', [LessonController::class, 'uploadVideo']);
        Route::post('lessons/{lesson}/resources', [ResourceController::class, 'store']);
        Route::delete('resources/{resource}', [ResourceController::class, 'destroy']);
    });

    Route::get('lessons/{lesson}/resources', [ResourceController::class, 'byLesson']);
});

/* ───────────────────────── QUIZZES ────────────────────── */

Route::middleware('auth:sanctum')->group(function () {
    Route::get('quizzes/{quiz}', [QuizController::class, 'show']);
    Route::get('quizzes/{quiz}/progress', [QuizController::class, 'progress']);

    // Student
    Route::post('quizzes/{quiz}/submit', [QuizController::class, 'submit']);

    Route::middleware('role:teacher,admin')->group(function () {
        Route::post('quizzes', [QuizController::class, 'store']);
        Route::put('quizzes/{quiz}', [QuizController::class, 'update']);
        Route::delete('quizzes/{quiz}', [QuizController::class, 'destroy']);
        Route::get('quizzes/{quiz}/submissions', [QuizController::class, 'submissions']);
        Route::post('quizzes/submissions/{submission}/grade', [QuizController::class, 'grade']);
        Route::post('quizzes/{quiz}/questions', [QuizController::class, 'storeQuestion']);
        Route::put('quizzes/{quiz}/questions/{question}', [QuizController::class, 'updateQuestion']);
        Route::delete('quizzes/{quiz}/questions/{question}', [QuizController::class, 'destroyQuestion']);
    });
});

/* ────────────────────────── EXAMS ─────────────────────── */
// Distinct from Quiz: single attempt, only within [startDate, endDate].

Route::middleware('auth:sanctum')->group(function () {
    Route::get('exams/{exam}', [ExamController::class, 'show']);
    Route::get('exams/{exam}/progress', [ExamController::class, 'progress']);

    // Student — backend must enforce the time window + single-attempt rule
    Route::post('exams/{exam}/attempt', [ExamController::class, 'submitAttempt']);

    Route::middleware('role:teacher,admin')->group(function () {
        Route::post('exams', [ExamController::class, 'store']);
        Route::put('exams/{exam}', [ExamController::class, 'update']);
        Route::delete('exams/{exam}', [ExamController::class, 'destroy']);
        Route::get('exams/{exam}/attempts', [ExamController::class, 'attempts']);
    });
});

/* ─────────────────────── ASSIGNMENTS ──────────────────── */

Route::middleware('auth:sanctum')->group(function () {
    Route::get('assignments/{assignment}', [AssignmentController::class, 'show']);

    // Student — multipart/form-data (attachments) or JSON
    Route::post('assignments/{assignment}/submit', [AssignmentController::class, 'submit']);

    Route::middleware('role:teacher,admin')->group(function () {
        Route::post('assignments', [AssignmentController::class, 'store']);
        Route::put('assignments/{assignment}', [AssignmentController::class, 'update']);
        Route::delete('assignments/{assignment}', [AssignmentController::class, 'destroy']);
        Route::get('assignments/{assignment}/submissions', [AssignmentController::class, 'submissions']);
        Route::post('assignments/submissions/{submission}/grade', [AssignmentController::class, 'grade']);
    });
});

/* ───────────────────────── STUDENTS ───────────────────── */

Route::middleware('auth:sanctum')->group(function () {
    // Student can enroll themself; teacher/admin browse the roster
    Route::post('students/{student}/enroll', [StudentController::class, 'enroll']);
    Route::get('students/me/groups', [StudentController::class, 'myGroups']);
    Route::get('students/{student}/courses', [StudentController::class, 'courses']);
    Route::get('students/{student}/meetings', [MeetingController::class, 'byStudent']);
    Route::get('students/{student}/groups', [StudentController::class, 'groups']);
    Route::get('students/{student}/courses/{course}/progress', [StudentController::class, 'progress']);
    Route::get('students/{student}/certificates', [CertificateController::class, 'byStudent']);
    Route::get('students/{student}/installments', [InstallmentController::class, 'byStudent']);

    Route::middleware('role:teacher,admin')->group(function () {
        Route::get('students', [StudentController::class, 'index']);
        Route::get('students/{student}', [StudentController::class, 'show']);
        Route::put('students/{student}', [StudentController::class, 'update']);

        // Private teacher notes about a student — never exposed to the student
        Route::get('students/{student}/notes', [StudentNoteController::class, 'byStudent']);
        Route::post('students/{student}/notes', [StudentNoteController::class, 'store']);
        Route::delete('student-notes/{studentNote}', [StudentNoteController::class, 'destroy']);
    });
});

/* ───────────────────────── TEACHERS ───────────────────── */

Route::middleware(['auth:sanctum', 'role:teacher,admin'])->group(function () {
    // 'analytics' response includes coursePerformance[] + monthlyStudents[]
    // (the enrollment-trend mock) in one payload.
    Route::get('teachers/dashboard', [TeacherController::class, 'dashboard']);
    Route::get('teachers/analytics', [TeacherController::class, 'analytics']);
    Route::get('teachers/grades', [TeacherController::class, 'grades']);
    Route::get('teachers', [TeacherController::class, 'index']);
    Route::get('teachers/{teacher}', [TeacherController::class, 'show']);
    Route::get('teachers/{teacher}/courses', [TeacherController::class, 'courses']);
});

/* ─────────────────────── CERTIFICATES ─────────────────── */

Route::middleware('auth:sanctum')->group(function () {
    Route::get('certificates/{certificate}', [CertificateController::class, 'show']);

    Route::middleware('role:teacher,admin')->group(function () {
        Route::post('courses/{course}/certificates/issue', [CertificateController::class, 'issue']);
    });
});

/* ────────────────────── NOTIFICATIONS ─────────────────── */
// Always scoped to the authenticated user — never a filterable public list.

Route::middleware('auth:sanctum')->group(function () {
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::put('notifications/{notification}/read', [NotificationController::class, 'markRead']);
    Route::put('notifications/read-all', [NotificationController::class, 'markAllRead']);
});

/* ─────────────────────── PAYMENTS ─────────────────────── */

Route::middleware('auth:sanctum')->group(function () {
    // Student — request/list own payments
    Route::get('payments', [PaymentController::class, 'index']); // scoped by role in controller
    Route::post('payments', [PaymentController::class, 'store']);

    Route::middleware('role:teacher,admin')->group(function () {
        Route::put('payments/{payment}/approve', [PaymentController::class, 'approve']);
        Route::put('payments/{payment}/reject', [PaymentController::class, 'reject']);
    });
});

/* ──────────────────── GROUPS & ATTENDANCE ─────────────── */

Route::middleware(['auth:sanctum', 'role:teacher,admin'])->group(function () {
    Route::apiResource('groups', GroupController::class)->except(['show']);
    Route::get('groups/{group}', [GroupController::class, 'show']);

    Route::get('groups/{group}/attendance', [AttendanceController::class, 'index']); // ?date=YYYY-MM-DD
    Route::post('groups/{group}/attendance', [AttendanceController::class, 'store']); // batch mark for a date
});

// A student can read their own attendance history across all their groups
Route::middleware('auth:sanctum')->group(function () {
    Route::get('students/{student}/attendance', [AttendanceController::class, 'byStudent']);
});

/* ──────────────── PARENTS & COMMUNICATION LOG ─────────── */

Route::middleware(['auth:sanctum', 'role:teacher,admin'])->group(function () {
    Route::apiResource('parents', ParentController::class)->except(['show']);
    Route::get('parents/{parent}', [ParentController::class, 'show']);
    Route::get('parents/{parent}/students', [ParentController::class, 'students']);

    Route::apiResource('communication-log', CommunicationLogController::class)
        ->only(['index', 'store', 'destroy']);
});

/* ─────────────────── ADMIN USER MANAGEMENT ───────────────── */

Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('users', [AdminUserController::class, 'index']);
    Route::post('users', [AdminUserController::class, 'store']);
    Route::get('users/{user}', [AdminUserController::class, 'show']);
    Route::put('users/{user}', [AdminUserController::class, 'update']);
    Route::delete('users/{user}', [AdminUserController::class, 'destroy']);
    Route::post('users/{id}/restore', [AdminUserController::class, 'restore']);
    Route::put('users/{user}/status', [AdminUserController::class, 'changeStatus']);
});
