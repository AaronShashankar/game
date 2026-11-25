<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\SubjectRepository;
use App\Support\ApiError;
use App\Support\Database;

// Admin management of subjects. A new subject starts hidden so its questions
// can be written before players see it; it can be shown once it has enough
// active questions for a full maze.
final class SubjectService
{
    // Icons an admin can pick for a subject (symbols in views/layouts/icons.php).
    public const ICONS = ['book', 'calculator', 'flask', 'globe', 'landmark', 'type', 'monitor', 'code', 'leaf', 'music', 'palette', 'bulb'];

    public function __construct(private SubjectRepository $subjects = new SubjectRepository()) {}

    public function list(): array
    {
        return [
            'subjects'      => $this->subjects->listWithCounts(),
            'icons'         => self::ICONS,
            'min_questions' => GameRules::minSubjectQuestions(),
        ];
    }

    public function save(?int $id, array $input): array
    {
        $name = preg_replace('/\s+/u', ' ', trim((string) ($input['name'] ?? '')));
        $description = trim((string) ($input['description'] ?? ''));
        $icon = (string) ($input['icon'] ?? 'book');

        $fail = fn (string $msg) => throw ApiError::badRequest('INVALID_SUBJECT', $msg);
        if ($name === '' || mb_strlen($name) > 100) $fail('Subject name is required (up to 100 characters)');
        if (strcasecmp($name, GameRules::MIXED_SUBJECT) === 0) $fail('"' . GameRules::MIXED_SUBJECT . '" is reserved; pick another name');
        if (mb_strlen($description) > 200) $fail('Description can be up to 200 characters');
        if (!in_array($icon, self::ICONS, true)) $fail('Pick one of the listed icons');
        $description = $description === '' ? null : $description;

        return Database::transaction(function () use ($id, $name, $description, $icon) {
            $clash = $this->subjects->findByName($name);   // case-insensitive
            if ($clash !== null && $clash['id'] !== $id) {
                throw ApiError::conflict('SUBJECT_EXISTS', "There is already a subject called \"{$clash['name']}\"");
            }
            if ($id === null) {
                $id = $this->subjects->create($name, $description, $icon);
            } else {
                $current = $this->subjects->find($id) ?? throw ApiError::notFound('Subject not found');
                $this->subjects->update($id, $current['name'], $name, $description, $icon);
            }
            return $this->subjects->find($id);
        });
    }

    public function setVisible(int $id, bool $visible): array
    {
        $subject = $this->subjects->find($id) ?? throw ApiError::notFound('Subject not found');
        if ($visible) {
            $min = GameRules::minSubjectQuestions();
            $active = $this->subjects->questionCounts($subject['name'])['active'];
            if ($active < $min) {
                throw ApiError::conflict('NOT_ENOUGH_QUESTIONS',
                    "\"{$subject['name']}\" has $active active question" . ($active === 1 ? '' : 's')
                    . ". Add at least $min so a Hard maze doesn't repeat questions.");
            }
        }
        $this->subjects->setVisible($id, $visible);
        return $this->subjects->find($id);
    }

    // Only empty subjects can be deleted: past answers point at questions, and
    // questions point at their subject. Hide a subject to take it out of play.
    public function delete(int $id): void
    {
        $subject = $this->subjects->find($id) ?? throw ApiError::notFound('Subject not found');
        $total = $this->subjects->questionCounts($subject['name'])['total'];
        if ($total > 0) {
            throw ApiError::conflict('SUBJECT_IN_USE',
                "\"{$subject['name']}\" has $total question" . ($total === 1 ? '' : 's') . '. Hide it instead, or move its questions to another subject first.');
        }
        $this->subjects->delete($id);
    }
}
