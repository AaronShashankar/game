<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Auth;
use App\Http\Request;
use App\Services\AdminService;
use App\Services\AuthService;
use App\Services\QuestionService;
use App\Services\SubjectService;

final class AdminController
{
    public function __construct(
        private AdminService $admin = new AdminService(),
        private AuthService $auth = new AuthService(),
        private QuestionService $questions = new QuestionService(),
        private SubjectService $subjects = new SubjectService(),
    ) {}

    public function stats(Request $r): array         { return ['stats' => $this->admin->stats()]; }
    public function users(Request $r): array         { return ['users' => $this->admin->users()]; }
    public function activity(Request $r): array      { return ['activity' => $this->admin->activity()]; }
    public function securityLogs(Request $r): array  { return ['logs' => $this->admin->securityLogs()]; }
    public function analytics(Request $r): array     { return ['analytics' => $this->admin->analytics()]; }
    public function export(Request $r): array        { return ['data' => $this->admin->export()]; }

    public function addUser(Request $r): array
    {
        $this->admin->addUser($r->required('username'), $r->required('email'), $r->required('password'),
                              $r->int('coins', 0), $r->int('level', 1));
        return ['message' => 'User added'];
    }

    public function deleteUser(Request $r): array
    {
        $this->admin->deleteUser($r->int('user_id'));
        return ['message' => 'User deleted'];
    }

    public function changePassword(Request $r): array
    {
        $this->auth->changeAdminPassword(Auth::adminId(), $r->required('current_password'), $r->required('new_password'));
        return ['message' => 'Password updated'];
    }

    // ---- Subjects -----------------------------------------------------------

    public function subjects(Request $r): array
    {
        return $this->subjects->list();
    }

    public function saveSubject(Request $r): array
    {
        $id = $r->get('id');
        $subject = $this->subjects->save($id === null || $id === '' ? null : $r->int('id'), [
            'name' => $r->get('name'), 'description' => $r->get('description'), 'icon' => $r->get('icon', 'book'),
        ]);
        return ['subject' => $subject];
    }

    public function subjectVisibility(Request $r): array
    {
        return ['subject' => $this->subjects->setVisible($r->int('id'), filter_var($r->get('visible'), FILTER_VALIDATE_BOOLEAN))];
    }

    public function deleteSubject(Request $r): array
    {
        $this->subjects->delete($r->int('id'));
        return ['message' => 'Subject deleted'];
    }

    // ---- Question bank ------------------------------------------------------

    public function questions(Request $r): array
    {
        return $this->questions->list([
            'subject' => $r->string('subject'), 'difficulty' => $r->string('difficulty'),
            'status' => $r->string('status'), 'q' => $r->string('q'),
        ]);
    }

    public function saveQuestion(Request $r): array
    {
        $id = $r->get('id');
        $question = $this->questions->save($id === null || $id === '' ? null : $r->int('id'), [
            'subject' => $r->get('subject'), 'difficulty' => $r->get('difficulty'), 'prompt' => $r->get('prompt'),
            'options' => $r->get('options'), 'answer_idx' => $r->get('answer_idx'),
            'explanation' => $r->get('explanation'), 'active' => $r->get('active', true),
        ]);
        return ['question' => $question];
    }

    public function questionStatus(Request $r): array
    {
        $this->questions->setActive($r->int('id'), filter_var($r->get('active'), FILTER_VALIDATE_BOOLEAN));
        return ['message' => 'Question updated'];
    }

    public function deleteQuestions(Request $r): array
    {
        $ids = $r->get('ids');
        if ($ids === null && $r->get('id') !== null) $ids = [$r->get('id')];
        return $this->questions->delete($ids);
    }

    public function importQuestions(Request $r): array
    {
        $csv = $r->get('csv');
        if (!is_string($csv) || trim($csv) === '') throw \App\Support\ApiError::badRequest('CSV_EMPTY', 'Choose a CSV file to import');
        return $this->questions->importCsv($csv);
    }
}
