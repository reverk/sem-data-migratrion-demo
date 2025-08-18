<?php

namespace App\Console\Commands;

use App\Models\Item;
use App\Models\PaymentMethod;
use App\Models\Transaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;

/**
 * Import cafe sales data from CSV file - MySQL specific implementation
 * 
 * This command is specifically designed for MySQL databases only.
 * It uses MySQL-specific features and optimizations.
 */
class ImportCafeSalesData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cafe:import-sales {file? : Path to the CSV file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import cafe sales data from CSV file (MySQL only)';

    private array $itemCache = [];
    private array $paymentMethodCache = [];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Validate that we're using MySQL connection
        if (!$this->validateMySQLConnection()) {
            return 1;
        }

        $filePath = $this->argument('file') ?? 'dataset_scripts/cleaned_cafe_sales.csv';
        
        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return 1;
        }

        $this->info("Starting MySQL import from: {$filePath}");

        try {
            // Initialize caches
            $this->loadCaches();

            // Read and process CSV
            $records = $this->readCsvFile($filePath);
            $totalRecords = count($records);

            $this->info("Found {$totalRecords} records to process");

            $progressBar = $this->output->createProgressBar($totalRecords);
            $progressBar->start();

            $imported = 0;
            $skipped = 0;
            $errors = 0;

            DB::connection('mysql')->transaction(function () use ($records, $progressBar, &$imported, &$skipped, &$errors) {
                foreach ($records as $record) {
                    try {
                        $result = $this->processRecord($record);
                        
                        if ($result === 'imported') {
                            $imported++;
                        } elseif ($result === 'skipped') {
                            $skipped++;
                        }
                    } catch (\Exception $e) {
                        $errors++;
                        $this->newLine();
                        $this->warn("Error processing record: " . $e->getMessage());
                        $this->warn("Record: " . json_encode($record));
                    }
                    
                    $progressBar->advance();
                }
            });

            $progressBar->finish();
            $this->newLine();
            $this->newLine();

            // Display summary
            $this->info("Import completed!");
            $this->table(
                ['Status', 'Count'],
                [
                    ['Imported', $imported],
                    ['Skipped (duplicates)', $skipped],
                    ['Errors', $errors],
                    ['Total processed', $imported + $skipped + $errors],
                ]
            );

            return 0;

        } catch (\Exception $e) {
            $this->error("Import failed: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Load items and payment methods into cache for faster lookups
     */
    private function loadCaches(): void
    {
        $this->info("Loading reference data from MySQL...");

        // Load items cache using MySQL connection
        $items = Item::on('mysql')->get();
        foreach ($items as $item) {
            $this->itemCache[$item->item_name] = $item->item_id;
        }

        // Load payment methods cache using MySQL connection
        $paymentMethods = PaymentMethod::on('mysql')->get();
        foreach ($paymentMethods as $method) {
            $this->paymentMethodCache[$method->method_name] = $method->payment_method_id;
        }

        $this->info("Loaded " . count($this->itemCache) . " items and " . count($this->paymentMethodCache) . " payment methods from MySQL");
    }

    /**
     * Process a single CSV record
     */
    private function processRecord(array $record): string
    {
        $transactionId = $record['Transaction ID'];
        
        // Check if transaction already exists in MySQL
        if (Transaction::on('mysql')->where('transaction_id', $transactionId)->exists()) {
            return 'skipped';
        }

        // Get or create item
        $itemId = $this->getOrCreateItem($record['Item'], (float)$record['Price Per Unit']);
        
        // Get or create payment method
        $paymentMethodId = $this->getOrCreatePaymentMethod($record['Payment Method']);

        // Create transaction in MySQL
        Transaction::on('mysql')->create([
            'transaction_id' => $transactionId,
            'item_id' => $itemId,
            'payment_method_id' => $paymentMethodId,
            'quantity' => (float)$record['Quantity'],
            'total_spent' => (float)$record['Total Spent'],
            'location' => $record['Location'],
            'transaction_date' => $record['Transaction Date'],
        ]);

        return 'imported';
    }

    /**
     * Get or create item and return its ID
     */
    private function getOrCreateItem(string $itemName, float $pricePerUnit): int
    {
        if (isset($this->itemCache[$itemName])) {
            return $this->itemCache[$itemName];
        }

        $item = Item::on('mysql')->create([
            'item_name' => $itemName,
            'price_per_unit' => $pricePerUnit,
        ]);

        $this->itemCache[$itemName] = $item->item_id;
        
        return $item->item_id;
    }

    /**
     * Get or create payment method and return its ID
     */
    private function getOrCreatePaymentMethod(string $methodName): int
    {
        if (isset($this->paymentMethodCache[$methodName])) {
            return $this->paymentMethodCache[$methodName];
        }

        $paymentMethod = PaymentMethod::on('mysql')->create([
            'method_name' => $methodName,
        ]);

        $this->paymentMethodCache[$methodName] = $paymentMethod->payment_method_id;
        
        return $paymentMethod->payment_method_id;
    }

    /**
     * Validate that we're using MySQL connection
     */
    private function validateMySQLConnection(): bool
    {
        try {
            $connection = DB::connection('mysql');
            $driver = $connection->getDriverName();
            
            if ($driver !== 'mysql') {
                $this->error("Error: This command requires MySQL connection. Current driver: {$driver}");
                $this->error("Please ensure your database connection is configured for MySQL.");
                return false;
            }

            // Test the connection
            $connection->getPdo();
            $this->info("✓ MySQL connection validated successfully");
            return true;

        } catch (\Exception $e) {
            $this->error("Error connecting to MySQL database: " . $e->getMessage());
            $this->error("Please check your MySQL configuration and ensure the database is running.");
            return false;
        }
    }

    /**
     * Read CSV file and return records as array
     */
    private function readCsvFile(string $filePath): array
    {
        $records = [];
        $handle = fopen($filePath, 'r');
        
        if (!$handle) {
            throw new \Exception("Unable to open file: {$filePath}");
        }

        // Read header row
        $headers = fgetcsv($handle);
        
        if (!$headers) {
            fclose($handle);
            throw new \Exception("Unable to read CSV headers");
        }

        // Read data rows
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) === count($headers)) {
                $records[] = array_combine($headers, $row);
            }
        }

        fclose($handle);
        
        return $records;
    }
}
