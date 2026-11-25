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

## Phase 3 — Gestion commerciale 🟡
- 🟡 Clients (CRM) · Fournisseurs · Catégories · Produits & services · Listes de prix · Packs · Matières · Stock

## Phase 4 — POS 🟡
- 🟡 Interface POS · Panier · Remises · Taxes · Paiements mixtes/partiels/crédit · Caisse · Tickets 58/80 mm

## Phase 5 — Documents 🟡
- 🟡 Devis · Factures (acompte, solde) · Avoirs · Reçus · PDF · Envoi e-mail / WhatsApp

## Phase 6 — ERP impression 🟡
- 🟡 Calculateurs (bâche, vinyle, DTF, sublimation, laser, Konica Minolta) · Coûts machines · Production (Kanban, calendrier) · Design · Consommation matières · Maintenance

## Phase 7 — Finances 🟡
- 🟡 Dépenses · Caisse · Dettes clients/fournisseurs · Analyse de marge · Rapports

## Phase 8 — Avancé 🟡
- 🟡 Notifications · Analyses · Sauvegardes/restauration · Journal d'audit · Exports

## Phase 9 — Tests ⬜
## Phase 10 — Déploiement 🟡
- 🟡 Guide d'installation de base ([INSTALLATION.md](INSTALLATION.md)) ; guide complet à venir
