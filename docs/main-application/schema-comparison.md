# Schema comparison

Code: `main-application/src/SchemaComparator.php`.

`compare($env1Tables, $env2Tables)` takes the `tables` arrays of two endpoints.
Inside the class env1 is called *offline* and env2 *online* (historic names).

## Result structure

```php
[
  'missing_tables_online'  => ['tbl', ...],   // in env1 only
  'missing_tables_offline' => ['tbl', ...],   // in env2 only
  'table_differences' => [
    'tbl' => [                                 // only non-empty keys are kept
      'missing_columns_online'      => [<column>, ...],
      'missing_columns_offline'     => [<column>, ...],
      'column_differences'          => ['col' => ['type' => ['offline' => .., 'online' => ..], ...]],
      'missing_indexes_online'      => [<index>, ...],
      'missing_indexes_offline'     => [<index>, ...],
      'missing_foreign_keys_online' => [<fk>, ...],
      'missing_foreign_keys_offline'=> [<fk>, ...],
      'primary_key_difference'      => ['type' => 'missing_offline'|'missing_online'|'different', ...],
    ],
  ],
]
```

Tables, columns, indexes and foreign keys are matched **by name**.

## What is compared

| Item | Compared | Not compared |
|---|---|---|
| Table | existence | engine, charset, collation, table comment |
| Column | existence, type, nullable, default, extra, comment | charset, collation, position |
| Primary key | existence, column list and order | |
| Index | existence by name | columns, uniqueness, type of an index that exists on both sides |
| Foreign key | existence by name | columns, referenced table, `ON DELETE/UPDATE` |

Column comments are compared only when **both** endpoints report a `comment`
field; an old endpoint without that field produces no comment differences.

## Normalization (MySQL 8 vs. MariaDB / MySQL 5.x)

Both servers describe identical columns differently. These rules prevent false
positives:

| Rule | Method | Example treated as equal |
|---|---|---|
| Integer display width is ignored | `normalizeType()` | `int(11)` = `int` |
| ...except `tinyint(1)` and `ZEROFILL` | `normalizeType()` | `tinyint(1)` ≠ `tinyint` |
| Missing default as text | `defaultCandidates()` | `'NULL'` = `null` |
| Quoted string defaults | `defaultCandidates()` | `'abc'` = `abc` |
| Timestamp function spelling | `defaultCandidates()` | `CURRENT_TIMESTAMP` = `current_timestamp()` |
| `DEFAULT_GENERATED` marker | `normalizeExtra()` | `DEFAULT_GENERATED on update CURRENT_TIMESTAMP` = `on update current_timestamp()` |
| `now()` etc. as synonyms | `normalizeExtra()` | `on update now()` = `on update current_timestamp` |
| MariaDB `PERSISTENT` | `normalizeExtra()` | `persistent generated` = `stored generated` |

Defaults are compared by candidate sets: since the flavour of each side is
unknown, two defaults are equal if **any** interpretation of one matches any
interpretation of the other.

## Counting

`countDifferences()` adds up: missing tables (both sides), missing columns,
differing columns (one per column, however many properties differ), missing
indexes, missing foreign keys, and one per primary key difference.

This number is the dashboard badge and `sync_history.differences_found`.

## Tests

There are no dedicated comparator tests yet; `tests/SqlGeneratorTest.php`
covers comparator behaviour where it feeds the SQL generator (defaults, extra,
comments). Add a test there when changing a normalization rule.
