<?php

namespace App\Controllers;

use App\Database;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;
use Kreait\Firebase\Exception\Auth\UserNotFound;

class AuthController
{
    public function register(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $email = trim($data['email'] ?? '');
        $password = trim($data['password'] ?? '');
        $username = trim($data['username'] ?? '');
        $role = trim($data['role'] ?? 'user');

        if ($email === '' || $password === '' || $username === '') {
            $response->getBody()->write(json_encode([
                'status' => 'error', 
                'message' => 'Email, username, and password are required'
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        try {
            $auth = Database::getAuth();
            $firestore = Database::getFirestore();

            // Create user in Firebase Auth
            $userRecord = $auth->createUser([
                'email' => $email,
                'password' => $password,
                'displayName' => $username,
            ]);

            // Store additional user data in Firestore
            $userData = [
                'uid' => $userRecord->uid,
                'username' => $username,
                'email' => $email,
                'role' => $role,
                'is_active' => true,
                'created_at' => new \DateTime(),
                'updated_at' => new \DateTime(),
            ];

            $firestore->collection('users')->document($userRecord->uid)->set($userData);

            $response->getBody()->write(json_encode([
                'status' => 'success',
                'message' => 'User registered successfully',
                'user' => [
                    'uid' => $userRecord->uid,
                    'username' => $username,
                    'email' => $email,
                    'role' => $role
                ]
            ]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode([
                'status' => 'error', 
                'message' => 'Registration failed: ' . $e->getMessage()
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }
    }

    public function login(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $email = trim($data['email'] ?? '');
        $password = trim($data['password'] ?? '');

        if ($email === '' || $password === '') {
            $response->getBody()->write(json_encode([
                'status' => 'error', 
                'message' => 'Email and password are required'
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        try {
            $auth = Database::getAuth();
            $firestore = Database::getFirestore();

            // Sign in with email and password
            $signInResult = $auth->signInWithEmailAndPassword($email, $password);
            $uid = $signInResult->firebaseUserId();

            // Get user data from Firestore
            $userDoc = $firestore->collection('users')->document($uid)->snapshot();
            
            if (!$userDoc->exists()) {
                throw new \RuntimeException('User data not found');
            }

            $userData = $userDoc->data();
            
            if (!$userData['is_active']) {
                throw new \RuntimeException('Account is deactivated');
            }

            // Store user session
            $_SESSION['user'] = [
                'uid' => $uid,
                'username' => $userData['username'],
                'email' => $userData['email'],
                'role' => $userData['role']
            ];

            $response->getBody()->write(json_encode([
                'status' => 'success',
                'user' => [
                    'uid' => $uid,
                    'username' => $userData['username'],
                    'email' => $userData['email'],
                    'role' => $userData['role']
                ]
            ]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode([
                'status' => 'error', 
                'message' => 'Invalid credentials'
            ]));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }
    }

    public function logout(Request $request, Response $response): Response
    {
        session_destroy();
        $response->getBody()->write(json_encode([
            'status' => 'success',
            'message' => 'Logged out successfully'
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function verifyToken(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $idToken = $data['idToken'] ?? '';

        if ($idToken === '') {
            $response->getBody()->write(json_encode([
                'status' => 'error', 
                'message' => 'ID token is required'
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        try {
            $auth = Database::getAuth();
            $verifiedToken = $auth->verifyIdToken($idToken);
            $uid = $verifiedToken->claims()->get('sub');

            // Get user data from Firestore
            $firestore = Database::getFirestore();
            $userDoc = $firestore->collection('users')->document($uid)->snapshot();
            
            if (!$userDoc->exists()) {
                throw new \RuntimeException('User data not found');
            }

            $userData = $userDoc->data();

            $response->getBody()->write(json_encode([
                'status' => 'success',
                'user' => [
                    'uid' => $uid,
                    'username' => $userData['username'],
                    'email' => $userData['email'],
                    'role' => $userData['role']
                ]
            ]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (FailedToVerifyToken $e) {
            $response->getBody()->write(json_encode([
                'status' => 'error', 
                'message' => 'Invalid token'
            ]));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        } catch (\Throwable $e) {
            $response->getBody()->write(json_encode([
                'status' => 'error', 
                'message' => 'Token verification failed'
            ]));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }
    }

    public function createDefaultAdmin(): void
    {
        try {
            $auth = Database::getAuth();
            $firestore = Database::getFirestore();

            // Check if admin user exists
            $adminQuery = $firestore->collection('users')
                ->where('role', '=', 'admin')
                ->limit(1);
            
            $adminDocs = $adminQuery->documents();
            
            if ($adminDocs->isEmpty()) {
                // Create default admin user
                $userRecord = $auth->createUser([
                    'email' => 'admin@smarthive.com',
                    'password' => 'Admin123',
                    'displayName' => 'Admin',
                ]);

                $userData = [
                    'uid' => $userRecord->uid,
                    'username' => 'Admin',
                    'email' => 'admin@smarthive.com',
                    'role' => 'admin',
                    'is_active' => true,
                    'created_at' => new \DateTime(),
                    'updated_at' => new \DateTime(),
                ];

                $firestore->collection('users')->document($userRecord->uid)->set($userData);
            }
        } catch (\Throwable $e) {
            // Log error but don't throw - this is initialization code
            error_log('Failed to create default admin: ' . $e->getMessage());
        }
    }
} 