<?php
    // /var/www/html/sistemacontable-shopify/proxy.php

    // Allow access from your frontend
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Headers: Content-Type, X-Shopify-Access-Token");

    // Handle preflight OPTIONS request
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        exit(0);
    }

    // Get the target URL from the query string
    $url = $_GET['url'] ?? '';

    if (!$url) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing URL parameter']);
        exit;
    }

    // Get the request body
    $input = file_get_contents('php://input');

    // Get the Access Token from headers (Apache/CGI converts headers to HTTP_UPPERCASE)
    $token = $_SERVER['HTTP_X_SHOPIFY_ACCESS_TOKEN'] ?? '';

    // Initialize cURL session
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $input);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "X-Shopify-Access-Token: $token"
    ]);

    // Execute request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Return response to frontend
    http_response_code($httpCode);
    header('Content-Type: application/json');
    echo $response;
?>
