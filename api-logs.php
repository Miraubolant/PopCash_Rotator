<?php
/**
 * API pour exposer les logs (protégée par token)
 * Utilisé par le dashboard centralisé
 */

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Vérification du token
$provided_token = $_GET['token'] ?? '';

if (empty($provided_token) || !hash_equals($stats_token, $provided_token)) {
    http_response_code(403);
    die(json_encode(['error' => 'Accès refusé']));
}

$hours = min(168, max(1, intval($_GET['hours'] ?? 24))); // Max 7 jours
$now = time();
$since = $now - ($hours * 60 * 60);

$logs = [];

if (file_exists($log_file)) {
    $handle = fopen($log_file, 'r');
    if ($handle) {
        while (($line = fgets($handle)) !== false) {
            $line = trim($line);
            if (empty($line)) continue;

            $parts = explode(' | ', $line, 5);
            if (count($parts) < 4) continue;

            $timestamp = strtotime($parts[0]);
            if ($timestamp >= $since) {
                $logs[] = [
                    'time' => $parts[0],
                    'ip' => $parts[1],
                    'country' => count($parts) >= 5 ? $parts[2] : '??',
                    'url' => count($parts) >= 5 ? $parts[3] : $parts[2]
                ];
            }
        }
        fclose($handle);
    }
}

echo json_encode([
    'instance' => $_SERVER['HTTP_HOST'] ?? 'unknown',
    'urls' => $urls,
    'logs' => $logs,
    'count' => count($logs)
]);
