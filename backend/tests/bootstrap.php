<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

// Variabili d'ambiente di test (i test unitari non toccano il DB).
putenv('JWT_SECRET=test-secret-0123456789abcdef0123456789abcdef');
$_ENV['JWT_SECRET'] = getenv('JWT_SECRET');
putenv('JWT_EXPIRY=3600');
$_ENV['JWT_EXPIRY'] = '3600';
