<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\QuestionRepository;
use App\Repositories\RunRepository;
use App\Repositories\UserRepository;

// The achievement catalogue and the rules that unlock each one. Rules are
// checked on the server after the events that can satisfy them.
final class AchievementService
{
    // code => [name, description, icon]
    public const CATALOGUE = [
        'first_clear'   => ['First steps',     'Finish your first maze',                                  'flag'],
        'perfect_run'   => ['Flawless',        'Finish a maze without a wrong answer or a skip',          'sparkles'],
        'combo_5'       => ['On a roll',       'Answer 5 questions in a row correctly in one maze',       'zap'],
        'hard_clear'    => ['Into the fog',    'Finish a Hard maze',                                      'eye-off'],
        'speedrun'      => ['Speedrunner',     'Finish a Medium or Hard maze in under 90 seconds',        'clock'],
        'daily_first'   => ['Daily explorer',  'Finish a daily maze',                                     'calendar'],
        'challenge_win' => ['Challenger',      "Beat a friend's time on their maze",                      'trophy'],
        'all_subjects'  => ['All-rounder',     'Finish a maze in every subject',                          'globe'],
        'scholar'       => ['Scholar',         'Answer 100 questions correctly',                          'book'],
        'level_5'       => ['Rising star',     'Reach level 5',                                           'star'],
        'level_10'      => ['Veteran',         'Reach level 10',                                          'award'],
        'rich'          => ['Rich explorer',   'Hold 500 coins at once',                                  'coins'],
        'streak_10'     => ['Dedicated',       'Keep a 10-day check-in streak',                           'flame'],
    ];

    public const SPEEDRUN_SECONDS = 90;
    public const COMBO_TARGET = 5;
    public const SCHOLAR_TARGET = 100;

    public function __construct(
        private UserRepository $users = new UserRepository(),
        private RunRepository $runs = new RunRepository(),
        private QuestionRepository $questions = new QuestionRepository(),
    ) {}

    // Checks every rule that the current state can satisfy and unlocks new ones.
    //   $run:     the run just answered in or completed (optional)
    //   $outcome: facts about a completed run: wrong, skipped, challenge_won
    // Returns the newly unlocked achievements for the UI to announce.
    public function check(int $userId, ?array $run = null, ?array $outcome = null): array
    {
        $u = $this->users->find($userId);
        $met = [
            'level_5'   => (int) $u['level'] >= 5,
            'level_10'  => (int) $u['level'] >= 10,
            'rich'      => (int) $u['coins'] >= 500,
            'streak_10' => (int) $u['streak'] >= 10,
            'scholar'   => (int) $u['correct_answers'] >= self::SCHOLAR_TARGET,
        ];
        if ($run !== null) {
            $met['combo_5'] = (int) $run['best_combo'] >= self::COMBO_TARGET;
        }
        if ($outcome !== null) {
            $met['first_clear'] = true;
            $met['perfect_run'] = $outcome['wrong'] === 0 && $outcome['skipped'] === 0;
            $met['hard_clear'] = $run['difficulty'] === 'Hard';
            $met['speedrun'] = $run['difficulty'] !== 'Easy' && (int) $run['duration_seconds'] < self::SPEEDRUN_SECONDS;
            $met['daily_first'] = $run['mode'] === 'daily';
            $met['challenge_win'] = (bool) ($outcome['challenge_won'] ?? false);
            $met['all_subjects'] = !array_diff($this->questions->subjects(), $this->runs->completedSubjects($userId));
        }

        $new = [];
        foreach ($met as $code => $ok) {
            if ($ok && $this->users->unlockAchievement($userId, $code)) $new[] = self::describe($code);
        }
        return $new;
    }

    // The whole catalogue with this player's unlock dates (null = locked).
    public function forUser(int $userId): array
    {
        $unlocked = $this->users->achievements($userId);
        $out = [];
        foreach (self::CATALOGUE as $code => $_) {
            $out[] = self::describe($code) + ['unlocked_at' => $unlocked[$code] ?? null];
        }
        return $out;
    }

    public static function describe(string $code): array
    {
        [$name, $description, $icon] = self::CATALOGUE[$code];
        return ['code' => $code, 'name' => $name, 'description' => $description, 'icon' => $icon];
    }
}
