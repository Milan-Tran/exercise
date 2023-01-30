<?php

namespace Leadinfo\Excercise\config;

use PDO;

class DB
{
    public function connect(): PDO
    {
        $connection_string = sprintf(
            'mysql:host=%s;dbname=%s;port=%s',
            env('DB_HOST', 'localhost'),
            env('DB_DATABASE', 'db'),
            env('DB_PORT', 3306)
        );

        $connection = new PDO($connection_string, env('DB_USER'), env('DB_PASSWORD'));
        $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $connection;
    }
}