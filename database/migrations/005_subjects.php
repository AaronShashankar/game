<?php
// Subjects become rows of their own, managed from the admin panel.
//   subjects   name, description and icon shown on the subject picker, and
//              whether players can see it (new subjects start hidden)
//   questions  subject now references subjects.name, so a question can't
//              point at a subject that doesn't exist, and a rename cascades

return function (PDO $db, Migrator $m): void {
    // A foreign key between string columns needs the same collation on both
    // sides, so copy whatever questions.subject already uses.
    $collation = $db->query("SELECT collation_name FROM information_schema.columns
        WHERE table_schema = DATABASE() AND table_name = 'questions' AND column_name = 'subject'")->fetchColumn();
    if (!is_string($collation) || !preg_match('/^\w+$/', $collation)) $collation = 'utf8mb4_unicode_ci';

    $db->exec("CREATE TABLE IF NOT EXISTS subjects (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) COLLATE $collation NOT NULL,
        description VARCHAR(200) NULL,
        icon VARCHAR(30) NOT NULL DEFAULT 'book',
        visible TINYINT(1) NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL,
        UNIQUE KEY uq_subject_name (name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=$collation");

    // Every subject already in the question bank stays playable.
    $added = $db->exec('INSERT IGNORE INTO subjects (name, visible) SELECT DISTINCT subject, 1 FROM questions');
    $m->say("subjects: $added added from the question bank");

    // The picker's copy used to be hard-coded in views/user/choose-subject.php.
    $meta = [
        'Mathematics'        => ['calculator', 'Numbers, arithmetic and problem solving'],
        'Science'            => ['flask', 'Physics, chemistry and biology'],
        'General Knowledge'  => ['globe', 'World facts, geography and culture'],
        'History'            => ['landmark', 'Events, people and civilisations'],
        'English Vocabulary' => ['type', 'Words, meanings and synonyms'],
    ];
    $describe = $db->prepare('UPDATE subjects SET icon = ?, description = ? WHERE name = ? AND description IS NULL');
    foreach ($meta as $name => [$icon, $desc]) $describe->execute([$icon, $desc, $name]);

    $hasFk = $db->query("SELECT 1 FROM information_schema.table_constraints
        WHERE table_schema = DATABASE() AND table_name = 'questions' AND constraint_name = 'fk_questions_subject'")->fetchColumn();
    if (!$hasFk) {
        $db->exec('ALTER TABLE questions ADD CONSTRAINT fk_questions_subject
                   FOREIGN KEY (subject) REFERENCES subjects(name) ON UPDATE CASCADE');
    }
};
