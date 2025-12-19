<?php
/**
 * Dashboard centralisé multi-instances
 * Agrège les stats de plusieurs rotateurs
 */

require_once __DIR__ . '/config.php';

// Vérification du token
$provided_token = $_GET['token'] ?? '';

if (empty($provided_token) || !hash_equals($stats_token, $provided_token)) {
    http_response_code(403);
    die('Accès refusé');
}

// Configuration des instances à agréger
$instances = [
    ['name' => 'Metro', 'url' => 'https://metro.miraubolant.com/api-logs.php'],
    ['name' => 'Vinted', 'url' => 'https://vinted.miraubolant.com/api-logs.php'],
];

function countryFlag($code) {
    if (strlen($code) !== 2 || $code === '??') return $code;
    $code = strtoupper($code);
    return mb_chr(127397 + ord($code[0])) . mb_chr(127397 + ord($code[1]));
}

function fetchLogs($url, $token) {
    $ctx = stream_context_create([
        'http' => ['timeout' => 5],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
    ]);
    $response = @file_get_contents($url . '?token=' . urlencode($token) . '&hours=24', false, $ctx);
    if ($response) {
        return json_decode($response, true);
    }
    return null;
}

// Récupérer les logs de toutes les instances
$all_logs = [];
$all_urls = [];
$instance_stats = [];

foreach ($instances as $instance) {
    $data = fetchLogs($instance['url'], $provided_token);
    if ($data) {
        $instance_stats[$instance['name']] = [
            'count' => $data['count'],
            'urls' => $data['urls'] ?? []
        ];
        foreach ($data['urls'] ?? [] as $url) {
            if (!in_array($url, $all_urls)) {
                $all_urls[] = $url;
            }
        }
        foreach ($data['logs'] ?? [] as $log) {
            $log['instance'] = $instance['name'];
            $all_logs[] = $log;
        }
    } else {
        $instance_stats[$instance['name']] = ['count' => 0, 'error' => true];
    }
}

// Calculer les stats agrégées
$total = count($all_logs);
$url_stats = [];
$country_stats = [];
$ip_stats = [];

foreach ($all_urls as $url) {
    $url_stats[$url] = 0;
}

foreach ($all_logs as $log) {
    $url = $log['url'];
    if (!isset($url_stats[$url])) {
        $url_stats[$url] = 0;
    }
    $url_stats[$url]++;

    $country = $log['country'] ?? '??';
    if (!isset($country_stats[$country])) {
        $country_stats[$country] = 0;
    }
    $country_stats[$country]++;

    $ip = $log['ip'];
    if (!isset($ip_stats[$ip])) {
        $ip_stats[$ip] = [
            'count' => 0,
            'country' => $country,
            'instance' => $log['instance'],
            'last_seen' => $log['time']
        ];
    }
    $ip_stats[$ip]['count']++;
    $ip_stats[$ip]['last_seen'] = $log['time'];
}

arsort($url_stats);
arsort($country_stats);
uasort($ip_stats, fn($a, $b) => $b['count'] - $a['count']);
$ip_stats = array_slice($ip_stats, 0, 30, true);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Multi-Instances</title>
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
        .container { max-width: 1200px; margin: 0 auto; }
        h1 { font-size: 20px; font-weight: 600; margin-bottom: 8px; }
        .subtitle { color: #8b949e; margin-bottom: 24px; font-size: 13px; }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-box {
            background: #161b22;
            border: 1px solid #30363d;
            border-radius: 6px;
            padding: 16px;
        }
        .stat-box.error { border-color: #f85149; }
        .stat-value { font-size: 28px; font-weight: 600; color: #58a6ff; }
        .stat-label { font-size: 12px; color: #8b949e; margin-top: 4px; }
        .instance-tag {
            display: inline-block;
            background: #238636;
            color: #fff;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            margin-bottom: 8px;
        }
        .instance-tag.metro { background: #8957e5; }
        .instance-tag.vinted { background: #f78166; }

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
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .card-body { padding: 0; }

        .countries {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            padding: 16px;
        }
        .country-tag {
            background: #21262d;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 13px;
        }
        .country-tag .cnt { color: #58a6ff; font-weight: 500; margin-left: 4px; }

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
            max-width: 400px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            color: #8b949e;
            font-size: 12px;
        }
        .count { color: #58a6ff; font-weight: 500; }
        .bar-bg {
            background: #21262d;
            border-radius: 3px;
            height: 6px;
            width: 120px;
        }
        .bar-fill {
            background: #238636;
            height: 6px;
            border-radius: 3px;
        }
        .ip { font-family: ui-monospace, monospace; font-size: 12px; color: #8b949e; }
        .time { color: #8b949e; font-size: 12px; }
        .flag { font-size: 16px; }

        .footer {
            text-align: center;
            margin-top: 24px;
            color: #484f58;
            font-size: 12px;
        }
        .footer a { color: #58a6ff; text-decoration: none; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Dashboard Multi-Instances</h1>
        <p class="subtitle">Agrégation des stats de toutes les instances PopCash</p>

        <div class="stats-grid">
            <div class="stat-box">
                <div class="stat-value"><?= number_format($total) ?></div>
                <div class="stat-label">Total redirections (24h)</div>
            </div>
            <div class="stat-box">
                <div class="stat-value"><?= count($ip_stats) ?></div>
                <div class="stat-label">IPs uniques</div>
            </div>
            <div class="stat-box">
                <div class="stat-value"><?= count($country_stats) ?></div>
                <div class="stat-label">Pays</div>
            </div>
            <?php foreach ($instance_stats as $name => $stats): ?>
            <div class="stat-box <?= isset($stats['error']) ? 'error' : '' ?>">
                <span class="instance-tag <?= strtolower($name) ?>"><?= $name ?></span>
                <div class="stat-value"><?= isset($stats['error']) ? '?' : number_format($stats['count']) ?></div>
                <div class="stat-label"><?= isset($stats['error']) ? 'Erreur connexion' : 'redirections' ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if (!empty($country_stats)): ?>
        <div class="card">
            <div class="card-header">Pays</div>
            <div class="countries">
                <?php foreach ($country_stats as $country => $cnt): ?>
                <span class="country-tag">
                    <?= countryFlag($country) ?>
                    <?= htmlspecialchars($country) ?>
                    <span class="cnt"><?= $cnt ?></span>
                </span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                Répartition par URL
                <span style="font-weight: normal; color: #8b949e;"><?= count($url_stats) ?> URLs</span>
            </div>
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
                        <?php foreach ($url_stats as $url => $count): ?>
                        <?php $percent = $total > 0 ? ($count / $total) * 100 : 0; ?>
                        <tr>
                            <td class="url-cell" title="<?= htmlspecialchars($url) ?>"><?= htmlspecialchars($url) ?></td>
                            <td class="count"><?= $count ?></td>
                            <td><?= round($percent, 1) ?>%</td>
                            <td>
                                <div class="bar-bg">
                                    <div class="bar-fill" style="width: <?= min(100, $percent) ?>%"></div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if (!empty($ip_stats)): ?>
        <div class="card">
            <div class="card-header">Top IPs (30 max)</div>
            <div class="card-body">
                <table>
                    <thead>
                        <tr>
                            <th>Instance</th>
                            <th>Pays</th>
                            <th>IP</th>
                            <th>Visites</th>
                            <th>Dernière</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ip_stats as $ip => $info): ?>
                        <tr>
                            <td><span class="instance-tag <?= strtolower($info['instance']) ?>"><?= $info['instance'] ?></span></td>
                            <td class="flag"><?= countryFlag($info['country']) ?></td>
                            <td class="ip"><?= htmlspecialchars($ip) ?></td>
                            <td class="count"><?= $info['count'] ?></td>
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
