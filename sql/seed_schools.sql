-- Sample schools for development/demo (landing page carousel)
-- Usage: import after schema.sql, or run: php scripts/seed_schools.php

USE lms_saas;

INSERT INTO schools (name, slug, school_code, email, phone, address, status, registered_at, approved_at) VALUES
('Nehemiah College Davao', 'nehemiah-college-davao', 'NEHEMIAH-COLLEGE-DAV', 'contact@nehemiahcollege.edu.ph', '0882-374-823', 'Davao City, Philippines', 'active', NOW(), NOW()),
('Greenfield Academy', 'greenfield-academy', 'GREENFIELD-ACAD', 'info@greenfieldacademy.edu', '02-8123-4567', 'Quezon City, Philippines', 'active', NOW(), NOW()),
('Summit High School', 'summit-high-school', 'SUMMIT-HS', 'admin@summiths.edu.ph', '032-555-0199', 'Cebu City, Philippines', 'active', NOW(), NOW()),
('Riverside Institute of Technology', 'riverside-institute-of-technology', 'RIVERSIDE-IT', 'hello@riversideit.edu', '045-611-2200', 'Angeles City, Pampanga', 'active', NOW(), NOW()),
('Northstar Learning Center', 'northstar-learning-center', 'NORTHSTAR-LC', 'office@northstarlc.edu.ph', '084-220-7788', 'General Santos City, Philippines', 'active', NOW(), NOW()),
('Harborview College', 'harborview-college', 'HARBORVIEW', 'registrar@harborview.edu', NULL, 'Iloilo City, Philippines', 'pending', NOW(), NULL);
