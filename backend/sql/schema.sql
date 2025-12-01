CREATE DATABASE IF NOT EXISTS OCES CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE OCES;

CREATE TABLE IF NOT EXISTS tblStudents (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  first_name_enc VARCHAR(255) NOT NULL,
  last_name_enc VARCHAR(255) NOT NULL,
  username_enc VARCHAR(255) NOT NULL,
  username_hash CHAR(64) NOT NULL,
  email_enc VARCHAR(255) NOT NULL,
  email_hash CHAR(64) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  tfa_enabled TINYINT(1) NOT NULL DEFAULT 0,
  totp_secret VARBINARY(512) NULL,
  last_login DATETIME NULL,
  failed_login_count INT UNSIGNED NOT NULL DEFAULT 0,
  locked_until DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_students_username_hash (username_hash),
  UNIQUE KEY uq_students_email_hash (email_hash),
  INDEX idx_students_email_hash (email_hash),
  INDEX idx_students_username_hash (username_hash),
  INDEX idx_students_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
 
CREATE TABLE IF NOT EXISTS tblCourses (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  course_name VARCHAR(255) NOT NULL,
  course_code VARCHAR(50) NOT NULL,
  course_start_date DATE NOT NULL,
  course_end_date DATE NOT NULL,
  description TEXT NULL,
  capacity INT UNSIGNED NOT NULL DEFAULT 0, -- 0 == unlimited
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_courses_course_code (course_code),
  INDEX idx_courses_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tblEnrollments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  student_id INT UNSIGNED NOT NULL,
  course_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_enrollments_student_id (student_id),
  INDEX idx_enrollments_course_id (course_id),
  INDEX idx_enrollments_created_at (created_at),
  CONSTRAINT fk_enroll_student FOREIGN KEY (student_id) REFERENCES tblStudents(id) ON DELETE CASCADE,
  CONSTRAINT fk_enroll_course FOREIGN KEY (course_id) REFERENCES tblCourses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tblWaitlist (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  student_id INT UNSIGNED NOT NULL,
  course_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_waitlist_student_id (student_id),
  INDEX idx_waitlist_course_id (course_id),
  CONSTRAINT fk_waitlist_student FOREIGN KEY (student_id) REFERENCES tblStudents(id) ON DELETE CASCADE,
  CONSTRAINT fk_waitlist_course FOREIGN KEY (course_id) REFERENCES tblCourses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO tblCourses (course_name, course_code, course_start_date, course_end_date, description, capacity) VALUES
('Capstone for Computer Software Technology', 'CST499', '2025-11-04', '2025-12-08', 'Instructor: Charmelia Butler', 5),
('Human Computer Interaction', 'INT303', '2025-12-09', '2026-01-26', 'Instructor: John Russell', 5),
('Web Design & Development', 'INT304', '2026-01-27', '2026-03-02', 'Instructor: To Be Determined', 5),
('Introduction to Cyber & Data Security Technology', 'CYB301', '2026-03-03', '2026-04-06', 'Instructor: To Be Determined', 5),
('Risk Management and Infrastructure', 'CYB401', '2026-04-07', '2026-05-11', 'Instructor: To Be Determined', 5);