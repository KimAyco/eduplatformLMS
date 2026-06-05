-- Allow longer subscriber-chosen school codes (letters, numbers, hyphens)
ALTER TABLE schools MODIFY COLUMN school_code VARCHAR(32) DEFAULT NULL;
