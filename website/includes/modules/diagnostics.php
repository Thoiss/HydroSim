<?php
if ($action === 'diagnostics') {
    if (!isset($_GET['check'])) {
        echo json_encode(['error' => 'No diagnostic check specified']);
        exit;
    }
    
    $check = $_GET['check'];
    
    switch ($check) {
        case 'db':
            $conn = db_connect();
            if ($conn) {
                // Get current database user
                $current_user = mysqli_query($conn, "SELECT USER(), CURRENT_USER(), DATABASE()");
                $user_info = mysqli_fetch_array($current_user);
                
                // Get database list
                $db_list = [];
                $dbs = mysqli_query($conn, "SHOW DATABASES");
                while ($row = mysqli_fetch_array($dbs)) {
                    $db_list[] = $row[0];
                }
                
                // Get database variables
                $vars = mysqli_query($conn, "SHOW VARIABLES WHERE Variable_name IN ('datadir', 'socket', 'port', 'bind_address', 'version_compile_os')");
                $config = [];
                while ($row = mysqli_fetch_array($vars)) {
                    $config[$row[0]] = $row[1];
                }
                
                echo json_encode([
                    'status' => 'connected',
                    'server_info' => mysqli_get_server_info($conn),
                    'host_info' => mysqli_get_host_info($conn),
                    'protocol_version' => mysqli_get_proto_info($conn),
                    'current_user' => $user_info[0],
                    'effective_user' => $user_info[1],
                    'current_database' => $user_info[2],
                    'databases' => $db_list,
                    'configuration' => $config
                ]);
                $conn->close();
            } else {
                echo json_encode([
                    'error' => 'Connection failed',
                    'errno' => mysqli_connect_errno(),
                    'error_msg' => mysqli_connect_error()
                ]);
            }
            break;
            
        case 'env':
            echo "=== ENVIRONMENT VARIABLES ===\n";
            print_r($_ENV);
            print_r(getenv());
            
            echo "\n=== SERVER VARIABLES ===\n";
            print_r($_SERVER);
            
            echo "\n=== PHP CONFIGURATION ===\n";
            echo "PHP Version: " . phpversion() . "\n";
            echo "PHP User: " . get_current_user() . "\n";
            echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
            echo "Script Filename: " . $_SERVER['SCRIPT_FILENAME'] . "\n";
            echo "Server Software: " . $_SERVER['SERVER_SOFTWARE'] . "\n";
            echo "Server Admin: " . ($_SERVER['SERVER_ADMIN'] ?? 'N/A') . "\n";
            
            echo "\n=== LOADED EXTENSIONS ===\n";
            print_r(get_loaded_extensions());
    }
    exit;
}