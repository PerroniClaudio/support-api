<?php

// Credenziali API
$accessKeyId = 'LA_TUA_ACCESS_KEY_ID';
$secretAccessKey = 'LA_TUA_SECRET_ACCESS_KEY';

// URL dell'endpoint API per configurare il webhook
$apiUrl = 'https://app.ninjarmm.com/v2/webhook';

// Dati del webhook da configurare
$webhookData = [
    'url' => 'https://stagingapi.ifortech.com/webhook/endpoint', // URL del tuo endpoint che riceverà i webhook
    'enabled' => true,
    'events' => ['ALERT_TRIGGERED', 'ALERT_RESOLVED'], // Eventi da monitorare
];

// Converti i dati del webhook in formato JSON
$jsonData = json_encode($webhookData);

// Crea un timestamp per l'header
$timestamp = gmdate('Y-m-d\TH:i:s\Z');

// Crea la stringa da firmare
$stringToSign = "PUT\n" . md5($jsonData) . "\napplication/json\n" . $timestamp . "\n/v2/webhook";

// Crea la firma utilizzando HMAC-SHA256
$signature = base64_encode(hash_hmac('sha256', $stringToSign, $secretAccessKey, true));

// Inizializza cURL
$ch = curl_init($apiUrl);

// Imposta gli header della richiesta
$headers = [
    'Content-Type: application/json',
    'Date: ' . $timestamp,
    'Authorization: NJ ' . $accessKeyId . ':' . $signature,
    'Accept: */*'
];

// Configura le opzioni cURL
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);

// Esegui la richiesta
$response = curl_exec($ch);

// Verifica se ci sono errori
if (curl_errno($ch)) {
    echo 'Errore cURL: ' . curl_error($ch);
} else {
    // Decodifica la risposta JSON
    $responseData = json_decode($response, true);
    // Gestisci la risposta in base alle tue esigenze
    print_r($responseData);
}

// Chiudi la sessione cURL
curl_close($ch);
