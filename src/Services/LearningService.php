<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\QuestionRepository;
use App\Repositories\ReviewRepository;

// What a player is learning: spaced repetition of missed questions and
// per-subject mastery.
//
// Leitner boxes: a wrong answer puts the question in box 1, due now (it can
// come back in the next maze). Each correct answer while it's being reviewed
// moves it up a box and pushes the due date out (1, 3, 7, 14, then 30 days);
// a correct answer after the 30-day wait retires it. A wrong answer at any point
// sends it back to box 1.
final class LearningService
{
    public function __construct(
        private ReviewRepository $reviews = new ReviewRepository(),
        private QuestionRepository $questions = new QuestionRepository(),
    ) {}

    // Returns true if the question was one being reviewed.
    public function recordAnswer(int $userId, int $questionId, bool $correct): bool
    {
        $row = $this->reviews->find($userId, $questionId);
        if (!$correct) {
            $this->reviews->restart($userId, $questionId);
            return $row !== null;
        }
        if ($row === null) return false;   // never missed: nothing to review

        $box = (int) $row['box'];
        $days = GameRules::reviewInterval($box);
        if ($days === null) {
            $this->reviews->remove($userId, $questionId);
        } else {
            $this->reviews->promote($userId, $questionId, $box + 1, $days);
        }
        return true;
    }

    public function summary(int $userId): array
    {
        $counts = $this->reviews->counts($userId);
        return [
            'reviews_due' => $counts['due'],
            'reviewing'   => $counts['learning'],
            'mastery'     => $this->questions->masteryFor($userId),
        ];
    }
}
