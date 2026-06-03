<?php
function getPaymentInstructions() {
    $instructionsFile = 'payment_instructions.json';
    if (file_exists($instructionsFile)) {
        $instructions = json_decode(file_get_contents($instructionsFile), true);
        return $instructions;
    }
    
    // Default values if file doesn't exist
    return [
        'gcash' => [
            'number' => '09123456789',
            'name' => 'Default GCash Name'
        ],
        'bank' => [
            'name' => 'Default Bank',
            'account' => '1234567890',
            'account_name' => 'Default Account Name'
        ],
        'office' => 'Default Office Address'
    ];
} 