@echo off
echo Setting up the database...

REM Import the schema and sample data
mysql -u root < database/schema.sql

IF %ERRORLEVEL% EQU 0 (
    echo Database setup completed successfully!
) ELSE (
    echo Error setting up the database. Please check your MySQL credentials and try again.
) 