<?php

// Script để cập nhật mail password
$envFile = '.env';
$envContent = file_get_contents($envFile);

echo "Nhập Gmail App Password: ";
$password = trim(fgets(STDIN));

// Thay thế MAIL_PASSWORD
$pattern = '/^MAIL_PASSWORD=.*$/m';
$replacement = 'MAIL_PASSWORD=' . $password;
$envContent = preg_replace($pattern, $replacement, $envContent);

file_put_contents($envFile, $envContent);
echo "Mail password updated successfully!\n";



