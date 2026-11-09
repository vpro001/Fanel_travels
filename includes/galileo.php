<?php
// Galileo (Travelport+, provider code 1G) through the Travelport Universal API (SOAP/XML).
//
// What happens, and which uAPI request does it:
//   search        LowFareSearchReq (AirService)              → fares shown on the results page
//   check price   AirPriceReq (AirService)                   → on the booking page, and again after payment
//   book          AirCreateReservationReq (AirService)       → creates the PNR, form of payment "Cash" (agency)
//   ticket        AirTicketingReq (AirService)               → issues the e-tickets on Fanel's Galileo office
//
// The airline is paid the way Fanel already pays for tickets issued on its Galileo office
// (BSP / consolidator settlement); the customer's money comes in through Paystack.
//
// Results are turned into the same shape as a Duffel offer, so the results, booking and
// confirmation pages work the same whichever supplier a fare came from. The Galileo details
// needed to book it later are kept in the offer under '_galileo'.
//
// Settings (config.php): TRAVELPORT_ENDPOINT, TRAVELPORT_USERNAME, TRAVELPORT_PASSWORD,
// TRAVELPORT_BRANCH, TRAVELPORT_SCHEMA, TRAVELPORT_TICKETING. See docs/GALILEO.md.

require_once __DIR__ . '/pricing.php';
require_once __DIR__ . '/airlines.php';

function tp_setting($name, $default = '')
{
    return defined($name) ? constant($name) : $default;
}

function galileo_ready()
{
    return tp_setting('TRAVELPORT_USERNAME') != '' && tp_setting('TRAVELPORT_PASSWORD') != '' && tp_setting('TRAVELPORT_BRANCH') != '';
}

// The XML namespaces for the schema version Fanel's account is on (e.g. v52_0)
function tp_ns($which)
{
    $v = tp_setting('TRAVELPORT_SCHEMA', 'v52_0');
    return "http://www.travelport.com/schema/{$which}_{$v}";
}

function tp_x($value)
{
    return htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

// Send one request. $body is the request element (e.g. <air:LowFareSearchReq ...>).
// Returns ['ok', 'xp' => DOMXPath of the answer, 'error_code', 'error'].
// error_code: not_configured, connection_failed (outcome unknown), no_results, or 'travelport' for a refusal.
function galileo_call($service, $body, $timeout = 60)
{
    if (!galileo_ready()) {
        return ['ok' => false, 'xp' => null, 'error_code' => 'not_configured', 'error' => 'Travelport is not set up in config.php.'];
    }
    $url = rtrim(tp_setting('TRAVELPORT_ENDPOINT', 'https://emea.universal-api.pp.travelport.com/B2BGateway/connect/uAPI/'), '/') . '/' . $service;
    $envelope = '<?xml version="1.0" encoding="UTF-8"?>'
        . '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"><soapenv:Header/><soapenv:Body>'
        . $body . '</soapenv:Body></soapenv:Envelope>';

    // Never repeated automatically: repeating a booking or ticketing request could do it twice.
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $envelope,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_HTTPHEADER => [
            'Content-Type: text/xml; charset=utf-8',
            'SOAPAction: ""',
            'Authorization: Basic ' . base64_encode(tp_setting('TRAVELPORT_USERNAME') . ':' . tp_setting('TRAVELPORT_PASSWORD')),
        ],
    ]);
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $curl_error = curl_error($ch);

    $request_name = preg_match('/<\w+:(\w+)/', $body, $m) ? $m[1] : $service;
    $log = ['status' => $status];

    $doc = new DOMDocument();
    $parsed = $response !== false && $response !== '' && @$doc->loadXML($response, LIBXML_NONET);
    if (!$parsed) {
        $log['error'] = $curl_error ?: 'Not XML';
        write_log('api', 'Travelport ' . $request_name, $log);
        return ['ok' => false, 'xp' => null, 'error_code' => 'connection_failed', 'error' => $curl_error ?: "Unexpected answer (HTTP $status)"];
    }
    $xp = new DOMXPath($doc);

    $fault = $xp->query("//*[local-name()='Fault']")->item(0);
    if ($fault) {
        $text = trim(tp_text($xp, ".//*[local-name()='faultstring']", $fault));
        $code = trim(tp_text($xp, ".//*[local-name()='ErrorInfo']/*[local-name()='Code']", $fault));
        $log['error'] = $code . ' ' . $text;
        write_log('api', 'Travelport ' . $request_name, $log);
        // "No solutions" / "no availability" is not an error, just an empty search
        if (preg_match('/no solution|no availab|no fares|no itinerar/i', $text)) {
            return ['ok' => false, 'xp' => $xp, 'error_code' => 'no_results', 'error' => $text];
        }
        return ['ok' => false, 'xp' => $xp, 'error_code' => 'travelport', 'error' => ($code ? "$code: " : '') . $text];
    }
    if ($status < 200 || $status >= 300) {
        write_log('api', 'Travelport ' . $request_name, $log + ['error' => 'HTTP error']);
        return ['ok' => false, 'xp' => $xp, 'error_code' => 'connection_failed', 'error' => "HTTP $status"];
    }
    write_log('api', 'Travelport ' . $request_name, $log);
    return ['ok' => true, 'xp' => $xp, 'error_code' => '', 'error' => ''];
}

function tp_text($xp, $query, $context = null)
{
    $n = $context ? $xp->query($query, $context)->item(0) : $xp->query($query)->item(0);
    return $n ? $n->textContent : '';
}

function tp_nodes($xp, $query, $context = null)
{
    return $context ? $xp->query($query, $context) : $xp->query($query);
}

// "NGN1234567.00" → ['1234567.00', 'NGN']
function tp_money($value)
{
    if (!preg_match('/^([A-Z]{3})([\d.]+)$/', (string) $value, $m)) return [null, null];
    return [$m[2], $m[1]];
}

function tp_iso_minutes($minutes)
{
    $minutes = max(0, (int) $minutes);
    return 'PT' . intdiv($minutes, 60) . 'H' . ($minutes % 60) . 'M';
}

function tp_place($code)
{
    static $cache = [];
    if (!isset($cache[$code])) {
        $a = db_one("SELECT iata_code, name, city_name, country_code FROM airports WHERE iata_code = ? ORDER BY type = 'airport' DESC LIMIT 1", [$code]);
        $cache[$code] = $a
            ? ['iata_code' => $code, 'name' => $a['name'], 'city_name' => $a['city_name'] ?: $a['name'], 'country_code' => $a['country_code']]
            : ['iata_code' => $code, 'name' => $code, 'city_name' => $code, 'country_code' => ''];
    }
    return $cache[$code];
}

function tp_cabin($cabin)
{
    $map = ['economy' => 'Economy', 'premium_economy' => 'PremiumEconomy', 'business' => 'Business', 'first' => 'First'];
    return isset($map[$cabin]) ? $map[$cabin] : 'Economy';
}

// Our travellers for a search: P1, P2... (adults first, then children, then infants)
function tp_passengers($adults, $children, $infants)
{
    $list = [];
    for ($i = 0; $i < $adults; $i++) $list[] = ['id' => 'P' . (count($list) + 1), 'type' => 'adult', 'code' => 'ADT', 'age' => null];
    for ($i = 0; $i < $children; $i++) $list[] = ['id' => 'P' . (count($list) + 1), 'type' => 'child', 'code' => 'CNN', 'age' => 10];
    for ($i = 0; $i < $infants; $i++) $list[] = ['id' => 'P' . (count($list) + 1), 'type' => 'infant_without_seat', 'code' => 'INF', 'age' => 1];
    return $list;
}

function tp_search_passengers_xml($passengers)
{
    $xml = '';
    foreach ($passengers as $p) {
        $xml .= '<com:SearchPassenger Code="' . $p['code'] . '"' . ($p['age'] !== null ? ' Age="' . $p['age'] . '"' : '')
            . ' BookingTravelerRef="' . $p['id'] . '" Key="' . $p['id'] . '"/>';
    }
    return $xml;
}

function tp_request_attrs()
{
    return 'TargetBranch="' . tp_x(tp_setting('TRAVELPORT_BRANCH')) . '" AuthorizedBy="FANEL" TraceId="' . bin2hex(random_bytes(8)) . '"';
}

function tp_pos()
{
    return '<com:BillingPointOfSaleInfo OriginApplication="UAPI"/>';
}

// ------------------------------------------------------------ search

// Same arguments as duffel_search_flights(). Returns ['ok', 'data' => ['offers' => [...]], 'error_code', 'error']
function galileo_search_flights($slices, $adults, $children, $infants, $cabin)
{
    $passengers = tp_passengers($adults, $children, $infants);
    $legs = '';
    foreach ($slices as $s) {
        $legs .= '<air:SearchAirLeg>'
            . '<air:SearchOrigin><com:CityOrAirport Code="' . tp_x($s['origin']) . '" PreferCity="true"/></air:SearchOrigin>'
            . '<air:SearchDestination><com:CityOrAirport Code="' . tp_x($s['destination']) . '" PreferCity="true"/></air:SearchDestination>'
            . '<air:SearchDepTime PreferredTime="' . tp_x($s['departure_date']) . '"/>'
            . '<air:AirLegModifiers><air:PreferredCabins><com:CabinClass Type="' . tp_cabin($cabin) . '"/></air:PreferredCabins></air:AirLegModifiers>'
            . '</air:SearchAirLeg>';
    }
    $body = '<air:LowFareSearchReq xmlns:air="' . tp_ns('air') . '" xmlns:com="' . tp_ns('common') . '" ' . tp_request_attrs() . ' SolutionResult="true">'
        . tp_pos() . $legs
        . '<air:AirSearchModifiers MaxSolutions="60"><air:PreferredProviders><com:Provider Code="1G"/></air:PreferredProviders></air:AirSearchModifiers>'
        . tp_search_passengers_xml($passengers)
        . '<air:AirPricingModifiers FaresIndicator="AllFares"/>'
        . '</air:LowFareSearchReq>';

    $r = galileo_call('AirService', $body, 60);
    if (!$r['ok']) {
        if ($r['error_code'] == 'no_results') {
            return ['ok' => true, 'data' => ['offers' => []], 'error_code' => '', 'error' => ''];
        }
        return ['ok' => false, 'data' => null, 'error_code' => $r['error_code'], 'error' => $r['error']];
    }
    $xp = $r['xp'];

    $segments = tp_read_segments($xp, "//*[local-name()='AirSegmentList']/*[local-name()='AirSegment']");
    $fares = tp_read_fares($xp, "//*[local-name()='FareInfoList']/*[local-name()='FareInfo']");

    $offers = [];
    foreach (tp_nodes($xp, "//*[local-name()='AirPricingSolution']") as $sol) {
        $offer = tp_make_offer($xp, $sol, $segments, $fares, $passengers);
        if ($offer) {
            galileo_save_offer($offer);
            $offers[] = $offer;
        }
    }
    return ['ok' => true, 'data' => ['offers' => $offers], 'error_code' => '', 'error' => ''];
}

// Flight segments by key
function tp_read_segments($xp, $query, $context = null)
{
    $list = [];
    foreach (tp_nodes($xp, $query, $context) as $n) {
        $operating = tp_nodes($xp, "./*[local-name()='CodeshareInfo']", $n)->item(0);
        $list[$n->getAttribute('Key')] = [
            'Key' => $n->getAttribute('Key'),
            'Group' => $n->getAttribute('Group') ?: '0',
            'Carrier' => $n->getAttribute('Carrier'),
            'FlightNumber' => $n->getAttribute('FlightNumber'),
            'Origin' => $n->getAttribute('Origin'),
            'Destination' => $n->getAttribute('Destination'),
            'DepartureTime' => $n->getAttribute('DepartureTime'),
            'ArrivalTime' => $n->getAttribute('ArrivalTime'),
            'FlightTime' => $n->getAttribute('FlightTime'),
            'Equipment' => $n->getAttribute('Equipment'),
            'ProviderCode' => $n->getAttribute('ProviderCode') ?: '1G',
            'Operating' => $operating ? ($operating->getAttribute('OperatingCarrier') ?: trim($operating->textContent)) : '',
            'ClassOfService' => $n->getAttribute('ClassOfService'),
            'CabinClass' => $n->getAttribute('CabinClass'),
        ];
    }
    return $list;
}

// Fare details by key: fare basis, bags, brand
function tp_read_fares($xp, $query, $context = null)
{
    $list = [];
    foreach (tp_nodes($xp, $query, $context) as $n) {
        $pieces = tp_text($xp, ".//*[local-name()='BaggageAllowance']/*[local-name()='NumberOfPieces']", $n);
        $weight = tp_nodes($xp, ".//*[local-name()='BaggageAllowance']/*[local-name()='MaxWeight']", $n)->item(0);
        $brand = tp_nodes($xp, "./*[local-name()='Brand']", $n)->item(0);
        $list[$n->getAttribute('Key')] = [
            'basis' => $n->getAttribute('FareBasis'),
            'bags' => $pieces !== '' ? (int) $pieces : ($weight && (float) $weight->getAttribute('Value') > 0 ? 1 : 0),
            'brand' => $brand ? $brand->getAttribute('Name') : '',
        ];
    }
    return $list;
}

// One AirPricingSolution → an offer in Duffel's shape
function tp_make_offer($xp, $sol, $segments, $fares, $passengers)
{
    list($amount, $currency) = tp_money($sol->getAttribute('TotalPrice'));
    if ($amount === null) return null;

    // Which segments, and the booking class / cabin / fare for each
    $keys = [];
    foreach (tp_nodes($xp, ".//*[local-name()='AirSegmentRef']", $sol) as $ref) $keys[] = $ref->getAttribute('Key');
    foreach (tp_nodes($xp, "./*[local-name()='AirSegment']", $sol) as $seg) $keys[] = $seg->getAttribute('Key');
    $keys = array_values(array_unique($keys));
    if (!$keys) return null;

    $info = tp_nodes($xp, "./*[local-name()='AirPricingInfo']", $sol)->item(0);
    $booking = [];
    if ($info) {
        foreach (tp_nodes($xp, "./*[local-name()='BookingInfo']", $info) as $b) {
            $booking[$b->getAttribute('SegmentRef')] = [
                'class' => $b->getAttribute('BookingCode'),
                'cabin' => $b->getAttribute('CabinClass'),
                'fare' => $b->getAttribute('FareInfoRef'),
            ];
        }
    }
    $plating = $info && $info->getAttribute('PlatingCarrier') ? $info->getAttribute('PlatingCarrier') : '';
    $refundable = $info ? $info->getAttribute('Refundable') : '';
    $has_change = $info && tp_nodes($xp, "./*[local-name()='ChangePenalty']", $info)->length > 0;

    $gds_segments = [];
    foreach ($keys as $k) {
        if (!isset($segments[$k])) return null;
        $s = $segments[$k];
        if (isset($booking[$k])) {
            $s['ClassOfService'] = $booking[$k]['class'] ?: $s['ClassOfService'];
            $s['CabinClass'] = $booking[$k]['cabin'] ?: $s['CabinClass'];
            $s['FareInfoRef'] = $booking[$k]['fare'];
        }
        $gds_segments[] = $s;
    }
    usort($gds_segments, function ($a, $b) {
        return [(int) $a['Group'], strtotime($a['DepartureTime'])] <=> [(int) $b['Group'], strtotime($b['DepartureTime'])];
    });
    if (!$plating) $plating = $gds_segments[0]['Carrier'];

    $offer = tp_offer_shape($gds_segments, $fares, $passengers, $amount, $currency, $plating, $refundable, $has_change);
    $offer['id'] = 'gal_' . bin2hex(random_bytes(12));
    return $offer;
}

// Build the Duffel-shaped offer from Galileo segments
function tp_offer_shape($gds_segments, $fares, $passengers, $amount, $currency, $plating, $refundable, $has_change)
{
    $groups = [];
    foreach ($gds_segments as $s) $groups[(int) $s['Group']][] = $s;
    ksort($groups);

    $international = false;
    $slices = [];
    foreach ($groups as $segs) {
        $out = [];
        $brand = '';
        foreach ($segs as $s) {
            $from = tp_place($s['Origin']);
            $to = tp_place($s['Destination']);
            if ($from['country_code'] != 'NG' || $to['country_code'] != 'NG') $international = true;
            $fare = isset($s['FareInfoRef'], $fares[$s['FareInfoRef']]) ? $fares[$s['FareInfoRef']] : ['bags' => 0, 'brand' => ''];
            if (!$brand) $brand = $fare['brand'];
            $minutes = $s['FlightTime'] !== '' ? (int) $s['FlightTime'] : (int) ((strtotime($s['ArrivalTime']) - strtotime($s['DepartureTime'])) / 60);
            $out[] = [
                'origin' => $from,
                'destination' => $to,
                'departing_at' => substr($s['DepartureTime'], 0, 19),
                'arriving_at' => substr($s['ArrivalTime'], 0, 19),
                'duration' => tp_iso_minutes($minutes),
                'marketing_carrier' => ['iata_code' => $s['Carrier'], 'name' => airline_name($s['Carrier'])],
                'marketing_carrier_flight_number' => $s['FlightNumber'],
                'operating_carrier' => ['name' => airline_name($s['Operating'] && strlen($s['Operating']) == 2 ? $s['Operating'] : $s['Carrier'])],
                'aircraft' => ['name' => $s['Equipment']],
                'passengers' => [[
                    'cabin_class_marketing_name' => $s['CabinClass'] ? preg_replace('/(?<!^)([A-Z])/', ' $1', $s['CabinClass']) . ($s['ClassOfService'] ? ' (' . $s['ClassOfService'] . ')' : '') : '',
                    'baggages' => $fare['bags'] ? [['type' => 'checked', 'quantity' => $fare['bags']]] : [],
                ]],
            ];
        }
        $first = $segs[0];
        $last = $segs[count($segs) - 1];
        $slices[] = [
            'origin' => tp_place($first['Origin']),
            'destination' => tp_place($last['Destination']),
            'duration' => tp_iso_minutes((strtotime($last['ArrivalTime']) - strtotime($first['DepartureTime'])) / 60),
            'fare_brand_name' => $brand,
            'segments' => $out,
        ];
    }

    return [
        'id' => '',
        'total_amount' => number_format((float) $amount, 2, '.', ''),
        'total_currency' => $currency,
        'expires_at' => gmdate('Y-m-d\TH:i:s\Z', time() + 30 * 60),   // Galileo fares aren't held: we re-check the price before booking
        'owner' => ['name' => airline_name($plating), 'iata_code' => $plating, 'logo_symbol_url' => ''],
        'passengers' => array_map(function ($p) { return ['id' => $p['id'], 'type' => $p['type']]; }, $passengers),
        'slices' => $slices,
        'conditions' => [
            'refund_before_departure' => $refundable === '' ? null : ['allowed' => $refundable == 'true'],
            'change_before_departure' => $has_change ? ['allowed' => true] : null,
        ],
        'passenger_identity_documents_required' => $international,
        '_galileo' => [
            'segments' => $gds_segments,
            'fares' => $fares,
            'passengers' => $passengers,
            'plating' => $plating,
        ],
    ];
}

// Offers are kept for a while so the booking page can find them again
function galileo_offer_file($id)
{
    return __DIR__ . '/../storage/cache/galileo-offer-' . preg_replace('/[^a-z0-9_]/i', '', $id) . '.json';
}

function galileo_save_offer($offer)
{
    file_put_contents(galileo_offer_file($offer['id']), json_encode($offer));
}

// ------------------------------------------------------------ price check

// The segments as uAPI wants them in a price or booking request
function tp_itinerary_xml($gds_segments)
{
    $xml = '<air:AirItinerary>';
    foreach ($gds_segments as $i => $s) {
        $next = isset($gds_segments[$i + 1]) ? $gds_segments[$i + 1] : null;
        $connects = $next && $next['Group'] == $s['Group'];
        $xml .= '<air:AirSegment Key="' . tp_x($s['Key']) . '" Group="' . tp_x($s['Group']) . '" Carrier="' . tp_x($s['Carrier']) . '"'
            . ' FlightNumber="' . tp_x($s['FlightNumber']) . '" Origin="' . tp_x($s['Origin']) . '" Destination="' . tp_x($s['Destination']) . '"'
            . ' DepartureTime="' . tp_x($s['DepartureTime']) . '" ArrivalTime="' . tp_x($s['ArrivalTime']) . '"'
            . ' ClassOfService="' . tp_x($s['ClassOfService']) . '" ProviderCode="' . tp_x($s['ProviderCode']) . '"'
            . ($s['Equipment'] ? ' Equipment="' . tp_x($s['Equipment']) . '"' : '')
            . ($connects ? '><air:Connection/></air:AirSegment>' : '/>');
    }
    return $xml . '</air:AirItinerary>';
}

// Price the exact flights and booking classes again.
// Returns ['ok', 'amount', 'currency', 'solution_xml' (ready to book), 'refundable', 'has_change', 'error_code', 'error']
function galileo_price($gal)
{
    $body = '<air:AirPriceReq xmlns:air="' . tp_ns('air') . '" xmlns:com="' . tp_ns('common') . '" ' . tp_request_attrs() . '>'
        . tp_pos() . tp_itinerary_xml($gal['segments'])
        . '<air:AirPricingModifiers FaresIndicator="AllFares"/>'
        . tp_search_passengers_xml($gal['passengers'])
        . '<air:AirPricingCommand/>'
        . '</air:AirPriceReq>';
    $r = galileo_call('AirService', $body, 45);
    if (!$r['ok']) {
        return ['ok' => false, 'error_code' => $r['error_code'] == 'no_results' ? 'offer_no_longer_available' : $r['error_code'], 'error' => $r['error']];
    }
    $xp = $r['xp'];
    $sol = tp_nodes($xp, "//*[local-name()='AirPriceResult']/*[local-name()='AirPricingSolution']")->item(0);
    if (!$sol) {
        return ['ok' => false, 'error_code' => 'offer_no_longer_available', 'error' => 'No price returned for these flights.'];
    }
    list($amount, $currency) = tp_money($sol->getAttribute('TotalPrice'));

    // Make the solution bookable: full segments inside it instead of references
    $book = new DOMDocument();
    $copy = $book->importNode($sol, true);
    $book->appendChild($copy);
    $bxp = new DOMXPath($book);
    $refs = [];
    foreach (tp_nodes($bxp, "./*[local-name()='AirSegmentRef']", $copy) as $ref) $refs[] = $ref;
    $insert_before = $copy->firstChild;
    foreach (tp_nodes($xp, "//*[local-name()='AirItinerary']/*[local-name()='AirSegment']") as $seg) {
        $copy->insertBefore($book->importNode($seg, true), $insert_before);
    }
    foreach ($refs as $ref) $copy->removeChild($ref);

    $info = tp_nodes($xp, "./*[local-name()='AirPricingInfo']", $sol)->item(0);
    return [
        'ok' => true,
        'amount' => number_format((float) $amount, 2, '.', ''),
        'currency' => $currency,
        'solution_xml' => $book->saveXML($copy),
        'refundable' => $info ? $info->getAttribute('Refundable') : '',
        'has_change' => $info && tp_nodes($xp, "./*[local-name()='ChangePenalty']", $info)->length > 0,
        'error_code' => '',
        'error' => '',
    ];
}

// Look up an offer again with today's price (booking page). Same answer shape as duffel('GET', '/air/offers/...').
function galileo_get_offer($id)
{
    $file = galileo_offer_file($id);
    if (!file_exists($file) || filemtime($file) < time() - 24 * 3600) {
        return ['ok' => false, 'data' => null, 'error_code' => 'offer_no_longer_available', 'error' => 'Search again.'];
    }
    $offer = json_decode(file_get_contents($file), true);
    $p = galileo_price($offer['_galileo']);
    if (!$p['ok']) {
        return ['ok' => false, 'data' => null, 'error_code' => $p['error_code'], 'error' => $p['error']];
    }
    $offer['total_amount'] = $p['amount'];
    $offer['total_currency'] = $p['currency'];
    $offer['expires_at'] = gmdate('Y-m-d\TH:i:s\Z', time() + 30 * 60);
    if ($p['refundable'] !== '') $offer['conditions']['refund_before_departure'] = ['allowed' => $p['refundable'] == 'true'];
    if ($p['has_change']) $offer['conditions']['change_before_departure'] = ['allowed' => true];
    galileo_save_offer($offer);
    return ['ok' => true, 'data' => $offer, 'error_code' => '', 'error' => ''];
}

// ------------------------------------------------------------ booking and ticketing

function tp_name($value)
{
    return strtoupper(trim(preg_replace('/\s+/', ' ', str_replace(['-', "'"], [' ', ''], $value))));
}

// The travellers for the PNR, from the saved booking
function tp_travelers_xml($booking_id, $gal, $plating)
{
    $by_id = [];
    foreach ($gal['passengers'] as $p) $by_id[$p['id']] = $p;
    $last_date = null;
    foreach ($gal['segments'] as $s) $last_date = substr($s['DepartureTime'], 0, 10);

    $xml = '';
    foreach (db_all("SELECT * FROM flight_passengers WHERE booking_id = ? ORDER BY id", [$booking_id]) as $p) {
        $code = isset($by_id[$p['duffel_passenger_id']]) ? $by_id[$p['duffel_passenger_id']]['code'] : 'ADT';
        $age = (int) date_diff(date_create($p['born_on']), date_create($last_date ?: 'today'))->y;
        $gender = strtoupper($p['gender']);
        $xml .= '<com:BookingTraveler Key="' . tp_x($p['duffel_passenger_id']) . '" TravelerType="' . $code . '" Gender="' . $gender . '"'
            . ' DOB="' . tp_x($p['born_on']) . '"' . ($code != 'ADT' ? ' Age="' . $age . '"' : '') . '>'
            . '<com:BookingTravelerName Prefix="' . strtoupper($p['title']) . '" First="' . tp_x(tp_name($p['given_name'])) . '" Last="' . tp_x(tp_name($p['family_name'])) . '"/>';
        if (strpos((string) $p['phone'], '+234') === 0) {
            $xml .= '<com:PhoneNumber Type="Mobile" CountryCode="234" Number="' . tp_x(substr($p['phone'], 4)) . '"/>';
        } elseif ($p['phone']) {
            $xml .= '<com:PhoneNumber Type="Mobile" Number="' . tp_x(ltrim($p['phone'], '+')) . '"/>';
        }
        if ($p['email']) $xml .= '<com:Email Type="Home" EmailID="' . tp_x($p['email']) . '"/>';
        if ($p['passport_number']) {
            // DOCS: P/issuing country/number/nationality/date of birth/gender/expiry/surname/first name
            $g = $gender . ($code == 'INF' ? 'I' : '');
            $docs = 'P/' . $p['passport_country'] . '/' . $p['passport_number'] . '/' . $p['passport_country'] . '/'
                . strtoupper(date('dMy', strtotime($p['born_on']))) . '/' . $g . '/' . strtoupper(date('dMy', strtotime($p['passport_expires_on']))) . '/'
                . str_replace(' ', '', tp_name($p['family_name'])) . '/' . str_replace(' ', '', tp_name($p['given_name']));
            $xml .= '<com:SSR Type="DOCS" Status="HK" Carrier="' . tp_x($plating) . '" FreeText="' . tp_x($docs) . '"/>';
        }
        if ($code == 'CNN') $xml .= '<com:NameRemark><com:RemarkData>P-C' . sprintf('%02d', $age) . '</com:RemarkData></com:NameRemark>';
        if ($code == 'INF') $xml .= '<com:NameRemark><com:RemarkData>' . strtoupper(date('dMy', strtotime($p['born_on']))) . '</com:RemarkData></com:NameRemark>';
        $xml .= '</com:BookingTraveler>';
    }
    return $xml;
}

// Create the PNR from a fresh price ($priced from galileo_price()).
// Returns ['ok', 'record', 'gds_pnr', 'reservation', 'airline_pnr', 'pricing_keys' => [], 'error_code', 'error']
function galileo_create_reservation($booking_id, $gal, $priced)
{
    $ticket_date = date('Y-m-d\TH:i:s', time() + 3600);
    $body = '<univ:AirCreateReservationReq xmlns:univ="' . tp_ns('universal') . '" xmlns:air="' . tp_ns('air') . '" xmlns:com="' . tp_ns('common') . '" '
        . tp_request_attrs() . ' RetainReservation="Both">'
        . tp_pos()
        . tp_travelers_xml($booking_id, $gal, $gal['plating'])
        . '<com:FormOfPayment Type="Cash" Key="FOP1"/>'
        . $priced['solution_xml']
        . '<com:ActionStatus Type="TAU" TicketDate="' . $ticket_date . '" ProviderCode="1G"/>'
        . '</univ:AirCreateReservationReq>';

    $r = galileo_call('AirService', $body, 120);
    if (!$r['ok']) {
        return ['ok' => false, 'error_code' => $r['error_code'], 'error' => $r['error']];
    }
    $xp = $r['xp'];
    $ur = tp_nodes($xp, "//*[local-name()='UniversalRecord']")->item(0);
    $provider = tp_nodes($xp, "//*[local-name()='ProviderReservationInfo']")->item(0);
    $air = tp_nodes($xp, "//*[local-name()='AirReservation']")->item(0);
    if (!$ur || !$air) {
        return ['ok' => false, 'error_code' => 'connection_failed', 'error' => 'The answer had no booking record.'];
    }
    $supplier = tp_nodes($xp, ".//*[local-name()='SupplierLocator']", $air)->item(0);
    $keys = [];
    foreach (tp_nodes($xp, "./*[local-name()='AirPricingInfo']", $air) as $i) $keys[] = $i->getAttribute('Key');
    return [
        'ok' => true,
        'record' => $ur->getAttribute('LocatorCode'),
        'gds_pnr' => $provider ? $provider->getAttribute('LocatorCode') : '',
        'reservation' => $air->getAttribute('LocatorCode'),
        'airline_pnr' => $supplier ? $supplier->getAttribute('SupplierLocatorCode') : '',
        'pricing_keys' => $keys,
        'error_code' => '',
        'error' => '',
    ];
}

// Issue the tickets for a reservation. Returns ['ok', 'tickets' => [['first', 'last', 'number']], 'error_code', 'error']
function galileo_ticket($reservation, $pricing_keys = [])
{
    $refs = '';
    foreach ($pricing_keys as $k) $refs .= '<air:AirPricingInfoRef Key="' . tp_x($k) . '"/>';
    $body = '<air:AirTicketingReq xmlns:air="' . tp_ns('air') . '" xmlns:com="' . tp_ns('common') . '" ' . tp_request_attrs() . ' ReturnInfoOnFail="true">'
        . tp_pos()
        . '<air:AirReservationLocatorCode>' . tp_x($reservation) . '</air:AirReservationLocatorCode>'
        . $refs
        . '</air:AirTicketingReq>';
    $r = galileo_call('AirService', $body, 120);
    if (!$r['ok']) {
        return ['ok' => false, 'tickets' => [], 'error_code' => $r['error_code'], 'error' => $r['error']];
    }
    $xp = $r['xp'];
    $tickets = [];
    foreach (tp_nodes($xp, "//*[local-name()='ETR']") as $etr) {
        $name = tp_nodes($xp, ".//*[local-name()='BookingTravelerName']", $etr)->item(0);
        foreach (tp_nodes($xp, ".//*[local-name()='Ticket']", $etr) as $t) {
            $tickets[] = [
                'first' => $name ? strtoupper($name->getAttribute('First')) : '',
                'last' => $name ? strtoupper($name->getAttribute('Last')) : '',
                'number' => $t->getAttribute('TicketNumber'),
            ];
        }
    }
    if (!$tickets) {
        $why = trim(tp_text($xp, "//*[local-name()='TicketFailureInfo']/@Message") ?: tp_text($xp, "//*[local-name()='ResponseMessage']"));
        return ['ok' => false, 'tickets' => [], 'error_code' => 'ticketing_failed', 'error' => $why ?: 'No ticket numbers returned.'];
    }
    return ['ok' => true, 'tickets' => $tickets, 'error_code' => '', 'error' => ''];
}

// The whole step after payment, called by issue_ticket() in includes/booking.php once the
// booking is in "ticketing". Safe to call again after a failure: a PNR that was already
// created is ticketed, never booked a second time.
function galileo_fulfil($booking_id)
{
    $b = db_one("SELECT * FROM flight_bookings WHERE id = ?", [$booking_id]);
    $offer = json_decode($b['offer_snapshot'], true);
    $gal = $offer['_galileo'];

    if (!$b['gds_reservation']) {
        if ($b['gds_state'] == 'booking') {
            needs_attention($booking_id, 'The last booking attempt got no clear answer. Check Galileo for a PNR in this name first; '
                . 'if there is none, use "Try again" with "No PNR exists" ticked.');
            return;
        }

        // 1. Price it again: we never pay more than the customer paid for
        $priced = galileo_price($gal);
        if (!$priced['ok']) {
            needs_attention($booking_id, "The fare is no longer available ({$priced['error_code']}: {$priced['error']}). The customer has paid: rebook or refund.");
            return;
        }
        if ($priced['currency'] != $b['supplier_currency'] || (float) $priced['amount'] > (float) $b['supplier_amount']) {
            needs_attention($booking_id, "The airline price went up after payment, from {$b['supplier_amount']} to {$priced['amount']} {$priced['currency']}. Contact the customer.");
            return;
        }

        // 2. Create the PNR. Marked first, so an unclear outcome is never booked twice.
        db_query("UPDATE flight_bookings SET gds_state = 'booking' WHERE id = ?", [$booking_id]);
        $res = galileo_create_reservation($booking_id, $gal, $priced);
        if (!$res['ok']) {
            if ($res['error_code'] == 'connection_failed') {
                needs_attention($booking_id, 'Galileo did not give a clear answer when booking. Check Galileo for a PNR in this name BEFORE trying again. (' . $res['error'] . ')');
            } else {
                db_query("UPDATE flight_bookings SET gds_state = NULL WHERE id = ?", [$booking_id]);
                needs_attention($booking_id, 'Galileo refused the booking: ' . $res['error']);
            }
            return;
        }
        db_query(
            "UPDATE flight_bookings SET gds_state = 'booked', gds_record = ?, gds_pnr = ?, gds_reservation = ?, gds_pricing_keys = ?, pnr = ?, charged_amount = ?, charged_currency = ? WHERE id = ?",
            [$res['record'], $res['gds_pnr'], $res['reservation'], json_encode($res['pricing_keys']), $res['airline_pnr'] ?: $res['gds_pnr'], $priced['amount'], $priced['currency'], $booking_id]
        );
        $b = db_one("SELECT * FROM flight_bookings WHERE id = ?", [$booking_id]);
    }

    if (tp_setting('TRAVELPORT_TICKETING', 'auto') != 'auto') {
        needs_attention($booking_id, "PNR {$b['gds_pnr']} is booked and ready to ticket (automatic ticketing is off). Issue it in Galileo, then mark it ticketed.");
        return;
    }

    // 3. Issue the tickets
    $t = galileo_ticket($b['gds_reservation'], (array) json_decode((string) $b['gds_pricing_keys'], true));
    if (!$t['ok']) {
        needs_attention($booking_id, "PNR {$b['gds_pnr']} is booked but the ticket was not issued ({$t['error']}). Press \"Try again\" or issue it in Galileo.");
        return;
    }

    // 4. Save ticket numbers against each traveller (matched by name)
    $passengers = db_all("SELECT id, given_name, family_name FROM flight_passengers WHERE booking_id = ? ORDER BY id", [$booking_id]);
    $left = $t['tickets'];
    foreach ($passengers as $i => $p) {
        $match = null;
        foreach ($left as $k => $tk) {
            if ($tk['last'] == tp_name($p['family_name']) && strpos(str_replace(' ', '', $tk['first']), str_replace(' ', '', tp_name($p['given_name']))) === 0) { $match = $k; break; }
        }
        if ($match === null && isset($left[array_key_first($left)])) $match = array_key_first($left);   // fall back to order
        if ($match !== null) {
            db_query("UPDATE flight_passengers SET ticket_number = ? WHERE id = ?", [$left[$match]['number'], $p['id']]);
            unset($left[$match]);
        }
    }
    db_query("UPDATE flight_bookings SET gds_state = 'ticketed', documents_json = ?, confirmed_at = NOW() WHERE id = ?", [json_encode($t['tickets']), $booking_id]);
    flight_status($booking_id, 'confirmed', 'Ticketed on Galileo: ' . $b['pnr']);
    send_booking_email($booking_id);
}
