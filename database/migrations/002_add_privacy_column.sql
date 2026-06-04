ALTER TABLE users ADD COLUMN profile_privacy ENUM('public', 'followers') DEFAULT 'public';
