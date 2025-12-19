# PopCash Rotator - Guide de déploiement

Ce document explique comment le même repo GitHub est utilisé pour 3 déploiements différents sur Coolify.

## Architecture

```
GitHub Repo: PopCash_Rotator
        │
        ├──► metro.miraubolant.com     (Twitter - Blog)
        │
        ├──► vinted.miraubolant.com    (Multi Hero Page)
        │
        └──► stats.miraubolant.com     (Dashboard centralisé)
```

## Instances Coolify

### 1. Twitter - Blog (`metro.miraubolant.com`)

**Objectif** : Rotateur pour les liens partagés sur Twitter/X et blogs

**Configuration Coolify** :
- Source : `https://github.com/Miraubolant/PopCash_Rotator`
- Build Pack : Dockerfile
- Port : 80
- Domaine : `metro.miraubolant.com`

**Variables d'environnement** :
```
ROTATOR_URLS=https://t.co/xxx,https://t.co/yyy,https://t.co/zzz
ROTATOR_TOKEN=Xk9mP2vL8nQwR4tY
```

**URLs actives** : Liens t.co (Twitter shortlinks)

---

### 2. Multi Hero Page (`vinted.miraubolant.com`)

**Objectif** : Rotateur pour les Hero Pages multi-sources (Reddit, Facebook)

**Configuration Coolify** :
- Source : `https://github.com/Miraubolant/PopCash_Rotator`
- Build Pack : Dockerfile
- Port : 80
- Domaine : `vinted.miraubolant.com`

**Variables d'environnement** :
```
ROTATOR_URLS=https://www.reddit.com/user/xxx,https://l.facebook.com/l.php?u=xxx
ROTATOR_TOKEN=Xk9mP2vL8nQwR4tY
```

**URLs actives** : Liens Reddit et Facebook

---

### 3. Dashboard Stats (`stats.miraubolant.com`)

**Objectif** : Dashboard centralisé qui agrège les stats des 2 autres instances

**Configuration Coolify** :
- Source : `https://github.com/Miraubolant/PopCash_Rotator`
- Build Pack : Dockerfile
- Port : 80
- Domaine : `stats.miraubolant.com`

**Variables d'environnement** :
```
ROTATOR_TOKEN=Xk9mP2vL8nQwR4tY
```

**Page principale** : `stats-multi.php?token=xxx`

---

## Flux de données

```
Visiteur PopCash
       │
       ▼
┌──────────────────┐     ┌──────────────────┐
│  metro (Twitter) │     │  vinted (Hero)   │
│                  │     │                  │
│  - Redirection   │     │  - Redirection   │
│  - Log local     │     │  - Log local     │
│  - api-logs.php  │     │  - api-logs.php  │
└────────┬─────────┘     └────────┬─────────┘
         │                        │
         └──────────┬─────────────┘
                    │
                    ▼
         ┌──────────────────┐
         │  stats.miraubolant│
         │                  │
         │  stats-multi.php │
         │  (agrège via API)│
         └──────────────────┘
```

## Points d'accès

| URL | Description |
|-----|-------------|
| `metro.miraubolant.com/` | Redirection Twitter |
| `metro.miraubolant.com/stats.php?token=xxx` | Stats instance Twitter |
| `metro.miraubolant.com/api-logs.php?token=xxx` | API JSON logs Twitter |
| `vinted.miraubolant.com/` | Redirection Hero Page |
| `vinted.miraubolant.com/stats.php?token=xxx` | Stats instance Hero |
| `vinted.miraubolant.com/api-logs.php?token=xxx` | API JSON logs Hero |
| `stats.miraubolant.com/stats-multi.php?token=xxx` | **Dashboard centralisé** |

## Modification des URLs

### Pour changer les URLs d'une instance :

1. Aller dans Coolify → Application concernée
2. Onglet "Environment Variables"
3. Modifier `ROTATOR_URLS` (URLs séparées par des virgules)
4. Redéployer

### Pour ajouter une nouvelle instance :

1. Créer une nouvelle app Coolify avec le même repo
2. Configurer les variables d'environnement
3. Ajouter l'instance dans `stats-multi.php` :

```php
$instances = [
    ['name' => 'Twitter - Blog', 'key' => 'twitter', 'url' => 'https://metro.miraubolant.com/api-logs.php'],
    ['name' => 'Multi Hero Page', 'key' => 'hero', 'url' => 'https://vinted.miraubolant.com/api-logs.php'],
    // Ajouter ici :
    ['name' => 'Nouvelle Instance', 'key' => 'new', 'url' => 'https://new.miraubolant.com/api-logs.php'],
];
```

4. Commit + Push
5. Redéployer stats.miraubolant.com

## Token de sécurité

Le même token est utilisé pour toutes les instances : `Xk9mP2vL8nQwR4tY`

Pour le changer :
1. Modifier `ROTATOR_TOKEN` dans Coolify pour chaque instance
2. Redéployer les 3 apps

## Logs

Les logs sont stockés localement dans chaque conteneur :
- Chemin : `/var/www/html/logs/redirections.log`
- Format : `timestamp | IP | Country | URL | User-Agent`
- Rétention : Aucune limite (surveiller la taille)

## Dépannage

### Le dashboard affiche "Erreur connexion"
- Vérifier que l'instance cible est déployée
- Vérifier que le token est identique sur toutes les instances
- Tester l'API directement : `curl https://metro.miraubolant.com/api-logs.php?token=xxx`

### Les drapeaux ne s'affichent pas
- Vérifier que ip-api.com est accessible
- Le timeout est de 1 seconde, si l'API est lente le pays sera "??"

### L'auto-refresh ne fonctionne pas
- Le refresh est à 5 secondes via JavaScript
- Vérifier que JavaScript est activé dans le navigateur
