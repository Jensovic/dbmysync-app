# SQL generation

Code: `main-application/src/SqlGenerator.php`, used by
`views/comparison.php`. Tests: `tests/SqlGeneratorTest.php`.

DbMySync only **displays** SQL. Nothing is executed. Review it, then run it on
the target database yourself.

## Inputs

```php
$generator = new SqlGenerator($env1Tables, $env2Tables, [
    'env1_engine' => $pair['env1_engine'],
    'env2_engine' => $pair['env2_engine'],
]);
$statements = $generator->generateSql($differences, 'env1_to_env2', $table, $item, $diffType);
```

| Parameter | Values |
|---|---|
| `$direction` | `env1_to_env2` (make env2 match env1) or `env2_to_env1` |
| `$table` | optional filter: one table |
| `$item` | optional filter: one column, index or foreign key name |
| `$diffType` | optional filter: `missing_table`, `missing_column`, `column_difference`, `missing_index`, `missing_foreign_key` |

Each statement is an array with `type`, `table`, the item name, `sql` and an
optional `warning`. The comparison view generates every combination up front
and embeds it as JSON; the buttons only open a modal with the matching entry.

## What gets generated

| Difference | Statement |
|---|---|
| Table missing in target | `CREATE TABLE` with columns (source order), primary key, indexes, `ENGINE`, charset `utf8mb4` / `utf8mb4_unicode_ci`; foreign keys as separate `ALTER TABLE` afterwards |
| Column missing in target | `ALTER TABLE ... ADD COLUMN ... FIRST` / `AFTER \`x\`` |
| Column differs | `ALTER TABLE ... MODIFY COLUMN` with a `-- Changes:` comment |
| Index missing in target | `ALTER TABLE ... ADD [UNIQUE] INDEX` |
| Foreign key missing in target | `ALTER TABLE ... ADD CONSTRAINT ... FOREIGN KEY` |
| Primary key difference | nothing (shown only) |
| Item exists only in the target | nothing – no `DROP` statements are ever generated |

## Column definition

`columnDefinition()` is shared by `CREATE TABLE`, `ADD COLUMN` and
`MODIFY COLUMN`, so all three produce the same column. It is built **only from
what the source reports**:

`` `name` type [CHARACTER SET x] [COLLATE y] NULL|NOT NULL [DEFAULT ...] [AUTO_INCREMENT] [ON UPDATE ...] [COMMENT '...'] ``

- Charset and collation only for string types (`char`, `varchar`, `*text`,
  `enum`, `set`).
- No default is invented. Because `MODIFY` replaces the whole definition, a
  default that exists only in the target is removed – intentionally.
- Expression defaults (`CURRENT_TIMESTAMP`, `current_timestamp(3)`, `uuid()`,
  parenthesized expressions) are not quoted; other expressions get parentheses.
- Literal defaults are quoted unless numeric, already quoted, or a bit/hex
  literal (`b'1'`, `x'0F'`).

## Column position

`computeColumnAnchors()` places a new column after the nearest preceding column
that already exists in the target, or `FIRST`. When the whole set is generated,
earlier added columns count as anchors (statements run in order); for a single
column only existing target columns do.

## Warnings in the SQL

Written as SQL comments in front of a statement:

- `-- WARNING: ... is a generated column` – the endpoint does not report the
  expression of virtual/stored columns; add `AS (...)` by hand.
- `-- NOTE: this endpoint does not report column comments` – the source
  endpoint is too old; comments would be lost.
- `-- ERROR: ... not found in source` – inconsistent input, nothing generated.

## Storage engine

Per environment in the tool pair (InnoDB, MyISAM, Aria, or empty = server
default). Used only for `CREATE TABLE`, chosen by the **target** environment.
`sanitizeEngine()` accepts only plain identifiers, because the value ends up
verbatim in SQL.

## Changing the generator

1. Write a failing case in `tests/SqlGeneratorTest.php` (schemas are plain
   arrays, no endpoint needed).
2. Keep `columnDefinition()` the single place that builds a column.
3. Run `vendor/bin/phpunit` in `main-application/`.
