<?php

/**
 * Test Script for Firebase Integration
 * This script tests creating a hive in Firebase
 */

require_once 'vendor/autoload.php';
require_once 'app/Database.php';

use App\Database;

echo "🔥 Testing Firebase Integration...\n\n";

try {
    // Test Firebase configuration
    echo "1. Testing Firebase configuration...\n";
    $config = Database::getConfig();
    echo "✅ Project ID: " . $config['projectId'] . "\n";
    echo "✅ API Key: " . substr($config['apiKey'], 0, 20) . "...\n\n";

    // Test Firestore connection
    echo "2. Testing Firestore connection...\n";
    $firestore = Database::getFirestore();
    echo "✅ Firestore client created\n\n";

    // Test creating a hive
    echo "3. Testing hive creation...\n";
    
    // Simulate user session
    session_start();
    $_SESSION['user'] = [
        'uid' => 'test_user_123',
        'username' => 'Test User',
        'email' => 'test@example.com',
        'role' => 'user'
    ];

    // Create test hive data
    $hiveData = [
        'name' => 'Test Hive - ' . date('Y-m-d H:i:s'),
        'device_id' => 'TEST_DEVICE_' . uniqid(),
        'location' => 'Test Location',
        'description' => 'This is a test hive created to verify Firebase integration',
        'owner_id' => 'test_user_123',
        'owner_name' => 'Test User',
        'status' => 'pending',
        'created_at' => new \DateTime(),
        'updated_at' => new \DateTime()
    ];

    echo "Creating hive with data:\n";
    echo "- Name: " . $hiveData['name'] . "\n";
    echo "- Device ID: " . $hiveData['device_id'] . "\n";
    echo "- Location: " . $hiveData['location'] . "\n";
    echo "- Owner: " . $hiveData['owner_name'] . "\n\n";

    // Add hive to Firestore
    $hivesCollection = $firestore->collection('hives');
    $docRef = $hivesCollection->add($hiveData);
    
    echo "✅ Hive created successfully!\n";
    echo "✅ Document ID: " . $docRef->id() . "\n\n";

    // Test reading hives
    echo "4. Testing hive retrieval...\n";
    $hivesSnapshot = $hivesCollection->documents();
    echo "✅ Total hives in database: " . $hivesSnapshot->size() . "\n\n";

    // Test creating sensor data
    echo "5. Testing sensor data creation...\n";
    $sensorData = [
        'sensor_id' => 'SENSOR_' . uniqid(),
        'hive_id' => $docRef->id(),
        'temperature' => 24.5,
        'humidity' => 65.0,
        'gas_level' => 180.0,
        'hive_weight' => 45.2,
        'battery_level' => 85.0,
        'signal_strength' => 95.0,
        'recorded_at' => new \DateTime()
    ];

    $sensorCollection = $firestore->collection('sensor_data');
    $sensorDocRef = $sensorCollection->add($sensorData);
    
    echo "✅ Sensor data created successfully!\n";
    echo "✅ Sensor Document ID: " . $sensorDocRef->id() . "\n\n";

    // Test creating an alert
    echo "6. Testing alert creation...\n";
    $alertData = [
        'type' => 'test',
        'level' => 'info',
        'message' => 'Test alert created successfully',
        'hive_id' => $docRef->id(),
        'hive_name' => $hiveData['name'],
        'is_read' => false,
        'created_at' => new \DateTime()
    ];

    $alertsCollection = $firestore->collection('alerts');
    $alertDocRef = $alertsCollection->add($alertData);
    
    echo "✅ Alert created successfully!\n";
    echo "✅ Alert Document ID: " . $alertDocRef->id() . "\n\n";

    echo "🎉 All tests passed! Firebase integration is working correctly.\n";
    echo "\nSummary:\n";
    echo "- ✅ Firebase configuration loaded\n";
    echo "- ✅ Firestore connection established\n";
    echo "- ✅ Hive created and stored\n";
    echo "- ✅ Sensor data recorded\n";
    echo "- ✅ Alert generated\n";
    echo "- ✅ Data retrieval working\n\n";

    echo "You can now use the web interface to view and manage your hives!\n";

} catch (\Throwable $e) {
    echo "❌ Test failed: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
