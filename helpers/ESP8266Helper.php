<?php
/**
 * ESP8266 Communication Helper
 * Handles HTTP communication with ESP8266 fingerprint sensor
 */

class ESP8266Helper {

    private $device_ip;
    private $timeout = 10; // seconds
    private $port = 80;

    /**
     * Constructor
     * @param string $device_ip IP address of ESP8266
     */
    public function __construct($device_ip) {
        $this->device_ip = $device_ip;
    }

    /**
     * Send HTTP request to ESP8266
     * @param string $endpoint API endpoint
     * @param array $data POST data
     * @return array Response from device
     */
    public function request($endpoint, $data = []) {
        $url = "http://{$this->device_ip}:{$this->port}/{$endpoint}";

        $payload = json_encode($data);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => [
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($payload)
                ],
                'content' => $payload,
                'timeout' => $this->timeout
            ]
        ]);

        try {
            $response = @file_get_contents($url, false, $context);

            if ($response === false) {
                return [
                    'success' => false,
                    'error' => 'Device timeout or unreachable',
                    'url' => $url
                ];
            }

            return json_decode($response, true) ?: [
                'success' => false,
                'error' => 'Invalid JSON response from device'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Start fingerprint enrollment
     * @param int $finger_index Finger number (1-5)
     * @return array Response with enrollment ID or error
     */
    public function startEnrollment($finger_index) {
        return $this->request('api/enroll/start', [
            'finger_index' => $finger_index
        ]);
    }

    /**
     * Get current enrollment status
     * @param int $enrollment_id Enrollment session ID
     * @return array Response with step info
     */
    public function getEnrollmentStatus($enrollment_id) {
        return $this->request('api/enroll/status', [
            'enrollment_id' => $enrollment_id
        ]);
    }

    /**
     * Complete enrollment and get sensor ID
     * @param int $enrollment_id Enrollment session ID
     * @return array Response with sensor_id
     */
    public function completeEnrollment($enrollment_id) {
        return $this->request('api/enroll/complete', [
            'enrollment_id' => $enrollment_id
        ]);
    }

    /**
     * Cancel enrollment
     * @param int $enrollment_id Enrollment session ID
     * @return array Response
     */
    public function cancelEnrollment($enrollment_id) {
        return $this->request('api/enroll/cancel', [
            'enrollment_id' => $enrollment_id
        ]);
    }

    /**
     * Get device health/status
     * @return array Status response
     */
    public function getStatus() {
        return $this->request('api/status');
    }

    /**
     * Set device IP
     * @param string $ip New IP address
     */
    public function setDeviceIP($ip) {
        $this->device_ip = $ip;
    }
}
