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
    ['name' => 'Twitter - Blog', 'key' => 'twitter', 'url' => 'https://metro.miraubolant.com/api-logs.php'],
    ['name' => 'Multi Hero Page', 'key' => 'hero', 'url' => 'https://vinted.miraubolant.com/api-logs.php'],
];

// Coordonnées approximatives des pays pour la carte
$country_coords = [
    'FR' => [46.603354, 1.888334], 'US' => [37.09024, -95.712891], 'GB' => [55.378051, -3.435973],
    'DE' => [51.165691, 10.451526], 'ES' => [40.463667, -3.74922], 'IT' => [41.87194, 12.56738],
    'BE' => [50.503887, 4.469936], 'CH' => [46.818188, 8.227512], 'CA' => [56.130366, -106.346771],
    'NL' => [52.132633, 5.291266], 'PT' => [39.399872, -8.224454], 'PL' => [51.919438, 19.145136],
    'BR' => [-14.235004, -51.92528], 'MX' => [23.634501, -102.552784], 'AR' => [-38.416097, -63.616672],
    'AU' => [-25.274398, 133.775136], 'JP' => [36.204824, 138.252924], 'CN' => [35.86166, 104.195397],
    'IN' => [20.593684, 78.96288], 'RU' => [61.52401, 105.318756], 'ZA' => [-30.559482, 22.937506],
    'MA' => [31.791702, -7.09262], 'DZ' => [28.033886, 1.659626], 'TN' => [33.886917, 9.537499],
    'EG' => [26.820553, 30.802498], 'NG' => [9.081999, 8.675277], 'KE' => [-0.023559, 37.906193],
    'AE' => [23.424076, 53.847818], 'SA' => [23.885942, 45.079162], 'TR' => [38.963745, 35.243322],
    'SE' => [60.128161, 18.643501], 'NO' => [60.472024, 8.468946], 'DK' => [56.26392, 9.501785],
    'FI' => [61.92411, 25.748151], 'AT' => [47.516231, 14.550072], 'CZ' => [49.817492, 15.472962],
    'RO' => [45.943161, 24.96676], 'HU' => [47.162494, 19.503304], 'GR' => [39.074208, 21.824312],
    'UA' => [48.379433, 31.16558], 'IE' => [53.41291, -8.24389], 'SG' => [1.352083, 103.819836],
    'HK' => [22.396428, 114.109497], 'TW' => [23.69781, 120.960515], 'KR' => [35.907757, 127.766922],
    'TH' => [15.870032, 100.992541], 'VN' => [14.058324, 108.277199], 'ID' => [-0.789275, 113.921327],
    'MY' => [4.210484, 101.975766], 'PH' => [12.879721, 121.774017], 'NZ' => [-40.900557, 174.885971],
    'CL' => [-35.675147, -71.542969], 'CO' => [4.570868, -74.297333], 'PE' => [-9.189967, -75.015152],
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
            'key' => $instance['key'],
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
            $log['instance_key'] = $instance['key'];
            $all_logs[] = $log;
        }
    } else {
        $instance_stats[$instance['name']] = ['key' => $instance['key'], 'count' => 0, 'error' => true];
    }
}

// Calculer les stats agrégées
$total = count($all_logs);
$url_stats = [];
$country_stats = [];
$ip_stats = [];
$hourly_stats = array_fill(0, 24, 0);

foreach ($all_urls as $url) {
    $url_stats[$url] = ['count' => 0, 'instance' => ''];
}

foreach ($all_logs as $log) {
    $url = $log['url'];
    if (!isset($url_stats[$url])) {
        $url_stats[$url] = ['count' => 0, 'instance' => $log['instance_key']];
    }
    $url_stats[$url]['count']++;
    $url_stats[$url]['instance'] = $log['instance_key'];

    $country = $log['country'] ?? '??';
    if (!isset($country_stats[$country])) {
        $country_stats[$country] = 0;
    }
    $country_stats[$country]++;

    // Stats horaires
    $hour = (int)date('G', strtotime($log['time']));
    $hourly_stats[$hour]++;

    $ip = $log['ip'];
    if (!isset($ip_stats[$ip])) {
        $ip_stats[$ip] = [
            'count' => 0,
            'country' => $country,
            'instance' => $log['instance'],
            'instance_key' => $log['instance_key'],
            'last_seen' => $log['time']
        ];
    }
    $ip_stats[$ip]['count']++;
    $ip_stats[$ip]['last_seen'] = $log['time'];
}

uasort($url_stats, fn($a, $b) => $b['count'] - $a['count']);
arsort($country_stats);
uasort($ip_stats, fn($a, $b) => $b['count'] - $a['count']);
$ip_stats = array_slice($ip_stats, 0, 50, true);

// Préparer les données pour la carte
$map_data = [];
foreach ($country_stats as $code => $count) {
    if (isset($country_coords[$code])) {
        $map_data[] = [
            'code' => $code,
            'count' => $count,
            'lat' => $country_coords[$code][0],
            'lng' => $country_coords[$code][1],
            'flag' => countryFlag($code)
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard PopCash</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
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
        .container { max-width: 1400px; margin: 0 auto; }
        h1 { font-size: 20px; font-weight: 600; margin-bottom: 8px; display: flex; align-items: center; gap: 12px; }
        .live-dot { width: 8px; height: 8px; background: #3fb950; border-radius: 50%; animation: pulse 2s infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
        .subtitle { color: #8b949e; margin-bottom: 24px; font-size: 13px; }
        .refresh-info { color: #8b949e; font-size: 11px; }

        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px; }
        @media (max-width: 900px) { .grid-2 { grid-template-columns: 1fr; } }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 12px;
            margin-bottom: 24px;
        }
        .stat-box {
            background: #161b22;
            border: 1px solid #30363d;
            border-radius: 6px;
            padding: 14px;
        }
        .stat-box.error { border-color: #f85149; }
        .stat-value { font-size: 26px; font-weight: 600; color: #58a6ff; }
        .stat-label { font-size: 11px; color: #8b949e; margin-top: 4px; }
        .stat-trend { font-size: 11px; color: #3fb950; margin-top: 4px; }
        .instance-tag {
            display: inline-block;
            color: #fff;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 10px;
            margin-bottom: 6px;
            white-space: nowrap;
        }
        .instance-tag.twitter { background: #1d9bf0; }
        .instance-tag.hero { background: #f78166; }

        .card {
            background: #161b22;
            border: 1px solid #30363d;
            border-radius: 6px;
            margin-bottom: 16px;
            overflow: hidden;
        }
        .card-header {
            padding: 10px 16px;
            border-bottom: 1px solid #30363d;
            font-weight: 600;
            font-size: 13px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .card-body { padding: 0; }

        #map { height: 300px; background: #0d1117; }
        .leaflet-container { background: #0d1117; }

        .countries {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            padding: 12px;
        }
        .country-tag {
            background: #21262d;
            padding: 3px 8px;
            border-radius: 16px;
            font-size: 12px;
            cursor: pointer;
            transition: background 0.2s;
        }
        .country-tag:hover { background: #30363d; }
        .country-tag .cnt { color: #58a6ff; font-weight: 500; margin-left: 4px; }

        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 6px 12px; text-align: left; border-bottom: 1px solid #21262d; }
        th { color: #8b949e; font-weight: 500; font-size: 11px; cursor: pointer; user-select: none; }
        th:hover { color: #e6edf3; }
        tr:last-child td { border-bottom: none; }
        tr:hover { background: #1f2428; }

        .url-cell {
            max-width: 350px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            color: #8b949e;
            font-size: 11px;
        }
        .count { color: #58a6ff; font-weight: 500; }
        .bar-bg { background: #21262d; border-radius: 3px; height: 5px; width: 80px; }
        .bar-fill { height: 5px; border-radius: 3px; }
        .bar-fill.twitter { background: #1d9bf0; }
        .bar-fill.hero { background: #f78166; }
        .bar-fill.default { background: #238636; }
        .ip { font-family: ui-monospace, monospace; font-size: 11px; color: #8b949e; }
        .time { color: #8b949e; font-size: 11px; }
        .flag { font-size: 14px; }

        .chart-container { padding: 16px; height: 120px; }
        .chart-bar-container { display: flex; align-items: flex-end; gap: 4px; height: 80px; }
        .chart-bar {
            flex: 1;
            background: #238636;
            border-radius: 2px 2px 0 0;
            min-height: 2px;
            position: relative;
        }
        .chart-bar:hover { background: #3fb950; }
        .chart-bar::after {
            content: attr(data-value);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            font-size: 9px;
            color: #8b949e;
            opacity: 0;
            transition: opacity 0.2s;
        }
        .chart-bar:hover::after { opacity: 1; }
        .chart-labels { display: flex; gap: 4px; margin-top: 4px; }
        .chart-labels span { flex: 1; text-align: center; font-size: 9px; color: #484f58; }

        .footer {
            text-align: center;
            margin-top: 24px;
            color: #484f58;
            font-size: 11px;
        }
        .footer a { color: #58a6ff; text-decoration: none; }

        .search-input {
            background: #0d1117;
            border: 1px solid #30363d;
            border-radius: 4px;
            padding: 4px 8px;
            color: #e6edf3;
            font-size: 11px;
            width: 150px;
        }
        .search-input:focus { outline: none; border-color: #58a6ff; }
    </style>
</head>
<body>
    <div class="container">
        <h1>
            <span class="live-dot"></span>
            Dashboard PopCash
        </h1>
        <p class="subtitle">
            Agrégation temps réel des instances
            <span class="refresh-info" id="lastUpdate">Mise à jour: <?= date('H:i:s') ?></span>
        </p>

        <div class="stats-grid">
            <div class="stat-box">
                <div class="stat-value" id="totalCount"><?= number_format($total) ?></div>
                <div class="stat-label">Redirections (24h)</div>
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
                <span class="instance-tag <?= $stats['key'] ?>"><?= $name ?></span>
                <div class="stat-value"><?= isset($stats['error']) ? '?' : number_format($stats['count']) ?></div>
                <div class="stat-label"><?= isset($stats['error']) ? 'Erreur' : 'redirections' ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="grid-2">
            <div class="card">
                <div class="card-header">Carte des visiteurs</div>
                <div id="map"></div>
            </div>
            <div class="card">
                <div class="card-header">Activité par heure</div>
                <div class="chart-container">
                    <div class="chart-bar-container">
                        <?php
                        $max_hourly = max($hourly_stats) ?: 1;
                        foreach ($hourly_stats as $hour => $count):
                            $height = ($count / $max_hourly) * 100;
                        ?>
                        <div class="chart-bar" style="height: <?= max(2, $height) ?>%" data-value="<?= $count ?>"></div>
                        <?php endforeach; ?>
                    </div>
                    <div class="chart-labels">
                        <?php for ($h = 0; $h < 24; $h++): ?>
                        <span><?= $h % 6 === 0 ? sprintf('%02d', $h) : '' ?></span>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($country_stats)): ?>
        <div class="card">
            <div class="card-header">
                Pays
                <span style="font-weight: normal; color: #8b949e;"><?= count($country_stats) ?> pays</span>
            </div>
            <div class="countries">
                <?php foreach ($country_stats as $country => $cnt): ?>
                <span class="country-tag" onclick="zoomToCountry('<?= $country ?>')">
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
                URLs
                <input type="text" class="search-input" placeholder="Rechercher..." id="urlSearch" onkeyup="filterTable('urlTable', this.value)">
            </div>
            <div class="card-body">
                <table id="urlTable">
                    <thead>
                        <tr>
                            <th>Instance</th>
                            <th>URL</th>
                            <th onclick="sortTable('urlTable', 2)">Hits ↕</th>
                            <th>%</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($url_stats as $url => $data): ?>
                        <?php $percent = $total > 0 ? ($data['count'] / $total) * 100 : 0; ?>
                        <tr>
                            <td><span class="instance-tag <?= $data['instance'] ?>"><?= $data['instance'] === 'twitter' ? 'Twitter' : 'Hero' ?></span></td>
                            <td class="url-cell" title="<?= htmlspecialchars($url) ?>"><?= htmlspecialchars($url) ?></td>
                            <td class="count"><?= $data['count'] ?></td>
                            <td><?= round($percent, 1) ?>%</td>
                            <td>
                                <div class="bar-bg">
                                    <div class="bar-fill <?= $data['instance'] ?>" style="width: <?= min(100, $percent * 2) ?>%"></div>
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
            <div class="card-header">
                Top IPs
                <input type="text" class="search-input" placeholder="Filtrer par pays..." id="ipSearch" onkeyup="filterTable('ipTable', this.value)">
            </div>
            <div class="card-body">
                <table id="ipTable">
                    <thead>
                        <tr>
                            <th>Instance</th>
                            <th>Pays</th>
                            <th>IP</th>
                            <th onclick="sortTable('ipTable', 3)">Visites ↕</th>
                            <th>Dernière</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ip_stats as $ip => $info): ?>
                        <tr data-country="<?= $info['country'] ?>">
                            <td><span class="instance-tag <?= $info['instance_key'] ?>"><?= $info['instance_key'] === 'twitter' ? 'Twitter' : 'Hero' ?></span></td>
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
            Auto-refresh: 5s ·
            <a href="?token=<?= htmlspecialchars($provided_token) ?>">Recharger</a>
        </p>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // Carte
        const mapData = <?= json_encode($map_data) ?>;
        const map = L.map('map', {
            center: [30, 0],
            zoom: 2,
            zoomControl: false,
            attributionControl: false
        });

        L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
            maxZoom: 19
        }).addTo(map);

        const markers = {};
        mapData.forEach(d => {
            const size = Math.min(40, Math.max(15, d.count / 2));
            const marker = L.circleMarker([d.lat, d.lng], {
                radius: size / 2,
                fillColor: '#58a6ff',
                color: '#58a6ff',
                weight: 1,
                opacity: 0.8,
                fillOpacity: 0.4
            }).addTo(map);
            marker.bindPopup(`${d.flag} ${d.code}: <strong>${d.count}</strong> visites`);
            markers[d.code] = marker;
        });

        function zoomToCountry(code) {
            if (markers[code]) {
                map.setView(markers[code].getLatLng(), 5);
                markers[code].openPopup();
            }
        }

        // Tri de tableau
        function sortTable(tableId, col) {
            const table = document.getElementById(tableId);
            const rows = Array.from(table.querySelectorAll('tbody tr'));
            const isAsc = table.dataset.sortDir !== 'asc';
            table.dataset.sortDir = isAsc ? 'asc' : 'desc';

            rows.sort((a, b) => {
                const aVal = parseInt(a.cells[col].textContent) || 0;
                const bVal = parseInt(b.cells[col].textContent) || 0;
                return isAsc ? aVal - bVal : bVal - aVal;
            });

            const tbody = table.querySelector('tbody');
            rows.forEach(row => tbody.appendChild(row));
        }

        // Filtre de tableau
        function filterTable(tableId, query) {
            const table = document.getElementById(tableId);
            const rows = table.querySelectorAll('tbody tr');
            query = query.toLowerCase();

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(query) ? '' : 'none';
            });
        }

        // Auto-refresh toutes les 5 secondes
        setTimeout(() => location.reload(), 5000);
    </script>
</body>
</html>
