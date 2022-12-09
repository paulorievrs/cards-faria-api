<?php

namespace App\Console\Services;

class GoogleSpreadsheetService
{
    private $baseUrl = '';
    private $spreadsheetId = '';

    public function __construct()
    {
        $this->spreadsheetId =  env('SPREADSHEET_ID');
        $this->baseUrl = 'https://sheets.googleapis.com/v4/spreadsheets/' .
                         $this->spreadsheetId .
                        "/values/G19:U?key=" .
                        env('API_KEY');

    }

    public function fetchAllRows()
    {
        return json_decode(file_get_contents($this->baseUrl), true)['values'];
    }

    public function getRowsWithKey()
    {
        $rows = $this->fetchAllRows();
        $rowsWithKey = [];

        foreach ($rows as $row) {
           if(sizeof($row) <= 2) continue;


            $rowsWithKey[] = [
                'L' => $row[0] ?? '',
                'name' => $row[1],
                'Nome Portugues' => $row[2],
                'qty' => $row[3],
                'price' => $row[4],
                'dono' => $row[5],
                'Tipo' => $row[6],
                'Raridade' => $row[7],
                'Custo' => $row[8],
                'additionalInfo' => $row[9],
                'image' => $row[10],
                'category' => $row[11],
                'FOIL?' => $row[13] ?? '',
                'RAD' => $row[14] ?? ''
            ];
        }

        return $rowsWithKey;

    }
}
