<?php 
// Extended URL handling features
function ssrf_vulnerable_active() { return true; }

// URL Preview/Fetch Feature
if ($action === 'preview_url') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['url'])) {
        $url = $_POST['url'] ?? $_GET['url'] ?? '';
        
        if (empty($url)) {
            echo json_encode(['error' => 'No URL provided']);
            exit;
        }

        // Fetch URL content for preview
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response !== false) {
            echo json_encode([
                'success' => true,
                'url' => $url,
                'http_code' => $http_code,
                'content_type' => $content_type,
                'content' => base64_encode($response),
                'preview' => substr(strip_tags($response), 0, 500),
                'length' => strlen($response)
            ]);
        } else {
            echo json_encode([
                'error' => $error ?: 'Failed to fetch URL',
                'url' => $url
            ]);
        }
        exit;
    }
}

// Import Content from URL
if ($action === 'import_url') {
    if (!is_admin()) {
        http_response_code(403);
        echo json_encode(['error' => 'Admin access required']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $url = $_POST['import_url'] ?? '';
        $import_type = $_POST['import_type'] ?? 'text';
        
        if (empty($url)) {
            echo json_encode(['error' => 'No URL provided']);
            exit;
        }

        // Fetch content from remote URL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $content = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($content !== false && $http_code === 200) {
            echo json_encode([
                'success' => true,
                'content' => $content,
                'url' => $url,
                'length' => strlen($content)
            ]);
        } else {
            echo json_encode([
                'error' => 'Failed to import from URL',
                'http_code' => $http_code
            ]);
        }
        exit;
    }
}

// Redirect/Proxy Endpoint
if ($action === 'proxy' || $action === 'redirect_external') {
    $target = $_GET['url'] ?? $_POST['url'] ?? '';
    
    if (empty($target)) {
        http_response_code(400);
        die('No target URL specified');
    }

    // Handle redirect and proxy requests
    if ($action === 'redirect_external') {
        // Direct redirect to external URL
        header('Location: ' . $target);
        exit;
    } else {
        // Proxy mode - fetch and display content
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $target);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HEADER, true);
        
        $response = curl_exec($ch);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $body = substr($response, $header_size);
        curl_close($ch);
        
        // Output proxied content
        echo $body;
        exit;
    }
}

// LEGACY: Original fetch_resource endpoint
if ($action === 'fetch_resource') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['url'])) {
        $url = $_POST['url'] ?? $_GET['url'] ?? '';
        
        if (empty($url)) {
            echo json_encode(['error' => 'No URL provided']);
            exit;
        }

        $parsed = parse_url($url);
        $scheme = $parsed['scheme'] ?? 'http';
        $host = $parsed['host'] ?? 'localhost';
        $port = $parsed['port'] ?? ($scheme === 'https' ? 443 : 80);
        
        // FILE:// PROTOCOL - Local File Access
        if ($scheme === 'file') {
            $filepath = str_replace('file://', '', $url);
            
            if (file_exists($filepath)) {
                $content = @file_get_contents($filepath);
                echo json_encode([
                    'success' => true,
                    'url' => $url,
                    'http_code' => 200,
                    'content' => $content,
                    'length' => strlen($content),
                    'protocol' => 'file'
                ]);
            } else {
                echo json_encode([
                    'error' => 'File not found or permission denied',
                    'url' => $url
                ]);
            }
            exit;
        }
        
        // HTTP/HTTPS - Remote Resource Fetching
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_NONE);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response !== false && $http_code > 0) {
            echo json_encode([
                'success' => true,
                'url' => $url,
                'http_code' => $http_code,
                'content' => $response,
                'length' => strlen($response),
                'protocol' => 'http'
            ]);
            exit;
        }
        
        // RAW TCP - Port Scanning & Banner Grabbing
        $socket = @fsockopen($host, $port, $errno, $errstr, 3);
        
        if ($socket) {
            stream_set_timeout($socket, 2);
            $banner = stream_get_contents($socket, 4096);
            fclose($socket);
            
            $banner_display = preg_replace('/[\x00-\x08\x0B-\x0C\x0E-\x1F\x7F]/u', '.', $banner);
            
            echo json_encode([
                'success' => true,
                'url' => $url,
                'port_open' => true,
                'port' => $port,
                'banner' => base64_encode($banner),
                'banner_text' => $banner_display,
                'banner_hex' => bin2hex(substr($banner, 0, 256)),
                'length' => strlen($banner),
                'protocol' => 'raw_tcp',
                'note' => 'Non-HTTP service detected - raw TCP banner shown'
            ]);
            exit;
        }
        
        echo json_encode([
            'error' => "Connection failed: $errstr (errno: $errno)",
            'url' => $url,
            'port_open' => false,
            'port' => $port,
            'curl_error' => $error
        ]);
        exit;
    }
}
