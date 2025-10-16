<?php

return [
    'apiKey' => getenv('FIREBASE_API_KEY') ?: 'AIzaSyBIQYaGk5eLuK8tNobLY8cSk3_NDtGkIXU',
    'authDomain' => getenv('FIREBASE_AUTH_DOMAIN') ?: 'smart-hive-e94ca.firebaseapp.com',
    'projectId' => getenv('FIREBASE_PROJECT_ID') ?: 'smart-hive-e94ca',
    'storageBucket' => getenv('FIREBASE_STORAGE_BUCKET') ?: 'smart-hive-e94ca.firebasestorage.app',
    'messagingSenderId' => getenv('FIREBASE_MESSAGING_SENDER_ID') ?: '643846748725',
    'appId' => getenv('FIREBASE_APP_ID') ?: '1:643846748725:web:4ab269aa31291bc2168f7c',
    'measurementId' => getenv('FIREBASE_MEASUREMENT_ID') ?: 'G-REY86XLJZG',
    
    // Service Account Key (for server-side operations)
    'serviceAccountKey' => getenv('FIREBASE_SERVICE_ACCOUNT_KEY') ?: null,
    'serviceAccountKeyPath' => getenv('FIREBASE_SERVICE_ACCOUNT_KEY_PATH') ?: null,
    
    // Database URL (for Realtime Database if used)
    'databaseUrl' => getenv('FIREBASE_DATABASE_URL') ?: null,
];
