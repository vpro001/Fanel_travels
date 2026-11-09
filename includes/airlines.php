<?php
// Airline names for the codes Galileo sends (it sends codes only, e.g. "P4").
// Unknown codes are shown as the code itself. Add more here as needed.

function airline_name($code)
{
    static $names = [
        'P4' => 'Air Peace', 'QI' => 'Ibom Air', 'W3' => 'Arik Air', 'VK' => 'ValueJet', 'UN' => 'United Nigeria Airlines',
        'Q9' => 'Green Africa', 'XK' => 'Xejet', 'NG' => 'Max Air', 'OJ' => 'Overland Airways', 'Y4' => 'Rano Air',
        'ET' => 'Ethiopian Airlines', 'KQ' => 'Kenya Airways', 'AT' => 'Royal Air Maroc', 'MS' => 'EgyptAir',
        'SA' => 'South African Airways', 'AW' => 'Africa World Airlines', 'HF' => 'Air Côte d\'Ivoire', 'KP' => 'ASKY Airlines',
        'WB' => 'RwandAir', 'TC' => 'Air Tanzania', 'UR' => 'Uganda Airlines', 'LN' => 'Libyan Airlines',
        'BA' => 'British Airways', 'VS' => 'Virgin Atlantic', 'AF' => 'Air France', 'KL' => 'KLM', 'LH' => 'Lufthansa',
        'LX' => 'Swiss', 'SN' => 'Brussels Airlines', 'IB' => 'Iberia', 'TP' => 'TAP Air Portugal', 'AZ' => 'ITA Airways',
        'TK' => 'Turkish Airlines', 'EK' => 'Emirates', 'QR' => 'Qatar Airways', 'EY' => 'Etihad Airways', 'SV' => 'Saudia',
        'WY' => 'Oman Air', 'GF' => 'Gulf Air', 'KU' => 'Kuwait Airways', 'MH' => 'Malaysia Airlines', 'TG' => 'Thai Airways',
        'SQ' => 'Singapore Airlines', 'CX' => 'Cathay Pacific', 'CA' => 'Air China', 'CZ' => 'China Southern', 'MU' => 'China Eastern',
        'GA' => 'Garuda Indonesia', 'AI' => 'Air India', 'DL' => 'Delta Air Lines', 'UA' => 'United Airlines', 'AA' => 'American Airlines',
        'AC' => 'Air Canada', 'AM' => 'Aeroméxico', 'FZ' => 'flydubai', 'G9' => 'Air Arabia', 'PC' => 'Pegasus Airlines',
    ];
    $code = strtoupper((string) $code);
    return isset($names[$code]) ? $names[$code] : $code;
}
