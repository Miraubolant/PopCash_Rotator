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

    // Initialiser les compteurs pour chaque URL
    foreach ($urls as $url) {
        $stats[$url] = 0;
    }

    if (!file_exists($log_file)) {
        return ['stats' => $stats, 'total' => 0, 'last_entries' => []];
    }

    $now = time();
    $h24_ago = $now - (24 * 60 * 60);
    $last_entries = [];

    // Lire le fichier ligne par ligne
    $handle = fopen($log_file, 'r');
    if ($handle) {
        while (($line = fgets($handle)) !== false) {
            $line = trim($line);
            if (empty($line)) continue;

            // Parser la ligne: timestamp | IP | URL | User-Agent
            $parts = explode(' | ', $line, 4);
            if (count($parts) < 3) continue;

            $timestamp = strtotime($parts[0]);
            $url = $parts[2];

            // Vérifier si dans les dernières 24h
            if ($timestamp >= $h24_ago) {
                if (isset($stats[$url])) {
                    $stats[$url]++;
                }
                $total++;

                // Garder les 10 dernières entrées
                $last_entries[] = [
                    'time' => $parts[0],
                    'ip' => $parts[1],
                    'url' => $url
                ];
                if (count($last_entries) > 10) {
                    array_shift($last_entries);
                }
            }
        }
        fclose($handle);
    }

    return [
        'stats' => $stats,
        'total' => $total,
        'last_entries' => array_reverse($last_entries)
    ];
}

$data = getStats24h($log_file, $urls);

// Affichage HTML
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stats Rotateur PopCash</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #1a1a2e;
            color: #eee;
            padding: 20px;
            line-height: 1.6;
        }
        .container { max-width: 900px; margin: 0 auto; }
        h1 { color: #00d4ff; margin-bottom: 10px; }
        .subtitle { color: #888; margin-bottom: 30px; }
        .card {
            background: #16213e;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #0f3460;
        }
        .card h2 { color: #00d4ff; margin-bottom: 15px; font-size: 1.2em; }
        .stat-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #0f3460;
        }
        .stat-row:last-child { border-bottom: none; }
        .stat-url {
            word-break: break-all;
            flex: 1;
            color: #a0a0a0;
        }
        .stat-count {
            font-weight: bold;
            color: #00d4ff;
            min-width: 60px;
            text-align: right;
        }
        .total {
            font-size: 2em;
            color: #00d4ff;
            text-align: center;
            padding: 20px;
        }
        .bar {
            background: #0f3460;
            height: 8px;
            border-radius: 4px;
            margin-top: 5px;
            overflow: hidden;
        }
        .bar-fill {
            background: linear-gradient(90deg, #00d4ff, #00ff88);
            height: 100%;
            border-radius: 4px;
            transition: width 0.3s;
        }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #0f3460; }
        th { color: #00d4ff; }
        td { color: #a0a0a0; font-size: 0.9em; }
        .refresh {
            text-align: center;
            margin-top: 20px;
            color: #666;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Rotateur PopCash</h1>
        <p class="subtitle">Statistiques des dernières 24 heures</p>

        <div class="card">
            <div class="total">
                <?= number_format($data['total']) ?> redirections
            </div>
        </div>

        <div class="card">
            <h2>Répartition par URL</h2>
            <?php foreach ($data['stats'] as $url => $count): ?>
                <?php $percent = $data['total'] > 0 ? ($count / $data['total']) * 100 : 0; ?>
                <div class="stat-row">
                    <span class="stat-url"><?= htmlspecialchars($url) ?></span>
                    <span class="stat-count"><?= $count ?> (<?= round($percent, 1) ?>%)</span>
                </div>
                <div class="bar">
                    <div class="bar-fill" style="width: <?= $percent ?>%"></div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (!empty($data['last_entries'])): ?>
        <div class="card">
            <h2>Dernières redirections</h2>
            <table>
                <thead>
                    <tr>
                        <th>Date/Heure</th>
                        <th>IP</th>
                        <th>URL</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['last_entries'] as $entry): ?>
                    <tr>
                        <td><?= htmlspecialchars($entry['time']) ?></td>
                        <td><?= htmlspecialchars($entry['ip']) ?></td>
                        <td><?= htmlspecialchars($entry['url']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <p class="refresh">
            Dernière mise à jour: <?= date('d/m/Y H:i:s') ?>
            <br><a href="?token=<?= htmlspecialchars($provided_token) ?>" style="color: #00d4ff;">Rafraîchir</a>
        </p>
    </div>
</body>
</html>
