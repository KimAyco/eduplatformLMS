<?php
/**
 * Seed sample schools for development/demo.
 * Usage: php scripts/seed_schools.php
 */
require_once dirname(__DIR__) . '/includes/bootstrap.php';

$schools = [
    [
        'name' => 'Nehemiah College Davao',
        'slug' => 'nehemiah-college-davao',
        'school_code' => 'NEHEMIAH-COLLEGE-DAV',
        'email' => 'contact@nehemiahcollege.edu.ph',
        'phone' => '0882-374-823',
        'address' => 'Davao City, Philippines',
        'status' => 'active',
    ],
    [
        'name' => 'Greenfield Academy',
        'slug' => 'greenfield-academy',
        'school_code' => 'GREENFIELD-ACAD',
        'email' => 'info@greenfieldacademy.edu',
        'phone' => '02-8123-4567',
        'address' => 'Quezon City, Philippines',
        'status' => 'active',
    ],
    [
        'name' => 'Summit High School',
        'slug' => 'summit-high-school',
        'school_code' => 'SUMMIT-HS',
        'email' => 'admin@summiths.edu.ph',
        'phone' => '032-555-0199',
        'address' => 'Cebu City, Philippines',
        'status' => 'active',
    ],
    [
        'name' => 'Riverside Institute of Technology',
        'slug' => 'riverside-institute-of-technology',
        'school_code' => 'RIVERSIDE-IT',
        'email' => 'hello@riversideit.edu',
        'phone' => '045-611-2200',
        'address' => 'Angeles City, Pampanga',
        'status' => 'active',
    ],
    [
        'name' => 'Northstar Learning Center',
        'slug' => 'northstar-learning-center',
        'school_code' => 'NORTHSTAR-LC',
        'email' => 'office@northstarlc.edu.ph',
        'phone' => '084-220-7788',
        'address' => 'General Santos City, Philippines',
        'status' => 'active',
    ],
    [
        'name' => 'Harborview College',
        'slug' => 'harborview-college',
        'school_code' => 'HARBORVIEW',
        'email' => 'registrar@harborview.edu',
        'phone' => null,
        'address' => 'Iloilo City, Philippines',
        'status' => 'pending',
    ],
];

$insert = db()->prepare(
    'INSERT INTO schools (name, slug, school_code, email, phone, address, status, registered_at, approved_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?)'
);

$checkSlug = db()->prepare('SELECT id FROM schools WHERE slug = ?');
$checkCode = db()->prepare('SELECT id FROM schools WHERE school_code = ?');

$created = 0;
$skipped = 0;

foreach ($schools as $school) {
    $checkSlug->execute([$school['slug']]);
    if ($checkSlug->fetch()) {
        echo "Skip (slug exists): {$school['name']}\n";
        $skipped++;
        continue;
    }

    $checkCode->execute([$school['school_code']]);
    if ($checkCode->fetch()) {
        echo "Skip (code exists): {$school['name']}\n";
        $skipped++;
        continue;
    }

    $approvedAt = $school['status'] === 'active' ? date('Y-m-d H:i:s') : null;
    $insert->execute([
        $school['name'],
        $school['slug'],
        $school['school_code'],
        $school['email'],
        $school['phone'],
        $school['address'],
        $school['status'],
        $approvedAt,
    ]);

    echo "Created: {$school['name']} ({$school['school_code']})\n";
    $created++;
}

echo "\nDone. Created {$created}, skipped {$skipped}.\n";
