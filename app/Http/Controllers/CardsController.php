<?php

namespace App\Http\Controllers;

use App\Console\Services\GoogleSpreadsheetService;
use Illuminate\Http\Request;

class CardsController extends Controller
{
    private GoogleSpreadsheetService $googleSpreadsheetService;
    public function __construct(GoogleSpreadsheetService $googleSpreadsheetService)
    {
        $this->googleSpreadsheetService = $googleSpreadsheetService;
    }

    public function fetchCards()
    {
        $cards = $this->googleSpreadsheetService->getRowsWithKey();
        return response()->json($cards);
    }
}
