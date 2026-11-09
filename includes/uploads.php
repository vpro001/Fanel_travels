<?php
// Saving uploaded documents safely:
//  - the real file type is checked with finfo (not the name or what the browser says)
//  - only PDF, JPEG and PNG, up to 5 MB each
//  - saved under a random name in storage/uploads/ (outside the public folders, blocked by .htaccess)
//  - only staff can download them, through admin/download.php

function allowed_upload_types()
{
    return ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];
}

// Save one file from $_FILES. Returns ['ok' => true, ...file details] or ['ok' => false, 'error' => message].
function save_upload($file, $folder)
{
    if (!isset($file['error']) || $file['error'] == UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'error' => 'No file chosen.'];
    }
    if ($file['error'] == UPLOAD_ERR_INI_SIZE || $file['error'] == UPLOAD_ERR_FORM_SIZE || $file['size'] > 5 * 1024 * 1024) {
        return ['ok' => false, 'error' => 'That file is bigger than 5 MB. Please send a smaller scan or photo.'];
    }
    if ($file['error'] != UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        return ['ok' => false, 'error' => 'The upload did not finish. Please try again.'];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    $types = allowed_upload_types();
    if (!isset($types[$mime])) {
        return ['ok' => false, 'error' => 'Please upload a PDF, JPG or PNG file.'];
    }

    $dir = __DIR__ . '/../storage/uploads/' . $folder;
    if (!is_dir($dir)) {
        mkdir($dir, 0770, true);
    }
    $stored = bin2hex(random_bytes(16)) . '.' . $types[$mime];
    if (!move_uploaded_file($file['tmp_name'], "$dir/$stored")) {
        return ['ok' => false, 'error' => 'We could not save that file. Please try again.'];
    }

    return [
        'ok' => true,
        'stored_name' => $stored,
        'original_name' => substr(preg_replace('/[^\w .()-]/u', '_', basename($file['name'])), 0, 200),
        'mime' => $mime,
        'size_bytes' => $file['size'],
        'sha256' => hash_file('sha256', "$dir/$stored"),
    ];
}

// $_FILES['docs'] with several files → a list of single-file arrays
function list_files($field)
{
    if (!isset($_FILES[$field]) || !is_array($_FILES[$field]['name'])) {
        return [];
    }
    $out = [];
    foreach ($_FILES[$field]['name'] as $key => $name) {
        $out[$key] = [
            'name' => $name,
            'type' => $_FILES[$field]['type'][$key],
            'tmp_name' => $_FILES[$field]['tmp_name'][$key],
            'error' => $_FILES[$field]['error'][$key],
            'size' => $_FILES[$field]['size'][$key],
        ];
    }
    return $out;
}


// ------------------------------------------------------------ public images (flyers, country photos)

// Save an uploaded JPG/PNG as 800px and 480px wide JPEGs in assets/img/<folder>/, keeping its shape.
// Returns [image name for photo(), ''] or [null, error message].
function save_site_image($file, $folder)
{
    if (empty($file['tmp_name']) || $file['error'] != UPLOAD_ERR_OK || $file['size'] > 8 * 1024 * 1024) {
        return [null, 'Choose a JPG or PNG image up to 8 MB.'];
    }
    $mime = finfo_file(finfo_open(FILEINFO_MIME_TYPE), $file['tmp_name']);
    $source = $mime == 'image/jpeg' ? @imagecreatefromjpeg($file['tmp_name']) : ($mime == 'image/png' ? @imagecreatefrompng($file['tmp_name']) : null);
    if (!$source) {
        return [null, 'The image must be a JPG or PNG.'];
    }
    $w = imagesx($source);
    $h = imagesy($source);
    if ($w < 300 || $h < 300) {
        return [null, 'The image is too small. Use one at least 600 pixels wide.'];
    }
    $name = $folder . '/' . bin2hex(random_bytes(8));
    foreach ([800, 480] as $size) {
        $out_w = min($size, $w);
        $out_h = (int) round($h * $out_w / $w);
        $out = imagecreatetruecolor($out_w, $out_h);
        imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));   // transparent PNGs get a white background
        imagecopyresampled($out, $source, 0, 0, 0, 0, $out_w, $out_h, $w, $h);
        if (!imagejpeg($out, __DIR__ . "/../assets/img/$name-$size.jpg", 82)) {
            return [null, "The image could not be saved. Check that assets/img/$folder/ can be written to."];
        }
    }
    return [$name, ''];
}

// Delete both sizes of an image saved by save_site_image()
function delete_site_image($name)
{
    if (!preg_match('#^(offers|destinations)/[a-f0-9]{16}$#', $name)) return;   // never touch the site's own photos
    foreach ([480, 800] as $size) @unlink(__DIR__ . "/../assets/img/$name-$size.jpg");
}
