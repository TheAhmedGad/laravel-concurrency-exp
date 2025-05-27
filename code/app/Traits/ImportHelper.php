<?php

namespace App\Traits;

use App\Models\Customer;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use PDO;
use PDOStatement;

use function Laravel\Prompts\select;

trait ImportHelper
{
    protected float $benchmarkStartTime;

    protected int $benchmarkStartMemory;

    protected int $startRowCount;

    protected int $startQueries;

    public function handle(): void
    {
        error_reporting(E_ALL);
        ini_set('display_errors', 1);
        Customer::truncate();
        
        $file = $this->selectFile();
        $filePath = $file[0];
        $totalRows = $file[1];

        $this->startBenchmark();

        try {
            $this->handleImport($filePath, $totalRows);
        } catch (\Exception $e) {
            $this->error(get_class($e).' '.Str::of($e->getMessage())->limit(100)->value());
        }

        $this->endBenchmark();
    }

    protected function selectFile(): array
    {
        $file = select(
            label: 'What file do you want to import?',
            options: ['CSV 100 Customers', 'CSV 1K Customers', 'CSV 10K Customers', 'CSV 100K Customers', 'CSV 1M Customers', 'CSV 2M Customers']
        );

        return match ($file) {
            'CSV 100 Customers' => [storage_path('csv/customers-100.csv'), 100],
            'CSV 1K Customers' => [storage_path('csv/customers-1000.csv'), 1000],
            'CSV 10K Customers' => [storage_path('csv/customers-10000.csv'), 10000],
            'CSV 100K Customers' => [storage_path('csv/customers-100000.csv'), 100000],
            'CSV 500K Customers' => [storage_path('csv/customers-500000.csv'), 500000],
            'CSV 1M Customers' => [storage_path('csv/customers-1000000.csv'), 1000000],
            'CSV 2M Customers' => [storage_path('csv/customers-2000000.csv'), 2000000],
        };
    }

    protected function startBenchmark(string $table = 'customers'): void
    {
        $this->startRowCount = DB::table($table)->count();
        $this->benchmarkStartTime = microtime(true);
        $this->benchmarkStartMemory = memory_get_usage();
        DB::enableQueryLog();
        $this->startQueries = count(DB::getQueryLog());
    }

    protected function endBenchmark(string $table = 'customers'): void
    {
        $executionTime = microtime(true) - $this->benchmarkStartTime;
        $memoryUsage = isset($this->totalProcessMemoryUsage) 
            ? $this->totalProcessMemoryUsage 
            : round((memory_get_usage() - $this->benchmarkStartMemory) / 1024 / 1024, 2);
        $queriesCount = isset($this->totalProcessQueries)
            ? $this->totalProcessQueries
            : count(DB::getQueryLog()) - $this->startQueries;

        // Get row count after we've stopped tracking queries
        $rowDiff = DB::table($table)->count() - $this->startRowCount;

        $formattedTime = match (true) {
            $executionTime >= 60 => sprintf('%dm %ds', floor($executionTime / 60), $executionTime % 60),
            $executionTime >= 1 => round($executionTime, 2).'s',
            default => round($executionTime * 1000).'ms',
        };

        $this->newLine();
        $this->line(sprintf(
            '⚡ <bg=bright-blue;fg=black> TIME: %s </> <bg=bright-green;fg=black> MEM: %sMB </> <bg=bright-yellow;fg=black> SQL: %s </> <bg=bright-magenta;fg=black> ROWS: %s </>',
            $formattedTime,
            $memoryUsage,
            number_format($queriesCount),
            number_format($rowDiff)
        ));
        $this->newLine();
    }
}