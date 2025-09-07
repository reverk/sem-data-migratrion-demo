<?php

namespace App\Console\Commands;

use App\Models\Item;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use App\Models\User;
use App\Models\MongoUser;
use App\Models\MongoItem;
use App\Models\MongoPaymentMethod;
use App\Models\MongoTransaction;
use App\Models\MongoTransactionWithDetails;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use MongoDB\Laravel\Connection;

/**
 * Copy all data from MySQL to MongoDB
 * 
 * This command copies all data from the MySQL database to MongoDB,
 * maintaining the same structure and relationships.
 */
class CopyDataToMongoDB extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'data:copy-to-mongodb 
                            {--batch-size=1000 : Number of records to process at once}
                            {--denormalize : Store denormalized data (embed relationships)}
                            {--migrate : Run MongoDB migrations before copying data}
                            {--dry-run : Show what would be copied without actually copying}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Copy all data from MySQL to MongoDB (optionally run migrations first)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (!$this->validateConnections()) {
            return 1;
        }

        $isDryRun = $this->option('dry-run');
        $batchSize = (int) $this->option('batch-size');
        $denormalize = $this->option('denormalize');
        $runMigrations = $this->option('migrate');

        if ($isDryRun) {
            $this->warn('🔍 DRY RUN MODE - No data will be actually copied');
        }

        $this->info('🚀 Starting data migration from MySQL to MongoDB');
        
        if ($denormalize) {
            $this->info('📊 Using denormalized structure (embedded relationships)');
        } else {
            $this->info('📋 Using normalized structure (separate collections)');
        }

        try {
            // Run MongoDB migrations if requested
            if ($runMigrations && !$isDryRun) {
                if (!$this->runMongoDBMigrations()) {
                    return 1;
                }
            } elseif ($runMigrations && $isDryRun) {
                $this->info('🔍 DRY RUN: Would run MongoDB migrations');
            }

            // Get counts from MySQL
            $counts = $this->getMySQLCounts();
            $this->displayDataSummary($counts);

            if (!$counts['total']) {
                $this->warn('No data found in MySQL database to copy');
                return 0;
            }

            if (!$isDryRun) {
                if (!$this->confirm('Do you want to proceed with the data migration?')) {
                    $this->info('Migration cancelled');
                    return 0;
                }

                // Check for existing data and warn user
                try {
                    $mongoCount = MongoTransaction::count();
                    if ($mongoCount > 0) {
                        $this->warn("⚠️  Found {$mongoCount} existing documents in MongoDB transactions collection");
                        if (!$this->confirm('This will drop existing collections and recreate them. Continue?')) {
                            $this->info('Migration cancelled');
                            return 0;
                        }
                    }
                } catch (\Exception $e) {
                    // Collection might not exist yet, which is fine
                    $this->info('📝 MongoDB collections will be created from scratch');
                }

                // Clear MongoDB collections first
                $this->clearMongoDBCollections();
            }

            // Copy data
            if ($denormalize) {
                $result = $this->copyDataDenormalized($batchSize, $isDryRun);
            } else {
                $result = $this->copyDataNormalized($batchSize, $isDryRun);
            }

            $this->displayMigrationSummary($result, $isDryRun);

            return 0;

        } catch (\Exception $e) {
            $this->error("Migration failed: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());
            return 1;
        }
    }

    /**
     * Validate database connections
     */
    private function validateConnections(): bool
    {
        try {
            // Test MySQL connection
            $mysqlConnection = DB::connection('mysql');
            if ($mysqlConnection->getDriverName() !== 'mysql') {
                $this->error('MySQL connection not configured properly');
                return false;
            }
            $mysqlConnection->getPdo();
            $this->info('✓ MySQL connection validated');

            // Test MongoDB connection
            $mongoConnection = DB::connection('mongodb');
            if (!($mongoConnection instanceof Connection)) {
                $this->error('MongoDB connection not configured properly');
                return false;
            }
            // Test the connection by attempting to list collections
            $mongoConnection->getMongoDB()->listCollections();
            $this->info('✓ MongoDB connection validated');

            return true;

        } catch (\Exception $e) {
            $this->error("Connection validation failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get record counts from MySQL
     */
    private function getMySQLCounts(): array
    {
        return [
            // 'users' => User::on('mysql')->count(),
            'items' => Item::on('mysql')->count(),
            'payment_methods' => PaymentMethod::on('mysql')->count(),
            'transactions' => Transaction::on('mysql')->count(),
            'total' => Item::on('mysql')->count() + 
                      PaymentMethod::on('mysql')->count() + Transaction::on('mysql')->count(),
        ];
    }

    /**
     * Display data summary
     */
    private function displayDataSummary(array $counts): void
    {
        $this->newLine();
        $this->info('📊 Data Summary from MySQL:');
        $this->table(
            ['Collection', 'Record Count'],
            [
                // ['Users', number_format($counts['users'])],
                ['Items', number_format($counts['items'])],
                ['Payment Methods', number_format($counts['payment_methods'])],
                ['Transactions', number_format($counts['transactions'])],
                ['TOTAL', number_format($counts['total'])],
            ]
        );
        $this->newLine();
    }

    /**
     * Clear MongoDB collections
     */
    private function clearMongoDBCollections(): void
    {
        $this->info('🗑️  Clearing existing MongoDB collections...');
        
        $models = [
            // 'users' => MongoUser::class,
            'items' => MongoItem::class,
            'payment_methods' => MongoPaymentMethod::class,
            'transactions' => MongoTransaction::class,
            'transactions_with_details' => MongoTransactionWithDetails::class,
        ];
        
        foreach ($models as $collectionName => $modelClass) {
            try {
                // Drop the entire collection (including indexes) instead of just truncating
                $modelClass::raw(function ($collection) {
                    $collection->drop();
                });
            } catch (\Exception $e) {
                // Collection might not exist, which is fine
                $this->warn("Could not drop collection {$collectionName}: " . $e->getMessage());
            }
        }
        
        $this->info('✓ MongoDB collections dropped (including indexes)');
    }

    /**
     * Copy data using normalized structure (separate collections)
     */
    private function copyDataNormalized(int $batchSize, bool $isDryRun): array
    {
        $result = [
            // 'users' => ['copied' => 0, 'errors' => 0],
            'items' => ['copied' => 0, 'errors' => 0],
            'payment_methods' => ['copied' => 0, 'errors' => 0],
            'transactions' => ['copied' => 0, 'errors' => 0],
        ];

        // Copy Users
        // $this->info('👥 Copying Users...');
        // $result['users'] = $this->copyUsers($batchSize, $isDryRun);

        // Copy Items
        $this->info('📦 Copying Items...');
        $result['items'] = $this->copyItems($batchSize, $isDryRun);

        // Copy Payment Methods
        $this->info('💳 Copying Payment Methods...');
        $result['payment_methods'] = $this->copyPaymentMethods($batchSize, $isDryRun);

        // Copy Transactions
        $this->info('💰 Copying Transactions...');
        $result['transactions'] = $this->copyTransactions($batchSize, $isDryRun);

        return $result;
    }

    /**
     * Copy data using denormalized structure (embedded relationships)
     */
    private function copyDataDenormalized(int $batchSize, bool $isDryRun): array
    {
        $result = [
            // 'users' => ['copied' => 0, 'errors' => 0],
            'transactions_with_details' => ['copied' => 0, 'errors' => 0],
        ];

        // Copy Users (still separate as they might not be related to transactions)
        // $this->info('👥 Copying Users...');
        // $result['users'] = $this->copyUsers($batchSize, $isDryRun);

        // Copy Transactions with embedded Item and Payment Method data
        $this->info('💰 Copying Transactions with embedded details...');
        $result['transactions_with_details'] = $this->copyTransactionsDenormalized($batchSize, $isDryRun);

        return $result;
    }

    /**
     * Copy users to MongoDB
     */
    private function copyUsers(int $batchSize, bool $isDryRun): array
    {
        $result = ['copied' => 0, 'errors' => 0];
        $totalUsers = User::on('mysql')->count();
        
        if (!$totalUsers) {
            return $result;
        }

        $progressBar = $this->output->createProgressBar($totalUsers);
        $progressBar->start();

        User::on('mysql')->chunk($batchSize, function ($users) use (&$result, $progressBar, $isDryRun) {
            foreach ($users as $user) {
                try {
                    if (!$isDryRun) {
                        MongoUser::create([
                            '_id' => $user->id,
                            'name' => $user->name,
                            'email' => $user->email,
                            'email_verified_at' => $user->email_verified_at,
                            'password' => $user->password,
                            'remember_token' => $user->remember_token,
                            'created_at' => $user->created_at,
                            'updated_at' => $user->updated_at,
                        ]);
                    }
                    $result['copied']++;
                } catch (\Exception $e) {
                    $result['errors']++;
                    $this->newLine();
                    $this->warn("Error copying user {$user->id}: " . $e->getMessage());
                }
                
                $progressBar->advance();
            }
        });

        $progressBar->finish();
        $this->newLine();

        return $result;
    }

    /**
     * Copy items to MongoDB
     */
    private function copyItems(int $batchSize, bool $isDryRun): array
    {
        $result = ['copied' => 0, 'errors' => 0];
        $totalItems = Item::on('mysql')->count();
        
        if (!$totalItems) {
            return $result;
        }

        $progressBar = $this->output->createProgressBar($totalItems);
        $progressBar->start();

        Item::on('mysql')->chunk($batchSize, function ($items) use (&$result, $progressBar, $isDryRun) {
            foreach ($items as $item) {
                try {
                    if (!$isDryRun) {
                        MongoItem::create([
                            '_id' => $item->item_id,
                            'item_name' => $item->item_name,
                            'price_per_unit' => $item->price_per_unit,
                            'created_at' => $item->created_at,
                            'updated_at' => $item->updated_at,
                        ]);
                    }
                    $result['copied']++;
                } catch (\Exception $e) {
                    $result['errors']++;
                    $this->newLine();
                    $this->warn("Error copying item {$item->item_id}: " . $e->getMessage());
                }
                
                $progressBar->advance();
            }
        });

        $progressBar->finish();
        $this->newLine();

        return $result;
    }

    /**
     * Copy payment methods to MongoDB
     */
    private function copyPaymentMethods(int $batchSize, bool $isDryRun): array
    {
        $result = ['copied' => 0, 'errors' => 0];
        $totalPaymentMethods = PaymentMethod::on('mysql')->count();
        
        if (!$totalPaymentMethods) {
            return $result;
        }

        $progressBar = $this->output->createProgressBar($totalPaymentMethods);
        $progressBar->start();

        PaymentMethod::on('mysql')->chunk($batchSize, function ($paymentMethods) use (&$result, $progressBar, $isDryRun) {
            foreach ($paymentMethods as $paymentMethod) {
                try {
                    if (!$isDryRun) {
                        MongoPaymentMethod::create([
                            '_id' => $paymentMethod->payment_method_id,
                            'method_name' => $paymentMethod->method_name,
                            'created_at' => $paymentMethod->created_at,
                            'updated_at' => $paymentMethod->updated_at,
                        ]);
                    }
                    $result['copied']++;
                } catch (\Exception $e) {
                    $result['errors']++;
                    $this->newLine();
                    $this->warn("Error copying payment method {$paymentMethod->payment_method_id}: " . $e->getMessage());
                }
                
                $progressBar->advance();
            }
        });

        $progressBar->finish();
        $this->newLine();

        return $result;
    }

    /**
     * Copy transactions to MongoDB (normalized)
     */
    private function copyTransactions(int $batchSize, bool $isDryRun): array
    {
        $result = ['copied' => 0, 'errors' => 0];
        $totalTransactions = Transaction::on('mysql')->count();
        
        if (!$totalTransactions) {
            return $result;
        }

        $progressBar = $this->output->createProgressBar($totalTransactions);
        $progressBar->start();

        Transaction::on('mysql')->chunk($batchSize, function ($transactions) use (&$result, $progressBar, $isDryRun) {
            foreach ($transactions as $transaction) {
                try {
                    if (!$isDryRun) {
                        MongoTransaction::create([
                            '_id' => $transaction->transaction_id,
                            'item_id' => $transaction->item_id,
                            'payment_method_id' => $transaction->payment_method_id,
                            'quantity' => $transaction->quantity,
                            'total_spent' => $transaction->total_spent,
                            'location' => $transaction->location,
                            'transaction_date' => $transaction->transaction_date,
                            'created_at' => $transaction->created_at,
                            'updated_at' => $transaction->updated_at,
                        ]);
                    }
                    $result['copied']++;
                } catch (\Exception $e) {
                    $result['errors']++;
                    $this->newLine();
                    $this->warn("Error copying transaction {$transaction->transaction_id}: " . $e->getMessage());
                }
                
                $progressBar->advance();
            }
        });

        $progressBar->finish();
        $this->newLine();

        return $result;
    }

    /**
     * Copy transactions with embedded item and payment method data (denormalized)
     */
    private function copyTransactionsDenormalized(int $batchSize, bool $isDryRun): array
    {
        $result = ['copied' => 0, 'errors' => 0];
        $totalTransactions = Transaction::on('mysql')->count();
        
        if (!$totalTransactions) {
            return $result;
        }

        $progressBar = $this->output->createProgressBar($totalTransactions);
        $progressBar->start();

        Transaction::on('mysql')->with(['item', 'paymentMethod'])->chunk($batchSize, function ($transactions) use (&$result, $progressBar, $isDryRun) {
            foreach ($transactions as $transaction) {
                try {
                    if (!$isDryRun) {
                        $document = [
                            '_id' => $transaction->transaction_id,
                            'quantity' => $transaction->quantity,
                            'total_spent' => $transaction->total_spent,
                            'location' => $transaction->location,
                            'transaction_date' => $transaction->transaction_date,
                            'created_at' => $transaction->created_at,
                            'updated_at' => $transaction->updated_at,
                        ];

                        // Embed item data
                        if ($transaction->item) {
                            $document['item'] = [
                                'item_id' => $transaction->item->item_id,
                                'item_name' => $transaction->item->item_name,
                                'price_per_unit' => $transaction->item->price_per_unit,
                            ];
                        }

                        // Embed payment method data
                        if ($transaction->paymentMethod) {
                            $document['payment_method'] = [
                                'payment_method_id' => $transaction->paymentMethod->payment_method_id,
                                'method_name' => $transaction->paymentMethod->method_name,
                            ];
                        }

                        MongoTransactionWithDetails::create($document);
                    }
                    $result['copied']++;
                } catch (\Exception $e) {
                    $result['errors']++;
                    $this->newLine();
                    $this->warn("Error copying denormalized transaction {$transaction->transaction_id}: " . $e->getMessage());
                }
                
                $progressBar->advance();
            }
        });

        $progressBar->finish();
        $this->newLine();

        return $result;
    }

    /**
     * Display migration summary
     */
    private function displayMigrationSummary(array $result, bool $isDryRun): void
    {
        $this->newLine();
        
        if ($isDryRun) {
            $this->info('🔍 DRY RUN SUMMARY - No data was actually copied');
        } else {
            $this->info('✅ MIGRATION COMPLETED');
        }
        
        $tableData = [];
        $totalCopied = 0;
        $totalErrors = 0;

        foreach ($result as $collection => $stats) {
            $tableData[] = [
                ucfirst(str_replace('_', ' ', $collection)),
                number_format($stats['copied']),
                number_format($stats['errors'])
            ];
            $totalCopied += $stats['copied'];
            $totalErrors += $stats['errors'];
        }

        $tableData[] = ['---', '---', '---'];
        $tableData[] = ['TOTAL', number_format($totalCopied), number_format($totalErrors)];

        $this->table(
            ['Collection', 'Records ' . ($isDryRun ? 'Found' : 'Copied'), 'Errors'],
            $tableData
        );

        if ($totalErrors > 0) {
            $this->warn("⚠️  {$totalErrors} errors occurred during migration. Check logs above for details.");
        }

        if (!$isDryRun && $totalCopied > 0) {
            $this->info("🎉 Successfully copied {$totalCopied} records to MongoDB!");
        }
    }

    /**
     * Run MongoDB migrations
     */
    private function runMongoDBMigrations(): bool
    {
        $this->info('📝 Running MongoDB migrations...');

        try {
            $exitCode = Artisan::call('migrate', [
                '--database' => 'mongodb',
                '--force' => true, // Don't ask for confirmation in production
            ]);

            if ($exitCode === 0) {
                $this->info('✅ MongoDB migrations completed successfully');
                
                // Display migration output if there's any
                $output = Artisan::output();
                if (!empty(trim($output))) {
                    $this->line($output);
                }
                
                return true;
            } else {
                $this->error('❌ MongoDB migrations failed');
                $this->error(Artisan::output());
                return false;
            }

        } catch (\Exception $e) {
            $this->error("Migration error: " . $e->getMessage());
            return false;
        }
    }
}
