<?php
/*
 * Copyright (C) 2019, Daniel Haslinger <creo+oss@mesanova.com>
 * This program is free software licensed under the terms of the GNU General Public License v3 (GPLv3).
 */

class CORE_PERSIST
{
    private $object_broker;
    private $classname;
    private $db;

    public function __construct($object_broker)
    {
        $this->classname = strtolower(static::class);

        $this->object_broker = $object_broker;
        $object_broker->apis[] = $this->classname;
        debug_log($this->classname . ": starting up");

        $this->setup();
    }


    public function __destruct()
    {
        // get rid of the database resource in order to avoid race conditions due to garbage collection
        $this->db->close();
    }


    public function setup()
    {
        // Prepare the database
        $this->db = new SQLite3($this->classname . '.sqlite3', SQLITE3_OPEN_CREATE | SQLITE3_OPEN_READWRITE);

        // some plugins may do things that take longer but keep the sqlite connection open, so we increase the timeout¿
        $this->db->busyTimeout(10000);

        // better concurrency? I'm in!
        $this->db->exec('PRAGMA journal_mode=WAL;');

        // Prepare the table structure
        $this->db->query('
            CREATE TABLE IF NOT EXISTS "data" (
                "key" VARCHAR PRIMARY KEY NOT NULL,
                "value" VARCHAR
            )
        ');

    }


    public function store($key, $value)
    {
        // Check if this key does already exist
        $keycount = $this->db->querySingle('SELECT COUNT("key") FROM "data" WHERE key = "' . $key . '" LIMIT 1');

        // Store key value pair
        if($keycount > 0)
        {
            $statement = $this->db->prepare('UPDATE "data" set value = :value where key = :key LIMIT 1');
        }
        else
        {
            $statement = $this->db->prepare('INSERT INTO "data" ("key", "value") VALUES (:key, :value)');
        }

        $statement->bindValue(':key', $key);
        $statement->bindValue(':value', $value);
        $statement->execute();

    }


    public function retrieve($key)
    {
        // Check if this key does already exist
        $keycount = $this->db->querySingle('SELECT COUNT("key") FROM "data" WHERE key = "' . $key . '" LIMIT 1');

        if($keycount > 0)
        {
            return $this->db->querySingle('SELECT "value" FROM "data" WHERE key = "' . $key . '" LIMIT 1');
        }
        else
        {
            return false;
        }
    }
}

?>