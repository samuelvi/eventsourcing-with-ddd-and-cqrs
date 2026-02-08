<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Mongo;

use MongoDB\Client;
use MongoDB\Database;

final class MongoClient
{
    private Database $database;

    public function __construct(string $uri, string $dbName)
    {
        $client = new Client($uri);
        $this->database = $client->selectDatabase($dbName);
    }

    public function getDatabase(): Database
    {
        return $this->database;
    }
}
