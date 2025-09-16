<?php
/**
 * CRM FMS Setup Test Script
 * Tests the clean installation and verifies all components are working
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/security.php';

echo "=======================================\n";
echo "CRM FMS Setup Test\n";
echo "=======================================\n\n";

$tests = [
    'database_connection' => false,
    'tables_exist' => false,
    'sample_data' => false,
    'permissions' => false,
    'api_endpoints' => false
];

try {
    // Test 1: Database Connection
    echo "1. Testing database connection...\n";
    $db = (new Database())->getConnection();
    echo "   ✓ Database connection successful\n";
    $tests['database_connection'] = true;

    // Test 2: Required Tables Exist
    echo "\n2. Checking required tables...\n";
    $requiredTables = [
        'users', 'roles', 'user_roles', 'buildings', 'floors', 'rooms',
        'departments', 'faculties', 'attendance', 'schedules', 'leave_requests',
        'audit_trail', 'system_alerts', 'qr_codes', 'monitoring_settings'
    ];

    $stmt = $db->query("SHOW TABLES");
    $existingTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $missingTables = [];
    foreach ($requiredTables as $table) {
        if (!in_array($table, $existingTables)) {
            $missingTables[] = $table;
        } else {
            echo "   ✓ Table '$table' exists\n";
        }
    }

    if (empty($missingTables)) {
        echo "   ✓ All required tables exist\n";
        $tests['tables_exist'] = true;
    } else {
        echo "   ✗ Missing tables: " . implode(', ', $missingTables) . "\n";
    }

    // Test 3: Sample Data Check
    echo "\n3. Checking sample data...\n";
    $stmt = $db->query("SELECT COUNT(*) as user_count FROM users");
    $userCount = $stmt->fetch()['user_count'];

    $stmt = $db->query("SELECT COUNT(*) as role_count FROM roles");
    $roleCount = $stmt->fetch()['role_count'];

    echo "   - Users: $userCount\n";
    echo "   - Roles: $roleCount\n";

    if ($userCount > 0 && $roleCount > 0) {
        echo "   ✓ Sample data appears to be installed\n";
        $tests['sample_data'] = true;
    } else {
        echo "   ! No sample data found (this is normal for clean installs)\n";
    }

    // Test 4: File Permissions
    echo "\n4. Checking file permissions...\n";
    $checkFiles = [
        '../config/database.php',
        '../config/security.php',
        '../api/auth/me.php',
        '../public/modules/dashboard/index.html'
    ];

    $permissionIssues = [];
    foreach ($checkFiles as $file) {
        $filePath = __DIR__ . '/' . $file;
        if (file_exists($filePath)) {
            if (is_readable($filePath)) {
                echo "   ✓ $file is readable\n";
            } else {
                $permissionIssues[] = "$file is not readable";
            }
        } else {
            $permissionIssues[] = "$file does not exist";
        }
    }

    // Check writable directories
    $writableDirs = ['../logs', '../public/uploads', '../public/qr-codes'];
    foreach ($writableDirs as $dir) {
        $dirPath = __DIR__ . '/' . $dir;
        if (!is_dir($dirPath)) {
            mkdir($dirPath, 0755, true);
            echo "   ✓ Created directory: $dir\n";
        }
        if (is_writable($dirPath)) {
            echo "   ✓ $dir is writable\n";
        } else {
            $permissionIssues[] = "$dir is not writable";
        }
    }

    if (empty($permissionIssues)) {
        echo "   ✓ All file permissions are correct\n";
        $tests['permissions'] = true;
    } else {
        echo "   ✗ Permission issues found:\n";
        foreach ($permissionIssues as $issue) {
            echo "      - $issue\n";
        }
    }

    // Test 5: API Endpoints (basic connectivity)
    echo "\n5. Testing API endpoints...\n";

    // Check if web server is running by testing a simple file access
    $testFileUrl = 'http://localhost/crmfms/public/modules/dashboard/index.html';
    $fileResponse = @file_get_contents($testFileUrl);

    if ($fileResponse === false) {
        echo "   ! Web server not accessible at localhost\n";
        echo "     - Ensure Apache/Nginx is running\n";
        echo "     - Check if CRM FMS is properly configured\n";
        echo "     - Verify the application URL\n";
        $tests['api_endpoints'] = false;
    } else {
        echo "   ✓ Web server is running and accessible\n";

        // Test auth endpoint
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => 'Cookie: PHPSESSID=test_session'
            ]
        ]);

        $authUrl = 'http://localhost/crmfms/api/auth/me.php';
        $authResponse = @file_get_contents($authUrl, false, $context);

        if ($authResponse !== false) {
            $authData = json_decode($authResponse, true);
            if ($authData && isset($authData['success'])) {
                echo "   ✓ Auth API endpoint accessible\n";
                $apiWorking = true;
            } else {
                echo "   ! Auth API returned unexpected response\n";
                $apiWorking = false;
            }
        } else {
            echo "   ! Auth API not accessible\n";
            $apiWorking = false;
        }

        // Test reports endpoint
        $reportsUrl = 'http://localhost/crmfms/api/reports/reports.php?action=latest_date';
        $reportsResponse = @file_get_contents($reportsUrl, false, $context);

        if ($reportsResponse !== false) {
            $reportsData = json_decode($reportsResponse, true);
            if ($reportsData && isset($reportsData['latest_date'])) {
                echo "   ✓ Reports API endpoint accessible\n";
                $apiWorking = isset($apiWorking) ? $apiWorking : true;
            } else {
                echo "   ! Reports API returned unexpected response\n";
                $apiWorking = false;
            }
        } else {
            echo "   ! Reports API not accessible\n";
            $apiWorking = false;
        }

        if (isset($apiWorking) && $apiWorking) {
            echo "   ✓ API endpoints are working\n";
            $tests['api_endpoints'] = true;
        } else {
            echo "   ! Some API endpoints are not working properly\n";
            $tests['api_endpoints'] = false;
        }
    }

} catch (Exception $e) {
    echo "\n❌ Test failed with error: " . $e->getMessage() . "\n";
    echo "Please check your configuration and try again.\n";
    exit(1);
}

// Summary
echo "\n=======================================\n";
echo "TEST SUMMARY\n";
echo "=======================================\n";

$passedTests = 0;
$totalTests = count($tests);

foreach ($tests as $test => $passed) {
    $status = $passed ? '✅ PASS' : '❌ FAIL';
    $testName = ucwords(str_replace('_', ' ', $test));
    echo sprintf("%-25s %s\n", $testName . ':', $status);
    if ($passed) $passedTests++;
}

echo "\nOverall: $passedTests/$totalTests tests passed\n";

if ($passedTests === $totalTests) {
    echo "\n🎉 All tests passed! Your CRM FMS installation appears to be working correctly.\n";
    echo "\nNext steps:\n";
    echo "1. Access the application at: http://localhost/crmfms/public\n";
    echo "2. Log in with admin credentials\n";
    echo "3. Start configuring your system\n";
} else {
    echo "\n⚠️  Some tests failed. Please review the issues above and fix them before proceeding.\n";
    echo "\nCommon solutions:\n";
    echo "- Check database configuration in config/database.php\n";
    echo "- Ensure web server is running and configured correctly\n";
    echo "- Verify file permissions are set correctly\n";
    echo "- Run the setup script again if tables are missing\n";
}

echo "\n=======================================\n";
?>