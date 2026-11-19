<?php

namespace Jensovic\DbMySync\Tests;

use Jensovic\DbMySync\SchemaComparator;
use Jensovic\DbMySync\SqlGenerator;
use PHPUnit\Framework\TestCase;

class SqlGeneratorTest extends TestCase
{
    private function col(array $overrides): array
    {
        return array_merge([
            'name' => 'c',
            'type' => 'int(11)',
            'nullable' => false,
            'default' => null,
            'extra' => '',
            'charset' => null,
            'collation' => null,
            'position' => 1,
        ], $overrides);
    }

    private function table(string $name, array $columns, array $pk = []): array
    {
        return [
            'name' => $name,
            'columns' => $columns,
            'primary_key' => $pk ? ['columns' => $pk] : null,
            'indexes' => [],
            'foreign_keys' => [],
        ];
    }

    /** The instanz_merkmale case: Prod is the source, the column there has no default. */
    private function merkmaleSchemas(): array
    {
        $common = [
            $this->col(['name' => 'IM_Instanz_FID', 'type' => 'int(10) unsigned', 'position' => 1]),
        ];
        $dev = $this->table('m', array_merge($common, [
            $this->col(['name' => 'IM_Merkmal', 'type' => 'varchar(40)', 'default' => "''",
                'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'position' => 2]),
            $this->col(['name' => 'IM_Aktualisiert_Am', 'type' => 'datetime', 'default' => 'current_timestamp()', 'position' => 3]),
        ]), ['IM_Instanz_FID', 'IM_Merkmal']);
        $prod = $this->table('m', array_merge($common, [
            $this->col(['name' => 'IM_Merkmal', 'type' => 'varchar(40)', 'default' => null,
                'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'position' => 2]),
            $this->col(['name' => 'IM_Aktualisiert_Am', 'type' => 'datetime', 'default' => 'CURRENT_TIMESTAMP',
                'extra' => 'DEFAULT_GENERATED on update CURRENT_TIMESTAMP', 'position' => 3]),
        ]), ['IM_Instanz_FID', 'IM_Merkmal']);

        return [[$dev], [$prod]];
    }

    public function testStringColumnWithoutDefaultGetsNoDefault(): void
    {
        $gen = new SqlGenerator([], []);
        $def = $gen->columnDefinition($this->col(['name' => 'x', 'type' => 'varchar(40)']));

        $this->assertSame('`x` varchar(40) NOT NULL', $def);
    }

    public function testModifyDropsDefaultMissingInSource(): void
    {
        [$dev, $prod] = $this->merkmaleSchemas();
        $diff = (new SchemaComparator())->compare($dev, $prod);
        $sql = (new SqlGenerator($dev, $prod))->generateSql($diff, 'env2_to_env1', 'm', 'IM_Merkmal', 'column_difference');

        $this->assertCount(1, $sql);
        $this->assertStringContainsString(
            'MODIFY COLUMN `IM_Merkmal` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL;',
            $sql[0]['sql']
        );
    }

    public function testMissingOnUpdateIsDetectedAndRestored(): void
    {
        [$dev, $prod] = $this->merkmaleSchemas();
        $diff = (new SchemaComparator())->compare($dev, $prod);

        $this->assertArrayHasKey('extra', $diff['table_differences']['m']['column_differences']['IM_Aktualisiert_Am']);

        $sql = (new SqlGenerator($dev, $prod))->generateSql($diff, 'env2_to_env1', 'm', 'IM_Aktualisiert_Am', 'column_difference');
        $this->assertStringContainsString(
            'MODIFY COLUMN `IM_Aktualisiert_Am` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;',
            $sql[0]['sql']
        );
    }

    public function testMariaDbAndMySqlOnUpdateAreEqual(): void
    {
        $a = [$this->table('t', [$this->col(['type' => 'datetime', 'default' => 'current_timestamp()',
            'extra' => 'on update current_timestamp()'])])];
        $b = [$this->table('t', [$this->col(['type' => 'datetime', 'default' => 'CURRENT_TIMESTAMP',
            'extra' => 'DEFAULT_GENERATED on update CURRENT_TIMESTAMP'])])];

        $this->assertSame(0, (new SchemaComparator())->countDifferences((new SchemaComparator())->compare($a, $b)));
    }

    public function testCreateTableUsesTargetEngineAndKeepsDefinitions(): void
    {
        [, $prod] = $this->merkmaleSchemas();
        $gen = new SqlGenerator([], $prod, ['env1_engine' => 'MyISAM', 'env2_engine' => 'InnoDB']);
        $diff = (new SchemaComparator())->compare([], $prod);
        $sql = $gen->generateSql($diff, 'env2_to_env1', 'm', null, 'missing_table')[0]['sql'];

        $this->assertStringContainsString(') ENGINE=MyISAM DEFAULT CHARSET', $sql);
        $this->assertStringContainsString('`IM_Merkmal` varchar(40) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,', $sql);
        $this->assertStringContainsString('ON UPDATE CURRENT_TIMESTAMP', $sql);
    }

    public function testEmptyEngineOmitsClauseAndInvalidEngineIsRejected(): void
    {
        [, $prod] = $this->merkmaleSchemas();
        $diff = (new SchemaComparator())->compare([], $prod);

        foreach (['', 'InnoDB; DROP TABLE x'] as $engine) {
            $sql = (new SqlGenerator([], $prod, ['env1_engine' => $engine]))
                ->generateSql($diff, 'env2_to_env1', 'm', null, 'missing_table')[0]['sql'];
            $this->assertStringNotContainsString('ENGINE=', $sql);
        }
    }

    public function testDefaultFlavours(): void
    {
        $gen = new SqlGenerator([], []);
        $cases = [
            [['type' => 'varchar(10)', 'default' => "'abc'"], "DEFAULT 'abc'"],
            [['type' => 'varchar(10)', 'default' => 'abc'], "DEFAULT 'abc'"],
            [['type' => 'varchar(10)', 'default' => "''"], "DEFAULT ''"],
            [['type' => 'varchar(10)', 'default' => "'it''s'"], "DEFAULT 'it''s'"],
            [['type' => 'int(11)', 'default' => '0'], 'DEFAULT 0'],
            [['type' => 'bit(1)', 'default' => "b'0'"], "DEFAULT b'0'"],
            [['type' => 'char(36)', 'default' => 'uuid()', 'extra' => 'DEFAULT_GENERATED'], 'DEFAULT (uuid())'],
            [['type' => 'datetime(3)', 'default' => 'current_timestamp(3)', 'extra' => 'on update current_timestamp(3)'],
                'DEFAULT current_timestamp(3) ON UPDATE current_timestamp(3)'],
        ];

        foreach ($cases as [$overrides, $expected]) {
            $this->assertStringContainsString($expected, $gen->columnDefinition($this->col($overrides)), json_encode($overrides));
        }
    }

    public function testNullableWithoutDefaultBothFlavours(): void
    {
        $gen = new SqlGenerator([], []);

        foreach ([null, 'NULL'] as $reported) {
            $this->assertSame(
                '`c` int(11) NULL',
                $gen->columnDefinition($this->col(['nullable' => true, 'default' => $reported]))
            );
        }
    }

    public function testAutoIncrementAndComment(): void
    {
        $gen = new SqlGenerator([], []);
        $def = $gen->columnDefinition($this->col(['extra' => 'auto_increment', 'comment' => "it's"]));

        $this->assertSame("`c` int(11) NOT NULL AUTO_INCREMENT COMMENT 'it''s'", $def);
    }

    public function testCommentDifferencesOnlyWhenBothSidesReportThem(): void
    {
        $withComment = [$this->table('t', [$this->col(['comment' => 'a'])])];
        $otherComment = [$this->table('t', [$this->col(['comment' => 'b'])])];
        $noComment = [$this->table('t', [$this->col([])])];
        $cmp = new SchemaComparator();

        $this->assertSame(1, $cmp->countDifferences($cmp->compare($withComment, $otherComment)));
        $this->assertSame(0, $cmp->countDifferences($cmp->compare($withComment, $noComment)));
    }
}
