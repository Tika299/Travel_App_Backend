<?php

// Script để cập nhật mail username
$envFile = '.env';
$envContent = file_get_contents($envFile);

// Thay thế MAIL_USERNAME và MAIL_FROM_ADDRESS
$envContent = preg_replace('/^MAIL_USERNAME=.*$/m', 'MAIL_USERNAME=dai1023456@gmail.com', $envContent);
$envContent = preg_replace('/^MAIL_FROM_ADDRESS=.*$/m', 'MAIL_FROM_ADDRESS=dai1023456@gmail.com', $envContent);

file_put_contents($envFile, $envContent);
echo "Mail username updated to dai1023456@gmail.com\n";
