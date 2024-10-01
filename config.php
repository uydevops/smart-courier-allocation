<?php

define('DB_DSN', 'mysql:host=localhost;dbname=veritabani;charset=utf8');
define('DB_USER', 'kullanici'); // Veritabanı kullanıcı adı
define('DB_PASS', 'sifre'); // Veritabanı şifresi

// Cache ayarları
define('CACHE_DIR', __DIR__ . '/cache');
define('CACHE_LIFETIME', 3600); // Cache süresi (saniye cinsinden)

// Diğer genel ayarlar
define('APP_NAME', 'UstaPos');
define('APP_VERSION', '1.0.0');
