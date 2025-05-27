<?php

namespace App\Console\Commands;

use App\Traits\ImportHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\DB;

class ImportCustomers extends Command
{
    use ImportHelper;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:customers';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'import customers from csv file';

    /**
     * Execute the console command.
     */
    public function handleImport($filePath): void
    {
        $now = now();
        $numberOfProcesses = 10;
        $chunkSize = 1000;
        $totalMemoryUsage = 0;
        $totalQueries = 0;

        $tasks = [];
        for ($i = 0; $i < $numberOfProcesses; $i++) {
            $tasks[] = function () use ($filePath, $i, $numberOfProcesses, $now, $chunkSize) {
                $processMemoryUsage = memory_get_usage();
                DB::reconnect();
                DB::enableQueryLog();

                $handle = fopen($filePath, 'r');
                fgets($handle); // Skip header
                $currentLine = 0;
                $customers = [];
                $processQueries = 0;

                while (($line = fgets($handle)) !== false) {
                    // Each process takes every Nth line
                    if ($currentLine++ % $numberOfProcesses !== $i) {
                        continue;
                    }

                    $row = str_getcsv($line);
                    $customers[] = [
                        'crm_id' => $row[1],
                        'first_name' => $row[2],
                        'last_name' => $row[3],
                        'company' => $row[4],
                        'city' => $row[5],
                        'country' => $row[6],
                        'phone_1' => $row[7],
                        'phone_2' => $row[8],
                        'email' => $row[9],
                        'subscription_date'=> $row[10],
                        'website'=> $row[11],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    if (count($customers) === $chunkSize) {
                        DB::table('customers')->insert($customers);
                        $processQueries += count(DB::getQueryLog());
                        DB::flushQueryLog();
                        $customers = [];
                    }
                }

                if (! empty($customers)) {
                    DB::table('customers')->insert($customers);
                    $processQueries += count(DB::getQueryLog());
                }

                fclose($handle);
                return [
                    'process_id' => $i,
                    'memory_usage' => round((memory_get_usage() - $processMemoryUsage) / 1024 / 1024, 2),
                    'queries' => $processQueries
                ];
            };
        }

        $results = Concurrency::run($tasks);
        
        // Sum up memory usage and queries from all processes
        foreach ($results as $result) {
            $totalMemoryUsage += $result['memory_usage'];
            $totalQueries += $result['queries'];
        }
        
        // Store the totals in class properties that can be accessed by the trait
        $this->totalProcessMemoryUsage = $totalMemoryUsage;
        $this->totalProcessQueries = $totalQueries;
    }
}
