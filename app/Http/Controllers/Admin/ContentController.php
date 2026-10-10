<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CefrLevel;
use App\Models\Course;
use App\Models\Exercise;
use App\Models\Lesson;
use App\Models\LessonSection;
use App\Models\Module;
use App\Models\Question;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ContentController extends Controller
{
    // ---- CEFR levels ----
    public function levels(): JsonResponse
    {
        return response()->json(['levels' => CefrLevel::withCount('courses')->orderBy('sort_order')->get()]);
    }

    public function updateLevel(Request $request, CefrLevel $level): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:80'],
            'description' => ['sometimes', 'nullable', 'string'],
            'exam_question_count' => ['sometimes', 'integer', 'between:5,100'],
            'exam_time_limit_minutes' => ['sometimes', 'integer', 'between:5,180'],
            'exam_pass_score' => ['sometimes', 'integer', 'between:50,100'],
        ]);
        $level->update($data);

        return response()->json(['level' => $level]);
    }

    // ---- Courses ----
    public function courses(Request $request): JsonResponse
    {
        $query = Course::with('level:id,code,name')->withCount('modules');
        if ($levelId = $request->query('level_id')) {
            $query->where('cefr_level_id', $levelId);
        }

        return response()->json(['courses' => $query->orderBy('cefr_level_id')->orderBy('sort_order')->get()]);
    }

    public function storeCourse(Request $request): JsonResponse
    {
        $data = $request->validate([
            'cefr_level_id' => ['required', 'exists:cefr_levels,id'],
            'skill' => ['required', Rule::in(['grammar', 'vocabulary', 'listening', 'speaking', 'reading', 'writing', 'pronunciation', 'everyday'])],
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'is_published' => ['boolean'],
        ]);

        $data['slug'] = $this->uniqueSlug(Course::class, $data['title']);
        $data['sort_order'] = (Course::where('cefr_level_id', $data['cefr_level_id'])->max('sort_order') ?? 0) + 1;

        return response()->json(['course' => Course::create($data)], 201);
    }

    public function updateCourse(Request $request, Course $course): JsonResponse
    {
        $data = $request->validate([
            'cefr_level_id' => ['sometimes', 'exists:cefr_levels,id'],
            'skill' => ['sometimes', Rule::in(['grammar', 'vocabulary', 'listening', 'speaking', 'reading', 'writing', 'pronunciation', 'everyday'])],
            'title' => ['sometimes', 'string', 'max:120'],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_published' => ['sometimes', 'boolean'],
        ]);
        $course->update($data);

        return response()->json(['course' => $course]);
    }

    public function deleteCourse(Course $course): JsonResponse
    {
        $course->delete();

        return response()->json(['message' => 'Course deleted.']);
    }

    // ---- Modules ----
    public function modules(Request $request): JsonResponse
    {
        $query = Module::with('course:id,title,cefr_level_id')->withCount('lessons');
        if ($courseId = $request->query('course_id')) {
            $query->where('course_id', $courseId);
        }

        return response()->json(['modules' => $query->orderBy('course_id')->orderBy('sort_order')->get()]);
    }

    public function storeModule(Request $request): JsonResponse
    {
        $data = $request->validate([
            'course_id' => ['required', 'exists:courses,id'],
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'is_published' => ['boolean'],
        ]);
        $data['sort_order'] = (Module::where('course_id', $data['course_id'])->max('sort_order') ?? 0) + 1;

        return response()->json(['module' => Module::create($data)], 201);
    }

    public function updateModule(Request $request, Module $module): JsonResponse
    {
        $data = $request->validate([
            'course_id' => ['sometimes', 'exists:courses,id'],
            'title' => ['sometimes', 'string', 'max:120'],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_published' => ['sometimes', 'boolean'],
        ]);
        $module->update($data);

        return response()->json(['module' => $module]);
    }

    public function deleteModule(Module $module): JsonResponse
    {
        $module->delete();

        return response()->json(['message' => 'Module deleted.']);
    }

    // ---- Lessons ----
    public function lessons(Request $request): JsonResponse
    {
        $query = Lesson::with('module:id,title,course_id', 'module.course:id,title,cefr_level_id')
            ->withCount(['sections', 'exercises']);
        if ($moduleId = $request->query('module_id')) {
            $query->where('module_id', $moduleId);
        }
        if ($search = $request->query('q')) {
            $query->whereLike('title', "%{$search}%", caseSensitive: false);
        }

        return response()->json(['lessons' => $query->orderBy('module_id')->orderBy('sort_order')->paginate(30)]);
    }

    public function showLesson(Lesson $lesson): JsonResponse
    {
        $lesson->load(['sections', 'exercises.questions.options', 'prerequisites:lessons.id,title']);

        return response()->json(['lesson' => $lesson]);
    }

    public function storeLesson(Request $request): JsonResponse
    {
        $data = $request->validate([
            'module_id' => ['required', 'exists:modules,id'],
            'title' => ['required', 'string', 'max:150'],
            'objective' => ['nullable', 'string'],
            'xp_reward' => ['integer', 'between:0,500'],
            'estimated_minutes' => ['integer', 'between:1,120'],
            'is_published' => ['boolean'],
        ]);
        $data['slug'] = $this->uniqueSlug(Lesson::class, $data['title']);
        $data['sort_order'] = (Lesson::where('module_id', $data['module_id'])->max('sort_order') ?? 0) + 1;
        $data['xp_reward'] ??= (int) Setting::get('xp_lesson_default', 20);

        return response()->json(['lesson' => Lesson::create($data)], 201);
    }

    public function updateLesson(Request $request, Lesson $lesson): JsonResponse
    {
        $data = $request->validate([
            'module_id' => ['sometimes', 'exists:modules,id'],
            'title' => ['sometimes', 'string', 'max:150'],
            'objective' => ['sometimes', 'nullable', 'string'],
            'xp_reward' => ['sometimes', 'integer', 'between:0,500'],
            'estimated_minutes' => ['sometimes', 'integer', 'between:1,120'],
            'is_published' => ['sometimes', 'boolean'],
            'prerequisite_ids' => ['sometimes', 'array'],
            'prerequisite_ids.*' => ['integer', 'exists:lessons,id'],
        ]);

        if (array_key_exists('prerequisite_ids', $data)) {
            $ids = array_values(array_filter($data['prerequisite_ids'], fn ($id) => $id !== $lesson->id));
            $lesson->prerequisites()->sync($ids);
            unset($data['prerequisite_ids']);
        }

        $lesson->update($data);

        return response()->json(['lesson' => $lesson->load('prerequisites:lessons.id,title')]);
    }

    public function duplicateLesson(Lesson $lesson): JsonResponse
    {
        $copy = DB::transaction(function () use ($lesson) {
            $copy = $lesson->replicate(['slug']);
            $copy->title = $lesson->title.' (copy)';
            $copy->slug = $this->uniqueSlug(Lesson::class, $copy->title);
            $copy->is_published = false;
            $copy->sort_order = (Lesson::where('module_id', $lesson->module_id)->max('sort_order') ?? 0) + 1;
            $copy->save();

            foreach ($lesson->sections as $section) {
                $new = $section->replicate();
                $new->lesson_id = $copy->id;
                $new->save();
            }
            foreach ($lesson->exercises as $exercise) {
                $newEx = $exercise->replicate();
                $newEx->lesson_id = $copy->id;
                $newEx->save();
                foreach ($exercise->questions as $question) {
                    $newQ = $question->replicate();
                    $newQ->exercise_id = $newEx->id;
                    $newQ->save();
                    foreach ($question->options as $option) {
                        $newO = $option->replicate();
                        $newO->question_id = $newQ->id;
                        $newO->save();
                    }
                }
            }

            return $copy;
        });

        return response()->json(['lesson' => $copy], 201);
    }

    public function deleteLesson(Lesson $lesson): JsonResponse
    {
        $lesson->delete();

        return response()->json(['message' => 'Lesson deleted.']);
    }

    // ---- Lesson sections ----
    public function storeSection(Request $request, Lesson $lesson): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['intro', 'explanation', 'examples', 'vocabulary', 'dialogue', 'reading', 'listening', 'note', 'tip'])],
            'title' => ['nullable', 'string', 'max:150'],
            'body' => ['nullable', 'string'],
            'body_id' => ['nullable', 'string'],
            'meta' => ['nullable', 'array'],
        ]);
        $data['lesson_id'] = $lesson->id;
        $data['sort_order'] = (LessonSection::where('lesson_id', $lesson->id)->max('sort_order') ?? 0) + 1;

        return response()->json(['section' => LessonSection::create($data)], 201);
    }

    public function updateSection(Request $request, LessonSection $section): JsonResponse
    {
        $data = $request->validate([
            'type' => ['sometimes', Rule::in(['intro', 'explanation', 'examples', 'vocabulary', 'dialogue', 'reading', 'listening', 'note', 'tip'])],
            'title' => ['sometimes', 'nullable', 'string', 'max:150'],
            'body' => ['sometimes', 'nullable', 'string'],
            'body_id' => ['sometimes', 'nullable', 'string'],
            'meta' => ['sometimes', 'nullable', 'array'],
        ]);
        $section->update($data);

        return response()->json(['section' => $section]);
    }

    public function deleteSection(LessonSection $section): JsonResponse
    {
        $section->delete();

        return response()->json(['message' => 'Section deleted.']);
    }

    // ---- Reordering (drag & drop persistence) ----
    public function reorder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['courses', 'modules', 'lessons', 'exercises', 'sections', 'questions'])],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $model = match ($data['type']) {
            'courses' => Course::class,
            'modules' => Module::class,
            'lessons' => Lesson::class,
            'exercises' => Exercise::class,
            'sections' => LessonSection::class,
            'questions' => Question::class,
        };

        DB::transaction(function () use ($model, $data) {
            foreach ($data['ids'] as $index => $id) {
                $model::where('id', $id)->update(['sort_order' => $index + 1]);
            }
        });

        return response()->json(['message' => 'Order saved.']);
    }

    private function uniqueSlug(string $model, string $title): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $i = 2;
        while ($model::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
