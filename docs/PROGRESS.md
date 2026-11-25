# HO CREATIVE — Suivi d'avancement

Légende : ✅ terminé et testé · 🟡 base posée (schéma prêt, interface à venir) · ⬜ à faire

## Phase 1 — Architecture ✅
- ✅ Architecture et arborescence ([ARCHITECTURE.md](ARCHITECTURE.md))
- ✅ Schéma de base complet : 87 tables, 232 clés étrangères ([DATABASE.md](DATABASE.md))
- ✅ Données de référence (rôles, 84 permissions, unités, TVA, catégories, machines, paramètres de coûts, numérotation)
- ✅ Système de configuration (`config/config.php` généré, paramètres métier en base)
- ✅ Noyau : routeur, contrôleur, vues, requête/réponse, validation FR
- ✅ Sécurité : PDO préparé, CSRF, XSS, sessions sécurisées, verrouillage anti force brute, permissions, audit ([SECURITY.md](SECURITY.md))
- ✅ Numérotation des documents sans trou (verrou de ligne, remise à zéro annuelle)
- ✅ Assistant d'installation en 6 étapes + verrouillage
- ✅ Connexion / déconnexion, page d'état du système

## Phase 2 — Fondations ✅
- ✅ Tableau de bord : CA jour/semaine/mois/année avec variation, dépenses, bénéfice estimé, marge, encours, devis en attente,
  ventes, factures (payées/partielles/impayées), clients, production (en cours/en retard/à livrer), stock faible,
  performance machines, paiements et activité récents ; graphiques CA/dépenses/bénéfice 12 mois, ventes journalières,
  ventes par catégorie et par machine ; top produits, services et clients — tout calculé depuis la base
- ✅ Navigation : barre latérale repliable filtrée par permission, fil d'Ariane, thème clair/sombre mémorisé sur le compte
- ✅ Notifications : cloche avec compteur, menu déroulant AJAX, centre de notifications, lecture individuelle
- ✅ Paramètres (11 onglets) : entreprise + logo, devise, taux de TVA, documents, numérotation (aperçu en direct,
  anti-doublon), POS, stock & production, PDF (cachet, signature), apparence, notifications, e-mail SMTP (test d'envoi), sécurité
- ✅ Utilisateurs : liste/recherche/filtres, création, modification, activation, déverrouillage, réinitialisation
  du mot de passe, suppression logique, permissions particulières par utilisateur ; protection du dernier administrateur
- ✅ Rôles : création, matrice de permissions par module, suppression des rôles personnalisés ; droits appliqués immédiatement
- ✅ Profil, changement de mot de passe, historique des connexions ; mot de passe oublié par e-mail
- ✅ Journal d'activité consultable (filtres, détail avant/après)
- ⏭ Recherche globale : livrée en Phase 3 avec les clients, produits et documents à rechercher

## Phase 3 — Gestion commerciale ✅
- ✅ Clients (CRM) : 8 types, ICE/IF/RC/CIN, recherche, filtres, tri, fiche à onglets (devis, factures, ventes,
  paiements, commandes, avoirs, fichiers, fidélité, prix négociés), solde et crédit autorisé, relevé de compte
  imprimable, WhatsApp, groupes de clients, import / export CSV
- ✅ Fournisseurs : fiche, dettes, historique d'achats, prix des matières par fournisseur, export CSV
- ✅ Catégories (sous-catégories, couleur, icône, ordre)
- ✅ Produits & services : 7 types, prix détail / pro / gros / contrat, prix minimum, coût et marge, TVA,
  code-barres, image, nomenclature matières, stock initial, bouton rapide POS, duplication, import / export CSV
- ✅ Packs : composants, valeur et coût calculés, remise, marge, période de disponibilité, visuel
- ✅ Matières premières : 16 types, laize/longueur, coût moyen pondéré, comparaison des fournisseurs, machines associées
- ✅ Stock : état et valorisation, alertes stock faible (notification), entrées / sorties / pertes / ajustements tracés,
  journal des mouvements filtrable et exportable, inventaires physiques avec validation des écarts
- ✅ Recherche globale (Ctrl+K) : clients, catalogue, packs, matières, fournisseurs, documents par numéro
- ⏭ Commandes, factures, paiements et retours fournisseurs : saisie en Phase 7 (les fiches les affichent déjà)
- ⏭ Transferts entre emplacements : un seul emplacement pour l'instant (le schéma est prêt)
- ⏭ Bon de sortie et relevé en PDF : Phase 5 (le relevé est déjà imprimable / enregistrable en PDF par le navigateur)

## Phase 4 — Point de vente ✅
- ✅ Écran POS plein écran (bureau, tablette, mobile) : recherche, scan de code-barres (Entrée), catégories, boutons rapides,
  images, packs, travaux au m² (largeur × hauteur × exemplaires), article libre avec création au catalogue
- ✅ Prix client automatiques (prix négocié → liste de prix → détail), remise de groupe, remise ligne et globale,
  prix modifiable selon permission, prix minimum, TVA par ligne
- ✅ Encaissement : espèces (rendu de monnaie), carte, virement, chèque, mobile (référence obligatoire), paiement mixte,
  partiel et à crédit (limite de crédit contrôlée)
- ✅ Ventes en attente (mise en attente / reprise / suppression), panier conservé si la page est rechargée
- ✅ Ticket thermique 58 / 80 mm (montant en lettres, ventilation TVA) et bon de vente A4
- ✅ Historique des ventes filtrable et exportable, détail, annulation (même session), retours partiels avec
  remise en stock et remboursement plafonné au montant payé (ou avoir sur le compte client)
- ✅ Caisse : ouverture (fond), dépôts / retraits / transferts, solde théorique, clôture avec comptage des billets et pièces,
  écart, rapports X et Z imprimables, historique des sessions, notification d'écart
- ✅ Stock déduit automatiquement (produits suivis, composants des packs, matières de la nomenclature)
- ✅ Points de fidélité (si activés), alerte de crédit client proche de la limite
- ✅ Facture A4 officielle depuis un ticket et conversion devis → vente : livrées en Phase 5

## Phase 5 — Documents commerciaux ✅
- ✅ Éditeur commun devis / factures : recherche catalogue (prix du client), travaux au m², lignes libres, remise ligne et
  globale, frais de design / pose / transport, TVA par ligne, totaux recalculés en direct **par le serveur**
- ✅ Devis : numérotation, validité, délai et date de livraison, suivi commercial (brouillon, envoyé, en attente, accepté,
  refusé avec motif, expiré automatiquement, converti), historique, duplication, alerte avant expiration, taux de transformation,
  coût et marge estimés (permission)
- ✅ Conversions : devis → facture, facture d'acompte (%), facture de solde (acomptes déduits automatiquement),
  bon de commande, vente au comptoir (ouverte directement au POS) ; bon de commande → facture ; ticket → facture
  (règlements du ticket imputés, réaffectation d'un ticket « comptoir » à un client)
- ✅ Factures : brouillon modifiable, validation = numéro définitif sans trou + coordonnées figées + verrouillage ;
  statuts payée / partielle / impayée / en retard (calculés), échéance, relances (e-mail, WhatsApp, appel, courrier)
- ✅ Annulation d'une facture validée par **avoir total automatique** (jamais de suppression) ; avoirs partiels par ligne
  avec remise en stock, imputés sur la facture, conservés au crédit du client ou remboursés (caisse)
- ✅ Encaissements : imputation sur plusieurs factures et tickets à crédit (manuelle ou automatique, plus anciens d'abord),
  avance client et imputation ultérieure, référence obligatoire (chèque, virement…), caisse alimentée, annulation tracée,
  reçus numérotés, export CSV
- ✅ PDF (dompdf intégré, aucune installation) : devis, factures (standard / acompte / solde), avoirs, reçus, bons de commande ;
  logo, coordonnées et mentions légales (ICE, IF, RC, patente, CNSS), ventilation TVA, montant en lettres, RIB,
  « Bon pour accord », signature, cachet, QR code de vérification, filigrane BROUILLON, pagination
- ✅ Modèles de documents personnalisables (titre, introduction, conditions, pied de page, couleur, éléments affichés)
- ✅ Envoi par e-mail (PDF joint + lien) et par WhatsApp (message pré-rempli + lien), historique des envois
- ✅ Lien de consultation sécurisé (jeton 160 bits, expiration, révocable) : le client consulte et télécharge le PDF
  sans compte et **accepte le devis en ligne** (nom, date, IP enregistrés, notification)
- ✅ Mises à jour du schéma automatiques (migrations) pour les installations existantes
- ⏭ Relevé de compte client en PDF : imprimable / enregistrable en PDF par le navigateur ; PDF dédié en Phase 7 (finances)

## Phase 6 — ERP impression 🟡
- 🟡 Calculateurs (bâche, vinyle, DTF, sublimation, laser, Konica Minolta) · Coûts machines · Production (Kanban, calendrier) · Design · Consommation matières · Maintenance

## Phase 7 — Finances 🟡
- 🟡 Dépenses · Caisse · Dettes clients/fournisseurs · Analyse de marge · Rapports

## Phase 8 — Avancé 🟡
- 🟡 Notifications · Analyses · Sauvegardes/restauration · Journal d'audit · Exports

## Phase 9 — Tests ⬜
## Phase 10 — Déploiement 🟡
- 🟡 Guide d'installation de base ([INSTALLATION.md](INSTALLATION.md)) ; guide complet à venir
