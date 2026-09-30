<?php

// URL Preview with Whitelist
if ($action === 'preview_url' && !function_exists('ssrf_vulnerable_active')) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['url'])) {
        $url = $_POST['url'] ?? $_GET['url'] ?? '';
        
        if (empty($url)) {
            echo json_encode(['error' => 'No URL provided']);
            exit;
        }

        // Validate and whitelist URLs
        $parsed = parse_url($url);
        
        if (!$parsed || !isset($parsed['scheme']) || !isset($parsed['host'])) {
            echo json_encode(['error' => 'Invalid URL format']);
            exit;
        }

        // Only allow HTTP/HTTPS
        if (!in_array($parsed['scheme'], ['http', 'https'])) {
            echo json_encode(['error' => 'Only HTTP/HTTPS protocols allowed']);
            exit;
        }

        // Block private/internal IP ranges
        $host = $parsed['host'];
        $ip = gethostbyname($host);
        
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            echo json_encode(['error' => 'Access to internal/private networks not allowed']);
            exit;
        }

        // Whitelist of allowed domains
        $allowed_domains = ['wikipedia.org', 'example.com', 'github.com'];
        $is_whitelisted = false;
        foreach ($allowed_domains as $domain) {
            if (str_ends_with($host, $domain)) {
                $is_whitelisted = true;
                break;
            }
        }

        if (!$is_whitelisted) {
            echo json_encode(['error' => 'Domain not in whitelist']);
            exit;
        }

        // Fetch with restrictions
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false); // Prevent redirect bypasses
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        if ($response !== false) {
            echo json_encode([
                'success' => true,
                'url' => $url,
                'http_code' => $http_code,
                'content_type' => $content_type,
                'preview' => substr(strip_tags($response), 0, 500),
                'note' => 'Secure mode: whitelisted domains only'
            ]);
        } else {
            echo json_encode(['error' => 'Failed to fetch URL']);
        }
        exit;
    }
}

// Import from URL (disabled in secure mode)
if ($action === 'import_url' && !function_exists('ssrf_vulnerable_active')) {
    http_response_code(403);
    echo json_encode([
        'error' => 'URL import feature is disabled for security',
        'note' => 'Please upload files directly or paste content manually'
    ]);
    exit;
}

// No Open Proxy/Redirect
if (($action === 'proxy' || $action === 'redirect_external') && !function_exists('ssrf_vulnerable_active')) {
    http_response_code(403);
    die('Proxy/redirect functionality disabled for security');
}
