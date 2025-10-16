// Firebase Configuration for Smart Hive Solution
// This file contains the Firebase configuration for client-side operations

import { initializeApp } from "firebase/app";
import { getAnalytics } from "firebase/analytics";
import { getAuth } from "firebase/auth";
import { getFirestore } from "firebase/firestore";

// Your web app's Firebase configuration
const firebaseConfig = {
    apiKey: "AIzaSyBIQYaGk5eLuK8tNobLY8cSk3_NDtGkIXU",
    authDomain: "smart-hive-e94ca.firebaseapp.com",
    projectId: "smart-hive-e94ca",
    storageBucket: "smart-hive-e94ca.firebasestorage.app",
    messagingSenderId: "643846748725",
    appId: "1:643846748725:web:4ab269aa31291bc2168f7c",
    measurementId: "G-REY86XLJZG"
};

// Initialize Firebase
const app = initializeApp(firebaseConfig);

// Initialize Firebase services
export const auth = getAuth(app);
export const db = getFirestore(app);
export const analytics = getAnalytics(app);

// Export the app instance
export default app;
