<?php
return [
    'mysqldump' => env('POSO_MYSQLDUMP', PHP_OS_FAMILY === 'Windows' ? 'C:/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysqldump.exe' : 'mysqldump'),
];
