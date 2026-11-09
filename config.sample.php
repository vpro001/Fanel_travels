<?php
// Copy this file to config.php and fill in your own values.
// config.php holds your real passwords and keys, so never share or commit it.

define('SITE_NAME', "Fanel Travels LTD");
// Full web address of the site, no slash at the end (used in payment and email links)
define('SITE_URL', 'https://www.example.com');
// A long random secret for signing links (email verification). Make one with:
//   php -r "echo bin2hex(random_bytes(32));"
define('APP_SECRET', '');
define('DEBUG', false);              // true shows PHP errors on screen; only on your own computer

// Database
define('DB_HOST', 'localhost');
define('DB_USER', 'fanel');
define('DB_PASS', '');
define('DB_NAME', 'fanel');

// Set to false only when testing on http://localhost (the live site must use https)
define('COOKIE_SECURE', true);

// 'live' = real fares from the flight supplier below, 'demo' = sample results (shows a "Demo data" banner)
define('FLIGHT_DATA', 'live');

// Flights: 'galileo' = Travelport Universal API, tickets issued on Fanel's Galileo office (docs/GALILEO.md)
//          'duffel'  = Duffel, paid from the Duffel balance
define('FLIGHT_SUPPLIER', 'galileo');

// Travelport Universal API (Galileo / 1G). Details come from Travelport when the account is set up.
// Pre-production: https://emea.universal-api.pp.travelport.com/B2BGateway/connect/uAPI/
// Production:     https://emea.universal-api.travelport.com/B2BGateway/connect/uAPI/
define('TRAVELPORT_ENDPOINT', 'https://emea.universal-api.pp.travelport.com/B2BGateway/connect/uAPI/');
define('TRAVELPORT_USERNAME', '');   // Universal API/uAPI...
define('TRAVELPORT_PASSWORD', '');
define('TRAVELPORT_BRANCH', '');     // Target branch, e.g. P7123456
define('TRAVELPORT_SCHEMA', 'v52_0'); // schema version your account is enabled for
define('TRAVELPORT_TICKETING', 'auto'); // 'auto' = issue the ticket as soon as the customer pays; 'hold' = book the PNR, staff issue it

// Duffel (hotels, and flights when FLIGHT_SUPPLIER is 'duffel'): https://app.duffel.com
define('DUFFEL_TOKEN', '');          // duffel_test_... or duffel_live_...
define('DUFFEL_WEBHOOK_SECRET', '');

// Paystack (payments): https://dashboard.paystack.com
define('PAYSTACK_SECRET_KEY', '');   // sk_test_... or sk_live_...
define('PAYSTACK_PUBLIC_KEY', '');   // pk_test_... or pk_live_...

// Email: 'smtp', 'mail' (PHP mail function) or 'log' (save emails to storage/logs instead of sending)
define('MAIL_METHOD', 'log');
define('SMTP_HOST', '');
define('SMTP_PORT', 587);
define('SMTP_USER', '');
define('SMTP_PASS', '');
define('MAIL_FROM', 'no-reply@example.com');
define('ADMIN_EMAIL', '');           // receives alerts when something needs a person to look at it
