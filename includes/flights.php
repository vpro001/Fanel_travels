<?php
// Which flight supplier to use. Pages call these instead of Duffel or Galileo directly.
//
//   FLIGHT_SUPPLIER = 'galileo'  Travelport Universal API, tickets issued on Fanel's Galileo office (includes/galileo.php)
//   FLIGHT_SUPPLIER = 'duffel'   Duffel, paid from the Duffel balance (includes/duffel.php)
//   FLIGHT_DATA = 'demo'         made-up fares for showing the site (includes/demo-flights.php), whatever the supplier
//
// Offer ids say where a fare came from: gal_... is Galileo, off_... is Duffel (or demo),
// so bookings made before a switch still go to the right place.

require_once __DIR__ . '/duffel.php';
require_once __DIR__ . '/galileo.php';

function flight_supplier()
{
    if (FLIGHT_DATA === 'demo') return 'duffel';
    return defined('FLIGHT_SUPPLIER') ? FLIGHT_SUPPLIER : 'duffel';
}

function offer_supplier($offer_id)
{
    return strpos((string) $offer_id, 'gal_') === 0 ? 'galileo' : 'duffel';
}

function valid_offer_id($offer_id)
{
    return (bool) preg_match('/^(off|gal)_[A-Za-z0-9_]{4,60}$/', (string) $offer_id);
}

function flight_search($slices, $adults, $children, $infants, $cabin)
{
    if (flight_supplier() == 'galileo') {
        return galileo_search_flights($slices, $adults, $children, $infants, $cabin);
    }
    return duffel_search_flights($slices, $adults, $children, $infants, $cabin);
}

// One fare with its latest price: ['ok', 'data' => offer, 'error_code', 'error']
function flight_get_offer($offer_id)
{
    if (offer_supplier($offer_id) == 'galileo') {
        return galileo_get_offer($offer_id);
    }
    return duffel('GET', '/air/offers/' . rawurlencode($offer_id));
}
