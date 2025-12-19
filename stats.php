<?php
/**
 * Statistiques du rotateur d'URLs PopCash
 * Accès protégé par token: stats.php?token=votre_token
 */

require_once __DIR__ . '/config.php';

// Vérification du token
$provided_token = $_GET['token'] ?? '';

if (empty($provided_token) || !hash_equals($stats_token, $provided_token)) {
    http_response_code(403);
    die('Accès refusé');
}

// Récupérer les logs des dernières 24h
function getStats24h($log_file, $urls) {
    $stats = [];
    $total = 0;
    $ip_stats = [];

    foreach ($urls as $url) {
        $stats[$url] = 0;
    }

    if (!file_exists($log_file)) {
        return ['stats' => $stats, 'total' => 0, 'ip_stats' => []];
    }

    $now = time();
    $h24_ago = $now - (24 * 60 * 60);

    $handle = fopen($log_file, 'r');
    if ($handle) {
        while (($line = fgets($handle)) !== false) {
            $line = trim($line);
            if (empty($line)) continue;

            $parts = explode(' | ', $line, 4);
            if (count($parts) < 3) continue;

            $timestamp = strtotime($parts[0]);
            $ip = $parts[1];
            $url = $parts[2];

            if ($timestamp >= $h24_ago) {
                if (isset($stats[$url])) {
                    $stats[$url]++;
                }
                $total++;

                // Regrouper par IP
                if (!isset($ip_stats[$ip])) {
                    $ip_stats[$ip] = [
                        'count' => 0,
                        'first_seen' => $parts[0],
                        'last_seen' => $parts[0]
                    ];
                }
                $ip_stats[$ip]['count']++;
                $ip_stats[$ip]['last_seen'] = $parts[0];
            }
        }
        fclose($handle);
    }

    // Trier par nombre de visites décroissant
    uasort($ip_stats, fn($a, $b) => $b['count'] - $a['count']);

    return [
        'stats' => $stats,
        'total' => $total,
        'ip_stats' => array_slice($ip_stats, 0, 20, true),
        'unique_ips' => count($ip_stats)
    ];
}

$data = getStats24h($log_file, $urls);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stats Rotateur</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif;
            background: #0d1117;
            color: #e6edf3;
            padding: 24px;
            line-height: 1.5;
            font-size: 14px;
        }
        .container { max-width: 1000px; margin: 0 auto; }
        h1 { font-size: 20px; font-weight: 600; margin-bottom: 16px; }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-box {
            background: #161b22;
            border: 1px solid #30363d;
            border-radius: 6px;
            padding: 16px;
        }
        .stat-value { font-size: 24px; font-weight: 600; color: #58a6ff; }
        .stat-label { font-size: 12px; color: #8b949e; margin-top: 4px; }

        .card {
            background: #161b22;
            border: 1px solid #30363d;
            border-radius: 6px;
            margin-bottom: 16px;
        }
        .card-header {
            padding: 12px 16px;
            border-bottom: 1px solid #30363d;
            font-weight: 600;
            font-size: 14px;
        }
        .card-body { padding: 0; }

        table { width: 100%; border-collapse: collapse; }
        th, td {
            padding: 8px 16px;
            text-align: left;
            border-bottom: 1px solid #21262d;
        }
        th { color: #8b949e; font-weight: 500; font-size: 12px; }
        tr:last-child td { border-bottom: none; }
        tr:hover { background: #1f2428; }

        .url-cell {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            color: #8b949e;
        }
        .count { color: #58a6ff; font-weight: 500; }
        .bar-bg {
            background: #21262d;
            border-radius: 3px;
            height: 6px;
            width: 100px;
        }
        .bar-fill {
            background: #238636;
            height: 6px;
            border-radius: 3px;
        }
        .ip { font-family: ui-monospace, monospace; font-size: 12px; color: #8b949e; }
        .time { color: #8b949e; font-size: 12px; }

        .footer {
            text-align: center;
            margin-top: 24px;
            color: #484f58;
            font-size: 12px;
        }
        .footer a { color: #58a6ff; text-decoration: none; }
        .footer a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <h1>PopCash Rotator Stats</h1>

        <div class="stats-grid">
            <div class="stat-box">
                <div class="stat-value"><?= number_format($data['total']) ?></div>
                <div class="stat-label">Redirections (24h)</div>
            </div>
            <div class="stat-box">
                <div class="stat-value"><?= $data['unique_ips'] ?? 0 ?></div>
                <div class="stat-label">IPs uniques</div>
            </div>
            <div class="stat-box">
                <div class="stat-value"><?= count($urls) ?></div>
                <div class="stat-label">URLs actives</div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Répartition par URL</div>
            <div class="card-body">
                <table>
                    <thead>
                        <tr>
                            <th>URL</th>
                            <th>Hits</th>
                            <th>%</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data['stats'] as $url => $count): ?>
                        <?php $percent = $data['total'] > 0 ? ($count / $data['total']) * 100 : 0; ?>
                        <tr>
                            <td class="url-cell" title="<?= htmlspecialchars($url) ?>"><?= htmlspecialchars($url) ?></td>
                            <td class="count"><?= $count ?></td>
                            <td><?= round($percent, 1) ?>%</td>
                            <td>
                                <div class="bar-bg">
                                    <div class="bar-fill" style="width: <?= $percent ?>%"></div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if (!empty($data['ip_stats'])): ?>
        <div class="card">
            <div class="card-header">Top IPs (20 max)</div>
            <div class="card-body">
                <table>
                    <thead>
                        <tr>
                            <th>IP</th>
                            <th>Visites</th>
                            <th>Première visite</th>
                            <th>Dernière visite</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data['ip_stats'] as $ip => $info): ?>
                        <tr>
                            <td class="ip"><?= htmlspecialchars($ip) ?></td>
                            <td class="count"><?= $info['count'] ?></td>
                            <td class="time"><?= htmlspecialchars($info['first_seen']) ?></td>
                            <td class="time"><?= htmlspecialchars($info['last_seen']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <p class="footer">
            Mis à jour: <?= date('d/m/Y H:i:s') ?> ·
            <a href="?token=<?= htmlspecialchars($provided_token) ?>">Rafraîchir</a>
        </p>
    </div>
</body>
</html>
