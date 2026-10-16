<?php

/*
 * Only a number and two 3-letter currency codes ever reach the remote URL,
 * the host is fixed and redirects are not followed.
 * Note: Google retired /finance/converter in 2018, so this returns 0 until
 * a replacement rate provider is configured.
 */
function convertCurrency($amount, $from, $to) {
    $query = http_build_query(array('a' => $amount, 'from' => $from, 'to' => $to));
    $context = stream_context_create(array('http' => array('timeout' => 5, 'follow_location' => 0, 'max_redirects' => 0)));
    $data = @file_get_contents('https://www.google.com/finance/converter?' . $query, false, $context);
    if ($data === false || !preg_match("/<span class=bld>(.*)<\/span>/", $data, $converted)) {
        return 0;
    }
    return round((float) preg_replace("/[^0-9.]/", "", $converted[1]), 2);
}

$amount = (float) str_replace(',', '.', isset($_POST['sum']) ? (string) $_POST['sum'] : '0');
$from = strtoupper(trim(isset($_POST['from']) ? (string) $_POST['from'] : ''));
$to = strtoupper(trim(isset($_POST['to']) ? (string) $_POST['to'] : ''));
if (!preg_match('/^[A-Z]{3}$/', $from) || !preg_match('/^[A-Z]{3}$/', $to)) {
    echo 0;
    exit;
}

echo convertCurrency($amount, $from, $to);
