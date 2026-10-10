<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Controller;
use App\Models\Achievement;
use App\Models\Bookmark;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Note;
use App\Models\User;
use App\Models\Vocabulary;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class UserContentController extends Controller
{
    public function bookmarks(Request $request): JsonResponse
    {
        $bookmarks = Bookmark::with('bookmarkable')
            ->where('user_id', $request->user()->id)
            ->latest()->get();

        return response()->json([
            'bookmarks' => $bookmarks->map(function ($b) {
                $item = $b->bookmarkable;
                if (! $item) {
                    return null;
                }
                $isLesson = $b->bookmarkable_type === Lesson::class;

                return [
                    'id' => $b->id,
                    'type' => $isLesson ? 'lesson' : 'vocabulary',
                    'target_id' => $item->id,
                    'title' => $isLesson ? $item->title : $item->word,
                    'subtitle' => $isLesson ? $item->objective : $item->definition,
                    'slug' => $isLesson ? $item->slug : null,
                    'created_at' => $b->created_at,
                ];
            })->filter()->values(),
        ]);
    }

    public function toggleBookmark(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['lesson', 'vocabulary'])],
            'id' => ['required', 'integer'],
        ]);

        $class = $data['type'] === 'lesson' ? Lesson::class : Vocabulary::class;
        abort_unless($class::where('id', $data['id'])->exists(), 404);

        $existing = Bookmark::where('user_id', $request->user()->id)
            ->where('bookmarkable_type', $class)
            ->where('bookmarkable_id', $data['id'])->first();

        if ($existing) {
            $existing->delete();

            return response()->json(['bookmarked' => false]);
        }

        Bookmark::create([
            'user_id' => $request->user()->id,
            'bookmarkable_type' => $class,
            'bookmarkable_id' => $data['id'],
        ]);

        return response()->json(['bookmarked' => true], 201);
    }

    public function notes(Request $request): JsonResponse
    {
        $query = Note::with('lesson:id,title,slug')->where('user_id', $request->user()->id);

        if ($lessonId = $request->query('lesson_id')) {
            $query->where('lesson_id', $lessonId);
        }

        // Flat list; the notes UI renders them all without pagination.
        return response()->json(['notes' => $query->latest()->limit(500)->get()]);
    }

    public function storeNote(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lesson_id' => ['nullable', 'integer', 'exists:lessons,id'],
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:10000'],
        ]);

        $note = Note::create([...$data, 'user_id' => $request->user()->id]);

        return response()->json(['note' => $note], 201);
    }

    public function updateNote(Request $request, Note $note): JsonResponse
    {
        abort_unless($note->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:10000'],
        ]);

        $note->update($data);

        return response()->json(['note' => $note]);
    }

    public function deleteNote(Request $request, Note $note): JsonResponse
    {
        abort_unless($note->user_id === $request->user()->id, 403);
        $note->delete();

        return response()->json(['message' => 'Note deleted.']);
    }

    public function search(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:80']]);
        $q = $data['q'];

        $lessons = Lesson::where('is_published', true)
            ->where(fn ($query) => $query->whereLike('title', "%{$q}%", caseSensitive: false)->orWhereLike('objective', "%{$q}%", caseSensitive: false))
            ->with('module.course.level:id,code')
            ->limit(10)->get()
            ->map(fn ($l) => [
                'type' => 'lesson',
                'id' => $l->id,
                'title' => $l->title,
                'slug' => $l->slug,
                'subtitle' => strtoupper($l->module->course->level->code).' · '.$l->module->course->title,
            ]);

        $courses = Course::where('is_published', true)
            ->whereLike('title', "%{$q}%", caseSensitive: false)
            ->with('level:id,code')
            ->limit(5)->get()
            ->map(fn ($c) => [
                'type' => 'course',
                'id' => $c->id,
                'title' => $c->title,
                'level_id' => $c->cefr_level_id,
                'subtitle' => strtoupper($c->level->code).' · '.$c->skill,
            ]);

        $words = Vocabulary::where(fn ($query) => $query->whereLike('word', "%{$q}%", caseSensitive: false)->orWhereLike('meaning_id', "%{$q}%", caseSensitive: false))
            ->limit(10)->get()
            ->map(fn ($v) => [
                'type' => 'vocabulary',
                'id' => $v->id,
                'title' => $v->word,
                'subtitle' => $v->definition,
            ]);

        return response()->json(['results' => $lessons->concat($courses)->concat($words)->values()]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'username' => ['sometimes', 'string', 'min:3', 'max:30', 'regex:/^[a-zA-Z0-9_]+$/', Rule::unique('users')->ignore($user->id)],
            'display_name' => ['sometimes', 'nullable', 'string', 'max:60'],
            'timezone' => ['sometimes', 'timezone'],
            'is_public' => ['sometimes', 'boolean'],
            'learning_goal' => ['sometimes', 'nullable', 'string', 'max:40'],
            'focus_skill' => ['sometimes', 'nullable', 'string', 'max:40'],
            'daily_goal_minutes' => ['sometimes', 'nullable', 'integer', 'between:5,240'],
        ]);

        $user->update($data);

        return response()->json(['user' => AuthController::userPayload($user->refresh())]);
    }

    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:2048'],
        ]);

        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $path = $request->file('avatar')->store('avatars', 'public');
        $user->update(['avatar_path' => $path]);

        return response()->json(['avatar_url' => '/storage/'.$path]);
    }

    public function onboard(Request $request): JsonResponse
    {
        $data = $request->validate([
            'learning_goal' => ['required', 'string', 'max:40'],
            'experience_level' => ['required', 'string', 'max:40'],
            'focus_skill' => ['required', 'string', 'max:40'],
            'daily_goal_minutes' => ['required', 'integer', 'between:5,240'],
            'timezone' => ['nullable', 'timezone'],
        ]);

        $user = $request->user();
        $user->fill($data);
        $user->onboarded_at = now();
        $user->save();

        return response()->json(['user' => AuthController::userPayload($user)]);
    }

    public function publicProfile(string $username): JsonResponse
    {
        $user = User::where('username', $username)->firstOrFail();

        abort_unless($user->is_public, 404, 'This profile is private.');

        return response()->json([
            'username' => $user->username,
            'display_name' => $user->display_name,
            'avatar_url' => $user->avatar_path ? '/storage/'.$user->avatar_path : null,
            'level' => $user->currentLevel?->only(['code', 'name']),
            'xp_total' => $user->xp_total,
            'account_level' => $user->accountLevel(),
            'lessons_completed' => $user->lessonCompletions()->count(),
            'achievements' => $user->achievements()->with('achievement')->get()
                ->map(fn ($ua) => ['name' => $ua->achievement->name, 'icon' => $ua->achievement->icon]),
            'member_since' => $user->created_at->toDateString(),
        ]);
    }

    public function achievements(Request $request): JsonResponse
    {
        $earned = $request->user()->achievements()->pluck('earned_at', 'achievement_id');

        $all = Achievement::where('is_active', true)->orderBy('threshold')->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'name' => $a->name,
                'description' => $a->description,
                'icon' => $a->icon,
                'earned' => $earned->has($a->id),
                'earned_at' => $earned->get($a->id),
            ]);

        return response()->json(['achievements' => $all]);
    }
}
