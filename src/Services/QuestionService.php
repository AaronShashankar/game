<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\QuestionRepository;
use App\Support\ApiError;
use App\Support\Database;

// Admin management of the question bank: validation, edits and CSV import.
// Questions are archived, never deleted, because past answers refer to them.
final class QuestionService
{
    public const CSV_COLUMNS = ['subject', 'difficulty', 'prompt', 'option1', 'option2', 'option3', 'option4', 'answer', 'explanation'];
    private const MAX_CSV_BYTES = 512_000;
    private const MAX_CSV_ROWS = 1000;

    public function __construct(private QuestionRepository $questions = new QuestionRepository()) {}

    public function list(array $filters): array
    {
        return [
            'questions' => $this->questions->search($filters),
            'subjects'  => $this->questions->allSubjects(),
        ];
    }

    public function save(?int $id, array $input): array
    {
        $q = $this->validate($input);
        if ($id === null) {
            $id = $this->questions->create($q);
        } elseif (!$this->questions->update($id, $q)) {
            throw ApiError::notFound('Question not found');
        }
        return $this->questions->find($id);
    }

    public function setActive(int $id, bool $active): void
    {
        if ($this->questions->find($id) === null) throw ApiError::notFound('Question not found');
        $this->questions->setActive($id, $active);
    }

    // Imports every row or none. Returns counts, or throws with every row's problems listed.
    public function importCsv(string $csv): array
    {
        if (strlen($csv) > self::MAX_CSV_BYTES) throw ApiError::badRequest('CSV_TOO_LARGE', 'CSV must be under 500 KB');
        $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv);   // Excel's UTF-8 byte order mark

        $lines = $this->parseCsv($csv);
        if (!$lines) throw ApiError::badRequest('CSV_EMPTY', 'The file has no rows');
        $header = array_map(fn ($h) => strtolower(trim((string) $h)), array_shift($lines));
        if ($header !== self::CSV_COLUMNS) {
            throw ApiError::badRequest('CSV_HEADER', 'The first row must be: ' . implode(',', self::CSV_COLUMNS));
        }
        if (count($lines) > self::MAX_CSV_ROWS) throw ApiError::badRequest('CSV_TOO_LARGE', 'At most ' . self::MAX_CSV_ROWS . ' rows per import');

        $valid = [];
        $errors = [];
        foreach ($lines as $i => $cells) {
            $rowNo = $i + 2;   // 1-based, after the header
            if (count(array_filter($cells, fn ($c) => trim((string) $c) !== '')) === 0) continue;   // blank line
            if (count($cells) !== count(self::CSV_COLUMNS)) {
                $errors[] = "Row $rowNo: expected " . count(self::CSV_COLUMNS) . ' columns, found ' . count($cells);
                continue;
            }
            $r = array_combine(self::CSV_COLUMNS, array_map(fn ($c) => trim((string) $c), $cells));
            try {
                if (!ctype_digit($r['answer'])) throw ApiError::badRequest('INVALID_ANSWER', 'answer must be the option number (1–4)');
                $valid[$rowNo] = $this->validate([
                    'subject' => $r['subject'], 'difficulty' => ucfirst(strtolower($r['difficulty'])), 'prompt' => $r['prompt'],
                    'options' => [$r['option1'], $r['option2'], $r['option3'], $r['option4']], 'answer_idx' => (int) $r['answer'] - 1, 'explanation' => $r['explanation'], 'active' => true,
                ]);
            } catch (ApiError $e) {
                $errors[] = "Row $rowNo: " . $e->getMessage();
            }
        }
        if ($errors) {
            throw new ApiError(422, 'CSV_INVALID', "Nothing was imported. Fix these rows and try again:\n" . implode("\n", array_slice($errors, 0, 20))
                . (count($errors) > 20 ? "\n…and " . (count($errors) - 20) . ' more' : ''));
        }

        return Database::transaction(function () use ($valid) {
            $added = $skipped = 0;
            foreach ($valid as $q) {
                if ($this->questions->existsInSubject($q['subject'], $q['prompt'])) { $skipped++; continue; }
                $this->questions->create($q);
                $added++;
            }
            return ['added' => $added, 'skipped_duplicates' => $skipped];
        });
    }

    private function parseCsv(string $csv): array
    {
        $fh = fopen('php://temp', 'r+');
        fwrite($fh, $csv);
        rewind($fh);
        $rows = [];
        while (($cells = fgetcsv($fh, 0, ',', '"', '')) !== false) {
            if ($cells === [null]) continue;   // empty line
            $rows[] = $cells;
        }
        fclose($fh);
        return $rows;
    }

    private function validate(array $in): array
    {
        $subject = trim((string) ($in['subject'] ?? ''));
        $difficulty = (string) ($in['difficulty'] ?? '');
        $prompt = trim((string) ($in['prompt'] ?? ''));
        $explanation = trim((string) ($in['explanation'] ?? ''));
        // answer_idx points into the options as given (blanks included), so
        // resolve the correct option before dropping empty ones.
        $raw = is_array($in['options'] ?? null) ? array_values(array_map(fn ($o) => trim((string) $o), $in['options'])) : [];
        $answerIn = $in['answer_idx'] ?? null;
        $correctText = is_numeric($answerIn) ? ($raw[(int) $answerIn] ?? '') : '';
        $options = array_values(array_filter($raw, fn ($o) => $o !== ''));

        $fail = fn (string $msg) => throw ApiError::badRequest('INVALID_QUESTION', $msg);
        if ($subject === '' || mb_strlen($subject) > 100) $fail('Subject is required (up to 100 characters)');
        if ($subject === GameRules::MIXED_SUBJECT) $fail('"' . GameRules::MIXED_SUBJECT . '" is reserved; pick a real subject');
        if (!in_array($difficulty, GameRules::QUESTION_DIFFICULTIES, true)) $fail('Difficulty must be Easy, Medium or Hard');
        if ($prompt === '' || mb_strlen($prompt) > 500) $fail('Question text is required (up to 500 characters)');
        if (count($options) < 2 || count($options) > 4) $fail('Give 2 to 4 answer options');
        foreach ($options as $o) if (mb_strlen($o) > 200) $fail('Each option can be up to 200 characters');
        if (count(array_unique(array_map('mb_strtolower', $options))) !== count($options)) $fail('Options must all be different');
        if ($correctText === '') $fail('The correct answer must be one of the filled-in options');
        if (mb_strlen($explanation) > 500) $fail('Explanation can be up to 500 characters');

        return [
            'subject' => $subject, 'difficulty' => $difficulty, 'prompt' => $prompt, 'options' => $options,
            'answer_idx' => array_search($correctText, $options, true), 'explanation' => $explanation === '' ? null : $explanation,
            'active' => filter_var($in['active'] ?? true, FILTER_VALIDATE_BOOLEAN),
        ];
    }
}
