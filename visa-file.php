<?php
require 'includes/init.php';

// A customer downloads a file our staff shared on their visa application (e.g. the e-visa).
// Only works in a browser that has opened the application with its reference and email
// (visa-track.php remembers that in the session), and only for files staff marked as shared.
$doc = db_one("SELECT d.*, a.reference FROM visa_documents d JOIN visa_applications a ON a.id = d.application_id WHERE d.id = ? AND d.shared = 1", [(int) get('id')]);
$access = isset($_SESSION['visa_access']) ? $_SESSION['visa_access'] : [];
$path = $doc ? __DIR__ . '/storage/uploads/visa/' . basename($doc['stored_name']) : '';

if (!$doc || !in_array($doc['reference'], $access, true) || !is_file($path)) {
    http_response_code(404);
    exit('File not found. Open your application from the link in your email, then try again.');
}

header('Content-Type: ' . $doc['mime']);
header('Content-Length: ' . filesize($path));
header('Content-Disposition: attachment; filename="' . str_replace(['"', "\r", "\n"], '', $doc['original_name']) . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($path);
