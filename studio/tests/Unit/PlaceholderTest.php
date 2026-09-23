<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Database;
use App\Repositories\ClientRepository;
use Tests\Support\Factory;
use Tests\Support\TestCase;

/**
 * Repeated named placeholders.
 *
 * MySQL with native prepares refuses `:search` used twice in one statement
 * ("SQLSTATE[HY093]: Invalid parameter number") while SQLite, which runs the
 * test suite, accepts it. The admin client search failed in production only;
 * these tests pin the rewrite that makes both drivers behave the same.
 */
final class PlaceholderTest extends TestCase
{
    public function testRepeatedPlaceholdersGetDistinctNames(): void
    {
        [$sql, $bindings] = Database::expandPlaceholders(
            'SELECT * FROM c WHERE a LIKE :search OR b LIKE :search OR d = :id OR e LIKE :search',
            ['search' => '%x%', 'id' => 3]
        );

        $this->assertSame(
            'SELECT * FROM c WHERE a LIKE :search OR b LIKE :search__2 OR d = :id OR e LIKE :search__3',
            $sql
        );
        $this->assertSame(['search' => '%x%', 'id' => 3, 'search__2' => '%x%', 'search__3' => '%x%'], $bindings);
    }

    public function testColonPrefixedBindingKeysAreCopiedToo(): void
    {
        [, $bindings] = Database::expandPlaceholders('a = :v OR b = :v', [':v' => 1]);

        $this->assertSame(1, $bindings['v__2']);
    }

    public function testQuotedLiteralsAndTimesAreLeftAlone(): void
    {
        $original = "SELECT DATE_FORMAT(d, '%H:%i'), ':name', \"x:y\", `a:b` FROM t WHERE t = '12:00:00' AND n = :name";

        [$sql, $bindings] = Database::expandPlaceholders($original, ['name' => 'n']);

        $this->assertSame($original, $sql);
        $this->assertSame(['name' => 'n'], $bindings);
    }

    public function testStatementsWithoutRepeatsAreUnchanged(): void
    {
        $original = 'UPDATE t SET a = :a, b = :b WHERE id = :id';

        $this->assertSame([$original, ['a' => 1, 'b' => 2, 'id' => 3]], Database::expandPlaceholders($original, ['a' => 1, 'b' => 2, 'id' => 3]));
        $this->assertSame(['SELECT 1', []], Database::expandPlaceholders('SELECT 1', []));
    }

    public function testClientSearchRunsThroughTheRepository(): void
    {
        $clientId = Factory::client('Recherchable');

        $found = (new ClientRepository())->paginate(1, 25, 'Recherch');

        $ids = array_map('intval', array_column($found['rows'], 'id'));
        $this->assertContains($clientId, $ids, 'The client search must find a client by last name.');
    }
}
