<!-- Copilot / AI agent instructions for the BERRADI PRINT PHP app -->
# Instructions pour les agents IA

But rapide
- Monolithe PHP léger pour la gestion d'un service d'impression (front public + zone admin).

Structure clé (à lire avant de modifier)
- `config/app.php` : constantes d'application (URL, devise, uploads, paramètres métier).
- `config/database.php` : `getDB()` — connexion PDO centralisée; toutes les requêtes utilisent PDO.
- `includes/functions.php` : utilitaires partagés (panier, uploadFichier, formatPrix, génération de numéros, notifications, CSRF).
- `pages/` : pages publiques (client-facing).
- `admin/pages/` : pages back-office protégées (auth via `$_SESSION['admin_id']`).
- `admin/includes/` et `includes/` : header/footer et fragments réutilisés.
- `database/schema.sql` : schéma de base de données canonique.

Principes d'implémentation observés
- Authentification admin : session simple (`estConnecte()`) ; vérifiez `admin_id` avant d'afficher `admin/pages/*`.
- DB : utilisez `getDB()` et des requêtes préparées (PDO). Ne pas bricoler la connexion PDO ailleurs.
- Uploads : utilisez `UPLOAD_DIR` + `uploadFichier()` ; contrôles d'extension et taille sont en `config/app.php`.
- Internationalisation/date/monnaie : `APP_LANG`, `dateFormatFr()` et `formatPrix()` sont la norme.
- CSRF : pages POST utilisent `csrfField()` et `verifyCsrf()` ; conserver ce pattern sur les nouveaux formulaires.

Workflows locaux utiles
- Lancer un serveur PHP de développement :

  php -S localhost:8000 -t .

- La configuration DB est codée dans `config/database.php` (valeurs en clair). Pour tests, changez ce fichier ou surchargez via un script d'environnement avant `require`.
- Le schéma initial de la base est dans `database/schema.sql`.

Conventions de code spécifiques au projet
- Pas de framework : code procedural léger + fonctions utilitaires.
- Réutiliser les fonctions existantes dans `includes/functions.php` (ex. `genererNumeroCommande()`, `creerNotification()`).
- Les pages admin s'attendent aux clés POST/GET et utilisent `setFlash()`/`getFlash()` pour les messages utilisateur.

Points d'attention (sécurité & maintenance)
- Les credentials DB sont versionnés ici ; traiter comme source unique pour reproduire l'environnement, mais alerter l'équipe si vous devez les modifier.
- Respecter `ALLOWED_EXTENSIONS` et `MAX_FILE_SIZE` pour éviter régressions upload.
- Les erreurs PDO peuvent déclencher `die()` dans `getDB()` — éviter d'exposer ces messages en production.

Exemples rapides (où regarder)
- Pour ajouter un nouvel champ produit : mise à jour du schéma (`database/schema.sql`), adaptation de `admin/pages/produit_edit.php` et `includes/functions.php` si logique partagée.
- Pour envoyer une notification admin : utiliser `creerNotification()` (définie dans `includes/functions.php`).

Votre sortie
- Créez des PRs petites et ciblées : ajouter une route/page, modifier la base, ou changer l'upload.
- Demandez au mainteneur quand vous touchez les constantes dans `config/app.php` ou le schéma DB.

Questions
- Dites-moi si vous voulez que j'ajoute exemples de patterns de sécurité, ou un checklist de review pour PRs.
