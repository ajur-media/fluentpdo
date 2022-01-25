<?php

// determine the test environment first
$pdo = new PDO("mysql:dbname=fluentdb;host=localhost;charset=utf8", "wombat", "wombatsql");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
$pdo->setAttribute(PDO::ATTR_CASE, PDO::CASE_LOWER);
