# 🔄 DbMySync

**Database Schema Comparison & Synchronization Tool**

DbMySync is a powerful PHP-based tool for comparing MySQL database schemas between different environments (Dev, Staging, Production) and generating SQL statements to synchronize them.

![PHP](https://img.shields.io/badge/PHP-7.4%2B-blue)
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

## 📦 What It Detects

### Tables
- ✅ Missing tables in either environment
- ✅ Complete CREATE TABLE statements with all constraints

### Columns
- ✅ Missing columns
- ✅ Different data types
- ✅ Different nullable settings
- ✅ Different default values
- ✅ Different charset/collation
- ✅ Different extra attributes (auto_increment, etc.)

### Indexes
- ✅ Missing indexes
- ✅ Different index types (UNIQUE, KEY)
- ✅ Different indexed columns

### Foreign Keys
- ✅ Missing foreign keys
- ✅ Different referenced tables/columns
- ✅ Different ON DELETE/UPDATE actions

## 🚀 Installation

### Requirements
- PHP 7.4 or higher
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
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
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
│   ├── public/          # Web root
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
└── test-installation/   # Test scripts
```

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

