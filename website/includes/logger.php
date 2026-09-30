<?php
require_once __DIR__ . '/db.php';

/**
 * Activity Logger for HydroSim Wiki
 * Logs critical actions and suspicious activity for security monitoring
 */

class ActivityLogger {
    
    // Severity levels
    const INFO = 'info';
    const WARNING = 'warning';
    const CRITICAL = 'critical';
    const SECURITY = 'security';
    
    /**
     * Log an activity to the database
     * 
     * @param string $action The action being performed (e.g., 'login', 'page_edit')
     * @param string $details Additional details about the action
     * @param string $severity Severity level (info, warning, critical, security)
     * @param string|null $username Override username (defaults to session username)
     */
    public static function log(string $action, string $details = '', string $severity = self::INFO, ?string $username = null): bool {
        $conn = db_connect();
        if (!$conn) {
            error_log("ActivityLogger: Failed to connect to database");
            return false;
        }
        
        // Get username from session if not provided
        if ($username === null) {
            $username = $_SESSION['username'] ?? 'anonymous';
        }
        
        // Get IP address
        $ip_address = self::getClientIp();
        
        // Get user agent
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Prepare and execute insert
        $stmt = $conn->prepare(
            'INSERT INTO activity_logs (username, ip_address, action, details, severity, user_agent) 
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        
        if (!$stmt) {
            error_log("ActivityLogger: Failed to prepare statement");
            $conn->close();
            return false;
        }
        
        $stmt->bind_param('ssssss', $username, $ip_address, $action, $details, $severity, $user_agent);
        $result = $stmt->execute();
        
        $stmt->close();
        $conn->close();
        
        return $result;
    }
    
    /**
     * Log a successful login
     */
    public static function logLogin(string $username): void {
        self::log('login_success', "User logged in successfully", self::INFO, $username);
    }
    
    /**
     * Log a logout
     */
    public static function logLogout(string $username): void {
        ActivityLogger::log('logout', "User logged out", ActivityLogger::INFO, $username);
    }
    
    /**
     * Log user registration
     */
    public static function logRegistration(string $username, string $role): void {
        $details = "New user registered with role: {$role}";
        $severity = ($role === 'admin') ? self::SECURITY : self::INFO;
        self::log('user_registration', $details, $severity, $username);
    }
    
    /**
     * Log page creation
     */
    public static function logPageCreate(string $page_title): void {
        self::log('page_create', "Created page: {$page_title}", self::INFO);
    }
    
    /**
     * Log page update
     */
    public static function logPageUpdate(string $page_title): void {
        self::log('page_update', "Updated page: {$page_title}", self::INFO);
    }
    
    /**
     * Log page deletion
     */
    public static function logPageDelete(string $page_title): void {
        self::log('page_delete', "Deleted page: {$page_title}", self::WARNING);
    }
    
    /**
     * Log unauthorized access attempt
     */
    public static function logUnauthorizedAccess(string $attempted_action): void {
        self::log(
            'unauthorized_access', 
            "Attempted unauthorized action: {$attempted_action}", 
            self::SECURITY
        );
    }
    
    /**
     * Log CSRF failure
     */
    public static function logCsrfFailure(string $action): void {
        self::log(
            'csrf_failure', 
            "CSRF token verification failed for action: {$action}", 
            self::SECURITY
        );
    }
    
    /**
     * Log suspicious activity patterns
     */
    public static function logSuspiciousActivity(string $description): void {
        self::log('suspicious_activity', $description, self::SECURITY);
    }
    
    /**
     * Get recent logs (for admin view)
     * 
     * @param int $limit Maximum number of logs to retrieve
     * @param string|null $severity Filter by severity level
     * @param string|null $username Filter by username
     * @return array Array of log entries
     */
    public static function getRecentLogs(int $limit = 100, ?string $severity = null, ?string $username = null): array {
        $conn = db_connect();
        if (!$conn) {
            return [];
        }
        
        $query = 'SELECT * FROM activity_logs WHERE 1=1';
        $params = [];
        $types = '';
        
        if ($severity !== null) {
            $query .= ' AND severity = ?';
            $params[] = $severity;
            $types .= 's';
        }
        
        if ($username !== null) {
            $query .= ' AND username = ?';
            $params[] = $username;
            $types .= 's';
        }
        
        $query .= ' ORDER BY timestamp DESC LIMIT ?';
        $params[] = $limit;
        $types .= 'i';
        
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            $conn->close();
            return [];
        }
        
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $logs = [];
        
        while ($row = $result->fetch_assoc()) {
            $logs[] = $row;
        }
        
        $stmt->close();
        $conn->close();
        
        return $logs;
    }
    
    /**
     * Get client IP address (handles proxies)
     */
    private static function getClientIp(): string {
        $ip = '';
        
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // X-Forwarded-For can contain multiple IPs, take the first one
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
        } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $ip = $_SERVER['HTTP_X_REAL_IP'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        }
        
        return $ip;
    }
    
    /**
     * Detect potentially suspicious patterns
     * Called to analyze ongoing activity
     */
    public static function detectSuspiciousPatterns(string $username): array {
        $conn = db_connect();
        if (!$conn) {
            return [];
        }
        
        $alerts = [];
        
        // Check for multiple failed logins in last 5 minutes
        $stmt = $conn->prepare(
            "SELECT COUNT(*) as count FROM activity_logs 
             WHERE username = ? 
             AND action = 'login_failed' 
             AND timestamp > DATE_SUB(NOW(), INTERVAL 5 MINUTE)"
        );
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            if ($row['count'] >= 5) {
                $alerts[] = "Multiple failed login attempts detected";
            }
        }
        $stmt->close();
        
        // Check for rapid successive actions (potential automation)
        $stmt = $conn->prepare(
            "SELECT COUNT(*) as count FROM activity_logs 
             WHERE username = ? 
             AND timestamp > DATE_SUB(NOW(), INTERVAL 1 MINUTE)"
        );
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            if ($row['count'] >= 20) {
                $alerts[] = "Unusually high activity rate detected (possible bot)";
            }
        }
        $stmt->close();
        
        $conn->close();
        return $alerts;
    }
}
