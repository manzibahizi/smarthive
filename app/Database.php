<?php

namespace App;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Firestore;
use Kreait\Firebase\Auth;
use Google\Cloud\Firestore\FirestoreClient;

class Database
{
    private static ?Firestore $firestore = null;
    private static ?Auth $auth = null;
    private static ?FirestoreClient $firestoreClient = null;

    public static function getFirestore(): Firestore
    {
        if (self::$firestore === null) {
            $config = require __DIR__ . '/../config/firebase.php';
            
            $factory = (new Factory)
                ->withProjectId($config['projectId'])
                ->withApiKey($config['apiKey']);

            // If service account key is provided, use it for server-side operations
            if (!empty($config['serviceAccountKey'])) {
                $factory = $factory->withServiceAccount($config['serviceAccountKey']);
            } elseif (!empty($config['serviceAccountKeyPath'])) {
                $factory = $factory->withServiceAccount($config['serviceAccountKeyPath']);
            }

            self::$firestore = $factory->createFirestore();
        }

        return self::$firestore;
    }

    public static function getAuth(): Auth
    {
        if (self::$auth === null) {
            $config = require __DIR__ . '/../config/firebase.php';
            
            $factory = (new Factory)
                ->withProjectId($config['projectId'])
                ->withApiKey($config['apiKey']);

            // If service account key is provided, use it for server-side operations
            if (!empty($config['serviceAccountKey'])) {
                $factory = $factory->withServiceAccount($config['serviceAccountKey']);
            } elseif (!empty($config['serviceAccountKeyPath'])) {
                $factory = $factory->withServiceAccount($config['serviceAccountKeyPath']);
            }

            self::$auth = $factory->createAuth();
        }

        return self::$auth;
    }

    public static function getFirestoreClient(): FirestoreClient
    {
        if (self::$firestoreClient === null) {
            $config = require __DIR__ . '/../config/firebase.php';
            
            $options = [
                'projectId' => $config['projectId'],
            ];

            // If service account key is provided, use it
            if (!empty($config['serviceAccountKey'])) {
                $options['keyFile'] = $config['serviceAccountKey'];
            } elseif (!empty($config['serviceAccountKeyPath'])) {
                $options['keyFilePath'] = $config['serviceAccountKeyPath'];
            }

            self::$firestoreClient = new FirestoreClient($options);
        }

        return self::$firestoreClient;
    }

    // Helper methods for common Firestore operations
    public static function collection(string $collection): \Google\Cloud\Firestore\CollectionReference
    {
        return self::getFirestoreClient()->collection($collection);
    }

    public static function document(string $collection, string $documentId = null): \Google\Cloud\Firestore\DocumentReference
    {
        if ($documentId) {
            return self::getFirestoreClient()->collection($collection)->document($documentId);
        }
        return self::getFirestoreClient()->collection($collection)->newDocument();
    }

    public static function batch(): \Google\Cloud\Firestore\WriteBatch
    {
        return self::getFirestoreClient()->batch();
    }

    public static function runTransaction(callable $callback)
    {
        return self::getFirestoreClient()->runTransaction($callback);
    }
}


