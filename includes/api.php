<?php


function getBananaQuestion()
{
    $apiUrl = "http://marcconrad.com/uob/banana/api.php?out=json&base64=no";

    // Initialize cURL
    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_URL => $apiUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10,

        // User agent
        CURLOPT_USERAGENT => "Banana Game PHP",

        // Request JSON
        CURLOPT_HTTPHEADER => [
            "Accept: application/json"
        ]
    ]);

    // Send request
    $response = curl_exec($curl);

    // Check cURL error
    if ($response === false) {

        $error = curl_error($curl);

        curl_close($curl);

        return [
            "success" => false,
            "message" => "API connection failed: " . $error
        ];
    }

    // Get HTTP status code
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

    curl_close($curl);

    // Check HTTP status
    if ($httpCode !== 200) {

        return [
            "success" => false,
            "message" => "API returned HTTP status: " . $httpCode
        ];
    }

    // Convert JSON response to PHP array
    $data = json_decode($response, true);

    // Check JSON error
    if (json_last_error() !== JSON_ERROR_NONE) {

        return [
            "success" => false,
            "message" => "Invalid JSON response from Banana API."
        ];
    }

    // Make sure required data exists
    if (
        !is_array($data) ||
        !isset($data["question"]) ||
        !isset($data["solution"])
    ) {

        return [
            "success" => false,
            "message" => "Question or solution is missing from API response."
        ];
    }

    // Return clean data
    return [
        "success" => true,
        "question" => $data["question"],
        "solution" => (int) $data["solution"]
    ];
}
