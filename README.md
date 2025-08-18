# SEM Data Migration Demo

A Laravel application demonstrating data migration patterns between MySQL and MongoDB databases. This project showcases how to import, transform, and migrate data between different database systems without requiring database migrations.

## 🚀 Features

- **CSV Data Import**: Import cafe sales data from CSV files into MySQL
- **Cross-Database Migration**: Copy data from MySQL to MongoDB with flexible options
- **Dual Database Support**: Seamless integration between MySQL and MongoDB
- **Data Transformation**: Support for both normalized and denormalized data structures
- **Batch Processing**: Efficient handling of large datasets with configurable batch sizes
- **Migration Validation**: Built-in connection validation and dry-run capabilities

## 📋 Requirements

- **PHP 8.2+**
- **Composer**
- **Node.js & NPM**
- **MySQL 8.0+** or **MariaDB**
- **MongoDB 4.4+**
- **MongoDB PHP Extension**

## 🛠️ Installation & Setup

### 1. Clone the Repository

```bash
git clone <repository-url>
cd sem-data-migratrion-demo
```

### 2. Install MongoDB PHP Extension

The MongoDB PHP extension is required for this project to function properly.

#### On Ubuntu/Debian

```bash
sudo apt update
sudo apt install php-dev php-pear libssl-dev pkg-config
sudo pecl install mongodb
echo "extension=mongodb.so" | sudo tee -a /etc/php/8.2/cli/php.ini
echo "extension=mongodb.so" | sudo tee -a /etc/php/8.2/fpm/php.ini
```

#### On macOS (using Homebrew)

```bash
brew install mongodb/brew/mongodb-database-tools
pecl install mongodb
echo "extension=mongodb.so" >> $(php --ini | grep "Loaded Configuration" | sed -e "s|.*:\s*||")
```

#### On Windows

1. Download the MongoDB PHP extension from [PECL](https://pecl.php.net/package/mongodb)
2. Extract and copy `php_mongodb.dll` to your PHP extensions directory
3. Add `extension=php_mongodb` to your `php.ini` file

#### Verify Installation

```bash
php -m | grep mongodb
```

### 3. Install Dependencies

```bash
# Install PHP dependencies
composer install

# Install Node.js dependencies
npm install
```

### 4. Environment Configuration

```bash
# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate
```

### 5. Configure Database Connections

Edit your `.env` file with your database credentials:

```env
# MySQL Configuration
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=your_username
DB_PASSWORD=your_password

# MongoDB Configuration
DB_URI=mongodb://localhost:27017
MONGODB_DATABASE=your_mongodb_database
```

### 6. Run Database Migrations

```bash
php artisan migrate
```

### 7. Seed Sample Data (Optional)

```bash
php artisan db:seed
```

### 8. Start the Application

```bash
# Development server
php artisan serve

# Or using the composer script for full development environment
composer run dev
```

## 🎯 Available Commands

### Import Cafe Sales Data

Import CSV data into MySQL database:

```bash
# Import from default file (dataset_scripts/cleaned_cafe_sales.csv)
php artisan cafe:import-sales

# Import from specific CSV file
php artisan cafe:import-sales /path/to/your/file.csv
```

**Example:**

```bash
php artisan cafe:import-sales dataset_scripts/cleaned_cafe_sales.csv
```

This command:

- Validates MySQL connection
- Reads CSV data and processes it in batches
- Creates items and payment methods automatically
- Imports transactions while avoiding duplicates
- Displays a detailed summary of the import process

### Copy Data to MongoDB

Copy data from MySQL to MongoDB **without requiring database migrations**:

```bash
# Basic migration (normalized structure)
php artisan data:copy-to-mongodb

# Migration with options
php artisan data:copy-to-mongodb --batch-size=500 --denormalize --dry-run
```

**Available Options:**

- `--batch-size=1000`: Number of records to process at once (default: 1000)
- `--denormalize`: Store denormalized data with embedded relationships
- `--dry-run`: Preview what would be migrated without actually copying data

**Examples:**

1. **Basic Migration (Normalized)**

```bash
php artisan data:copy-to-mongodb
```

This creates separate MongoDB collections: `users`, `items`, `payment_methods`, `transactions`

2. **Denormalized Migration**

```bash
php artisan data:copy-to-mongodb --denormalize
```

This creates: `users` and `transactions_with_details` (with embedded item and payment method data)

3. **Dry Run**

```bash
php artisan data:copy-to-mongodb --dry-run
```

Shows what would be migrated without actually copying data

4. **Custom Batch Size**

```bash
php artisan data:copy-to-mongodb --batch-size=500
```

Processes 500 records per batch instead of the default 1000

**Important Notes:**

- ✅ **No database migrations required** - Works directly with existing data
- ✅ **Connection validation** - Automatically validates both MySQL and MongoDB connections
- ✅ **Data integrity** - Preserves all relationships and data types
- ✅ **Flexible structure** - Choose between normalized or denormalized data storage
- ✅ **Safe operation** - Dry-run mode lets you preview before actual migration

## 📊 Project Structure

```text
app/
├── Console/Commands/
│   ├── ImportCafeSalesData.php    # CSV import command
│   └── CopyDataToMongoDB.php      # MySQL to MongoDB migration
├── Models/
│   ├── User.php
│   ├── Item.php
│   ├── PaymentMethod.php
│   └── Transaction.php
database/
├── migrations/                    # MySQL table definitions
├── seeders/                      # Sample data seeders
└── factories/                    # Model factories
dataset_scripts/
├── cleaned_cafe_sales.csv        # Sample CSV data
└── main.py                       # Python EDA script
```

## 🔧 Development

### Running Tests

```bash
php artisan test
```

### Code Style

```bash
vendor/bin/pint
```

### Queue Workers

```bash
php artisan queue:work
```

## 📖 Usage Examples

### Complete Workflow Example

1. **Import CSV data to MySQL**

```bash
php artisan cafe:import-sales dataset_scripts/cleaned_cafe_sales.csv
```

2. **Preview migration** (recommended first step)

```bash
php artisan data:copy-to-mongodb --dry-run
```

3. **Migrate to MongoDB** (normalized)

```bash
php artisan data:copy-to-mongodb
```

4. **Or migrate with denormalized structure**

```bash
php artisan data:copy-to-mongodb --denormalize
```

### Working with Different Data Structures

**Normalized Structure** (separate collections):

- `users` collection
- `items` collection  
- `payment_methods` collection
- `transactions` collection (references other collections)

**Denormalized Structure** (embedded data):

- `users` collection
- `transactions_with_details` collection (contains embedded item and payment method data)

## 🚨 Troubleshooting

### MongoDB Extension Issues

If you encounter MongoDB extension errors:

1. **Verify installation**

```bash
php -m | grep mongodb
```

2. **Check PHP version compatibility**

```bash
php --version
```

3. **Restart web server** after installing the extension

### Database Connection Issues

1. **Test MySQL connection**

```bash
php artisan tinker
DB::connection('mysql')->getPdo();
```

2. **Test MongoDB connection**

```bash
php artisan tinker
DB::connection('mongodb')->getMongoDB()->listCollections();
```

### Common Issues

- **"Class 'MongoDB\Laravel\Connection' not found"**: Install MongoDB PHP extension
- **Connection timeout**: Check your database server status and credentials
- **Permission denied**: Ensure proper file permissions for the project directory

## 📝 License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
