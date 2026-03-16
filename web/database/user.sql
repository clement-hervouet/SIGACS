-- you should have to run this SQL only once, to create the user and grant the necessary permissions to access the database.

--'your-user' and 'your-passwd' should be replaced with your actual MySQL username and password.
--Note: The '%' wildcard allows the user to connect from any host. You can replace it with 'localhost' if you want to restrict access to the local machine only.

DROP USER IF EXISTS 'your-user'@'localhost';

CREATE USER 'your-user'@'%' IDENTIFIED BY 'your-passwd';

--no need for DELETE: the actual programm don't permit to delete users, only to update them (change password)
GRANT SELECT, INSERT, UPDATE ON users_base.* TO 'your-user'@'%';

--Note: FLUSH PRIVILEGES seems to be deprecated in future update of MySQL
-- still supported on version "9.5.0 - MySQL Community Server - GPL"
FLUSH PRIVILEGES;