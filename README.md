# PopCash URL Rotator

Rotateur d'URLs simple et léger pour les campagnes PopCash.

## Fonctionnalités

- Rotation aléatoire entre plusieurs URLs
- Redirection 302 (temporaire)
- Logs avec IP anonymisée
- Page de statistiques protégée par token
- Compatible Apache/Nginx

## Installation

1. Uploader les fichiers sur votre serveur
2. Modifier `config.php` avec vos URLs
3. Changer le token dans `config.php`
4. S'assurer que le dossier `logs/` est accessible en écriture

## Configuration

Éditez `config.php` :

```php
$urls = [
    'https://example.com/landing1',
    'https://example.com/landing2',
    'https://example.com/promo',
];

$stats_token = 'votre_token_secret';
```

## Utilisation

- **Redirection**: `https://votre-domaine.com/`
- **Statistiques**: `https://votre-domaine.com/stats.php?token=votre_token`

## Déploiement Coolify

Voir les instructions de déploiement dans la section dédiée.

## Sécurité

- Les IPs sont anonymisées dans les logs
- Les fichiers de logs sont protégés par `.htaccess`
- `config.php` n'est pas accessible directement
- La page stats est protégée par token
