<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

$api_key = 'UW_API_SLEUTEL_HIER';

$input = json_decode(file_get_contents('php://input'), true);
$user_message = $input['message'] ?? '';
$conversation_history = $input['history'] ?? [];

// System prompt met instructies voor de chatbot
$system_prompt = "Instructies aan de chatbot";

$messages = $conversation_history;
$messages[] = [
    'role' => 'user',
    'content' => $user_message
];

$data = [
    'model' => 'claude-sonnet-4-20250514',
    'max_tokens' => 1024,
    'system' => $system_prompt,
    'messages' => $messages
];

$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'x-api-key: ' . $api_key,
    'anthropic-version: 2023-06-01'
]);

$response = curl_exec($ch);
curl_close($ch);

echo $response;
?>

