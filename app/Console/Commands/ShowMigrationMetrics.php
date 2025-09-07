<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Item;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use App\Models\MongoItem;
use App\Models\MongoPaymentMethod;
use App\Models\MongoTransaction;
use App\Models\MongoTransactionWithDetails;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Migration Metrics Command
 * 
 * Database Usage Strategy:
 * - MySQL models: Transaction, Item, PaymentMethod (use Eloquent with connection defined in model)
 * - MongoDB models: MongoTransaction, MongoItem, MongoPaymentMethod, MongoTransactionWithDetails
 * - Avoids raw SQL queries to ensure MongoDB compatibility
 * - Uses Eloquent ORM exclusively to prevent "method not supported by MongoDB" errors
 */
class ShowMigrationMetrics extends Command
{
    protected $signature = 'migration:metrics 
                            {--input-file=./dataset_scripts/dirty_cafe_sales.csv : Path to original input CSV file}
                            {--cleaned-file=./dataset_scripts/cleaned_cafe_sales.csv : Path to cleaned CSV file}';

    protected $description = 'Display comprehensive migration metrics and data quality assessment';

    public function handle()
    {
        $this->info('=== DATA MIGRATION METRICS REPORT ===');
        $this->newLine();

        $this->displayRecordCounts();
        $this->newLine();
        
        $this->displayIntermediateStepMetrics();
        $this->newLine();
        
        // $this->displayCompletenessMetrics();
        // $this->newLine();
        
        // $this->displayAccuracyMetrics();
        // $this->newLine();
        
        // $this->displayConsistencyMetrics();
        // $this->newLine();
        
        // $this->displayUniquenessMetrics();
        // $this->newLine();
        
        // $this->displayIntegrityMetrics();
        // $this->newLine();
        
        $this->displaySummary();
    }

    private function displayIntermediateStepMetrics()
    {
        $this->info('🔄 INTERMEDIATE STEP METRICS');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        // CSV to MySQL metrics
        $inputFile = $this->option('input-file');
        $cleanedFile = $this->option('cleaned-file');
        $mysqlCount = Transaction::count(); // MySQL transactions using Eloquent
        
        $inputCount = 0;
        $cleanedCount = 0;
        
        if (File::exists($inputFile)) {
            $inputCount = max(0, count(file($inputFile)) - 1);
        }
        
        if (File::exists($cleanedFile)) {
            $cleanedCount = max(0, count(file($cleanedFile)) - 1);
        }

        $this->line("📄 CSV Cleaning Step:");
        if ($inputCount > 0 && $cleanedCount > 0) {
            $cleaningEfficiency = ($cleanedCount / $inputCount) * 100;
            $this->line("   • Cleaning efficiency: " . number_format($cleaningEfficiency, 2) . "%");
            $this->line("   • Records retained: " . number_format($cleanedCount) . " of " . number_format($inputCount));
        } else {
            $this->line("   • CSV files not found for analysis");
        }

        $this->line("🗄️ CSV to MySQL Import:");
        if ($cleanedCount > 0 && $mysqlCount > 0) {
            $importEfficiency = ($mysqlCount / $cleanedCount) * 100;
            $this->line("   • Import success rate: " . number_format($importEfficiency, 2) . "%");
            $this->line("   • Records imported: " . number_format($mysqlCount) . " of " . number_format($cleanedCount));
        } else {
            $this->line("   • Import data not available");
        }

        // MySQL to MongoDB metrics using proper Eloquent models
        $mongoNormalizedCount = MongoTransaction::count(); // MongoDB using Eloquent
        $mongoDenormalizedCount = MongoTransactionWithDetails::count(); // MongoDB using Eloquent

        $this->line("🍃 MySQL to MongoDB Migration:");
        if ($mysqlCount > 0) {
            if ($mongoNormalizedCount > 0) {
                $normalizedMigrationRate = ($mongoNormalizedCount / $mysqlCount) * 100;
                $this->line("   • Normalized migration rate: " . number_format($normalizedMigrationRate, 2) . "%");
            }
            
            if ($mongoDenormalizedCount > 0) {
                $denormalizedMigrationRate = ($mongoDenormalizedCount / $mysqlCount) * 100;
                $this->line("   • Denormalized migration rate: " . number_format($denormalizedMigrationRate, 2) . "%");
            }
        }

        // Data transformation metrics using proper Eloquent models
        $this->line("🔄 Data Transformation Quality:");
        $validItems = Item::count(); // MySQL items using Eloquent
        $validPaymentMethods = PaymentMethod::count(); // MySQL payment methods using Eloquent
        $transactionsWithValidItems = Transaction::whereNotNull('item_id')->count(); // MySQL transactions
        $transactionsWithValidPayments = Transaction::whereNotNull('payment_method_id')->count(); // MySQL transactions

        if ($mysqlCount > 0) {
            $itemLinkageRate = ($transactionsWithValidItems / $mysqlCount) * 100;
            $paymentLinkageRate = ($transactionsWithValidPayments / $mysqlCount) * 100;
            
            $this->line("   • Item linkage success: " . number_format($itemLinkageRate, 2) . "%");
            $this->line("   • Payment method linkage success: " . number_format($paymentLinkageRate, 2) . "%");
            $this->line("   • Reference data created: " . number_format($validItems) . " items, " . number_format($validPaymentMethods) . " payment methods");
        }
    }

    private function displayRecordCounts()
    {
        $this->info('📊 RECORD COUNTS (Verified)');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        // Count records from files if they exist
        $inputFile = $this->option('input-file');
        $cleanedFile = $this->option('cleaned-file');
        
        $inputCount = 0;
        $cleanedCount = 0;
        
        if (File::exists($inputFile)) {
            $inputCount = max(0, count(file($inputFile)) - 1); // Subtract header row
        }
        
        if (File::exists($cleanedFile)) {
            $cleanedCount = max(0, count(file($cleanedFile)) - 1); // Subtract header row
        }

        // Count records from databases using proper Eloquent models
        $mysqlCount = Transaction::count(); // MySQL using Eloquent (connection defined in model)
        $mongoNormalizedCount = MongoTransaction::count(); // MongoDB using Eloquent (connection defined in model)
        $mongoDenormalizedCount = MongoTransactionWithDetails::count(); // MongoDB using Eloquent (connection defined in model)

        $this->line("• Input records: " . number_format($inputCount) . " transactions (from {$inputFile})");
        $this->line("• Cleaned records: " . number_format($cleanedCount) . " transactions (from {$cleanedFile})");
        $this->line("• MySQL records: " . number_format($mysqlCount) . " transactions");
        $this->line("• MongoDB normalized: " . number_format($mongoNormalizedCount) . " transactions");
        // $this->line("• MongoDB denormalized: " . number_format($mongoDenormalizedCount) . " transactions");

        if ($inputCount > 0 && $cleanedCount > 0) {
            $retentionRate = ($cleanedCount / $inputCount) * 100;
            $droppedCount = $inputCount - $cleanedCount;
            $droppedRate = ($droppedCount / $inputCount) * 100;
            
            $this->line("• Data retention rate: " . number_format($retentionRate, 2) . "%");
            $this->line("• Records dropped: " . number_format($droppedCount) . " transactions (" . number_format($droppedRate, 2) . "% loss)");
        }
    }

    private function displayCompletenessMetrics()
    {
        $this->info('✅ COMPLETENESS ASSESSMENT');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        $total = Transaction::count();

        if ($total === 0) {
            $this->warn('No MySQL transactions found for completeness assessment');
            return;
        }

        // Calculate completeness for each field using Eloquent
        $transactionIds = Transaction::whereNotNull('transaction_id')->count();
        $items = Transaction::whereNotNull('item_id')->count();
        $totalSpent = Transaction::whereNotNull('total_spent')->where('total_spent', '>', 0)->count();
        $quantities = Transaction::whereNotNull('quantity')->where('quantity', '>', 0)->count();
        $paymentMethods = Transaction::whereNotNull('payment_method_id')->count();
        $locations = Transaction::whereNotNull('location')->count();
        $transactionDates = Transaction::whereNotNull('transaction_date')->count();

        $this->line("• Transaction IDs: " . number_format(($transactionIds / $total) * 100, 2) . "% complete");
        $this->line("• Items: " . number_format(($items / $total) * 100, 2) . "% complete");
        $this->line("• Total Spent: " . number_format(($totalSpent / $total) * 100, 2) . "% complete");
        $this->line("• Quantities: " . number_format(($quantities / $total) * 100, 2) . "% complete");
        $this->line("• Payment Methods: " . number_format(($paymentMethods / $total) * 100, 2) . "% complete");
        $this->line("• Locations: " . number_format(($locations / $total) * 100, 2) . "% complete");
        $this->line("• Transaction Dates: " . number_format(($transactionDates / $total) * 100, 2) . "% complete");
    }

    private function displayAccuracyMetrics()
    {
        $this->info('🎯 ACCURACY ASSESSMENT');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        $total = Transaction::count();

        if ($total === 0) {
            $this->warn('No MySQL transactions found for accuracy assessment');
            return;
        }

        // Check total spent accuracy (should equal quantity * item price)
        $accurateAmounts = Transaction::with('item')
            ->get()
            ->filter(function ($transaction) {
                if (!$transaction->item) return false;
                $expectedTotal = $transaction->quantity * $transaction->item->price_per_unit;
                return abs($transaction->total_spent - $expectedTotal) < 0.01;
            })->count();

        $amountAccuracy = ($accurateAmounts / $total) * 100;
        $this->line("• Total spent calculation accuracy: " . number_format($amountAccuracy, 2) . "%");

        // Check for valid item references
        $validItems = Transaction::whereHas('item')->count();
        $itemAccuracy = ($validItems / $total) * 100;
        $this->line("• Valid item references: " . number_format($itemAccuracy, 2) . "%");

        // Check for valid payment method references
        $validPaymentMethods = Transaction::whereHas('paymentMethod')->count();
        $paymentAccuracy = ($validPaymentMethods / $total) * 100;
        $this->line("• Valid payment method references: " . number_format($paymentAccuracy, 2) . "%");

        // Check location enum compliance
        $validLocations = Transaction::whereIn('location', ['In-store', 'Takeaway'])->count();
        $locationAccuracy = ($validLocations / $total) * 100;
        $this->line("• Valid location values: " . number_format($locationAccuracy, 2) . "%");
    }

    private function displayConsistencyMetrics()
    {
        $this->info('🔄 CONSISTENCY ASSESSMENT');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        $total = Transaction::count();

        if ($total === 0) {
            $this->warn('No MySQL transactions found for consistency assessment');
            return;
        }

        // Check total spent format consistency (should be positive decimal)
        $consistentAmountFormat = Transaction::where('total_spent', '>=', 0)->count();
        $amountConsistency = ($consistentAmountFormat / $total) * 100;
        $this->line("• Total spent format consistency: " . number_format($amountConsistency, 2) . "%");

        // Check quantity format consistency (should be positive decimal)
        $consistentQuantityFormat = Transaction::where('quantity', '>', 0)->count();
        $quantityConsistency = ($consistentQuantityFormat / $total) * 100;
        $this->line("• Quantity format consistency: " . number_format($quantityConsistency, 2) . "%");

        // Check location enum consistency
        $consistentLocations = Transaction::whereIn('location', ['In-store', 'Takeaway'])->count();
        $locationConsistency = ($consistentLocations / $total) * 100;
        $this->line("• Location value consistency: " . number_format($locationConsistency, 2) . "%");

        // Check transaction date format consistency
        $consistentDates = Transaction::whereNotNull('transaction_date')->count();
        $dateConsistency = ($consistentDates / $total) * 100;
        $this->line("• Transaction date consistency: " . number_format($dateConsistency, 2) . "%");

        // Check decimal precision consistency using Eloquent (MySQL only)
        $transactions = Transaction::select('total_spent')->get();
        $precisionCheck = $transactions->filter(function ($transaction) {
            return round($transaction->total_spent, 2) == $transaction->total_spent;
        })->count();
        $precisionConsistency = $total > 0 ? ($precisionCheck / $total) * 100 : 0;
        $this->line("• Decimal precision consistency: " . number_format($precisionConsistency, 2) . "%");
    }

    private function displayUniquenessMetrics()
    {
        $this->info('🔍 UNIQUENESS ASSESSMENT');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        $totalTransactions = Transaction::count();
        $uniqueTransactionIds = Transaction::distinct('transaction_id')->count('transaction_id');

        if ($totalTransactions === 0) {
            $this->warn('No MySQL transactions found for uniqueness assessment');
            return;
        }

        $uniquenessRate = ($uniqueTransactionIds / $totalTransactions) * 100;
        $duplicates = $totalTransactions - $uniqueTransactionIds;

        $this->line("• Unique transaction IDs: " . number_format($uniqueTransactionIds) . " of " . number_format($totalTransactions));
        $this->line("• Uniqueness rate: " . number_format($uniquenessRate, 2) . "%");
        $this->line("• Duplicate records: " . number_format($duplicates));

        // Check for potential duplicate transactions using Eloquent (MySQL only)
        $allTransactions = Transaction::select('item_id', 'total_spent', 'transaction_date', 'location')->get();
        $groupedTransactions = $allTransactions->groupBy(function ($transaction) {
            return $transaction->item_id . '|' . $transaction->total_spent . '|' . $transaction->transaction_date . '|' . $transaction->location;
        });
        
        $potentialDuplicates = $groupedTransactions->filter(function ($group) {
            return $group->count() > 1;
        })->sum(function ($group) {
            return $group->count();
        });

        $this->line("• Potential content duplicates: " . number_format($potentialDuplicates) . " records");
    }

    private function displayIntegrityMetrics()
    {
        $this->info('🔗 INTEGRITY ASSESSMENT');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        // Check referential integrity
        $mysqlTransactions = Transaction::count();
        $validItemReferences = Transaction::whereHas('item')->count();
        $validPaymentReferences = Transaction::whereHas('paymentMethod')->count();

        if ($mysqlTransactions === 0) {
            $this->warn('No MySQL transactions found for integrity assessment');
            return;
        }

        $itemIntegrity = ($validItemReferences / $mysqlTransactions) * 100;
        $paymentIntegrity = ($validPaymentReferences / $mysqlTransactions) * 100;

        $this->line("• Item reference integrity: " . number_format($itemIntegrity, 2) . "%");
        $this->line("• Payment method reference integrity: " . number_format($paymentIntegrity, 2) . "%");

        // Check cross-database integrity
        $mongoTransactions = MongoTransaction::count();
        $crossDbIntegrity = $mysqlTransactions > 0 ? ($mongoTransactions / $mysqlTransactions) * 100 : 0;
        $this->line("• Cross-database migration integrity: " . number_format($crossDbIntegrity, 2) . "%");

        // Check constraint violations
        $negativeQuantities = Transaction::where('quantity', '<=', 0)->count();
        $negativeAmounts = Transaction::where('total_spent', '<=', 0)->count();
        $invalidDates = Transaction::whereNull('transaction_date')->count();
        $constraintViolations = $negativeQuantities + $negativeAmounts + $invalidDates;

        $this->line("• Business rule violations: " . number_format($constraintViolations) . " records");
        
        // Check orphaned records
        $orphanedTransactions = Transaction::whereDoesntHave('item')->orWhereDoesntHave('paymentMethod')->count();
        $this->line("• Orphaned transaction records: " . number_format($orphanedTransactions));

        // Check MongoDB collection integrity using proper Eloquent models
        $mongoItems = MongoItem::count(); // MongoDB using Eloquent
        $mongoPaymentMethods = MongoPaymentMethod::count(); // MongoDB using Eloquent
        $mysqlItems = Item::count(); // MySQL using Eloquent
        $mysqlPaymentMethods = PaymentMethod::count(); // MySQL using Eloquent
        
        if ($mysqlItems > 0 && $mongoItems > 0) {
            $itemMigrationIntegrity = ($mongoItems / $mysqlItems) * 100;
            $this->line("• Item migration integrity: " . number_format($itemMigrationIntegrity, 2) . "%");
        }
        
        if ($mysqlPaymentMethods > 0 && $mongoPaymentMethods > 0) {
            $paymentMigrationIntegrity = ($mongoPaymentMethods / $mysqlPaymentMethods) * 100;
            $this->line("• Payment method migration integrity: " . number_format($paymentMigrationIntegrity, 2) . "%");
        }
    }

    private function displaySummary()
    {
        $this->info('📈 MIGRATION SUMMARY');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        $mysqlCount = Transaction::on('mysql')->count();
        $mongoNormalizedCount = MongoTransaction::on('mongodb')->count();
        $mongoDenormalizedCount = MongoTransactionWithDetails::on('mongodb')->count();

        $mysqlItems = Item::on('mysql')->count();
        $mongoItems = MongoItem::on('mongodb')->count();
        
        $mysqlPaymentMethods = PaymentMethod::on('mysql')->count();
        $mongoPaymentMethods = MongoPaymentMethod::on('mongodb')->count();

        $this->line("✅ MySQL migration: " . ($mysqlCount > 0 ? 'COMPLETED' : 'PENDING'));
        $this->line("✅ MongoDB normalized migration: " . ($mongoNormalizedCount > 0 ? 'COMPLETED' : 'PENDING'));
        // $this->line("✅ MongoDB denormalized migration: " . ($mongoDenormalizedCount > 0 ? 'COMPLETED' : 'PENDING'));
        
        $this->newLine();
        $this->line("📊 Final Record Counts:");
        $this->line("   • Transactions (MySQL): " . number_format($mysqlCount));
        $this->line("   • Transactions (MongoDB Normalized): " . number_format($mongoNormalizedCount));
        $this->line("   • Transactions (MongoDB Denormalized): " . number_format($mongoDenormalizedCount));
        $this->line("   • Items (MySQL): " . number_format($mysqlItems));
        $this->line("   • Items (MongoDB): " . number_format($mongoItems));
        $this->line("   • Payment Methods (MySQL): " . number_format($mysqlPaymentMethods));
        $this->line("   • Payment Methods (MongoDB): " . number_format($mongoPaymentMethods));

        if ($mysqlCount > 0) {
            $overallQuality = $this->calculateOverallQuality();
            $this->newLine();
            $this->line("🎯 Overall Data Quality Score: " . number_format($overallQuality, 1) . "%");
        }
    }

    private function calculateOverallQuality()
    {
        $total = Transaction::count();

        if ($total === 0) {
            return 0;
        }

        $scores = [];

        // Completeness (25% weight)
        $complete = Transaction::whereNotNull('transaction_id')
            ->whereNotNull('item_id')
            ->where('total_spent', '>', 0)
            ->where('quantity', '>', 0)
            ->whereNotNull('payment_method_id')
            ->whereNotNull('location')
            ->whereNotNull('transaction_date')
            ->count();
        $scores['completeness'] = ($complete / $total) * 100 * 0.25;

        // Accuracy (25% weight) - check if total_spent matches quantity * item price
        $accurate = Transaction::with('item')
            ->get()
            ->filter(function ($t) {
                if (!$t->item) return false;
                $expectedTotal = $t->quantity * $t->item->price_per_unit;
                return abs($t->total_spent - $expectedTotal) < 0.01;
            })->count();
        $scores['accuracy'] = ($accurate / $total) * 100 * 0.25;

        // Consistency (20% weight)
        $consistent = Transaction::where('total_spent', '>=', 0)
            ->where('quantity', '>', 0)
            ->whereIn('location', ['In-store', 'Takeaway'])
            ->whereNotNull('transaction_date')
            ->count();
        $scores['consistency'] = ($consistent / $total) * 100 * 0.20;

        // Uniqueness (15% weight)
        $uniqueIds = Transaction::distinct('transaction_id')->count('transaction_id');
        $scores['uniqueness'] = ($uniqueIds / $total) * 100 * 0.15;

        // Integrity (15% weight)
        $withIntegrity = Transaction::whereHas('item')->whereHas('paymentMethod')->count();
        $scores['integrity'] = ($withIntegrity / $total) * 100 * 0.15;

        return array_sum($scores);
    }
}