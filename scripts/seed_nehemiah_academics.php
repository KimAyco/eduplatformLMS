<?php
/**
 * Seed subject catalog and BSIT program for Nehemiah College Davao.
 * Usage: php scripts/seed_nehemiah_academics.php
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$slug = 'nehemiah-college-davao';
$stmt = db()->prepare('SELECT id FROM schools WHERE slug = ? LIMIT 1');
$stmt->execute([$slug]);
$schoolId = (int) ($stmt->fetchColumn() ?: 0);

if ($schoolId <= 0) {
    fwrite(STDERR, "School not found: {$slug}\n");
    exit(1);
}

$subjects = [
    'ENG101' => 'Communication Skills 1',
    'FIL101' => 'Komunikasyon sa Akademikong Filipino',
    'MATH101' => 'College Algebra',
    'NSTP1' => 'National Service Training Program 1',
    'PE101' => 'Physical Education 1',
    'IT101' => 'Introduction to Computing',
    'CS101' => 'Computer Programming 1',
    'PHYS101' => 'General Physics',
    'HUM101' => 'Art Appreciation',
    'SOC101' => 'Understanding the Self',
    'ENG102' => 'Communication Skills 2',
    'MATH102' => 'Trigonometry',
    'PE102' => 'Physical Education 2',
    'IT102' => 'Computer Programming 2',
    'CS102' => 'Data Structures',
    'NET101' => 'Introduction to Networking',
    'DB101' => 'Database Systems',
    'WEB101' => 'Web Development',
    'IT201' => 'Object-Oriented Programming',
    'IT202' => 'Systems Analysis and Design',
    'IT203' => 'Operating Systems',
    'IT204' => 'Information Assurance and Security',
    'IT301' => 'Software Engineering',
    'IT302' => 'Mobile Application Development',
    'IT303' => 'Cloud Computing',
    'IT401' => 'Capstone Project 1',
    'IT402' => 'Capstone Project 2',
    'IT403' => 'IT Practicum',
];

$subjectIds = [];
$insertSubject = db()->prepare('INSERT IGNORE INTO subjects (school_id, name, description) VALUES (?, ?, ?)');
$findSubject = db()->prepare('SELECT id FROM subjects WHERE school_id = ? AND name = ?');

foreach ($subjects as $code => $description) {
    $insertSubject->execute([$schoolId, $code, $description]);
    $findSubject->execute([$schoolId, $code]);
    $subjectIds[$code] = (int) $findSubject->fetchColumn();
}

echo 'Subjects ready: ' . count($subjectIds) . PHP_EOL;

$programCheck = db()->prepare('SELECT id FROM programs WHERE school_id = ? AND name = ?');
$programCheck->execute([$schoolId, 'BSIT']);
$programId = (int) ($programCheck->fetchColumn() ?: 0);

if ($programId <= 0) {
    $programId = ProgramRepository::create(
        $schoolId,
        'BSIT',
        'BSIT',
        'Bachelor of Science in Information Technology — 4-year program'
    );
    echo "Created program BSIT (id {$programId})" . PHP_EOL;
} else {
    echo "Program BSIT already exists (id {$programId})" . PHP_EOL;
}

$levelNames = ['Year 1', 'Year 2', 'Year 3', 'Year 4'];
$levelIds = [];

foreach ($levelNames as $index => $levelName) {
    $existing = db()->prepare('SELECT id FROM program_levels WHERE program_id = ? AND level_order = ?');
    $existing->execute([$programId, $index + 1]);
    $levelId = (int) ($existing->fetchColumn() ?: 0);
    if ($levelId <= 0) {
        $levelId = ProgramRepository::addLevel($programId, $schoolId, $levelName) ?? 0;
        if ($levelId <= 0) {
            $stmt = db()->prepare('SELECT id FROM program_levels WHERE program_id = ? AND name = ?');
            $stmt->execute([$programId, $levelName]);
            $levelId = (int) ($stmt->fetchColumn() ?: 0);
        }
    }
    $levelIds[$levelName] = $levelId;
}

$curriculum = [
    'Year 1' => [
        '1st Semester' => ['ENG101', 'MATH101', 'IT101', 'NSTP1', 'PE101'],
        '2nd Semester' => ['FIL101', 'PHYS101', 'CS101', 'HUM101', 'PE102'],
    ],
    'Year 2' => [
        '1st Semester' => ['ENG102', 'MATH102', 'IT102', 'NET101', 'SOC101'],
        '2nd Semester' => ['CS102', 'DB101', 'WEB101', 'IT201'],
    ],
    'Year 3' => [
        '1st Semester' => ['IT202', 'IT203', 'IT204'],
        '2nd Semester' => ['IT301', 'IT302', 'IT303'],
    ],
    'Year 4' => [
        '1st Semester' => ['IT401'],
        '2nd Semester' => ['IT402', 'IT403'],
    ],
];

foreach ($curriculum as $levelName => $terms) {
    $levelId = $levelIds[$levelName] ?? 0;
    if ($levelId <= 0) {
        continue;
    }

    $termOrder = 1;
    foreach ($terms as $termName => $codes) {
        $termCheck = db()->prepare('SELECT id FROM program_terms WHERE program_level_id = ? AND term_order = ?');
        $termCheck->execute([$levelId, $termOrder]);
        $termId = (int) ($termCheck->fetchColumn() ?: 0);
        if ($termId <= 0) {
            $termId = ProgramRepository::addTerm($levelId, $schoolId, $termName) ?? 0;
        }
        if ($termId <= 0) {
            $termOrder++;
            continue;
        }

        $ids = [];
        foreach ($codes as $code) {
            if (!empty($subjectIds[$code])) {
                $ids[] = $subjectIds[$code];
            }
        }
        ProgramRepository::syncTermSubjects($termId, $schoolId, $ids);
        echo "  {$levelName} · {$termName}: " . count($ids) . ' subject(s)' . PHP_EOL;
        $termOrder++;
    }
}

echo "Done seeding Nehemiah College Davao academics.\n";
