<?php

namespace App\Console\Commands;

use App\Console\Services\GoogleSpreadsheetService;
use Illuminate\Console\Command;
use Revolution\Google\Sheets\Sheets;
use Google\Client;
class TestSpreadsheetCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:testspreadsheet';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $googleSpreadsheetService = new GoogleSpreadsheetService();
        dd($googleSpreadsheetService->getRowsWithKey());
    }
}
