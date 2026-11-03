-- InBorgo Phase 1; MySQL 8.0+ or MariaDB 10.6+. Import into an empty database.
SET NAMES utf8mb4;
CREATE TABLE users (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, username VARCHAR(190) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL, role VARCHAR(20) NOT NULL DEFAULT 'admin',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE login_attempts (
 identifier CHAR(64) PRIMARY KEY, attempts INT NOT NULL DEFAULT 0, window_started DATETIME NOT NULL, INDEX(window_started)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE languages (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, code VARCHAR(16) NOT NULL UNIQUE,
 name VARCHAR(80) NOT NULL, native_name VARCHAR(80) NOT NULL,
 is_default TINYINT NOT NULL DEFAULT 0, is_active TINYINT NOT NULL DEFAULT 1, sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE locations (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, slug VARCHAR(160) NOT NULL UNIQUE,
 internal_name VARCHAR(190) NOT NULL, latitude DECIMAL(10,7) NOT NULL, longitude DECIMAL(10,7) NOT NULL,
 trigger_radius INT NOT NULL DEFAULT 50, featured_image VARCHAR(255) NOT NULL DEFAULT '',
 opening_hours VARCHAR(255) NOT NULL DEFAULT '', closed_days VARCHAR(30) NOT NULL DEFAULT '',
 guided_only TINYINT NOT NULL DEFAULT 0, accessibility_notes TEXT, general_notes TEXT,
 is_demo TINYINT NOT NULL DEFAULT 1, is_active TINYINT NOT NULL DEFAULT 1,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE location_translations (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, location_id INT UNSIGNED NOT NULL, language_id INT UNSIGNED NOT NULL,
 title VARCHAR(190) NOT NULL, short_description TEXT, narration_text TEXT, full_description TEXT,
 opening_information TEXT, accessibility_information TEXT,
 UNIQUE(location_id,language_id), FOREIGN KEY(location_id) REFERENCES locations(id) ON DELETE CASCADE,
 FOREIGN KEY(language_id) REFERENCES languages(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE location_media (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, location_id INT UNSIGNED NOT NULL, language_id INT UNSIGNED NULL,
 type ENUM('image','audio') NOT NULL, file_path VARCHAR(255) NOT NULL, caption VARCHAR(255) NOT NULL DEFAULT '', sort_order INT NOT NULL DEFAULT 0,
 FOREIGN KEY(location_id) REFERENCES locations(id) ON DELETE CASCADE, FOREIGN KEY(language_id) REFERENCES languages(id) ON DELETE RESTRICT,
 INDEX(location_id,type,language_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE routes (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, slug VARCHAR(160) NOT NULL UNIQUE, internal_name VARCHAR(190) NOT NULL,
 featured_image VARCHAR(255) NOT NULL DEFAULT '', estimated_duration_minutes INT NOT NULL DEFAULT 60,
 estimated_distance_m INT NOT NULL DEFAULT 1000, difficulty VARCHAR(80) NOT NULL DEFAULT '', is_active TINYINT NOT NULL DEFAULT 1,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE route_translations (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, route_id INT UNSIGNED NOT NULL, language_id INT UNSIGNED NOT NULL,
 title VARCHAR(190) NOT NULL, short_description TEXT, full_description TEXT, starting_instructions TEXT,
 UNIQUE(route_id,language_id), FOREIGN KEY(route_id) REFERENCES routes(id) ON DELETE CASCADE,
 FOREIGN KEY(language_id) REFERENCES languages(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE route_stops (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, route_id INT UNSIGNED NOT NULL, location_id INT UNSIGNED NOT NULL,
 stop_order INT NOT NULL, custom_trigger_radius INT NULL, is_optional TINYINT NOT NULL DEFAULT 0,
 UNIQUE(route_id,location_id), UNIQUE(route_id,stop_order),
 FOREIGN KEY(route_id) REFERENCES routes(id) ON DELETE CASCADE, FOREIGN KEY(location_id) REFERENCES locations(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE route_stop_translations (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, route_stop_id INT UNSIGNED NOT NULL, language_id INT UNSIGNED NOT NULL,
 arrival_instruction TEXT, departure_instruction TEXT, route_specific_notes TEXT,
 UNIQUE(route_stop_id,language_id), FOREIGN KEY(route_stop_id) REFERENCES route_stops(id) ON DELETE CASCADE,
 FOREIGN KEY(language_id) REFERENCES languages(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
