<?php
declare(strict_types=1);

function db(): PDO
{
    static $connection = null;

    if ($connection instanceof PDO) {
        return $connection;
    }

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);

    try {
        $connection = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $exception) {
        error_log('Quetta AgriLink database connection failed: ' . $exception->getMessage());
        http_response_code(500);
        exit(APP_ENV === 'development'
            ? 'Database connection could not be established. Check config/config.php and import database/quetta_agrilink.sql.'
            : 'A service error occurred. Please try again shortly.');
    }

    return $connection;
}

function fetch_one(string $sql, array $params = []): ?array
{
    $statement = db()->prepare($sql);
    $statement->execute($params);
    $result = $statement->fetch();

    return $result === false ? null : $result;
}

function fetch_all(string $sql, array $params = []): array
{
    $statement = db()->prepare($sql);
    $statement->execute($params);

    return $statement->fetchAll();
}

function execute_query(string $sql, array $params = []): bool
{
    $statement = db()->prepare($sql);
    return $statement->execute($params);
}

