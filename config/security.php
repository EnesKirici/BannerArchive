<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Otomatik IP Banlama Ayarları
    |--------------------------------------------------------------------------
    */

    'auto_ban' => [
        // Zaman penceresi (saniye) - bu süre içinde eşik aşılırsa ban uygulanır
        'window' => (int) env('AUTO_BAN_WINDOW', 60),

        // Maksimum istek sayısı (pencere içinde)
        'max_requests' => (int) env('AUTO_BAN_MAX_REQUESTS', 100),

        // Ban süresi (dakika) - null ise kalıcı ban
        'ban_duration' => env('AUTO_BAN_DURATION', 60),

        // Tekrarlayan ihlallerde kalıcı ban eşiği
        'permanent_after_violations' => (int) env('AUTO_BAN_PERMANENT_AFTER', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Şüpheli Dosya Yükleme Ayarları
    |--------------------------------------------------------------------------
    */

    'upload' => [
        // İzin verilen gerçek MIME tipleri
        'allowed_mimes' => [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/avif',
        ],

        // İzin verilen uzantılar
        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'],

        // Tehlikeli dosya imzaları (magic bytes - hex)
        'dangerous_signatures' => [
            'PK' => 'ZIP/DOCX/APK arşivi',
            "\x7fELF" => 'ELF çalıştırılabilir dosya',
            'MZ' => 'Windows EXE/DLL',
            '<?php' => 'PHP script',
            '<?=' => 'PHP short tag',
            '<script' => 'JavaScript/HTML script',
        ],

        // Maksimum dosya boyutu (KB)
        'max_size_kb' => (int) env('UPLOAD_MAX_SIZE_KB', 10240),

        // Şüpheli yükleme sonrası ban için eşik
        'ban_after_attempts' => (int) env('UPLOAD_BAN_AFTER_ATTEMPTS', 3),

        // Şüpheli yükleme takip penceresi (dakika)
        'suspicious_window' => (int) env('UPLOAD_SUSPICIOUS_WINDOW', 30),

        // Dakikada izin verilen yükleme isteği (IP başına)
        'rate_limit_upload' => (int) env('UPLOAD_RATE_LIMIT', 15),

        // Dakikada izin verilen dönüştürme isteği (IP başına)
        'rate_limit_convert' => (int) env('CONVERT_RATE_LIMIT', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Video İndirici Ayarları
    |--------------------------------------------------------------------------
    */

    'video' => [
        // Maksimum indirilebilir dosya boyutu (MB)
        'max_filesize_mb' => (int) env('VIDEO_MAX_FILESIZE_MB', 500),

        // yt-dlp indirme işlemi zaman aşımı (saniye)
        'process_timeout' => (int) env('VIDEO_PROCESS_TIMEOUT', 300),

        // Video bilgisi çekme zaman aşımı (saniye)
        'info_timeout' => (int) env('VIDEO_INFO_TIMEOUT', 60),

        // Geçersiz platform URL denemesi sonrası ban eşiği
        'ban_after_attempts' => (int) env('VIDEO_BAN_AFTER_ATTEMPTS', 10),

        // Şüpheli URL takip penceresi (dakika)
        'suspicious_window' => (int) env('VIDEO_SUSPICIOUS_WINDOW', 30),

        // Dakikada izin verilen bilgi çekme isteği (IP başına)
        'rate_limit_info' => (int) env('VIDEO_RATE_LIMIT_INFO', 10),

        // Dakikada izin verilen indirme isteği (IP başına)
        'rate_limit_download' => (int) env('VIDEO_RATE_LIMIT_DOWNLOAD', 3),

        // Sunucuda aynı anda çalışabilecek toplam indirme sayısı
        'max_concurrent_downloads' => (int) env('VIDEO_MAX_CONCURRENT', 3),

        // İndirme çıktısında izin verilen dosya uzantıları
        'allowed_output_extensions' => ['mp4', 'mp3', 'm4a', 'webm', 'mkv', 'mov'],
    ],

    /*
    |--------------------------------------------------------------------------
    | IP Whitelist
    |--------------------------------------------------------------------------
    | Bu IP'ler asla otomatik olarak banlanmaz.
    | Virgülle ayırarak birden fazla IP/CIDR eklenebilir.
    */

    'whitelist' => array_filter(
        array_map('trim', explode(',', env('IP_WHITELIST', '127.0.0.1,::1')))
    ),

    /*
    |--------------------------------------------------------------------------
    | Bilinen Bot User-Agent Kalıpları (Whitelist)
    |--------------------------------------------------------------------------
    */

    'bot_whitelist' => [
        'Googlebot',
        'Bingbot',
        'YandexBot',
        'DuckDuckBot',
    ],

];
