<?php
declare(strict_types=1);

/**
 * Quetta AgriLink local configuration.
 * Copy config.example.php over this file in a new installation and adjust values.
 */
const APP_NAME = 'Quetta AgriLink';
const APP_ENV = 'development';
const APP_URL = '/quetta-agrilink';
const DB_HOST = '127.0.0.1';
const DB_PORT = '3306';
const DB_NAME = 'quetta_agrilink';
const DB_USER = 'root';
const DB_PASS = '';
const SESSION_IDLE_MINUTES = 60;
const MAX_UPLOAD_BYTES = 5_242_880;
const UPLOAD_STORAGE_PATH = __DIR__ . '/../uploads';

date_default_timezone_set('Asia/Karachi');
