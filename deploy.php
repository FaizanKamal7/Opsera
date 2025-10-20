<?php
namespace Deployer;

require 'vendor/autoload.php';
require 'recipe/symfony.php';

use Symfony\Component\Dotenv\Dotenv;

// Load .env
$dotenv = new Dotenv();
$dotenv->load(__DIR__.'/.env');

// Access variable
$gitPassword = $_ENV['DEPLOY_PASSWORD'] ?? 'fallback_password';

// Example: use it in your repo URL
set('repository', 'http://KaFa:' . $gitPassword . '@git.wiltec.info:3000/Dev/service-portal.git');

// Config

add('shared_files', []);
add('shared_dirs', []);
add('writable_dirs', []);

// Hosts

// Hooks
after('deploy:failed', 'deploy:unlock');
