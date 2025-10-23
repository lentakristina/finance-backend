<?php

return [
    'paths' => ['api/*'],
    
    'allowed_methods' => ['*'],
    
    'allowed_origins' => ['*'], // Temporarily allow all for testing
    
    'allowed_origins_patterns' => [],
    
    'allowed_headers' => ['*'],
    
    'exposed_headers' => ['Authorization'],
    
    'max_age' => 0,
    
    'supports_credentials' => false,
];