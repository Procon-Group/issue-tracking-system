-- Run once on databases created from the original DATABASE.sql.
-- Longer password column for password_hash(), and a column for hashed remember-me tokens.
-- Existing md5 passwords keep working and are upgraded automatically on next login.

ALTER TABLE `users` MODIFY `password` varchar(255) NOT NULL COMMENT 'password_hash()';
ALTER TABLE `users` ADD `remember_token` varchar(64) DEFAULT NULL COMMENT 'sha256 of remember-me cookie token';
