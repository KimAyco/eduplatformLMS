ALTER TABLE ai_request_queue
    ADD KEY idx_ai_queue_school_created (school_id, created_at);
