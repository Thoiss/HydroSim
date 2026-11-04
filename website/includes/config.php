<?php
// Basic configuration (env overrides allow running in docker; defaults match the original VM)
$DB_HOST = getenv('DB_HOST') ?: '127.0.0.1';
$DB_PORT = (int)(getenv('DB_PORT') ?: 3307);
$DB_USER = 'webapp';
$DB_PASS = 'waterdb';
$DB_NAME = 'scadalts';
