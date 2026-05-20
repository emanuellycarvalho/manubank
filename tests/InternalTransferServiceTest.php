<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

final class InternalTransferServiceTest extends TestCase
{
    private \PDO $pdo;
    private \InternalTransferService $service;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:', null, null, [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);

        $this->pdo->exec(
            'CREATE TABLE categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                type TEXT NOT NULL,
                color TEXT NOT NULL,
                is_active INTEGER DEFAULT 1
            )'
        );

        $this->service = new \InternalTransferService($this->pdo);
    }

    public function testCreatesInternalTransferCategoryOnDemand(): void
    {
        $id = $this->service->resolveCategoryId();

        $this->assertGreaterThan(0, $id);

        $stmt = $this->pdo->prepare('SELECT name, type FROM categories WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        $this->assertSame('Movimentação interna', $row['name']);
        $this->assertSame('Neutro', $row['type']);
    }

    public function testMatchesProfileNameCaseInsensitive(): void
    {
        $this->assertTrue(
            $this->service->matchesProfileName('Pix enviado Emanuelly Carvalho', 'emanuelly')
        );
        $this->assertFalse(
            $this->service->matchesProfileName('Pix enviado João Silva', 'emanuelly')
        );
    }

    public function testApplyToRowsOverridesCategoryWhenNameMatches(): void
    {
        $categoryId = $this->service->resolveCategoryId();

        $rows = [
            [
                'category_id'            => 99,
                'type'                   => 'entrada',
                'raw_description'        => 'Transferência Maria Souza',
                'translated_description' => 'Outros',
            ],
            [
                'category_id'            => 99,
                'type'                   => 'saída',
                'raw_description'        => 'Pix recebido Emanuelly Carvalho',
                'translated_description' => 'Outros',
            ],
        ];

        $result = $this->service->applyToRows($rows, 'Emanuelly');

        $this->assertSame(99, $result[0]['category_id']);
        $this->assertSame($categoryId, $result[1]['category_id']);
        $this->assertSame('Movimentação interna', $result[1]['translated_description']);
    }

    public function testApplyToRowsSkipsWhenProfileNameEmpty(): void
    {
        $rows = [
            [
                'category_id'     => 5,
                'raw_description' => 'Pix Emanuelly',
            ],
        ];

        $result = $this->service->applyToRows($rows, '');

        $this->assertSame(5, $result[0]['category_id']);
    }
}
