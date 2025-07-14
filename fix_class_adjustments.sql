-- Drop the existing table if it exists
DROP TABLE IF EXISTS `class_adjustments`;

-- Create the class_adjustments table with the correct structure
CREATE TABLE `class_adjustments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `application_id` int(11) NOT NULL,
  `adjustment_date` date NOT NULL,
  `adjustment_time` varchar(50) NOT NULL,
  `subject` varchar(100) NOT NULL,
  `adjusted_by` int(11) NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `remarks` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `application_id` (`application_id`),
  KEY `adjusted_by` (`adjusted_by`),
  CONSTRAINT `class_adjustments_ibfk_1` FOREIGN KEY (`application_id`) REFERENCES `leave_applications` (`application_id`) ON DELETE CASCADE,
  CONSTRAINT `class_adjustments_ibfk_2` FOREIGN KEY (`adjusted_by`) REFERENCES `users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add sample data (optional)
-- INSERT INTO `class_adjustments` (`application_id`, `adjustment_date`, `adjustment_time`, `subject`, `adjusted_by`, `status`, `remarks`) VALUES
-- (1, '2025-07-15', '9:30 AM - 10:20 AM', 'Mathematics', 1, 'pending', 'Sample adjustment');
