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
            'name' => 'Default GCash Account Name'
        ],
        'bank' => [
            'name' => 'Default Bank Name',
            'account' => '1234567890',
            'account_name' => 'Default Bank Account Name'
        ],
        'office' => 'Default Office Address for Cash Payments'
    ];
} 