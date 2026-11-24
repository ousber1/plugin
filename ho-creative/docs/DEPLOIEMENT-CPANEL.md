# Installation sur hébergement cPanel (mutualisé) – HO CREATIVE

Installation simple, directement sur votre domaine (ex. **https://hocreative.shop/**). Aucun SSH, aucun Composer, aucun Node.js, aucune modification de « racine du document ».

## Avant de commencer (5 minutes, une seule fois)
1. **PHP 8.3** : cPanel → *Logiciel* → **MultiPHP Manager** (ou **Select PHP Version**) → choisissez **PHP 8.3** (ou 8.4) pour le domaine.
   Dans *Select PHP Version → Extensions*, vérifiez que sont cochées : `pdo_mysql`, `mbstring`, `intl`, `gd`, `zip`, `fileinfo`, `curl`, `xml`/`dom`, `openssl`, `bcmath`.
2. **Base de données** : cPanel → **Bases de données MySQL** →
   * *Créer une base* (ex. `moncompte_hoc`),
   * *Ajouter un utilisateur* avec un mot de passe fort (ex. `moncompte_hocuser`),
   * *Ajouter l'utilisateur à la base* → cochez **TOUS LES PRIVILÈGES**.
   Notez les noms **complets** (avec le préfixe de votre compte) et le mot de passe.
3. **SSL** : cPanel → *SSL/TLS Status* → lancez **AutoSSL** pour avoir `https://`.

## Installation en 3 étapes
1. cPanel → **Gestionnaire de fichiers** → ouvrez **`public_html`**. Supprimez la page par défaut de l'hébergeur si elle existe (`index.html`, `default.html`). Ne touchez pas à `cgi-bin` ni `.well-known`.
2. Cliquez **Téléverser**, envoyez `ho-creative-cpanel.zip` dans `public_html`, puis clic droit sur le fichier → **Extraire** → dossier `/public_html`. Vous devez obtenir :
   ```
   public_html/
   ├── index.php
   ├── .htaccess          (fichier caché : Paramètres → « Afficher les fichiers cachés »)
   ├── assets/  images/  docs/  vendor/
   └── hocreative/        ← application (protégée, inaccessible depuis le web)
   ```
   Vous pouvez ensuite supprimer le fichier `.zip`.
3. Ouvrez **https://hocreative.shop/** : l'assistant d'installation s'affiche. Saisissez le nom de la base, l'utilisateur MySQL, son mot de passe (serveur : `localhost`), puis votre compte administrateur (e-mail + mot de passe ≥ 10 caractères). Cliquez **Installer**.

C'est terminé : la boutique est en ligne et le back-office est sur **https://hocreative.shop/admin**. L'assistant se verrouille automatiquement après l'installation (`/install` répond 404).

> Si une page « HO CREATIVE – configuration de l'hébergement » apparaît à la place, elle indique exactement quoi corriger (version PHP, extension manquante, dossier non inscriptible).

## Démarrer le magasin
1. *Admin → Magasin → Rayons* : créez vos rayons (couleur des boutons de caisse).
2. *Articles & stock → Nouvel article* : désignation, prix TTC, TVA, code-barres, stock initial.
3. *Équipe & rôles* : créez vos caissiers (rôle **Caissier**).
4. *Ouvrir la caisse* : saisissez le fond de caisse, vendez, puis clôturez en fin de journée.
Une douchette code-barres USB fonctionne comme un clavier : aucun pilote ni réglage.

## Tâche Cron (recommandée)
cPanel → **Tâches Cron** → réglage commun « **Une fois par minute** » → commande :
```
/usr/local/bin/php /home/VOTRE_UTILISATEUR/public_html/hocreative/artisan schedule:run >/dev/null 2>&1
```
`VOTRE_UTILISATEUR` = votre identifiant cPanel (visible dans cPanel). Si votre hébergeur utilise un autre chemin pour PHP 8.3, demandez-le au support (ex. `/opt/cpanel/ea-php83/root/usr/bin/php`).
Le Cron gère : expiration des devis, nettoyage, alerte de stock bas et **sauvegarde quotidienne**. Le site fonctionne sans, mais ces tâches ne s'exécutent pas.

## E-mails
Par défaut, l'application utilise le serveur d'e-mail de l'hébergement (aucun réglage). Pour une meilleure délivrabilité, créez une boîte (cPanel → *Comptes de messagerie*, ex. `commandes@hocreative.shop`) et renseignez-la dans l'assistant (SMTP) ou plus tard dans `public_html/hocreative/.env` :
```
MAIL_MAILER=smtp
MAIL_HOST=mail.hocreative.shop
MAIL_PORT=587
MAIL_USERNAME=commandes@hocreative.shop
MAIL_PASSWORD=********
MAIL_FROM_ADDRESS=commandes@hocreative.shop
```
Activez SPF et DKIM dans cPanel → *Délivrabilité des e-mails*.

## Taille des fichiers clients
cPanel → *MultiPHP INI Editor* → `upload_max_filesize = 100M`, `post_max_size = 110M`, `memory_limit = 512M`. Ajustez ensuite la limite dans *Admin → Paramètres → Délais & fichiers*.

## Sécurité de cette disposition
Le dossier `hocreative/` (code, `.env`, fichiers clients, sauvegardes) est dans `public_html` mais **bloqué** par trois protections : règle de `public_html/.htaccess`, `hocreative/.htaccess` « Require all denied », et refus de tout fichier caché. Testez après installation : `https://hocreative.shop/hocreative/.env` doit répondre **403**. (Ces règles nécessitent Apache ou LiteSpeed, ce qui est le cas de cPanel.)

Option avancée (encore plus isolée) : déplacer `hocreative/` dans votre dossier personnel, à côté de `public_html` — l'application le détecte automatiquement (adaptez alors le chemin du Cron).

## Dépannage
| Symptôme | Solution |
|---|---|
| Page « configuration de l'hébergement » | Suivez les points listés (souvent : passer en PHP 8.3). |
| Page par défaut de l'hébergeur | Supprimez `public_html/index.html` / `default.html`. |
| Erreur 500 sans texte | Vérifiez que `.htaccess` a bien été extrait ; consultez `public_html/hocreative/storage/logs/` et le *Journal des erreurs* cPanel. |
| « Connexion MySQL impossible » | Noms complets avec préfixe, utilisateur ajouté à la base avec tous les privilèges, serveur `localhost`. |
| Erreur « Fichier trop volumineux » | Voir « Taille des fichiers clients ». |
| E-mails non reçus | Vérifiez les spams, configurez SMTP + SPF/DKIM. |

## Avant l'ouverture au public
Voir la checklist du `README.md` : informations légales (ICE, RC, IF…), TVA validée par votre comptable, vrais prix de chaque produit, zones de livraison, moyens de paiement, CGV, puis suppression des données de démonstration (Cron unique) :
`/usr/local/bin/php /home/VOTRE_UTILISATEUR/public_html/hocreative/artisan hocreative:purge-demo --force`
