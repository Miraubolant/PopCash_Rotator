# PopCash URL Rotator

Rotateur d'URLs simple et performant pour les campagnes PopCash, avec tracking des stats et géolocalisation.

## Fonctionnalités

- Rotation aléatoire entre plusieurs URLs
- Redirection 302 (temporaire)
- Logs avec IP anonymisée
- Géolocalisation des visiteurs (pays avec drapeaux)
- Dashboard de statistiques (24h)
- Dashboard multi-instances centralisé
- Configuration via variables d'environnement
- Compatible Docker / Coolify

## Structure

```
url-rotator/
├── index.php          # Rotateur principal
├── config.php         # Configuration (URLs, token)
├── stats.php          # Dashboard stats (single instance)
├── stats-multi.php    # Dashboard centralisé (multi-instances)
├── api-logs.php       # API JSON pour les logs
├── Dockerfile         # Image Docker PHP 8.2
└── logs/
    └── redirections.log
```

## Installation

### Option 1 : Serveur classique

1. Uploader les fichiers sur votre serveur
2. Configurer les URLs dans `config.php`
3. Changer le token de sécurité
4. S'assurer que `logs/` est accessible en écriture

### Option 2 : Docker / Coolify

1. Créer une app depuis le repo GitHub
2. Build Pack : **Dockerfile**
3. Port : **80**
4. Configurer les variables d'environnement (voir ci-dessous)

## Configuration

### Via config.php

```php
$urls = [
    'https://example.com/landing1',
    'https://example.com/landing2',
];

$stats_token = 'votre_token_secret';
```

### Via variables d'environnement (recommandé pour multi-instances)

| Variable | Description |
|----------|-------------|
| `ROTATOR_URLS` | URLs séparées par des virgules |
| `ROTATOR_TOKEN` | Token de sécurité pour les stats |

Exemple dans Coolify :
```
ROTATOR_URLS=https://url1.com,https://url2.com,https://url3.com
ROTATOR_TOKEN=MonTokenSecret123
```

## URLs

| Page | URL |
|------|-----|
| Redirection | `https://votre-domaine.com/` |
| Stats | `https://votre-domaine.com/stats.php?token=xxx` |
| Stats multi | `https://votre-domaine.com/stats-multi.php?token=xxx` |
| API logs | `https://votre-domaine.com/api-logs.php?token=xxx` |

## Multi-instances

Pour agréger les stats de plusieurs instances, modifiez le tableau `$instances` dans `stats-multi.php` :

```php
$instances = [
    ['name' => 'Metro', 'url' => 'https://metro.example.com/api-logs.php'],
    ['name' => 'Vinted', 'url' => 'https://vinted.example.com/api-logs.php'],
];
```

## Géolocalisation

La géolocalisation utilise [ip-api.com](http://ip-api.com) (gratuit, sans clé API).
- Limite : 45 requêtes/minute
- Timeout : 1 seconde (n'impacte pas la redirection)

## Sécurité

- IPs anonymisées dans les logs (dernier octet masqué)
- Fichiers de logs protégés par `.htaccess`
- `config.php` inaccessible directement
- Stats protégées par token
- Headers de sécurité (X-Content-Type-Options, X-Frame-Options)

## Format des logs

```
2024-01-15 14:30:22 | 192.168.1.xxx | FR | https://example.com/landing1 | Mozilla/5.0...
```

## Licence

MIT
