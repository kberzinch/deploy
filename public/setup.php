<?php declare(strict_types = 1);

require __DIR__ . '/../vendor/autoload.php';

if (file_exists(__DIR__ . '/../config.php')) {
    exit(file_get_contents(__DIR__ . '/../config_exists.html'));
}

exit(file_get_contents(__DIR__ . '/../setup_wizard.html'));
