<?php

declare(strict_types=1);

/**
 * Singleton para gestão da conexão PDO à base de dados SQLite.
 *
 * Garante uma única instância de conexão por processo e ativa
 * foreign keys no SQLite via PRAGMA.
 */
final class Database
{
    private static ?PDO $connection = null;

    private const DB_FILENAME = 'finance.sqlite';

    private function __construct()
    {
    }

    private function __clone(): void
    {
    }

    /**
     * Impede deserialização do singleton.
     *
     * @throws \RuntimeException
     */
    public function __wakeup(): void
    {
        throw new \RuntimeException('Não é permitido deserializar o singleton Database.');
    }

    /**
     * Obtém a instância PDO partilhada.
     *
     * @throws \PDOException Se a conexão falhar
     */
    public static function getConnection(): PDO
    {
        if (self::$connection === null) {
            $dbPath = __DIR__ . DIRECTORY_SEPARATOR . self::DB_FILENAME;

            self::$connection = new PDO(
                'sqlite:' . $dbPath,
                null,
                null,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );

            self::$connection->exec('PRAGMA foreign_keys = ON;');
        }

        return self::$connection;
    }

    /**
     * Repõe a conexão (útil em testes ou após recriar o ficheiro .sqlite).
     */
    public static function resetConnection(): void
    {
        self::$connection = null;
    }

    /**
     * Caminho absoluto do ficheiro SQLite.
     */
    public static function getDatabasePath(): string
    {
        return __DIR__ . DIRECTORY_SEPARATOR . self::DB_FILENAME;
    }
}
