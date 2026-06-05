USE lms_saas;

ALTER TABLE schools ADD COLUMN school_code VARCHAR(12) DEFAULT NULL AFTER slug;
CREATE UNIQUE INDEX uq_schools_code ON schools (school_code);

-- Backfill codes for existing schools (run generateSchoolCode logic via app or manual update)
-- After migration, visit once or run: php scripts/backfill_school_codes.php if needed
