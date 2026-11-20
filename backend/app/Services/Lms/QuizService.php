<?php

namespace App\Services\Lms;

use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\Student;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class QuizService
{
    public function __construct(
        protected TenantContext $tenantContext
    ) {}

    /**
     * Create an online quiz.
     */
    public function createQuiz(array $data, User $user): Quiz
    {
        return DB::transaction(function () use ($data, $user) {
            $quiz = Quiz::create([
                'tenant_id' => $this->tenantContext->getTenantId(),
                'branch_id' => $this->tenantContext->getBranchId(),
                'class_id' => $data['class_id'],
                'section_id' => $data['section_id'] ?? null,
                'subject_id' => $data['subject_id'],
                'created_by' => $user->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'duration_minutes' => $data['duration_minutes'] ?? 30,
                'total_marks' => $data['total_marks'] ?? 0,
                'pass_marks' => $data['pass_marks'] ?? 0,
                'start_time' => $data['start_time'] ?? null,
                'end_time' => $data['end_time'] ?? null,
                'is_published' => $data['is_published'] ?? false,
            ]);

            if (!empty($data['questions']) && is_array($data['questions'])) {
                $this->addQuestions($quiz->id, $data['questions']);
            }

            return $quiz->fresh(['schoolClass', 'section', 'subject', 'questions']);
        });
    }

    /**
     * Add or update questions for a quiz.
     */
    public function addQuestions(string $quizId, array $questions): array
    {
        $quiz = Quiz::findOrFail($quizId);
        $saved = [];
        $totalMarks = 0;

        foreach ($questions as $idx => $q) {
            $marks = isset($q['marks']) ? (float) $q['marks'] : 1.00;
            $totalMarks += $marks;

            $question = QuizQuestion::create([
                'tenant_id' => $quiz->tenant_id,
                'quiz_id' => $quiz->id,
                'question_text' => $q['question_text'],
                'question_type' => $q['question_type'], // multiple_choice, true_false, short_answer
                'marks' => $marks,
                'options' => $q['options'] ?? null,
                'correct_answer' => trim((string) $q['correct_answer']),
                'explanation' => $q['explanation'] ?? null,
                'order_number' => $q['order_number'] ?? ($idx + 1),
            ]);

            $saved[] = $question;
        }

        // Auto-recalculate total quiz marks
        $quiz->update([
            'total_marks' => $totalMarks > 0 ? $totalMarks : $quiz->total_marks,
        ]);

        return $saved;
    }

    /**
     * Publish or unpublish quiz.
     */
    public function publishQuiz(string $quizId, bool $publish = true): Quiz
    {
        $quiz = Quiz::findOrFail($quizId);
        $quiz->update(['is_published' => $publish]);
        return $quiz->fresh();
    }

    /**
     * Start a quiz attempt for a student.
     */
    public function startQuizAttempt(string $quizId, User $user, ?string $studentId = null): QuizAttempt
    {
        $quiz = Quiz::findOrFail($quizId);

        if (!$studentId) {
            $student = Student::where('user_id', $user->id)->first();
            if (!$student) {
                throw new AuthorizationException('User has no linked student profile.');
            }
            $studentId = $student->id;
        }

        // Check if student already completed or has active attempt
        $existing = QuizAttempt::where('quiz_id', $quiz->id)
            ->where('student_id', $studentId)
            ->where('status', 'in_progress')
            ->first();

        if ($existing) {
            return $existing;
        }

        return QuizAttempt::create([
            'tenant_id' => $this->tenantContext->getTenantId(),
            'branch_id' => $quiz->branch_id,
            'quiz_id' => $quiz->id,
            'student_id' => $studentId,
            'started_at' => Carbon::now(),
            'score' => 0.00,
            'total_marks' => (float) $quiz->total_marks,
            'percentage' => 0.00,
            'is_passed' => false,
            'status' => 'in_progress',
            'answers' => [],
        ]);
    }

    /**
     * Submit and auto-grade quiz attempt.
     */
    public function submitQuizAttempt(string $attemptId, array $submittedAnswers): QuizAttempt
    {
        $attempt = QuizAttempt::with(['quiz.questions', 'student'])->findOrFail($attemptId);
        $quiz = $attempt->quiz;

        $totalScore = 0.00;
        $gradedAnswers = [];
        $questions = $quiz->questions->keyBy('id');

        foreach ($submittedAnswers as $entry) {
            $qId = $entry['question_id'] ?? null;
            $givenAnswer = trim((string) ($entry['answer'] ?? ''));

            if (!$qId || !$questions->has($qId)) {
                continue;
            }

            $question = $questions->get($qId);
            $qType = $question->question_type;
            $correctAns = trim((string) $question->correct_answer);
            $isCorrect = false;

            if ($qType === 'multiple_choice') {
                $isCorrect = (strcasecmp($givenAnswer, $correctAns) === 0);
            } elseif ($qType === 'true_false') {
                $normGiven = in_array(strtolower($givenAnswer), ['true', '1', 't', 'yes']);
                $normCorrect = in_array(strtolower($correctAns), ['true', '1', 't', 'yes']);
                $isCorrect = ($normGiven === $normCorrect);
            } elseif ($qType === 'short_answer') {
                $isCorrect = (strcasecmp($givenAnswer, $correctAns) === 0);
            }

            $marksAwarded = $isCorrect ? (float) $question->marks : 0.00;
            $totalScore += $marksAwarded;

            $gradedAnswers[] = [
                'question_id' => $question->id,
                'question_text' => $question->question_text,
                'question_type' => $question->question_type,
                'given_answer' => $givenAnswer,
                'correct_answer' => $correctAns,
                'is_correct' => $isCorrect,
                'marks_awarded' => $marksAwarded,
                'max_marks' => (float) $question->marks,
            ];
        }

        $totalMarks = (float) $quiz->total_marks > 0 ? (float) $quiz->total_marks : 1.00;
        $percentage = round(($totalScore / $totalMarks) * 100, 2);
        $passMarks = (float) $quiz->pass_marks;
        $isPassed = ($totalScore >= $passMarks);

        $attempt->update([
            'completed_at' => Carbon::now(),
            'score' => $totalScore,
            'total_marks' => $totalMarks,
            'percentage' => $percentage,
            'is_passed' => $isPassed,
            'status' => 'completed',
            'answers' => $gradedAnswers,
        ]);

        return $attempt->fresh(['quiz', 'student']);
    }

    /**
     * Get aggregate result report for a quiz.
     */
    public function getQuizReport(string $quizId): array
    {
        $quiz = Quiz::with('questions')->findOrFail($quizId);
        $attempts = QuizAttempt::with('student')
            ->where('quiz_id', $quiz->id)
            ->where('status', 'completed')
            ->orderBy('score', 'desc')
            ->get();

        $totalAttempts = $attempts->count();
        $passedCount = $attempts->where('is_passed', true)->count();
        $averageScore = $totalAttempts > 0 ? round($attempts->avg('score'), 2) : 0;
        $highestScore = $totalAttempts > 0 ? (float) $attempts->max('score') : 0;
        $lowestScore = $totalAttempts > 0 ? (float) $attempts->min('score') : 0;
        $passPercentage = $totalAttempts > 0 ? round(($passedCount / $totalAttempts) * 100, 2) : 0;

        return [
            'quiz' => $quiz,
            'summary' => [
                'total_attempts' => $totalAttempts,
                'passed_count' => $passedCount,
                'failed_count' => $totalAttempts - $passedCount,
                'pass_percentage' => $passPercentage,
                'average_score' => $averageScore,
                'highest_score' => $highestScore,
                'lowest_score' => $lowestScore,
            ],
            'attempts' => $attempts,
        ];
    }

    /**
     * List quizzes with filters.
     */
    public function listQuizzes(array $filters = []): Collection
    {
        $query = Quiz::with(['schoolClass', 'section', 'subject', 'creator'])
            ->withCount(['questions', 'attempts'])
            ->orderBy('created_at', 'desc');

        if (!empty($filters['class_id'])) {
            $query->where('class_id', $filters['class_id']);
        }

        if (!empty($filters['subject_id'])) {
            $query->where('subject_id', $filters['subject_id']);
        }

        if (isset($filters['is_published'])) {
            $query->where('is_published', filter_var($filters['is_published'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query->get();
    }
}

