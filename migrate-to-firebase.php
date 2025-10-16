<?php

/**
 * Migration Script: JSON to Firebase
 * 
 * This script migrates existing JSON data from the storage directory to Firebase Firestore.
 * Run this script once after setting up Firebase to migrate your existing data.
 */

require_once 'vendor/autoload.php';
require_once 'app/Database.php';

use App\Database;

class FirebaseMigration
{
    private $firestore;
    private $storageDir;

    public function __construct()
    {
        $this->firestore = Database::getFirestoreClient();
        $this->storageDir = __DIR__ . '/storage';
    }

    public function migrate()
    {
        echo "Starting Firebase migration...\n\n";

        try {
            // Create default admin user
            $this->createDefaultAdmin();
            
            // Migrate hives
            $this->migrateHives();
            
            // Migrate alerts
            $this->migrateAlerts();
            
            // Migrate notifications (user contacts)
            $this->migrateNotifications();
            
            // Migrate other data files
            $this->migrateOtherData();

            echo "\n✅ Migration completed successfully!\n";
            echo "Your data has been migrated to Firebase Firestore.\n";
            echo "You can now remove the JSON files from the storage directory if desired.\n";

        } catch (\Throwable $e) {
            echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
            echo "Please check your Firebase configuration and try again.\n";
        }
    }

    private function createDefaultAdmin()
    {
        echo "Creating default admin user...\n";
        
        try {
            $authController = new \App\Controllers\AuthController();
            $authController->createDefaultAdmin();
            echo "✅ Default admin user created\n";
        } catch (\Throwable $e) {
            echo "⚠️  Warning: Could not create default admin: " . $e->getMessage() . "\n";
        }
    }

    private function migrateHives()
    {
        $hivesFile = $this->storageDir . '/hives.json';
        
        if (!file_exists($hivesFile)) {
            echo "⚠️  No hives.json file found, skipping hives migration\n";
            return;
        }

        echo "Migrating hives...\n";
        
        $hivesData = json_decode(file_get_contents($hivesFile), true);
        
        if (!is_array($hivesData)) {
            echo "⚠️  Invalid hives.json format, skipping\n";
            return;
        }

        $migrated = 0;
        foreach ($hivesData as $hive) {
            try {
                // Convert old format to new format
                $hiveData = [
                    'name' => $hive['name'] ?? '',
                    'device_id' => $hive['device_id'] ?? '',
                    'location' => $hive['location'] ?? '',
                    'description' => $hive['description'] ?? '',
                    'owner_id' => $hive['owner_id'] ?? '',
                    'owner_name' => $hive['owner_name'] ?? '',
                    'status' => $hive['status'] ?? 'pending',
                    'created_at' => isset($hive['created_at']) ? 
                        new \DateTime($hive['created_at']) : new \DateTime(),
                    'updated_at' => new \DateTime()
                ];

                // Add approval data if exists
                if (isset($hive['approved_at'])) {
                    $hiveData['approved_at'] = new \DateTime($hive['approved_at']);
                }
                if (isset($hive['approved_by'])) {
                    $hiveData['approved_by'] = $hive['approved_by'];
                }

                $this->firestore->collection('hives')->add($hiveData);
                $migrated++;
                
            } catch (\Throwable $e) {
                echo "⚠️  Failed to migrate hive '{$hive['name']}': " . $e->getMessage() . "\n";
            }
        }

        echo "✅ Migrated {$migrated} hives\n";
    }

    private function migrateAlerts()
    {
        $alertsFile = $this->storageDir . '/alerts.json';
        
        if (!file_exists($alertsFile)) {
            echo "⚠️  No alerts.json file found, skipping alerts migration\n";
            return;
        }

        echo "Migrating alerts...\n";
        
        $alertsData = json_decode(file_get_contents($alertsFile), true);
        
        if (!is_array($alertsData)) {
            echo "⚠️  Invalid alerts.json format, skipping\n";
            return;
        }

        $migrated = 0;
        foreach ($alertsData as $alert) {
            try {
                $alertData = [
                    'type' => $alert['type'] ?? 'general',
                    'level' => $alert['level'] ?? 'medium',
                    'message' => $alert['message'] ?? '',
                    'hive_id' => $alert['hive_id'] ?? null,
                    'hive_name' => $alert['hive_name'] ?? null,
                    'deleted_by' => $alert['deleted_by'] ?? null,
                    'value' => $alert['value'] ?? null,
                    'unit' => $alert['unit'] ?? null,
                    'is_read' => false,
                    'created_at' => isset($alert['created_at']) ? 
                        new \DateTime($alert['created_at']) : new \DateTime()
                ];

                $this->firestore->collection('alerts')->add($alertData);
                $migrated++;
                
            } catch (\Throwable $e) {
                echo "⚠️  Failed to migrate alert: " . $e->getMessage() . "\n";
            }
        }

        echo "✅ Migrated {$migrated} alerts\n";
    }

    private function migrateNotifications()
    {
        $notificationsFile = $this->storageDir . '/notifications.json';
        
        if (!file_exists($notificationsFile)) {
            echo "⚠️  No notifications.json file found, skipping notifications migration\n";
            return;
        }

        echo "Migrating user notifications/contacts...\n";
        
        $notificationsData = json_decode(file_get_contents($notificationsFile), true);
        
        if (!is_array($notificationsData)) {
            echo "⚠️  Invalid notifications.json format, skipping\n";
            return;
        }

        $migrated = 0;
        foreach ($notificationsData as $userId => $contactInfo) {
            try {
                // Update user document with contact information
                $userDoc = $this->firestore->collection('users')->document($userId);
                $userSnapshot = $userDoc->snapshot();
                
                if ($userSnapshot->exists()) {
                    $userDoc->update([
                        'phone' => $contactInfo['phone'] ?? '',
                        'email' => $contactInfo['email'] ?? '',
                        'updated_at' => new \DateTime()
                    ]);
                    $migrated++;
                }
                
            } catch (\Throwable $e) {
                echo "⚠️  Failed to migrate contact for user {$userId}: " . $e->getMessage() . "\n";
            }
        }

        echo "✅ Migrated {$migrated} user contacts\n";
    }

    private function migrateOtherData()
    {
        $otherFiles = [
            'harvests.json' => 'harvests',
            'tips.json' => 'tips',
            'training.json' => 'training',
            'training_applications.json' => 'training_applications',
            'tasks.json' => 'tasks'
        ];

        foreach ($otherFiles as $filename => $collection) {
            $filePath = $this->storageDir . '/' . $filename;
            
            if (!file_exists($filePath)) {
                echo "⚠️  No {$filename} file found, skipping {$collection} migration\n";
                continue;
            }

            echo "Migrating {$collection}...\n";
            
            $data = json_decode(file_get_contents($filePath), true);
            
            if (!is_array($data)) {
                echo "⚠️  Invalid {$filename} format, skipping\n";
                continue;
            }

            $migrated = 0;
            foreach ($data as $item) {
                try {
                    // Add timestamp if not present
                    if (!isset($item['created_at'])) {
                        $item['created_at'] = new \DateTime();
                    } elseif (is_string($item['created_at'])) {
                        $item['created_at'] = new \DateTime($item['created_at']);
                    }

                    $this->firestore->collection($collection)->add($item);
                    $migrated++;
                    
                } catch (\Throwable $e) {
                    echo "⚠️  Failed to migrate {$collection} item: " . $e->getMessage() . "\n";
                }
            }

            echo "✅ Migrated {$migrated} {$collection} items\n";
        }
    }
}

// Run migration if called directly
if (php_sapi_name() === 'cli') {
    $migration = new FirebaseMigration();
    $migration->migrate();
}
