-- Add new columns to referral_forms table for external student support
ALTER TABLE referral_forms ADD COLUMN IF NOT EXISTS student_email VARCHAR(160) DEFAULT NULL;
ALTER TABLE referral_forms ADD COLUMN IF NOT EXISTS is_external_student BOOLEAN DEFAULT FALSE;
ALTER TABLE referral_forms ADD COLUMN IF NOT EXISTS description TEXT DEFAULT NULL;
ALTER TABLE referral_forms ADD COLUMN IF NOT EXISTS is_anonymous BOOLEAN DEFAULT FALSE;
ALTER TABLE referral_forms ADD COLUMN IF NOT EXISTS email_notification_sent BOOLEAN DEFAULT FALSE;
ALTER TABLE referral_forms ADD COLUMN IF NOT EXISTS email_notification_date DATETIME DEFAULT NULL;
ALTER TABLE referral_forms ADD COLUMN IF NOT EXISTS intake_form_id INT UNSIGNED DEFAULT NULL;

-- Create pre-counseling intake forms table
CREATE TABLE IF NOT EXISTS referral_intake_forms (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    referral_id INT UNSIGNED NOT NULL,
    student_user_id INT UNSIGNED DEFAULT NULL,
    student_name VARCHAR(160) NOT NULL,
    student_email VARCHAR(160) DEFAULT NULL,
    intake_datetime DATETIME NOT NULL,
    
    -- Pre-counseling questions
    why_visiting TEXT DEFAULT NULL,
    what_concerns TEXT DEFAULT NULL,
    how_long VARCHAR(160) DEFAULT NULL,
    previous_counseling BOOLEAN DEFAULT FALSE,
    emergency_contact VARCHAR(160) DEFAULT NULL,
    
    -- Status tracking
    completed_by_student BOOLEAN DEFAULT FALSE,
    reviewed_by_counselor_id INT UNSIGNED DEFAULT NULL,
    reviewed_by_counselor_name VARCHAR(160) DEFAULT NULL,
    counselor_notes TEXT DEFAULT NULL,
    counselor_approved BOOLEAN DEFAULT FALSE,
    approval_datetime DATETIME DEFAULT NULL,
    
    status VARCHAR(40) NOT NULL DEFAULT 'Pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_intake_referral (referral_id),
    KEY idx_intake_student (student_user_id),
    KEY idx_intake_status (status),
    FOREIGN KEY (referral_id) REFERENCES referral_forms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Add index for external student lookups
ALTER TABLE referral_forms ADD KEY IF NOT EXISTS idx_referral_external (is_external_student, student_email);
