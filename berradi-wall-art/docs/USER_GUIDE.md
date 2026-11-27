# Guide d'utilisation — BERRADI WALL ART

Ce guide décrit le travail quotidien dans l'administration (`https://votre-domaine/admin`, ou le chemin choisi
dans `ADMIN_PATH`). Chaque membre de l'équipe ne voit que les menus autorisés par son rôle.

---

## 1. Premiers pas après l'installation

1. **Paramètres → Entreprise** : raison sociale, adresse, téléphone, WhatsApp, logo, et surtout **ICE, RC, IF**
   (obligatoires sur les factures marocaines), banque et RIB.
2. **Paramètres → E-mail** : configurez le SMTP de votre hébergeur (adresse `noreply@votre-domaine`), puis
   « Envoyer un e-mail de test ».
3. **Paramètres → Taxes** : TVA 20 % et « prix TTC » (par défaut), libellé TVA.
4. **Paramètres → Livraison** : tarif par défaut, seuil de livraison gratuite, délais. Les tarifs par ville sont
   dans **Livraison** (§ 11).
5. **Moyens de paiement** : vérifiez les textes FR/AR, le plafond éventuel du paiement à la livraison et, si vous avez
   un contrat CMI, configurez le paiement par carte (§ 12). Puis **Outils SEO** (vérification Google) et **Suivi &
   pixels** (§ 14 et 15).
6. **Supports & cadres** : vérifiez vos prix au m², prix minimum, coût de revient, et prix des cadres au mètre.
   Ces prix alimentent le configurateur photo et la génération des variantes.
7. **Équipe** : créez un compte par personne avec le bon rôle (voir § 17). Ne partagez jamais un compte.
8. **Maintenance** : si vous aviez installé le catalogue de démonstration, supprimez-le avant l'ouverture.
   Consultez le **Contrôle de sécurité** en bas de la page : tous les points doivent être verts.

---

## 2. Le tableau de bord

Il affiche les chiffres réels : chiffre d'affaires (jour, mois, total), reste à encaisser, commandes par étape,
clients, dépenses et bénéfice estimé du mois (la formule est rappelée sous les tuiles). Les graphiques ont un
lien « Voir les données » pour lire les valeurs exactes.

La **cloche** en haut à droite regroupe vos alertes : nouvelles commandes, visuels validés ou à modifier par
le client, devis acceptés, messages du formulaire de contact, stock faible, factures échues, fiches de
production en retard. Cliquer sur une alerte ouvre la commande ou la fiche concernée.

---

## 3. Traiter une commande

**Commandes** liste toutes les commandes avec des onglets par statut. Le parcours normal :

| Statut | Ce qu'il faut faire |
|---|---|
| Nouvelle | Appeler ou écrire au client (bouton WhatsApp), vérifier l'adresse |
| Confirmée | Si l'article contient une photo client : vérifier le visuel (§ 4). Sinon : « Validée pour production » |
| Validation du visuel | Attendre la réponse du client à l'épreuve |
| Validée pour production | Les fiches de production sont créées automatiquement |
| Impression → Encadrement → Contrôle qualité | Avance tout seul quand l'atelier coche les étapes (§ 5) |
| Prête à expédier | Créer l'expédition (§ 6) |
| Expédiée → Livrée | Mettre à jour le statut du colis ; enregistrer l'argent reçu (§ 7) |

* Seuls les changements possibles sont proposés. Cochez « Prévenir le client » pour lui envoyer un e-mail
  (avec votre commentaire).
* **Annuler** remet le stock et le code promo à disposition et arrête la production. L'argent déjà reçu reste
  enregistré : faites ensuite un remboursement.
* **Bon de préparation** : fiche imprimable pour l'atelier et l'emballage.
* **Nouvelle commande** (bouton en haut de la liste) : pour les commandes reçues par téléphone ou WhatsApp —
  produits du catalogue (prix boutique ou prix négocié) et articles sur mesure. Le client est retrouvé par son
  e-mail ou son téléphone.

---

## 4. Visuels clients (photos personnalisées)

Menu **Visuels clients** : chaque article personnalisé à vérifier, avec la résolution d'impression (DPI) en
couleur (rouge = trop faible).

Sur la fiche d'un visuel :

* **Valider pour l'impression** : vous approuvez vous-même.
* **Envoyer l'épreuve au client** : il reçoit un lien pour voir le rendu final et répondre « Je valide » ou
  « Demander une modification ». Si le client n'a pas d'e-mail, copiez le lien affiché (une seule fois) et
  envoyez-le par WhatsApp. Chaque lien ne sert qu'une fois.
* **Demander une modification** : notez le problème (cadrage, qualité…).
* **Fichier d'impression** : déposez le fichier préparé (PDF, TIFF, JPG, PNG) ; il reste privé.
* Quand tous les visuels d'une commande sont validés, elle passe seule en production.

Les photos des clients sont privées : chaque téléchargement de l'original est journalisé. Elles sont supprimées
automatiquement après le délai de conservation (§ 16).

---

## 5. Production

Menu **Production** : tableau par étape (contrôle photo, préparation du fichier, impression, découpe,
encadrement, contrôle qualité, emballage, expédition). Chaque carte est une fiche (un article de commande).

* **Démarrer** puis **Terminer l'étape** : les étapes se font dans l'ordre. La durée est calculée.
* **Terminer l'impression** déduit automatiquement les matières du stock (toile au m², baguette au mètre), à
  condition d'avoir relié chaque support et cadre à une matière du stock dans **Supports & cadres**.
* Sur la fiche : priorité (urgente en rouge), opérateur, machine, échéance, main-d'œuvre, autres frais, mise en
  pause. Le coût réel (matières + temps machine + main-d'œuvre) s'affiche.
* L'étape **Expédition** se termine quand vous enregistrez le colis sur la commande.
* **Machines** : déclarez vos imprimantes et machines avec leur coût horaire.

---

## 6. Expédition

Sur la commande (statut « Prête à expédier ») → **Nouvelle expédition** : transporteur (Amana, CTM…), numéro
de suivi, lien de suivi (https), coût réel payé au transporteur, montant à encaisser (contre-remboursement).
« Marquer comme expédiée » + « Prévenir le client » envoie l'e-mail avec le suivi. Mettez ensuite le colis
« Livré » (la commande passe « Livrée ») ou « Retourné ».

Le coût réel du transporteur est déduit dans le bénéfice estimé.

---

## 7. Paiements

* **Paiement reçu** (sur la commande) : uniquement l'argent réellement reçu — espèces remises par le livreur,
  virement crédité, chèque encaissé. Le montant ne peut pas dépasser le reste à payer.
* **Remboursement** (sur la commande) : jamais plus que ce qui a été encaissé. Les paiements ne se suppriment
  pas ; une erreur se corrige par un remboursement.
* Menu **Paiements** : journal de tous les encaissements et remboursements, avec filtres.

---

## 8. Devis

Menu **Devis → Nouveau devis** : client (fiche existante ou saisie libre, ICE pour une entreprise), lignes en
prix HT (quantités décimales possibles, remise par ligne, TVA par ligne), validité, conditions.

* **Envoyer par e-mail** : le client reçoit un lien pour voir le devis, télécharger le PDF et l'accepter ou le
  refuser en ligne. Vous êtes prévenu par la cloche.
* Modifier un devis déjà envoyé le remet en brouillon : il faut le renvoyer (l'ancien lien ne marche plus).
* Réponse reçue par téléphone : boutons « Accepté » / « Refusé ».
* **Créer la commande** : transforme un devis accepté en commande confirmée, avec exactement le même total.
* Les devis dépassés expirent tout seuls.

---

## 9. Factures et avoirs

* Sur une commande → **Créer la facture** : un brouillon est préparé à partir de la commande (HT, TVA, TTC).
  Complétez si besoin la société et l'ICE du client.
* **Nouvelle facture** (menu Factures) : pour une prestation sans commande (lignes saisies).
* **Émettre** : la facture reçoit son numéro définitif (FAC-2026-000001…), vos mentions légales sont figées et
  une empreinte d'intégrité est calculée. Cochez « Envoyer » pour l'adresser au client.
* **Une facture émise ne se modifie et ne se supprime jamais.** Pour corriger : **Avoir** (total ou partiel,
  avec un motif). Un avoir total annule la facture, qui reste dans vos registres.
* Le badge « Intègre » confirme que la facture n'a pas été modifiée depuis son émission.
* Les PDF sont en français ou en arabe selon la langue choisie ; les factures émises sont archivées.
* Le client retrouve ses factures dans son espace client.

---

## 10. Stock, achats, dépenses, finances

* **Stock** : matières (toiles, papiers, encres, baguettes, emballages) avec unité, seuil d'alerte et coût
  moyen pondéré. Mouvements : entrée (avec coût), sortie, perte, retour, **inventaire physique** (vous saisissez
  la quantité comptée, l'écart est enregistré).
* **Fournisseurs** et **Achats** : brouillon → commandé → **Réceptionner** (les quantités entrent en stock au
  coût d'achat) ; enregistrez les règlements fournisseur.
* **Dépenses** : loyer, salaires, électricité, publicité… en TTC avec la TVA récupérable et le justificatif
  (stocké en privé).
* **Vue financière** : ventes, facturé net, encaissements, décaissements, trésorerie nette mensuelle, TVA
  collectée − déductible (estimation à valider avec votre comptable), créances, dettes fournisseurs.
* **Rapports** : ventes, produits et marges, catégories, clients, villes, canaux, production, stock, dépenses,
  qualité du catalogue — tous exportables en CSV pour Excel.

---

## 11. Boutique : livraison, codes promo, avis, contenu, newsletter

### Livraison (zones et villes)
Menu **Livraison**. Le prix appliqué au panier :

* la ville du client a un tarif → **ce tarif** ; livraison gratuite au-delà du seuil de la ville (s'il est vide :
  celui de la zone) ;
* ville non listée → **tarif général** et seuil général (Paramètres → Livraison, rappelés en haut de la page).

* **Nouvelle zone** : nom (ex. « Moyen Atlas »), prix de référence (proposé pour chaque nouvelle ville), seuil de
  gratuité, délai en jours.
* Dans chaque zone, **Ajouter une ville** : prix TTC, seuil de gratuité (vide = celui de la zone), délai.
  Une ville ne peut avoir qu'un seul tarif : « Fès », « fes » et « FES » sont la même ville.
* Crayon = modifier, corbeille = supprimer. Décochez « Actif » pour suspendre une ville sans la perdre.
* Le client voit le prix dès qu'il choisit sa ville dans le panier ; les commandes déjà passées gardent leur prix.

### Codes promo
Menu **Codes promo → Nouveau code** :

* Type : **pourcentage** (avec plafond en DH conseillé), **montant fixe** ou **livraison gratuite**.
* Conditions facultatives : montant minimum de commande, un produit ou une catégorie, dates de début et de fin,
  nombre total d'utilisations et nombre par client.
* Le code est saisi en majuscules automatiquement ; deux codes ne peuvent pas avoir le même nom.
* Un code déjà utilisé ne se supprime pas (il figure sur des commandes) : il est **désactivé**.
* Une commande annulée rend l'utilisation du code disponible.

### Avis clients
Seul un client **dont la commande est livrée** peut laisser un avis (une note de 1 à 5 et un texte) depuis son
espace client, une fois par produit et par commande. Il porte la mention « Achat vérifié ».

* Menu **Avis clients** : onglet « À modérer » (la cloche vous prévient de chaque nouvel avis).
* **Publier** : l'avis apparaît sur la page du produit et compte dans la note moyenne (affichée aussi dans Google).
* **Mettre en avant** : l'avis apparaît aussi sur la page d'accueil.
* **Retirer** le dépublie ; **Supprimer** l'efface (pour un contenu injurieux ou des données personnelles).
* Ne modifiez jamais le texte d'un client : publiez-le tel quel ou ne le publiez pas.

### Contenu du site
Menu **Contenu boutique**, trois onglets :

* **Bannières** : grande bannière d'accueil (3 au maximum affichées) ou bandeau promo (2). Image large
  (2000 × 900 px conseillé), titre, sous-titre, texte et lien du bouton (une adresse du site comme
  `/collections`, ou un lien `https://`), dates d'affichage facultatives, version arabe.
* **Galerie clients** : photos de tableaux installés chez les clients. Une photo n'est publiée **que si vous cochez
  « Consentement écrit du client obtenu »** — gardez la preuve de cet accord (message WhatsApp, e-mail).
* **Questions fréquentes** : question et réponse en français et en arabe, ordre d'affichage. Elles apparaissent sur
  la page FAQ (et dans les résultats Google sous forme de questions).

Si la version arabe est vide, le texte français est affiché aux visiteurs arabophones.

### Newsletter
Menu **Newsletter** : inscrits (avec leur consentement, la langue et la date) et désinscrits.

* **Exporter (CSV)** pour importer la liste dans votre outil d'e-mailing (Brevo, Mailchimp…). Chaque envoi doit
  contenir un lien de désinscription.
* **Désinscrire** : à faire dès qu'une personne le demande par téléphone ou par message.

---

## 12. Moyens de paiement et paiement par carte (CMI)

Menu **Finance → Moyens de paiement** (Super Admin et Administrator).

* Chaque moyen a un nom, une description (affichée au moment de payer) et des instructions (affichées après la
  commande), **en français et en arabe**. L'ordre d'affichage se règle avec « Ordre ».
* **Montant min. / max.** : par exemple, le paiement à la livraison seulement jusqu'à 3 000 DH. Au-delà, le client ne
  le voit plus (et le serveur le refuse aussi).
* **Ajouter un moyen de paiement** : pour un mode hors ligne (Cash Plus, chèque, paiement en agence…). Un moyen déjà
  utilisé par une commande ne se supprime pas : il est désactivé. Au moins un moyen hors ligne reste toujours actif.
* Le RIB affiché pour le virement vient des **Paramètres → Entreprise**.

### Paiement par carte bancaire (CMI)
1. Signez le contrat e-commerce avec votre banque : elle vous donne l'**identifiant marchand (clientid)** et la
   **clé de magasin (store key)**, et vous demande l'**adresse de notification** affichée sur la page (…/webhooks/payments/cmi).
2. Dans « Carte bancaire (CMI) » : saisissez les deux identifiants, laissez **Mode test** coché, activez, enregistrez.
   La clé est chiffrée et n'est plus jamais affichée (laisser le champ vide la conserve).
3. Faites une commande de test avec les cartes de test fournies par le CMI. Quand tout fonctionne, décochez Mode test.

Pour le client : après « Commander », il arrive sur la page sécurisée du CMI (code 3-D Secure de sa banque). Ses
données de carte ne passent jamais par votre site. **La commande n'est marquée « payée » que lorsque le CMI envoie
sa confirmation signée** directement à votre serveur ; vous êtes prévenu par la cloche. Si le paiement échoue,
le client peut réessayer depuis son suivi de commande (« Payer par carte »).

PayPal n'est pas proposé : PayPal n'accepte pas le dirham marocain.

---

## 13. « Sur mon mur » (aperçu du tableau chez le client)

Sur chaque fiche produit, le bouton **Sur mon mur** ouvre un studio plein écran, à taille réelle :

* **Créatif** : salon, chambre, bureau, salle à manger ou entrée dessinés à l'échelle (canapé de 2,20 m…), couleur du
  mur (ou sa propre couleur), sol, lumière de jour ou de soirée.
* **Ma photo** : le client choisit une photo de son mur.
* **Caméra (pro)** : la caméra du téléphone filme le mur en direct avec le tableau, un indicateur de niveau aide à tenir
  le téléphone droit, puis « Capturer ».
* **Taille réelle** : sur une photo, le client indique la largeur de mur visible, ou mieux, **mesure un objet connu**
  (une feuille A4 collée au mur, une porte de 204 cm) en plaçant deux points : le tableau apparaît alors exactement
  à sa taille.
* Il change de taille directement dans le studio (la fiche produit suit), déplace le tableau du doigt, puis
  **télécharge l'image** ou la **partage** (WhatsApp…).

Les photos et la caméra restent sur l'appareil du client : rien n'est envoyé au site. La caméra demande une
connexion **https** (voir le guide d'installation).

---

## 14. Outils SEO

Menu **Marketing → Outils SEO** :

* **Général** : titre et description par défaut (avec l'aperçu Google et le compteur de caractères : vert = bonne
  longueur, rouge = trop long), codes de vérification **Google Search Console**, Bing, Yandex, Pinterest, domaine
  Facebook (collez seulement le code), compte X/Twitter, **entreprise locale** (type, horaires
  « Mo-Fr 09:00-19:00, Sa 10:00-18:00 », coordonnées GPS, gamme de prix — utilise l'adresse de l'entreprise),
  règles supplémentaires du **robots.txt**. Déclarez le plan du site (…/sitemap.xml) dans Search Console.
* **Pages** : titre et description de l'accueil, de la boutique, des collections, du configurateur, de la FAQ… en
  français et en arabe, avec aperçu Google. Le titre saisi est utilisé tel quel. « Ne pas indexer » retire la page de
  Google et du plan du site. Les produits, catégories et collections ont leurs propres champs SEO (onglet « Arabe & SEO »).
* **Redirections** : quand une adresse change (produit renommé, ancienne page), redirigez l'ancienne vers la nouvelle
  en **301** pour garder le référencement. Une page qui existe n'est jamais redirigée.
* **Erreurs 404** : les adresses demandées qui n'existent pas, les plus visitées en premier, avec leur provenance.
  « Rediriger » prépare la redirection en un clic.
* **Audit** : note SEO du catalogue et liste des corrections par produit / catégorie / collection (titre trop court ou
  trop long, méta-description manquante, version arabe manquante, images sans texte alternatif, titres en double…),
  avec un bouton « Modifier ».

---

## 15. Suivi & pixels publicitaires

Menu **Marketing → Suivi & pixels**. Collez seulement les identifiants (le format est vérifié) :

| Outil | Où trouver l'identifiant |
|---|---|
| Google Analytics 4 | Admin → Flux de données → ID de mesure `G-…` |
| Google Tag Manager | Conteneur `GTM-…` |
| Microsoft Clarity | Paramètres → ID du projet |
| Meta Pixel (Facebook / Instagram) | Gestionnaire d'événements → ID du pixel (chiffres) |
| TikTok Pixel | Ads Manager → Outils → Événements |
| Google Ads | ID `AW-…` + étiquette de la conversion « Achat » |
| Snap Pixel, Pinterest Tag | Leur gestionnaire d'événements |

* **Bandeau cookies** (activé par défaut, recommandé par la loi 09-08 / CNDP) : les outils de mesure attendent
  l'accord « Mesure d'audience », les pixels publicitaires l'accord « Publicité ». Le client peut changer d'avis via
  « Gérer les cookies » en bas de page. Sans aucun identifiant, rien n'est chargé et aucun bandeau n'apparaît.
* **Événements envoyés automatiquement** à chaque outil : vue d'un produit, ajout au panier, début de commande,
  **commande passée avec son montant** (une seule fois par commande), recherche, message de contact, création de
  compte, clic WhatsApp / téléphone. Vous pouvez donc créer vos audiences et mesurer vos campagnes sans réglage technique.
* **Meta Conversions API** : ajoutez le jeton d'accès (Gestionnaire d'événements → Paramètres) pour que chaque
  commande soit aussi envoyée depuis le serveur, même si le client utilise un bloqueur de publicité — uniquement s'il a
  accepté la publicité. Le bouton « Envoyer un événement de test » vérifie la connexion (avec un code de test).
* **Domaines supplémentaires** (avancé) : si vous ajoutez dans Google Tag Manager un autre outil (Hotjar…), indiquez
  ici son adresse https:// pour que la sécurité du site l'autorise.

---

## 16. Sauvegardes, conservation des données, clients

* **Sauvegardes** : sauvegarde automatique chaque nuit (avec la tâche cron) et bouton « Sauvegarder
  maintenant ». Téléchargez régulièrement une sauvegarde sur un support sûr. Restauration : phpMyAdmin → Importer.
  Les fichiers (images, factures PDF) se sauvegardent avec l'outil Sauvegarde de cPanel.
* **Conservation** (même page) : délais après lesquels sont supprimées les photos non commandées, les photos et
  fichiers d'impression des commandes terminées, l'ancien journal d'activité, le contenu des anciens e-mails et
  les paniers abandonnés. Commandes, factures et paiements ne sont jamais supprimés.
* Fiche client → **Exporter ses données** (demande d'accès) ou **Anonymiser** (demande d'effacement).

---

## 17. Rôles de l'équipe

| Rôle | Accès principal |
|---|---|
| Super Admin | Tout (1 à 3 personnes maximum) |
| Administrator | Tout sauf la gestion des rôles |
| Sales Manager | Commandes, clients, devis, paiements (lecture), codes promo, avis, contenu boutique, newsletter, outils SEO, suivi & pixels, zones de livraison, expéditions, rapports |
| Designer | Catalogue, visuels clients (validation), production (lecture) |
| Production Manager | Production, visuels, stock, achats, fournisseurs, expéditions |
| Accountant | Factures, paiements, dépenses, finances, rapports, achats |
| Customer Support | Clients et commandes (lecture), statuts, avis, expéditions |

Les rôles se modifient dans **Rôles & droits**. Désactivez le compte d'une personne qui quitte l'entreprise
(menu Équipe).

---

## 18. En cas de problème

* **Les e-mails ne partent pas** : **Journal des e-mails** (statut, erreur, bouton « Renvoyer ») et vérifiez le SMTP.
* **Une alerte « cron » dans le contrôle de sécurité** : la tâche planifiée ne tourne pas (voir le guide
  d'installation).
* **Une mise à jour est annoncée** sur le tableau de bord : Maintenance → Mettre à jour (faites d'abord une
  sauvegarde).
* **Qui a fait quoi ?** : **Journal d'activité** (connexions, changements de statut, factures, exports…).
