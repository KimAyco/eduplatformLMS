USE lms_saas;

CREATE INDEX IF NOT EXISTS idx_users_school_role_status ON users (school_id, role, status);
CREATE INDEX IF NOT EXISTS idx_materials_class_created ON materials (class_id, created_at);
CREATE INDEX IF NOT EXISTS idx_assignments_class_due ON assignments (class_id, due_date);
CREATE INDEX IF NOT EXISTS idx_quizzes_class_due ON quizzes (class_id, due_date);
