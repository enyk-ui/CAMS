<?php
/**
 * CAMS Server mDNS Registration Helper
 * 
 * For Windows: Install Bonjour Print Services from Apple
 * https://support.apple.com/kb/DL999
 * 
 * This script provides instructions and can broadcast the server's availability.
 */

header('Content-Type: application/json');

// Get server IP
$serverIP = $_SERVER['SERVER_ADDR'] ?? gethostbyname(gethostname());

$response = [
    'success' => true,
    'server_ip' => $serverIP,
    'hostname' => gethostname(),
    'mdns_name' => 'cams-server.local',
    'instructions' => []
];

// Check if running on Windows
if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    $response['platform'] = 'Windows';
    $response['instructions'] = [
        '1. Install Bonjour Print Services from Apple:',
        '   https://support.apple.com/kb/DL999',
        '',
        '2. Or install iTunes (includes Bonjour)',
        '',
        '3. After installation, the server will be discoverable as:',
        '   cams-server.local',
        '',
        '4. Alternative: Use dns-sd command to register:',
        '   dns-sd -R "CAMS Server" _http._tcp local 80',
        '',
        'Current Server IP: ' . $serverIP
    ];
} else {
    $response['platform'] = 'Linux/Mac';
    $response['instructions'] = [
        '1. Install Avahi (Linux):',
        '   sudo apt-get install avahi-daemon',
        '',
        '2. Create service file:',
        '   /etc/avahi/services/cams.service',
        '',
        '3. Or use avahi-publish:',
        '   avahi-publish -s "CAMS Server" _http._tcp 80',
        '',
        'Current Server IP: ' . $serverIP
    ];
}

// Create a simple mDNS advertisement file for reference
$mdnsConfig = <<<XML
<?xml version="1.0" standalone='no'?>
<!DOCTYPE service-group SYSTEM "avahi-service.dtd">
<service-group>
  <name>CAMS Attendance Server</name>
  <service>
    <type>_http._tcp</type>
    <port>80</port>
    <txt-record>path=/api/</txt-record>
  </service>
</service-group>
XML;

$response['avahi_service_config'] = $mdnsConfig;

echo json_encode($response, JSON_PRETTY_PRINT);
?>
