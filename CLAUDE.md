# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a Laravel application demonstrating data migration patterns between MySQL and MongoDB databases. The project showcases importing CSV data into MySQL and then migrating it to MongoDB with flexible data structure options.

## Key Commands

### Development
- `composer run dev` - Start full development environment (Laravel server + queue worker + Vite)
- `php artisan serve` - Start Laravel development server only
- `npm run dev` - Start Vite development server
- `npm run build` - Build assets for production

### Testing and Code Quality
- `php artisan test` - Run PHPUnit tests
- `vendor/bin/pint` - Run Laravel Pint code style fixer

### Database Operations
- `php artisan migrate` - Run Laravel migrations (MySQL)
- `php artisan migrate --database=mongodb` - Run MongoDB migrations
- `php artisan db:seed` - Seed sample data

### Data Migration Commands
- `php artisan cafe:import-sales [file]` - Import CSV cafe sales data to MySQL
- `php artisan data:copy-to-mongodb [options]` - Copy data from MySQL to MongoDB

#### Migration Options
- `--batch-size=1000` - Number of records to process at once
- `--denormalize` - Store denormalized data with embedded relationships
- `--dry-run` - Preview migration without copying data
- `--migrate` - Run MongoDB migrations before copying

## Architecture

### Database Structure
The project uses a dual-database architecture:

**MySQL Models (Eloquent):**
- `User` - Standard Laravel user model
- `Item` - Cafe items with pricing
- `PaymentMethod` - Payment methods (cash, card, etc.)
- `Transaction` - Individual sales transactions with foreign keys

**MongoDB Models (Laravel-MongoDB):**
- `MongoUser` - User data in MongoDB
- `MongoItem` - Items collection
- `MongoPaymentMethod` - Payment methods collection
- `MongoTransaction` - Normalized transactions (references other collections)
- `MongoTransactionWithDetails` - Denormalized transactions (embedded data)

### Key Components

**Console Commands:**
- `ImportCafeSalesData` (app/Console/Commands/ImportCafeSalesData.php:18) - MySQL-specific CSV import with batch processing and caching
- `CopyDataToMongoDB` (app/Console/Commands/CopyDataToMongoDB.php:25) - Multi-database migration with validation and options

**Data Processing Features:**
- Batch processing for large datasets
- Connection validation for both databases
- Dry-run capability for safe testing
- Progress bars and detailed reporting
- Error handling and rollback protection

### Migration Patterns

The project supports two data structure approaches:

1. **Normalized Structure**: Separate collections mirroring MySQL tables
2. **Denormalized Structure**: Embedded documents for better MongoDB performance

### Configuration Requirements

**Environment Variables:**
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

**PHP Extensions Required:**
- MongoDB PHP extension (`php-mongodb`)

### Development Notes

- All models explicitly specify database connections using `on('mysql')` or `on('mongodb')`
- CSV processing uses chunked reading to handle large files
- Transaction safety implemented for MySQL imports
- MongoDB collections are dropped and recreated during migration to ensure clean state
- Progress tracking and detailed error reporting throughout all operations