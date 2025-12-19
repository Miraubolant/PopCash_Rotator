<?php
/**
 * Configuration du rotateur d'URLs PopCash
 * Modifiez ce tableau pour ajouter/supprimer des URLs
 */

$urls = [
    'https://vintdress.fr/landing1',
    'https://vintdress.fr/landing2',
    'https://funevent.fr/promo',
];

/**
 * Token de sécurité pour accéder aux statistiques
 * Changez cette valeur par un token sécurisé !
 */
$stats_token = 'votre_token_secret_ici';

/**
 * Chemin vers le fichier de logs
 */
$log_file = __DIR__ . '/logs/redirections.log';
