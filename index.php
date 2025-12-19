<?php
/**
 * Rotateur d'URLs PopCash
 * Redirige aléatoirement vers une URL de la liste
 */

require_once __DIR__ . '/config.php';

// Vérifier qu'il y a des URLs configurées
if (empty($urls)) {
    http_response_code(503);
    die('Aucune URL configurée');
}

// Sélectionner une URL aléatoire
$selected_url = $urls[array_rand($urls)];

// Anonymiser l'IP (masquer les derniers octets)
function anonymizeIp($ip) {
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        // IPv4: masquer le dernier octet
        return preg_replace('/\.\d+$/', '.xxx', $ip);
    } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        // IPv6: masquer les 4 derniers groupes
        $parts = explode(':', $ip);
        $parts = array_slice($parts, 0, 4);
        return implode(':', $parts) . ':xxxx:xxxx:xxxx:xxxx';
    }
    return 'unknown';
}

// Récupérer l'IP réelle (gestion des proxys)
function getRealIp() {
    $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = $_SERVER[$header];
            // Prendre la première IP si plusieurs (X-Forwarded-For)
            if (strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

// Récupérer le pays via ip-api.com (gratuit, sans clé)
function getCountry($ip) {
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        return 'Local';
    }
    $ctx = stream_context_create(['http' => ['timeout' => 1]]);
    $response = @file_get_contents("http://ip-api.com/json/{$ip}?fields=countryCode", false, $ctx);
    if ($response) {
        $data = json_decode($response, true);
        if (isset($data['countryCode'])) {
            return $data['countryCode'];
        }
    }
    return '??';
}

// Logger la redirection
function logRedirection($url, $log_file) {
    $timestamp = date('Y-m-d H:i:s');
    $real_ip = getRealIp();
    $country = getCountry($real_ip);
    $ip = anonymizeIp($real_ip);
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

    // Limiter la taille du User-Agent pour éviter les abus
    $user_agent = substr($user_agent, 0, 200);

    // Format: timestamp | IP | Country | URL | User-Agent
    $log_entry = sprintf(
        "%s | %s | %s | %s | %s\n",
        $timestamp,
        $ip,
        $country,
        $url,
        $user_agent
    );

    // Créer le dossier logs si nécessaire
    $log_dir = dirname($log_file);
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }

    // Écrire dans le fichier (append)
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

// Logger la redirection
logRedirection($selected_url, $log_file);

// Redirection 302 (temporaire)
header('Location: ' . $selected_url, true, 302);
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
exit;
