<?php

namespace Radle\Modules\Reddit;

class Rate_Limit_Monitor {
    private $option_name = 'radle_rate_limit_data';
    private $debug_mode = false;

    public function is_monitoring_enabled() {
        $this->debug_mode = true;

        $value =  get_option('radle_enable_rate_limit_monitoring' , 'yes');

        $value = (!$value) ? 'yes' : $value;

        return ($value === 'yes') ? true :false;
    }

    public function record_rate_limit_usage($used, $remaining, $reset, $is_failure, $endpoint = '', $payload = '') {
        global $radleLogs;
        if (!$this->is_monitoring_enabled()) {
            return;
        }

        $data = get_option($this->option_name, []);
        $timestamp = current_time('timestamp');

        $new_entry = [
            'timestamp' => $timestamp,
            'used' => intval($used),
            'remaining' => intval($remaining),
            'reset' => intval($reset),
            'is_failure' => $is_failure
        ];

        $data[] = $new_entry;

        // Keep only the last 30 days of data
        $thirty_days_ago = $timestamp - (30 * 24 * 60 * 60);
        $data = array_filter($data, function($item) use ($thirty_days_ago) {
            return $item['timestamp'] > $thirty_days_ago;
        });

        update_option($this->option_name, $data);

        if ($this->debug_mode) {
            $radleLogs->log(sprintf(
                "API Call - Endpoint: %s, Payload: %s, Used: %d, Remaining: %d, Reset: %d, Is Failure: %s",
                $endpoint,
                wp_json_encode($payload),
                $used,
                $remaining,
                $reset,
                $is_failure ? 'Yes' : 'No'
            ), 'rate_limit');
        }
    }

    public function get_rate_limit_data($period = 'last-hour') {

        global $radleLogs;

        if (!$this->is_monitoring_enabled()) {
            return [];
        }

        $data = get_option($this->option_name, []);
        $now = current_time('timestamp');

        switch ($period) {
            case 'last-hour':
                $start_time = $now - 3600;
                $group_by = 60; // Group by minute
                break;
            case '24h':
                $start_time = $now - 86400;
                $group_by = 3600; // Group by hour
                break;
            case '7d':
                $start_time = $now - 604800;
                $group_by = 86400; // Group by day
                break;
            case '30d':
                $start_time = $now - 2592000;
                $group_by = 86400; // Group by day
                break;
            default:
                return [];
        }

        $filtered_data = array_filter($data, function($item) use ($start_time) {
            // Check if 'timestamp' is set in the item.
            if (!isset($item['timestamp'])) {
                global $radleLogs;
                $radleLogs->log("Missing timestamp in data item: " . print_r($item, true), 'rate_limit');
                return false;
            }
            return $item['timestamp'] >= $start_time;
        });

        $grouped_data = $this->group_data($filtered_data, $start_time, $group_by);

        $radleLogs->log("Rate limit data retrieved for period: $period", 'rate_limit');

        return $grouped_data;
    }

    public function group_data($data, $start_time, $group_by) {
        $grouped = array_fill(0, ceil((time() - $start_time) / $group_by), ['calls' => 0, 'breaches' => 0, 'failures' => 0]);

        usort($data, function($a, $b) {
            return $a['timestamp'] <=> $b['timestamp'];
        });

        $window_start = 0;
        $window_used = 0;

        foreach ($data as $item) {
            $index = floor(($item['timestamp'] - $start_time) / $group_by);

            if ($item['timestamp'] >= $window_start + 600) {
                $window_start = $item['timestamp'] - $item['reset'];
                $window_used = 0;
            }

            $new_calls = max(0, $item['used'] - $window_used);
            $window_used = $item['used'];

            if ($index >= 0 && $index < count($grouped)) {
                $grouped[$index]['calls'] += $new_calls;

                if ($item['used'] > 90) {
                    $grouped[$index]['breaches']++;
                }
                if ($item['is_failure']) {
                    $grouped[$index]['failures']++;
                }
            }
        }

        return array_values($grouped);
    }

    private function get_end_time() {
        return current_time('timestamp');
    }

    public function delete_all_data() {
        global $radleLogs;
        $result = delete_option($this->option_name);
        $radleLogs->log("Rate limit data deleted. Result: " . ($result ? 'Success' : 'Failure'), 'rate_limit');
        return $result;
    }

    /**
     * Generates sample rate limit data and saves it to the database.
     */
    public static function generate_and_save_sample_data() {

        global $radleLogs;

        $sample_data = self::generate_sample_data();
        $result = update_option('radle_rate_limit_data', $sample_data);
    }

    /**
     * Generates sample rate limit data for testing.
     *
     * @return array Sample rate limit data
     */
    private static function generate_sample_data() {
        $now = time();
        $thirty_days_ago = $now - (30 * 24 * 60 * 60);
        $data = [];

        $base_usage = 50;
        $day_multiplier = 1;

        for ($timestamp = $thirty_days_ago; $timestamp <= $now; $timestamp += wp_rand(300, 900)) {
            $day_of_week = date('N', $timestamp);
            $day_multiplier = ($day_of_week >= 6) ? 0.7 : 1;

            $hour = date('G', $timestamp);
            $hour_multiplier = 1;
            if ($hour >= 9 && $hour < 18) {
                $hour_multiplier = 1.5;
            } elseif ($hour >= 1 && $hour < 6) {
                $hour_multiplier = 0.3;
            }

            $used = round($base_usage * $day_multiplier * $hour_multiplier * (0.8 + (wp_rand(0, 40) / 100)));

            // Simulate high traffic or rate limit breaches
            $is_failure = false;
            if (wp_rand(1, 100) > 95) {
                $used = wp_rand(90, 130);
                $is_failure = ($used >= 120); // Assume a failure when used is very high
            }

            $remaining = max(0, 1000 - $used);
            $reset = wp_rand(1, 600);

            $data[] = [
                'timestamp' => $timestamp,
                'used' => $used,
                'remaining' => $remaining,
                'reset' => $reset,
                'is_failure' => $is_failure
            ];
        }

        return $data;
    }
}
