@echo off
REM === SETUP VARIABLE ===
set dbUser=root
set dbPass=
set dbName=seminar_kp
set backupDir=D:\DOWNLOAD\seminar_kp
set mysqlBinPath=C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin


REM === FORMAT TANGGAL & WAKTU ===
for /f "tokens=2 delims==" %%I in ('"wmic os get LocalDateTime /value"') do set datetime=%%I
set year=%datetime:~0,4%
set month=%datetime:~4,2%
set day=%datetime:~6,2%
set hour=%datetime:~8,2%
set minute=%datetime:~10,2%
set second=%datetime:~12,2%
set fileName=%dbName%_backup_%year%-%month%-%day%_%hour%%minute%%second%.sql

REM === PASTIKAN FOLDER BACKUP ADA ===
if not exist "%backupDir%" mkdir "%backupDir%"

REM === EKSEKUSI BACKUP ===
"%mysqlBinPath%\mysqldump.exe" -u %dbUser% %dbName% > "%backupDir%\%fileName%"

echo Backup selesai: %fileName%
