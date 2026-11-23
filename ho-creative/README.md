# HO CREATIVE – boutique web-to-print & gestion d'imprimerie

Application Laravel 13 (PHP 8.3+, MySQL/MariaDB) pour une imprimerie au Maroc. Elle comprend :
1. une **boutique en ligne** (FR par défaut, AR en RTL) avec configurateur et prix en direct, parcours fichier, éditeur en ligne, BAT, devis, panier, commande et espace client ;
2. un **back-office** complet : ventes multicanal (web, comptoir, téléphone, WhatsApp, B2B), CRM, devis, paiements, documents PDF, production Kanban, stock, achats, livraisons, rapports, rôles et audit.

**Hébergement cPanel** : extrayez `ho-creative-cpanel.zip` dans `public_html`, ouvrez le site, suivez l'assistant (voir le guide). L'application est dans `public_html/hocreative/`, bloquée par `.htaccess`.

Guides : [Déploiement cPanel](docs/DEPLOIEMENT-CPANEL.md) · [Moteur de prix](docs/TARIFICATION.md) · [Mises à jour & sauvegardes](docs/MISE-A-JOUR-ET-SAUVEGARDE.md)

## Architecture
| Couche | Choix | Pourquoi |
|---|---|---|
| Framework | Laravel 13, Blade, sessions/cache/queue en base | Fonctionne sur un mutualisé, sans Redis ni worker |
| Front-end | CSS/JS vanilla dans `public/assets` (aucun build), Fabric.js 5.3 (MIT) pour l'éditeur | Aucune dépendance Node en production |
| Prix | `PricingEngine` + `Totals` en BigDecimal, grilles versionnées | Calcul serveur déterministe, historique figé |
| Permissions | spatie/laravel-permission ; 7 rôles ; contrôles d'accès par enregistrement (`App\Support\Access`) | |
| PDF | mPDF (polices DejaVu / XB Riyaz, mise en forme arabe et RTL) | |
| Fichiers | Disque `private` (`storage/app/private`), servis uniquement par `FileController` après vérification | URLs copiées inutilisables par un tiers |
| Planification | `schedule:run` (Cron 1 min) : maintenance, sauvegarde, file d'attente bornée | |

Services clés (`app/Services`) : `OrderService` (chemin unique de création de commande : boutique, comptoir et conversion de devis), `QuoteService`, `ProofService`, `ArtworkService`, `PaymentService` (+ `Payments/CmiGateway`, `SandboxGateway`), `DocumentService`, `ProductionService`, `StockService`, `PurchaseService`, `DeliveryEstimator`, `NumberGenerator`, `ReportService`, `AuditLogger`.

## Flux métier
* **Statuts séparés** : commande (`pending → confirmed → in_production → ready → completed / cancelled`), paiement (`unpaid / partial / paid / partially_refunded / refunded`) et exécution (`unfulfilled → in_production → ready → shipped → delivered / picked_up`). Le client voit un statut simplifié ; coûts et notes internes restent privés.
* **Production** : un travail par ligne, étapes `artwork_pending, prepress_review, proof_pending, approved, printing, finishing, quality_control, packing, ready_for_pickup, shipped, delivered, cancelled`. Le serveur refuse `approved` et au-delà si le BAT courant n'est pas approuvé, et `printing` si la commande n'est pas confirmée.
* **BAT** : versionné ; un nouveau BAT remplace le précédent (« superseded »), remet l'approbation à zéro et repasse le travail en « BAT en attente ». La réponse enregistre version, utilisateur (ou lien invité), date, IP et commentaire.
* **Idempotence** : clés uniques pour paiements (`payments.idempotency_key`), événements prestataire (`payment_events` unique `provider+event_id`), mouvements de stock (`stock_movements.idempotency_key`), coûts (`job_costs.idempotency_key`), documents (`documents.uniq_key`, ex. une seule facture par commande), soumission du checkout (`orders.checkout_key`), conversion de devis (`orders.quote_id` unique). Les formulaires sensibles portent une clé UUID ; un rejeu ne crée rien.
* **Documents** : devis, pro forma, facture, avoir, bon de commande, bon de livraison, reçu. Numérotation annuelle sans trou (`{YYYY}`, `{SEQ:5}`…), contenu figé à l'émission ; les corrections de facture passent par un avoir.
* **Dates** : fuseau `Africa/Casablanca`, jours ouvrés, heure limite, jours fériés, délai produit + délai de zone ; l'estimation signale si elle dépend du BAT.
* **Paiement en ligne** : adaptateur CMI (3D Pay Hosting, hash ver3) – à valider avec la documentation et l'environnement de test CMI avant activation. Seul le rappel serveur signé confirme un paiement ; le retour navigateur est informatif.

## Rôles
| Rôle | Accès principal |
|---|---|
| Propriétaire (`owner`) | Tout |
| Commercial (`sales`) | Clients, devis, commandes, remises, encaissements, documents, livraisons |
| Graphiste / prépresse (`designer`) | Fichiers, contrôles, BAT, modèles |
| Production | Kanban, travaux, consommation matière |
| Stock | Matières, fournisseurs, achats |
| Expédition (`dispatch`) | Livraisons, retraits |
| Comptabilité (`accounting`) | Finances, paiements, remboursements, dépenses, rapports |

CA, marges, dépenses et paramètres sont réservés à `finance.view` / `settings.manage`.

## Rapports (Admin → Rapports, export CSV)
Chaque rapport affiche sa définition. En résumé : **CA HT** = sous-total − remises + livraison des commandes non annulées passées dans la période ; **marge brute** = prix HT des lignes − coûts réels saisis (main-d'œuvre, calage, machine, matières au coût moyen pondéré, sous-traitance, gâche) ; **créances** = total TTC − paiements + remboursements ; **délai de production** = de la commande au passage en prêt/expédié/livré ; **consommation / gâche** = mouvements de stock valorisés au coût du moment ; **dépenses** = saisies dans la période.

## Contenu modifiable sans code
Accueil (sections réordonnables, JSON simple : `hero` {text, cta_label, cta_url}, `promotions` {items:[{title,text,url}]}, `how_it_works` {steps:[{title,text}]}, `quote_cta` {text}, `rich_text` {html} — chaque texte en `{"fr":…,"ar":…}`), pages, navigation, catégories, produits, options et prix, modèles, codes promo, zones de livraison, jours fériés, modèles d'e-mails, identité visuelle (nom, logo, couleurs), mentions légales, numérotation, TVA, moyens de paiement, horaires.

## Développement local
```bash
composer install
cp .env.example .env   # DB_CONNECTION=sqlite ou mysql, APP_ENV=local, APP_DEBUG=true, MAIL_MAILER=log
php artisan key:generate
php artisan hocreative:install --demo      # migrations + rôles + catalogue + compte propriétaire
php artisan serve
php artisan test                            # 28 tests, SQLite en mémoire
DB_CONNECTION=mysql DB_DATABASE=hoc_test DB_USERNAME=… DB_PASSWORD=… php artisan test   # contre MySQL/MariaDB
```
Paiement de test : *Paramètres → Prestataire en ligne = Test* (inactif quand `APP_ENV=production`).

## Données de démonstration
Le catalogue d'exemple (17 produits, 22 catégories, 4 modèles, visuels SVG originaux) porte le badge « Démo » et ses **prix sont fictifs**. `--demo` / la case de l'installateur ajoutent clients, commandes, devis, avis, matières, fournisseurs et dépenses fictifs, marqués « DÉMO » et inclus dans le tableau de bord jusqu'à leur suppression : `php artisan hocreative:purge-demo`. Aucun compte de connexion de démonstration n'est créé.

## Checklist de mise en production (à saisir par le propriétaire)
1. *Paramètres → Société* : raison sociale, adresse, ICE, RC, IF, patente, CNSS, banque, RIB, mentions de bas de document.
2. *Paramètres → Taxes* : taux et règles de TVA **validés par votre comptable** ; le logiciel ne garantit pas la conformité fiscale (format de facture, mentions obligatoires, facturation électronique éventuelle).
3. *Produits → Options & prix* : remplacer tous les prix d'exemple, décocher/retirer le badge démo, publier.
4. *Zones de livraison*, *Jours fériés* (ajouter les fêtes religieuses de l'année), horaires et heure limite.
5. *Paramètres → Contact* : WhatsApp, téléphone, e-mail de réception des demandes de devis.
6. Moyens de paiement actifs ; pour CMI : contrat, `CMI_CLIENT_ID`, `CMI_STORE_KEY`, `CMI_GATEWAY_URL` dans `.env`, tests en environnement CMI puis activation.
7. Pages CGV et confidentialité (loi 09-08 ; déclaration CNDP le cas échéant) – textes fournis à compléter.
8. SMTP, SPF/DKIM ; Cron ; HTTPS ; `APP_DEBUG=false`.
9. `php artisan hocreative:purge-demo`, puis créer les comptes de l'équipe (*Équipe & rôles*).
10. Faire une commande test complète et une restauration de sauvegarde test.

## Limites connues
* Éditeur en ligne : export raster PNG (jusqu'à 300 dpi, RVB) intégré dans un PDF au format exact avec fond perdu ; ce n'est **pas** un PDF vectoriel ni CMJN. Polices limitées aux polices système courantes.
* Contrôles automatiques des fichiers : dimensions et résolution effective des PNG/JPEG, détection CMJN des JPEG, taille de page du PDF quand elle est lisible. Fond perdu réel, profil couleur, polices incorporées et conformité PDF/X sont signalés pour **contrôle manuel** au prépresse, jamais déclarés « prêts ».
* L'interface du back-office est en français (bascule RTL possible) ; la boutique, l'espace client, les e-mails et les PDF sont traduits en arabe.
* Adaptateur CMI non testé contre l'environnement réel CMI (pas d'identifiants dans ce projet).
