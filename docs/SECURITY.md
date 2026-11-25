# HO CREATIVE — Architecture de sécurité

| Menace | Protection | Où |
|---|---|---|
| Injection SQL | PDO avec requêtes préparées natives ; noms de tables/colonnes dynamiques validés par liste blanche (`Database::assertIdentifier`) | `app/Core/Database.php` |
| XSS | Échappement systématique `e()` dans les vues ; Content-Security-Policy ; `X-Content-Type-Options: nosniff` | `helpers.php`, `Response::securityHeaders()` |
| CSRF | Jeton de session (64 hex) vérifié automatiquement par le routeur pour **toute** requête POST ; en-tête `X-CSRF-Token` pour l'AJAX ; jeton renouvelé à la connexion | `Csrf.php`, `Router.php`, `assets/js/app.js` |
| Mots de passe | `password_hash(PASSWORD_DEFAULT)` (bcrypt / argon2 selon PHP), rehash automatique, 10 caractères min. avec majuscules, minuscules et chiffres | `Auth.php` |
| Force brute | Verrouillage du compte après N échecs (5 par défaut, configurable) pendant M minutes (15) ; limite de 30 échecs par IP ; message d'erreur générique ; temps de réponse constant si le compte n'existe pas | `Auth::attempt()`, table `login_attempts` |
| Vol / fixation de session | Cookie `HttpOnly`, `SameSite=Lax`, `Secure` en HTTPS, `use_strict_mode`, régénération à la connexion et toutes les 15 min, empreinte navigateur, expiration d'inactivité (120 min par défaut) | `Session.php` |
| Contrôle d'accès | 6 rôles, 84 permissions `module.action`, surcharges par utilisateur ; middleware `can:` + `Controller::authorize()` ; refus journalisés | `Auth::can()`, `Router.php` |
| Open redirect | Redirections internes uniquement (route validée par regex ou URL commençant par l'URL de l'application) | `AuthController`, `Controller::back()` |
| Fichiers sensibles | `.htaccess` racine + `.htaccess` dans `app/ config/ database/ storage/ docs/` ; extensions `.sql .log .lock .env .bak…` refusées | `.htaccess` |
| Fichiers envoyés | Contrôle MIME réel (`finfo`), liste blanche, taille max., nom aléatoire. Images publiques (logo, cachet, signature) ré-encodées par GD dans `assets/uploads` où PHP est désactivé ; fichiers privés dans `storage/uploads`, servis par contrôleur authentifié | `Uploader.php`, `assets/uploads/.htaccess` |
| Mot de passe oublié | Jeton 256 bits, seule l'empreinte SHA-256 est stockée, validité 60 min, usage unique, réponse identique que le compte existe ou non, 3 demandes/heure/compte et 10/heure/IP | `PasswordResetController.php` |
| Fichiers clients | Stockés dans `storage/uploads/customers/{id}` (hors web), nom aléatoire, servis par `CustomerController::downloadFile` après vérification de la permission, en-têtes `nosniff` + CSP stricte | `CustomerController.php` |
| Coûts confidentiels | Prix d'achat, coûts et marges masqués (écrans, exports CSV, réponses JSON) sans la permission `products.view_cost` | contrôleurs catalogue / stock |
| Export CSV | Neutralisation de l'injection de formules Excel (`=`, `+`, `-`, `@`) | `Csv.php` |
| Point de vente | Le panier envoyé par le navigateur n'est qu'une intention : prix, remises, prix minimum, crédit, stock et totaux sont recalculés et contrôlés côté serveur selon les permissions (`pos.price_override`, `pos.discount`, `pos.below_min_price`, `pos.credit_sale`) ; chaque vente est enregistrée en une transaction ; numéros attribués sous verrou | `SaleService.php` |
| Secrets en base | Mot de passe SMTP chiffré AES-256-GCM avec la clé applicative | `Crypto.php` |
| Révocation des droits | Version globale des permissions : toute modification d'un rôle ou d'un utilisateur s'applique à la requête suivante de chaque session ; un compte désactivé est déconnecté immédiatement | `Auth::permissions()` |
| Sauvegardes (Phase 8) | `storage/backups` interdit au web, téléchargement réservé à `backups.manage`, somme SHA-256 | à venir |
| Fuite d'erreurs | `display_errors = 0` ; erreurs journalisées dans `storage/logs` ; page 500 générique | `bootstrap.php` |
| Clickjacking | `X-Frame-Options: SAMEORIGIN`, `frame-ancestors 'self'` | `Response.php` |
| Réinstallation | `storage/installed.lock` bloque l'assistant ; recommandation de supprimer `install/` | `install/index.php` |
| Traçabilité | `activity_logs` : utilisateur, action, entité, valeurs avant/après (champs sensibles masqués), IP, navigateur | `AuditLog.php` |
| Liens de documents | Jeton aléatoire 160 bits (`random_bytes`), expiration configurable, révocation, accès limité au seul document (consultation, PDF, acceptation d'un devis) ; pages `noindex`, CSP stricte sans script pour le rendu | `ShareService.php`, `PublicDocumentController.php` |
| Documents commerciaux | Lignes, prix, remises, frais et totaux recalculés côté serveur (mêmes règles que le POS) ; facture validée verrouillée et corrigée uniquement par avoir ; brouillon non envoyable ; chaque validation, envoi, annulation, avoir, paiement et acceptation en ligne journalisé | `DocumentService.php`, `InvoiceService.php` |
| Génération PDF | dompdf sans accès réseau (`isRemoteEnabled = false`), sans PHP (`isPhpEnabled = false`), `chroot` limité ; images intégrées en data URI ; dossier `vendor/` interdit au web | `DocumentRenderer.php`, `.htaccess` |
| Intégrité financière | Numérotation sous verrou `SELECT … FOR UPDATE` dans la transaction du document : pas de doublon ni de trou ; documents validés verrouillés (`is_locked`) | `DocumentNumber.php` |

## Rôles par défaut

| Rôle | Périmètre |
|---|---|
| Administrateur | Tout |
| Manager | Tout sauf utilisateurs, paramètres, sauvegardes |
| Caissier | POS, caisse, clients, devis, factures, encaissements |
| Production | Ordres de production, machines, matières, livraisons |
| Designer | Commandes de design, statut production |
| Stock | Catalogue, stock, inventaires, fournisseurs, achats |

Les permissions sont modifiables dans *Administration › Rôles & permissions*, avec des surcharges par utilisateur.
