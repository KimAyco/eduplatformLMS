<?php
/**
 * Seed sample Virtual Library resources for development/demo.
 * Usage: php scripts/seed_library_resources.php [school-slug]
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$slug = $argv[1] ?? 'nehemiah-college-davao';

$stmt = db()->prepare('SELECT id FROM schools WHERE slug = ? LIMIT 1');
$stmt->execute([$slug]);
$schoolId = (int) ($stmt->fetchColumn() ?: 0);

if ($schoolId <= 0) {
    fwrite(STDERR, "School not found: {$slug}\n");
    exit(1);
}

$adminStmt = db()->prepare("SELECT id FROM users WHERE school_id = ? AND role = 'school_admin' AND status = 'active' ORDER BY id ASC LIMIT 1");
$adminStmt->execute([$schoolId]);
$adminId = (int) ($adminStmt->fetchColumn() ?: 0);

if ($adminId <= 0) {
    fwrite(STDERR, "No active school admin found for school id {$schoolId}\n");
    exit(1);
}

$marker = 'seed:v1:';
$existing = db()->prepare('SELECT COUNT(*) FROM library_resources WHERE school_id = ? AND description LIKE ?');
$existing->execute([$schoolId, $marker . '%']);
if ((int) $existing->fetchColumn() > 0) {
    echo "Library seed data already exists for {$slug}. Skipping.\n";
    exit(0);
}

$subjectIds = [];
$subjStmt = db()->prepare('SELECT id, name FROM subjects WHERE school_id = ?');
$subjStmt->execute([$schoolId]);
while ($row = $subjStmt->fetch()) {
    $subjectIds[$row['name']] = (int) $row['id'];
}

$libraryDir = UPLOAD_DIR . '/' . $schoolId . '/library';
if (!is_dir($libraryDir)) {
    mkdir($libraryDir, 0755, true);
}

$sampleFilePath = $schoolId . '/library/seed-intro-to-computing.txt';
$sampleFullPath = UPLOAD_DIR . '/' . $sampleFilePath;
if (!is_file($sampleFullPath)) {
    file_put_contents($sampleFullPath, "Introduction to Computing — Sample Reading\r\n\r\nThis is a demo resource in the Virtual Library.\r\n\r\nTopics covered:\r\n- Computer hardware basics\r\n- Software and operating systems\r\n- Internet and cloud concepts\r\n");
}

$worksheetPath = $schoolId . '/library/seed-programming-worksheet.txt';
$worksheetFullPath = UPLOAD_DIR . '/' . $worksheetPath;
if (!is_file($worksheetFullPath)) {
    file_put_contents($worksheetFullPath, "Programming 1 — Practice Worksheet\r\n\r\n1. What is a variable?\r\n2. Write a program that prints Hello, World!\r\n3. Explain the difference between int and float.\r\n");
}

$resources = [
    [
        'title' => 'Introduction to Computing — Lesson 1',
        'description' => $marker . 'Overview of computer systems, hardware, software, and basic IT concepts for first-year students.',
        'resource_kind' => 'lesson',
        'subject_id' => $subjectIds['IT101'] ?? null,
        'type' => 'doc',
        'content' => '<h2>Lesson 1: What is a Computer?</h2><p>A computer is an electronic device that processes data using instructions called programs.</p><ul><li><strong>Input</strong> — keyboard, mouse, sensors</li><li><strong>Processing</strong> — CPU executes instructions</li><li><strong>Output</strong> — monitor, printer, speakers</li><li><strong>Storage</strong> — hard drive, SSD, cloud</li></ul><p>Discuss with your class how each component works together in everyday devices like laptops and smartphones.</p>',
        'body' => 'First lesson in the IT101 intro series.',
        'audience' => 'all',
    ],
    [
        'title' => 'Computer Programming 1 — Module Overview',
        'description' => $marker . 'Module guide covering variables, control structures, functions, and weekly lab activities.',
        'resource_kind' => 'module',
        'subject_id' => $subjectIds['CS101'] ?? null,
        'type' => 'link',
        'content' => 'https://www.w3schools.com/python/',
        'external_link' => 'https://www.w3schools.com/python/',
        'audience' => 'all',
    ],
    [
        'title' => 'Data Structures — Reference Guide',
        'description' => $marker . 'Quick reference for arrays, linked lists, stacks, queues, trees, and hash tables.',
        'resource_kind' => 'reference',
        'subject_id' => $subjectIds['CS102'] ?? null,
        'type' => 'link',
        'content' => 'https://visualgo.net/en',
        'external_link' => 'https://visualgo.net/en',
        'audience' => 'all',
    ],
    [
        'title' => 'Web Development — HTML & CSS Basics (Video)',
        'description' => $marker . 'Introductory video lesson on building your first web page with HTML and CSS.',
        'resource_kind' => 'lesson',
        'subject_id' => $subjectIds['WEB101'] ?? null,
        'type' => 'link',
        'content' => 'https://www.youtube.com/watch?v=G3e-cpL7ofc',
        'external_link' => 'https://www.youtube.com/watch?v=G3e-cpL7ofc',
        'audience' => 'all',
    ],
    [
        'title' => 'College Algebra — Formula Sheet',
        'description' => $marker . 'Printable formula sheet for polynomials, quadratics, and exponential functions.',
        'resource_kind' => 'worksheet',
        'subject_id' => $subjectIds['MATH101'] ?? null,
        'type' => 'file',
        'file_path' => $worksheetPath,
        'original_name' => 'algebra-formula-sheet.txt',
        'mime_type' => 'text/plain',
        'file_size' => filesize($worksheetFullPath),
        'file_access_mode' => 'downloadable',
        'audience' => 'all',
    ],
    [
        'title' => 'Communication Skills — Essay Writing Guide',
        'description' => $marker . 'Step-by-step guide for academic essays: thesis, outline, body paragraphs, and citations.',
        'resource_kind' => 'book',
        'subject_id' => $subjectIds['ENG101'] ?? null,
        'type' => 'doc',
        'content' => '<h2>Academic Essay Writing</h2><p><strong>Step 1:</strong> Choose a clear thesis statement.</p><p><strong>Step 2:</strong> Create an outline with introduction, body, and conclusion.</p><p><strong>Step 3:</strong> Support each point with evidence and examples.</p><p><strong>Step 4:</strong> Revise for clarity, grammar, and proper citations.</p>',
        'body' => 'Recommended reading for ENG101 students.',
        'audience' => 'all',
    ],
    [
        'title' => 'Database Systems — ER Diagram Tutorial',
        'description' => $marker . 'Teacher resource: slides and activities for entity-relationship modeling.',
        'resource_kind' => 'lesson',
        'subject_id' => $subjectIds['DB101'] ?? null,
        'type' => 'link',
        'content' => 'https://www.lucidchart.com/pages/er-diagrams',
        'external_link' => 'https://www.lucidchart.com/pages/er-diagrams',
        'audience' => 'teachers',
    ],
    [
        'title' => 'Intro to Computing — Reading Material',
        'description' => $marker . 'Downloadable reading packet for IT101 Week 1.',
        'resource_kind' => 'book',
        'subject_id' => $subjectIds['IT101'] ?? null,
        'type' => 'file',
        'file_path' => $sampleFilePath,
        'original_name' => 'intro-to-computing-week1.txt',
        'mime_type' => 'text/plain',
        'file_size' => filesize($sampleFullPath),
        'file_access_mode' => 'downloadable',
        'audience' => 'all',
    ],
    [
        'title' => 'Software Engineering — Agile Methods Primer',
        'description' => $marker . 'Short primer on Scrum, Kanban, and sprint planning for IT301.',
        'resource_kind' => 'module',
        'subject_id' => $subjectIds['IT301'] ?? null,
        'type' => 'doc',
        'content' => '<h2>Agile in Practice</h2><p>Agile teams deliver work in small increments called <em>sprints</em>. Daily stand-ups, retrospectives, and backlog grooming keep the team aligned.</p><blockquote>Working software over comprehensive documentation.</blockquote>',
        'body' => 'Supplement for Software Engineering course.',
        'audience' => 'all',
    ],
    [
        'title' => 'Networking Fundamentals — Lab Worksheet',
        'description' => $marker . 'Hands-on worksheet for identifying network topologies and IP addressing.',
        'resource_kind' => 'worksheet',
        'subject_id' => $subjectIds['NET101'] ?? null,
        'type' => 'file',
        'file_path' => $worksheetPath,
        'original_name' => 'networking-lab-worksheet.txt',
        'mime_type' => 'text/plain',
        'file_size' => filesize($worksheetFullPath),
        'file_access_mode' => 'downloadable',
        'audience' => 'all',
    ],
];

$inserted = 0;
foreach ($resources as $resource) {
    LibraryResourceRepository::createFromAdmin($schoolId, $adminId, $resource);
    $inserted++;
    echo 'Added: ' . $resource['title'] . PHP_EOL;
}

echo "Done. Seeded {$inserted} library resources for {$slug} (school id {$schoolId}).\n";
