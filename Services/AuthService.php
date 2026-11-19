<?php
// Services/AuthService.php

class AuthService {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function authenticateUser($email, $password) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = :email AND role = 'user' LIMIT 1");
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            if (isset($user['is_active']) && ($user['is_active'] == 0 || $user['is_active'] === false)) {
                return ['success' => false, 'message' => 'Account deactivated.'];
            }
            $this->createSession($user, 'user');
            return ['success' => true, 'user' => $user];
        }
        return ['success' => false, 'message' => 'Invalid credentials.'];
    }

    public function authenticateAdmin($email, $password) {
        $stmt = $this->db->prepare("SELECT * FROM admin WHERE email = :email LIMIT 1");
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($admin && password_verify($password, $admin['password'])) {
            $admin['id'] = $admin['admin_id']; // Normalize ID
            $this->createSession($admin, 'admin');
            return ['success' => true, 'user' => $admin];
        }
        return ['success' => false, 'message' => 'Invalid credentials.'];
    }

    private function createSession($user, $role) {
        if(session_status() !== PHP_SESSION_ACTIVE) session_start();
        // New session ID on login prevents session fixation, and clears any
        // leftover keys from a previous login (e.g. player -> admin).
        session_regenerate_id(true);
        $_SESSION = [];
        $_SESSION['loggedin'] = true;
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $role;
        $_SESSION['is_admin'] = ($role === 'admin');
    }

    public function logout() {
        if(session_status() !== PHP_SESSION_ACTIVE) session_start();
        session_destroy();
    }
}
