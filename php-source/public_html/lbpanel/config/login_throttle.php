<?php
/**
 * Login throttling: six failed passwords per IP within fifteen minutes.
 *
 * File based on purpose — no schema change is needed on the production
 * database, and the counters live outside the web root.
 */

if (!function_exists('wdx_throttle_file')) {
    function wdx_throttle_file(): string
    {
        $dir = dirname(__DIR__, 3) . '/.wdx_data/throttle';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $ip = (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $ip = trim(explode(',', $ip)[0]);
        return $dir . '/' . hash('sha256', $ip) . '.json';
    }

    /** @return array{count:int,first:int} */
    function wdx_throttle_state(): array
    {
        $file = wdx_throttle_file();
        $raw = @file_get_contents($file);
        $data = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($data) || !isset($data['count'], $data['first'])) {
            return ['count' => 0, 'first' => time()];
        }
        return ['count' => (int)$data['count'], 'first' => (int)$data['first']];
    }

    function wdx_throttle_window(): int
    {
        return 15 * 60;
    }

    function wdx_throttle_limit(): int
    {
        return 6;
    }

    /** True when this IP must not be allowed to try another password yet. */
    function wdx_throttle_blocked(): bool
    {
        $state = wdx_throttle_state();
        if (time() - $state['first'] > wdx_throttle_window()) {
            @unlink(wdx_throttle_file());
            return false;
        }
        return $state['count'] >= wdx_throttle_limit();
    }

    function wdx_throttle_remaining(): int
    {
        $state = wdx_throttle_state();
        $left = wdx_throttle_window() - (time() - $state['first']);
        return max(1, (int)ceil($left / 60));
    }

    function wdx_throttle_fail(): void
    {
        $state = wdx_throttle_state();
        if (time() - $state['first'] > wdx_throttle_window()) {
            $state = ['count' => 0, 'first' => time()];
        }
        $state['count']++;
        @file_put_contents(wdx_throttle_file(), json_encode($state), LOCK_EX);
    }

    function wdx_throttle_reset(): void
    {
        @unlink(wdx_throttle_file());
    }
}
