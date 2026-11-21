<?php

namespace App\Tests\Directory;

use App\Directory\Directory;
use App\Directory\DirectoryClient;
use PHPUnit\Framework\TestCase;

/** F-18 : annuaire LDAP, champs configurables ; la saisie est échappée, les champs facultatifs suivent le choix de l'utilisateur. */
final class DirectoryTest extends TestCase
{
    private const ATTRS = ['first' => 'givenName', 'last' => 'sn', 'mail' => 'mail', 'department' => 'departmentNumber', 'phone' => 'telephoneNumber'];

    private function client(array $rows, ?array &$seen = null, bool $configured = true): DirectoryClient
    {
        return new class($rows, $seen, $configured) implements DirectoryClient {
            public function __construct(private array $rows, private ?array &$seen, private bool $configured) {}
            public function isConfigured(): bool { return $this->configured; }
            public function search(string $filter, array $attributes, int $limit): array
            {
                $this->seen = ['filter' => $filter, 'attributes' => $attributes, 'limit' => $limit];

                return $this->rows;
            }
        };
    }

    public function testMapsEntriesAndKeepsOptionalFieldsOnlyWhenAsked(): void
    {
        $seen = null;
        $rows = [['givenName' => 'Sophie', 'sn' => 'Laurent', 'mail' => 'Sophie.Laurent@univ-reims.fr', 'departmentNumber' => 'Scolarité', 'telephoneNumber' => '0326000000']];
        $d = new Directory($this->client($rows, $seen), self::ATTRS);
        $r = $d->search('laur', true, false);
        self::assertSame([['name' => 'Sophie Laurent', 'address' => 'sophie.laurent@univ-reims.fr', 'detail' => 'Scolarité']], $r);
        self::assertNotContains('telephoneNumber', $seen['attributes'], 'le téléphone n’est pas demandé à l’annuaire si l’utilisateur ne le veut pas');
        self::assertSame([['name' => 'Sophie Laurent', 'address' => 'sophie.laurent@univ-reims.fr', 'detail' => null]], $d->search('laur', false, false));
    }

    public function testTheTypedTextIsEscapedAgainstFilterInjection(): void
    {
        $seen = null;
        (new Directory($this->client([], $seen), self::ATTRS))->search('x*)(uid=*))(|(mail=*', false, false);
        self::assertStringNotContainsString('(uid=*)', $seen['filter']);
        self::assertStringContainsString('\2a', $seen['filter'], 'l’astérisque saisi est échappé');
        self::assertStringContainsString('\28', $seen['filter'], 'les parenthèses saisies sont échappées');
    }

    public function testShortQueriesUnconfiguredDirectoryAndEntriesWithoutValidMailGiveNothing(): void
    {
        $seen = null;
        $rows = [['givenName' => 'X', 'sn' => 'Y', 'mail' => 'pas-une-adresse']];
        self::assertSame([], (new Directory($this->client($rows, $seen), self::ATTRS))->search('a', false, false));
        self::assertNull($seen, 'moins de deux caractères : aucun appel');
        self::assertSame([], (new Directory($this->client($rows, $seen), self::ATTRS))->search('xy', false, false));
        self::assertSame([], (new Directory($this->client($rows, $seen, false), self::ATTRS))->search('xy', false, false));
    }
}
