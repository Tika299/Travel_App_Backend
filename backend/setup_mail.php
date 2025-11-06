<?php

// Script để cấu hình mail settings
$envFile = '.env';
$envContent = file_get_contents($envFile);

// Thay thế mail settings
$mailSettings = [
    'MAIL_MAILER=smtp',
    'MAIL_HOST=smtp.gmail.com',
    'MAIL_PORT=587',
    'MAIL_USERNAME=nguyenvandai896@gmail.com',
    'MAIL_PASSWORD=your_app_password_here',
    'MAIL_ENCRYPTION=tls',
    'MAIL_FROM_ADDRESS=nguyenvandai896@gmail.com',
    'MAIL_FROM_NAME="${APP_NAME}"'
];

foreach ($mailSettings as $setting) {
    $key = explode('=', $setting)[0];
    $pattern = "/^{$key}=.*$/m";
    if (preg_match($pattern, $envContent)) {
        $envContent = preg_replace($pattern, $setting, $envContent);
    } else {
        $envContent .= "\n" . $setting;
    }
}

file_put_contents($envFile, $envContent);
echo "Mail settings updated. Please update MAIL_PASSWORD with your Gmail app password.\n";



