<?php

namespace App\Console\Services;

class GoogleSpreadsheetService
{
    private $spreadsheetId = '';
    private $apiKey = '';
    private $tab = '';

    /**
     * Lê a aba inteira (cabeçalho + dados). O mapeamento é feito por NOME de coluna
     * (linha de cabeçalho), não por posição — assim adicionar/mover colunas na
     * planilha não quebra a API.
     */
    private $range = 'A1:BZ';

    /**
     * Cabeçalho (normalizado, sem acento/minúsculo) -> chave de saída.
     * Para adicionar uma coluna nova basta cadastrar o rótulo aqui.
     */
    private $columnMap = [
        'nome do card' => 'name',
        'nome em prbr' => 'Nome Portugues',
        'nome ptbr'    => 'Nome Portugues',
        'nome pt-br'   => 'Nome Portugues',
        'search code'  => 'searchCode',
        'idioma'       => 'idioma',
        'set id'       => 'setId',
        'qtd'          => 'qty',
        'preco'        => 'price',
        'condicao'     => 'condicao',
        'acabamento'   => 'acabamento',
        'foil-promo'   => 'acabamento',
        'extra'        => 'additionalInfo',
        'colecao'      => 'colecao',
        'tipo'         => 'Tipo',
        'subtipo'      => 'subtipo',
        'raridade'     => 'Raridade',
        'custo'        => 'Custo',
        'formato'      => 'formato',
        'artista'      => 'artista',
        'imagem'       => 'image',
        'cor'          => 'category',
        'ra'           => 'ra',
        'dono'         => 'dono',
    ];

    public function __construct()
    {
        $this->spreadsheetId = env('SPREADSHEET_ID');
        $this->apiKey        = env('API_KEY');
        // Vazio (default) = primeira aba da planilha (hoje "CARTAS"). Resiste a
        // renomear a aba. Pode-se fixar uma aba específica via SPREADSHEET_TAB.
        $this->tab           = env('SPREADSHEET_TAB', '');
    }

    private function buildUrl(): string
    {
        $tab = trim($this->tab);

        // Sem nome de aba => a API retorna a PRIMEIRA aba. Com nome, vai entre
        // aspas simples (cobre espaços/parênteses no título).
        $range = $tab === ''
            ? rawurlencode($this->range)
            : rawurlencode("'" . $tab . "'!" . $this->range);

        return 'https://sheets.googleapis.com/v4/spreadsheets/' .
               $this->spreadsheetId .
               '/values/' . $range .
               '?key=' . $this->apiKey;
    }

    public function fetchAllRows(): array
    {
        $context = stream_context_create([
            'http' => ['timeout' => 10, 'ignore_errors' => true],
        ]);

        $raw = @file_get_contents($this->buildUrl(), false, $context);

        if ($raw === false) {
            return [];
        }

        $decoded = json_decode($raw, true);

        return $decoded['values'] ?? [];
    }

    /**
     * Normaliza um rótulo de cabeçalho: minúsculo, sem acento, trim.
     */
    private function normalizeHeader(string $value): string
    {
        // Colapsa qualquer espaço/quebra de linha interna (ex.: "Search\nCode").
        $value = trim(mb_strtolower(preg_replace('/\s+/', ' ', $value)));
        $from = ['á','à','â','ã','ä','é','ê','è','í','ì','î','ó','ô','õ','ò','ö','ú','ù','û','ü','ç'];
        $to   = ['a','a','a','a','a','e','e','e','i','i','i','o','o','o','o','o','u','u','u','u','c'];

        return str_replace($from, $to, $value);
    }

    /**
     * Descobre o índice de cada coluna a partir da linha de cabeçalho.
     * Retorna [chaveDeSaida => indice].
     */
    private function resolveColumns(array $headerRow): array
    {
        $indexes = [];

        foreach ($headerRow as $idx => $label) {
            $normalized = $this->normalizeHeader((string) $label);

            if (isset($this->columnMap[$normalized]) && !isset($indexes[$this->columnMap[$normalized]])) {
                $indexes[$this->columnMap[$normalized]] = $idx;
            }
        }

        return $indexes;
    }

    /**
     * Acha a linha de cabeçalho (a que contém "Nome do Card"). O cliente pode
     * mover os títulos de linha (ex.: da linha 1 para a 2). Procura nas primeiras
     * 15 linhas; cai na linha 0 se não achar.
     */
    private function findHeaderIndex(array $rows): int
    {
        $limit = min(count($rows), 15);
        for ($i = 0; $i < $limit; $i++) {
            foreach ($rows[$i] as $cell) {
                if ($this->normalizeHeader((string) $cell) === 'nome do card') {
                    return $i;
                }
            }
        }
        return 0;
    }

    public function getRowsWithKey(): array
    {
        $rows = $this->fetchAllRows();

        if (count($rows) < 2) {
            return [];
        }

        $headerIndex = $this->findHeaderIndex($rows);
        $cols = $this->resolveColumns($rows[$headerIndex]);

        // Sem a coluna de nome não há como montar cartas.
        if (!isset($cols['name'])) {
            return [];
        }

        // "Search Code" ficou sem cabeçalho no rearranjo do cliente, mas o código
        // continua na coluna C (índice 2). Usa como fallback se não houver header.
        if (!isset($cols['searchCode']) && !in_array(2, $cols, true)) {
            $cols['searchCode'] = 2;
        }

        $get = function (array $row, string $key, $default = '') use ($cols) {
            if (!isset($cols[$key])) {
                return $default;
            }
            $value = $row[$cols[$key]] ?? $default;

            return is_string($value) ? trim($value) : $value;
        };

        $cards = [];

        foreach (array_slice($rows, $headerIndex + 1) as $row) {
            $name = $get($row, 'name');

            // Ignora sub-cabeçalhos, totais e linhas vazias.
            if ($name === '') {
                continue;
            }

            // Linha de rótulo/fórmula (ex.: "FÓRMULA") não tem qty nem preço.
            if ($get($row, 'qty') === '' && $get($row, 'price') === '') {
                continue;
            }

            $acabamento = $get($row, 'acabamento');

            $cards[] = [
                'name'           => $name,
                'searchCode'     => $get($row, 'searchCode'),
                'Nome Portugues' => $get($row, 'Nome Portugues', '-'),
                'idioma'         => mb_strtoupper($get($row, 'idioma')),
                'setId'          => $get($row, 'setId'),
                'qty'            => $get($row, 'qty', '0'),
                'price'          => $get($row, 'price', '0'),
                'condicao'       => mb_strtoupper($get($row, 'condicao')),
                'acabamento'     => $acabamento,
                'FOIL?'          => $acabamento, // compat com o front antigo
                'additionalInfo' => $get($row, 'additionalInfo'),
                'colecao'        => $get($row, 'colecao'),
                // Tipo vem por extenso e pode ser composto ("Lendário-Artefato",
                // "Artefato-Criatura"). Sai (quase) cru; o front trata como multi-valor.
                'Tipo'           => $this->cleanTipo($get($row, 'Tipo')),
                'subtipo'        => $get($row, 'subtipo'),
                'Raridade'       => $this->canonicalizeRaridade($get($row, 'Raridade')),
                'Custo'          => $get($row, 'Custo'),
                // Formato mantém TODOS os nomes (incl. Pauper/Premodern); o front
                // monta os chips dinamicamente a partir dos valores presentes.
                'formato'        => $this->cleanFormato($get($row, 'formato')),
                'artista'        => $get($row, 'artista'),
                'image'          => $get($row, 'image'),
                'category'       => mb_strtoupper($get($row, 'category')),
                'ra'             => mb_strtoupper($get($row, 'ra')),
                'dono'           => $get($row, 'dono'),
            ];
        }

        return $cards;
    }

    /**
     * Raridade sai como palavra canônica em PT. Aceita PT, inglês e letras legadas.
     */
    private function canonicalizeRaridade(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $key = mb_strtolower(str_replace(
            ['á','à','â','ã','é','ê','í','ó','ô','õ','ú','ç'],
            ['a','a','a','a','e','e','i','o','o','o','u','c'],
            $value
        ));

        $map = [
            'c' => 'Comum', 'comum' => 'Comum', 'common' => 'Comum',
            'i' => 'Incomum', 'incomum' => 'Incomum', 'uncommon' => 'Incomum',
            'r' => 'Rara', 'rara' => 'Rara', 'rare' => 'Rara',
            'm' => 'Mítica', 'mitica' => 'Mítica', 'mitico' => 'Mítica',
            'mitico-rara' => 'Mítica', 'mythic' => 'Mítica', 'mythic rare' => 'Mítica',
            'e' => 'Especial', 'especial' => 'Especial', 'special' => 'Especial',
        ];

        return $map[$key] ?? $value;
    }

    /**
     * Formato de legalidade — mantém TODOS os nomes por extenso (Standard, Pioneer,
     * Modern, Legacy, Vintage, Commander, Pauper, Premodern...). Só limpa/normaliza
     * o separador ('.' ou '-') e a capitalização. Nada é descartado.
     */
    private function cleanFormato(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        // separa por '.' ou '-'; ignora letra solta ("B" vazada da cor).
        $tokens = preg_split('/[.\-]+/', $value);
        $out = [];

        foreach ($tokens as $token) {
            $token = trim($token);
            if ($token === '' || mb_strlen($token) === 1) {
                continue;
            }
            $out[] = mb_convert_case(mb_strtolower($token), MB_CASE_TITLE);
        }

        return implode('-', array_unique($out));
    }

    /**
     * Tipo — mantém por extenso (multi-tipo separado por '-'). Só unifica a variação
     * "Mágica-Instantânea" / "Mágica Instantânea" para "Instantânea".
     */
    private function cleanTipo(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        return str_ireplace(
            ['Mágica-Instantânea', 'Mágica Instantânea', 'Magica-Instantanea', 'Magica Instantanea'],
            'Instantânea',
            $value
        );
    }
}
