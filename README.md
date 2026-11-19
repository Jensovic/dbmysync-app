# 🔄 DbMySync

**Database Schema Comparison & Synchronization Tool**

DbMySync is a powerful PHP-based tool for comparing MySQL database schemas between different environments (Dev, Staging, Production) and generating SQL statements to synchronize them.

![PHP](https://img.shields.io/badge/PHP-8.1%2B-blue)
![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-orange)
![License](https://img.shields.io/badge/license-MIT-green)

## ✨ Features

- 🔍 **Schema Comparison**: Compare database schemas between two environments
- 📊 **Detailed Diff View**: See differences in tables, columns, indexes, and foreign keys
- 🛠️ **SQL Generation**: Automatically generate SQL statements to sync databases
- 🎯 **Granular Control**: Generate SQL for specific tables, columns, or changes
- 🔐 **Secure API**: Token-based authentication for endpoint access
- 🎨 **Modern UI**: Clean, responsive interface with modal dialogs
- 📋 **Copy to Clipboard**: One-click copy of generated SQL statements
- 🔄 **Bidirectional Sync**: Sync from Dev to Prod or Prod to Dev
- ⚡ **Fast Dashboard**: Difference counts are cached and refreshed on demand, per pair, per group or all at once

## 📦 What It Detects

### Tables
- ✅ Missing tables in either environment
- ✅ Complete CREATE TABLE statements with all constraints

### Columns
- ✅ Missing columns
- ✅ Different data types
- ✅ Different nullable settings
- ✅ Different default values
- ✅ Different extra attributes (`auto_increment`, `ON UPDATE CURRENT_TIMESTAMP`, generated columns)
- ✅ Different column comments (only when both endpoints report them, dbmysync-addin with `comment` support)
- ❌ Charset/collation are carried into generated SQL, but not compared

#### Generated SQL
CREATE TABLE, ADD COLUMN and MODIFY COLUMN share one column definition built only from
what the source reports: no default is invented, `ON UPDATE` and `AUTO_INCREMENT` are kept.
Because MODIFY replaces the whole definition, a default that exists only in the target is
removed. Generated (virtual/stored) columns cannot be reproduced - the endpoint does not
report their expression - and are flagged with a warning comment.

The storage engine for CREATE TABLE is set per environment in the tool pair
(e.g. MyISAM locally, InnoDB in production; empty = no ENGINE clause).

#### MySQL 8 vs. MariaDB / MySQL 5.x
Both servers report the same schema differently in `INFORMATION_SCHEMA`. These
variants are treated as equal, so comparing across versions shows no false positives:

| Older MySQL / MariaDB | MySQL 8 | Why it is equal |
|---|---|---|
| `int(11)`, `tinyint(4)`, `bigint(20)` | `int`, `tinyint`, `bigint` | Integer display width has no effect on storage; MySQL 8.0.19+ omits it |
| Default `NULL` (as text) | Default `NULL` | MariaDB reports a missing default as the string `NULL` |
| Default `'abc'` | Default `abc` | MariaDB quotes string defaults, MySQL does not |
| Default `CURRENT_TIMESTAMP` | Default `current_timestamp()` | Same expression |
| Extra `on update current_timestamp()` | Extra `DEFAULT_GENERATED on update CURRENT_TIMESTAMP` | MySQL 8 flags every expression default |

Still reported as differences: `tinyint(1)` vs. other `tinyint` widths (it marks a
boolean, and MySQL 8 keeps it), integer widths with `ZEROFILL`, and any length of
non-integer types such as `varchar(50)` vs. `varchar(255)`.

### Indexes
- ✅ Missing indexes (matched by name)
- ❌ Same index name with different columns or uniqueness

### Foreign Keys
- ✅ Missing foreign keys (matched by name)
- ❌ Different referenced tables/columns, ON DELETE/UPDATE actions

See [docs/known-limitations.md](docs/known-limitations.md) for everything that is
not (yet) detected or generated.

## 🚀 Installation

### Requirements
- PHP 8.1 or higher (main application and endpoint; PHP 7 is no longer supported)
- MySQL 5.7 or higher
- Composer
- Apache/Nginx with mod_rewrite

### 1. Clone the Repository
```bash
git clone https://github.com/Jensovic/dbmysync-app.git
cd dbmysync-app
```

### 2. Install Dependencies

**Main Application:**
```bash
cd main-application
composer install
```

**Endpoint:**
```bash
cd ../endpoint
composer install
```

`composer.lock` is not versioned, so `composer install` resolves the newest
versions that fit the PHP version running Composer. Run it with the PHP version
the server uses and check the result with `composer audit`.

### 3. Configure Database

**Main Application:**
- The SQLite database will be created automatically at `main-application/data/dbmysync.db`
- Import the schema: `sqlite3 data/dbmysync.db < database/schema.sql`

**Endpoint:**
```bash
cd endpoint/config
cp config.example.php config.php
```

Edit `config.php` with your database credentials:
```php
return [
    'db_host' => 'localhost',
    'db_name' => 'your_database',
    'db_user' => 'your_username',
    'db_pass' => 'your_password',
    'api_token' => 'your-secure-random-token-here'
];
```

### 4. Set Up Web Server

**Apache (.htaccess included):**
- Point your virtual host to `main-application/public/` for the main app
- Point another virtual host to `endpoint/public/` for the endpoint

**Nginx:**
```nginx
server {
    listen 80;
    server_name dbmysync.local;
    root /path/to/dbmysync-app/main-application/public;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
    }
}
```

## 📖 Usage

### 1. Add Environment Pair
1. Open the main application in your browser
2. Click "Add New Tool Pair"
3. Enter names for both environments (e.g., "Production" and "Development")
4. Enter the endpoint URLs and API tokens
5. Save

### 2. Compare Schemas
1. Click "Compare" on your tool pair
2. View the detailed comparison results
3. See differences in tables, columns, indexes, and foreign keys

### 3. Generate SQL
- Click "SQL to adjust Prod" to sync Production with Development
- Click "SQL to adjust Dev" to sync Development with Production
- Use granular buttons for specific tables or columns
- Copy the generated SQL and execute it in your database

## 🏗️ Architecture

```
dbmysync-app/
├── main-application/     # Main web interface
│   ├── public/          # Web root (index.php, styles.css, dashboard.js, icons/)
│   ├── src/             # PHP classes
│   ├── views/           # HTML templates
│   ├── data/            # SQLite database
│   └── database/        # SQL schemas
│
├── endpoint/            # API endpoint (deploy on each server)
│   ├── public/          # Web root
│   ├── src/             # API logic
│   └── config/          # Database configuration
│
├── test-installation/   # Test scripts
└── docs/                # Developer documentation
```

Detailed developer documentation (architecture, endpoint API, comparison and
SQL rules, operations) lives in [docs/](docs/README.md).

## 🔒 Security

- ✅ Token-based API authentication
- ✅ SQL injection protection via PDO prepared statements
- ✅ No passwords stored in main application
- ✅ HTTPS recommended for production use

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 👨‍💻 Author

**Jensovic**
- GitHub: [@Jensovic](https://github.com/Jensovic)

## 🙏 Acknowledgments

- Built with PHP and love ❤️
- Uses Guzzle for HTTP requests
- SQLite for local data storage

---

**⭐ If you find this tool useful, please consider giving it a star!**

