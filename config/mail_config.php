<?php
return [
  'host' => getenv('SMTP_HOST') ?: 'smtp.gmail.com',
  'username' => getenv('SMTP_USER') ?: '',
  'password' => getenv('SMTP_PASS') ?: '',
  'port' => getenv('SMTP_PORT') ?: 587,
  'from_email' => getenv('MAIL_FROM') ?: 'no-reply@example.com',
  'from_name' => getenv('MAIL_FROM_NAME') ?: 'My Shop',
];
