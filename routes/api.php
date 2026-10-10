<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\ContentController;
use App\Http\Controllers\Admin\QuestionController;
use App\Http\Controllers\Api\AttemptController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ExamController;
use App\Http\Controllers\Api\LearnController;
use App\Http\Controllers\Api\PracticeController;
use App\Http\Controllers\Api\UserContentController;
use App\Http\Controllers\Api\VocabularyController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

// ---- Auth (guest) ----
Route::middleware('throttle:20,1')->group(function () {
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/recover', [AuthController::class, 'recover']);
});

// ---- Public ----
Route::get('/profiles/{username}', [UserContentController::class, 'publicProfile']);

// ---- Authenticated user ----
Route::middleware(['auth:web', 'not-suspended'])->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/change-password', [AuthController::class, 'changePassword']);

    Route::post('/onboarding', [UserContentController::class, 'onboard']);
    Route::patch('/profile', [UserContentController::class, 'updateProfile']);
    Route::post('/profile/avatar', [UserContentController::class, 'uploadAvatar']);

    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/stats', [DashboardController::class, 'stats']);
    Route::get('/history', [DashboardController::class, 'history']);
    Route::get('/notifications', [DashboardController::class, 'notifications']);
    Route::post('/notifications/read', [DashboardController::class, 'markNotificationsRead']);
    Route::get('/achievements', [UserContentController::class, 'achievements']);

    // Learning
    Route::get('/levels', [LearnController::class, 'levels']);
    Route::get('/levels/{level}', [LearnController::class, 'level']);
    Route::get('/lessons/{lesson:slug}', [LearnController::class, 'lesson']);
    Route::get('/exercises/{exercise}', [LearnController::class, 'exercise']);

    // Attempts
    Route::post('/questions/{question}/check', [AttemptController::class, 'checkAnswer'])->middleware('throttle:120,1');
    Route::post('/exercises/{exercise}/submit', [AttemptController::class, 'submitExercise'])->middleware('throttle:30,1');
    Route::post('/lessons/{lesson}/complete', [AttemptController::class, 'completeLesson'])->middleware('throttle:30,1');

    // Exams & placement
    Route::post('/placement/start', [ExamController::class, 'startPlacement']);
    Route::get('/placement/result', [ExamController::class, 'placementResult']);
    Route::post('/placement/choose-start', [ExamController::class, 'chooseStart']);
    Route::post('/levels/{level}/exam/start', [ExamController::class, 'startFinal']);
    Route::get('/exams/{attempt}', [ExamController::class, 'active']);
    Route::post('/exams/{attempt}/submit', [ExamController::class, 'submit'])->middleware('throttle:10,1');
    Route::get('/exams', [ExamController::class, 'history']);

    // Vocabulary & flashcards
    Route::get('/vocabulary', [VocabularyController::class, 'index']);
    Route::get('/vocabulary/mine', [VocabularyController::class, 'mine']);
    Route::post('/vocabulary/{vocabulary}/save', [VocabularyController::class, 'save']);
    Route::delete('/vocabulary/{vocabulary}/save', [VocabularyController::class, 'remove']);
    Route::get('/review/queue', [VocabularyController::class, 'reviewQueue']);
    Route::post('/review/{entry}', [VocabularyController::class, 'review'])->middleware('throttle:120,1');

    // Speaking & writing
    Route::post('/speaking/score', [PracticeController::class, 'scoreSpeaking'])->middleware('throttle:60,1');
    Route::get('/speaking/history', [PracticeController::class, 'speakingHistory']);
    Route::post('/writing/submit', [PracticeController::class, 'submitWriting'])->middleware('throttle:30,1');

    // Bookmarks, notes, search
    Route::get('/bookmarks', [UserContentController::class, 'bookmarks']);
    Route::post('/bookmarks/toggle', [UserContentController::class, 'toggleBookmark']);
    Route::get('/notes', [UserContentController::class, 'notes']);
    Route::post('/notes', [UserContentController::class, 'storeNote']);
    Route::patch('/notes/{note}', [UserContentController::class, 'updateNote']);
    Route::delete('/notes/{note}', [UserContentController::class, 'deleteNote']);
    Route::get('/search', [UserContentController::class, 'search']);
});

// ---- Admin ----
Route::middleware(['auth:web', 'not-suspended', 'admin'])->prefix('admin')->group(function () {
    Route::get('/overview', [AdminController::class, 'overview']);

    Route::get('/users', [AdminController::class, 'users']);
    Route::get('/users/{user}', [AdminController::class, 'showUser']);
    Route::post('/users/{user}/suspend', [AdminController::class, 'suspendUser']);
    Route::patch('/users/{user}/role', [AdminController::class, 'updateUserRole']);
    Route::post('/users/{user}/recovery-code', [AdminController::class, 'regenerateRecoveryCode']);

    Route::get('/levels', [ContentController::class, 'levels']);
    Route::patch('/levels/{level}', [ContentController::class, 'updateLevel']);

    Route::get('/courses', [ContentController::class, 'courses']);
    Route::post('/courses', [ContentController::class, 'storeCourse']);
    Route::patch('/courses/{course}', [ContentController::class, 'updateCourse']);
    Route::delete('/courses/{course}', [ContentController::class, 'deleteCourse']);

    Route::get('/modules', [ContentController::class, 'modules']);
    Route::post('/modules', [ContentController::class, 'storeModule']);
    Route::patch('/modules/{module}', [ContentController::class, 'updateModule']);
    Route::delete('/modules/{module}', [ContentController::class, 'deleteModule']);

    Route::get('/lessons', [ContentController::class, 'lessons']);
    Route::get('/lessons/{lesson}', [ContentController::class, 'showLesson']);
    Route::post('/lessons', [ContentController::class, 'storeLesson']);
    Route::patch('/lessons/{lesson}', [ContentController::class, 'updateLesson']);
    Route::post('/lessons/{lesson}/duplicate', [ContentController::class, 'duplicateLesson']);
    Route::delete('/lessons/{lesson}', [ContentController::class, 'deleteLesson']);

    Route::post('/lessons/{lesson}/sections', [ContentController::class, 'storeSection']);
    Route::patch('/sections/{section}', [ContentController::class, 'updateSection']);
    Route::delete('/sections/{section}', [ContentController::class, 'deleteSection']);

    Route::post('/reorder', [ContentController::class, 'reorder']);

    Route::get('/exercises', [QuestionController::class, 'exercises']);
    Route::post('/exercises', [QuestionController::class, 'storeExercise']);
    Route::patch('/exercises/{exercise}', [QuestionController::class, 'updateExercise']);
    Route::delete('/exercises/{exercise}', [QuestionController::class, 'deleteExercise']);

    Route::get('/exercises/{exercise}/questions', [QuestionController::class, 'questions']);
    Route::post('/exercises/{exercise}/questions', [QuestionController::class, 'storeQuestion']);
    Route::patch('/questions/{question}', [QuestionController::class, 'updateQuestion']);
    Route::delete('/questions/{question}', [QuestionController::class, 'deleteQuestion']);

    Route::get('/vocabulary', [AdminController::class, 'vocabulary']);
    Route::post('/vocabulary', [AdminController::class, 'storeVocabulary']);
    Route::patch('/vocabulary/{vocabulary}', [AdminController::class, 'updateVocabulary']);
    Route::delete('/vocabulary/{vocabulary}', [AdminController::class, 'deleteVocabulary']);

    Route::get('/achievements', [AdminController::class, 'achievements']);
    Route::post('/achievements', [AdminController::class, 'storeAchievement']);
    Route::patch('/achievements/{achievement}', [AdminController::class, 'updateAchievement']);
    Route::delete('/achievements/{achievement}', [AdminController::class, 'deleteAchievement']);

    Route::post('/audio', [AdminController::class, 'uploadAudio']);

    Route::get('/settings', [AdminController::class, 'settings']);
    Route::patch('/settings', [AdminController::class, 'updateSettings']);
});
