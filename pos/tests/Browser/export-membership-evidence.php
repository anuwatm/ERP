<?php

use App\Support\PosCanonicalJson;

$directory = dirname(__DIR__, 3).'/temp/phase2882-membership';
if (is_file($directory.'/cleanup.json')) {
    throw new RuntimeException('Membership fixture is sealed.');
}
require dirname(__DIR__, 3).'/backend/vendor/autoload.php';
$pdo = new PDO('sqlite:'.$directory.'/database.sqlite');
$key = openssl_pkey_get_private(file_get_contents($directory.'/synthetic-private.pem'));
$public = openssl_pkey_get_details($key)['key'];
$events = [];
foreach ($pdo->query('select envelope from pos_outbox order by sequence')->fetchAll(PDO::FETCH_COLUMN) as $bytes) {
    $event = json_decode($bytes, true, flags: JSON_THROW_ON_ERROR);
    $signed = $event;
    unset($signed['signature']);
    if (openssl_verify(PosCanonicalJson::encode($signed), base64_decode($event['signature'], true), $public, OPENSSL_ALGO_SHA256) !== 1) {
        throw new RuntimeException('Independent source signature verification failed.');
    }
    $events[] = $event;
}
$destination = dirname(__DIR__, 3).'/document/acceptance/phase28_8_2';
file_put_contents($destination.'/synthetic-public.pem', $public);
file_put_contents($destination.'/signed-events.json', json_encode($events, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
file_put_contents($destination.'/signature-verification.json', json_encode(['algorithm' => 'RSA-SHA256', 'verified' => count($events), 'failed' => 0, 'scope' => 'All synthetic source outbox envelopes; independent ERP canonicalizer/public-key verification, not all imported into ERP'], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo 'Verified '.count($events)." source signatures\n";
