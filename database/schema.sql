-- =============================================
-- BERRADI PRINT - Schéma de Base de Données
-- Système de Gestion de Services d'Impression
-- =============================================

CREATE DATABASE IF NOT EXISTS lobefuthkh_print CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE lobefuthkh_print;

-- =============================================
-- Table: Administrateurs
-- =============================================
CREATE TABLE IF NOT EXISTS admins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    telephone VARCHAR(20),
    mot_de_passe VARCHAR(255) NOT NULL,
    role ENUM('super_admin', 'admin', 'operateur') DEFAULT 'operateur',
    photo VARCHAR(255) DEFAULT NULL,
    actif TINYINT(1) DEFAULT 1,
    derniere_connexion DATETIME DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =============================================
-- Table: Clients
-- =============================================
CREATE TABLE IF NOT EXISTS clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    prenom VARCHAR(100) NOT NULL,
    email VARCHAR(255) DEFAULT NULL,
    telephone VARCHAR(20) NOT NULL,
    adresse TEXT,
    ville VARCHAR(100),
    code_postal VARCHAR(10),
    type_client ENUM('particulier', 'entreprise') DEFAULT 'particulier',
    nom_entreprise VARCHAR(200) DEFAULT NULL,
    ice VARCHAR(20) DEFAULT NULL, -- Identifiant Commun de l'Entreprise (Maroc)
    notes TEXT,
    total_commandes INT DEFAULT 0,
    total_depense DECIMAL(12,2) DEFAULT 0.00,
    actif TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =============================================
-- Table: Catégories de Services
-- =============================================
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(200) NOT NULL,
    slug VARCHAR(200) NOT NULL UNIQUE,
    description TEXT,
    icone VARCHAR(50) DEFAULT 'bi-printer',
    image VARCHAR(255) DEFAULT NULL,
    ordre INT DEFAULT 0,
    actif TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =============================================
-- Table: Produits / Services
-- =============================================
CREATE TABLE IF NOT EXISTS produits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    categorie_id INT NOT NULL,
    nom VARCHAR(300) NOT NULL,
    slug VARCHAR(300) NOT NULL,
    description TEXT,
    description_courte VARCHAR(500),
    prix_base DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    prix_unitaire DECIMAL(10,2) DEFAULT NULL,
    unite VARCHAR(50) DEFAULT 'pièce', -- pièce, m², mètre linéaire, etc.
    quantite_min INT DEFAULT 1,
    delai_production VARCHAR(100) DEFAULT '24-48h',
    image VARCHAR(255) DEFAULT NULL,
    images_galerie TEXT, -- JSON array d'images
    options JSON DEFAULT NULL, -- Options configurables (taille, papier, finition, etc.)
    specifications JSON DEFAULT NULL,
    populaire TINYINT(1) DEFAULT 0,
    actif TINYINT(1) DEFAULT 1,
    ordre INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (categorie_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =============================================
-- Table: Options de Produit
-- =============================================
CREATE TABLE IF NOT EXISTS produit_options (
    id INT AUTO_INCREMENT PRIMARY KEY,
    produit_id INT NOT NULL,
    nom_option VARCHAR(100) NOT NULL, -- Ex: Taille, Papier, Finition
    type_option ENUM('select', 'radio', 'checkbox', 'text', 'number', 'file') DEFAULT 'select',
    valeurs JSON NOT NULL, -- [{valeur: "A4", prix_supplement: 0}, {valeur: "A3", prix_supplement: 5}]
    obligatoire TINYINT(1) DEFAULT 1,
    ordre INT DEFAULT 0,
    FOREIGN KEY (produit_id) REFERENCES produits(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =============================================
-- Table: Commandes
-- =============================================
CREATE TABLE IF NOT EXISTS commandes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero_commande VARCHAR(20) NOT NULL UNIQUE,
    client_id INT DEFAULT NULL,
    -- Infos client (dupliquées pour historique)
    client_nom VARCHAR(200) NOT NULL,
    client_telephone VARCHAR(20) NOT NULL,
    client_email VARCHAR(255) DEFAULT NULL,
    client_adresse TEXT NOT NULL,
    client_ville VARCHAR(100) NOT NULL,
    client_code_postal VARCHAR(10) DEFAULT NULL,
    -- Montants
    sous_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    remise_montant DECIMAL(12,2) DEFAULT 0.00,
    remise_pourcentage DECIMAL(5,2) DEFAULT 0.00,
    frais_livraison DECIMAL(10,2) DEFAULT 0.00,
    tva_montant DECIMAL(12,2) DEFAULT 0.00,
    total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    -- Paiement
    methode_paiement ENUM('cash_on_delivery', 'especes', 'virement', 'cheque') DEFAULT 'cash_on_delivery',
    statut_paiement ENUM('en_attente', 'paye', 'partiel', 'rembourse') DEFAULT 'en_attente',
    montant_paye DECIMAL(12,2) DEFAULT 0.00,
    -- Statut commande
    statut ENUM('nouvelle', 'confirmee', 'en_production', 'prete', 'en_livraison', 'livree', 'annulee') DEFAULT 'nouvelle',
    -- Livraison
    type_livraison ENUM('livraison', 'retrait') DEFAULT 'livraison',
    date_livraison_souhaitee DATE DEFAULT NULL,
    date_livraison_reelle DATETIME DEFAULT NULL,
    -- Notes
    notes_client TEXT,
    notes_internes TEXT,
    -- Fichiers
    fichiers_client JSON DEFAULT NULL,
    -- Suivi
    source ENUM('site', 'telephone', 'whatsapp', 'direct', 'autre') DEFAULT 'site',
    admin_id INT DEFAULT NULL,
    priorite ENUM('normale', 'urgente', 'express') DEFAULT 'normale',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL,
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =============================================
-- Table: Lignes de Commande
-- =============================================
CREATE TABLE IF NOT EXISTS commande_lignes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    commande_id INT NOT NULL,
    produit_id INT DEFAULT NULL,
    designation VARCHAR(500) NOT NULL,
    description TEXT,
    options_selectionnees JSON DEFAULT NULL,
    quantite INT NOT NULL DEFAULT 1,
    prix_unitaire DECIMAL(10,2) NOT NULL,
    prix_total DECIMAL(12,2) NOT NULL,
    fichier_client VARCHAR(255) DEFAULT NULL,
    notes TEXT,
    statut_production ENUM('en_attente', 'en_cours', 'termine') DEFAULT 'en_attente',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (commande_id) REFERENCES commandes(id) ON DELETE CASCADE,
    FOREIGN KEY (produit_id) REFERENCES produits(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =============================================
-- Table: Historique des Commandes
-- =============================================
CREATE TABLE IF NOT EXISTS commande_historique (
    id INT AUTO_INCREMENT PRIMARY KEY,
    commande_id INT NOT NULL,
    statut_ancien VARCHAR(50),
    statut_nouveau VARCHAR(50) NOT NULL,
    commentaire TEXT,
    admin_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (commande_id) REFERENCES commandes(id) ON DELETE CASCADE,
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =============================================
-- Table: Devis
-- =============================================
CREATE TABLE IF NOT EXISTS devis (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero_devis VARCHAR(20) NOT NULL UNIQUE,
    client_id INT DEFAULT NULL,
    client_nom VARCHAR(200) NOT NULL,
    client_telephone VARCHAR(20) NOT NULL,
    client_email VARCHAR(255) DEFAULT NULL,
    lignes JSON NOT NULL,
    sous_total DECIMAL(12,2) NOT NULL,
    tva_montant DECIMAL(12,2) DEFAULT 0.00,
    total DECIMAL(12,2) NOT NULL,
    statut ENUM('brouillon', 'envoye', 'accepte', 'refuse', 'expire') DEFAULT 'brouillon',
    date_validite DATE,
    notes TEXT,
    commande_id INT DEFAULT NULL,
    admin_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE SET NULL,
    FOREIGN KEY (commande_id) REFERENCES commandes(id) ON DELETE SET NULL,
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =============================================
-- Table: Dépenses
-- =============================================
CREATE TABLE IF NOT EXISTS depenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    categorie_depense VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    montant DECIMAL(12,2) NOT NULL,
    date_depense DATE NOT NULL,
    fournisseur VARCHAR(200) DEFAULT NULL,
    reference VARCHAR(100) DEFAULT NULL,
    admin_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- =============================================
-- Table: Paramètres
-- =============================================
CREATE TABLE IF NOT EXISTS parametres (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cle VARCHAR(100) NOT NULL UNIQUE,
    valeur TEXT,
    groupe VARCHAR(50) DEFAULT 'general',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =============================================
-- Table: Messages / Notifications
-- =============================================
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(50) NOT NULL,
    titre VARCHAR(255) NOT NULL,
    message TEXT,
    lien VARCHAR(500) DEFAULT NULL,
    lue TINYINT(1) DEFAULT 0,
    admin_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- =============================================
-- Table: Villes de livraison
-- =============================================
CREATE TABLE IF NOT EXISTS villes_livraison (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL,
    frais_livraison DECIMAL(10,2) NOT NULL DEFAULT 30.00,
    delai_livraison VARCHAR(50) DEFAULT '24-48h',
    actif TINYINT(1) DEFAULT 1
) ENGINE=InnoDB;

-- =============================================
-- DONNÉES INITIALES
-- =============================================

-- Admin par défaut (mot de passe: admin123)
INSERT IGNORE INTO admins (nom, prenom, email, telephone, mot_de_passe, role) VALUES
('Berradi', 'Admin', 'admin@berradiprint.ma', '+212600000000', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin');

-- Catégories de services d'impression
INSERT IGNORE INTO categories (nom, slug, description, icone, ordre) VALUES
('Cartes de Visite', 'cartes-de-visite', 'Cartes de visite professionnelles, différents formats et finitions', 'bi-person-vcard', 1),
('Flyers & Dépliants', 'flyers-depliants', 'Flyers, dépliants et prospectus pour votre communication', 'bi-file-earmark-richtext', 2),
('Affiches & Posters', 'affiches-posters', 'Affiches grand format et posters publicitaires', 'bi-image', 3),
('Banderoles & Bâches', 'banderoles-baches', 'Banderoles PVC, bâches publicitaires et kakémonos', 'bi-flag', 4),
('Stickers & Autocollants', 'stickers-autocollants', 'Stickers personnalisés, autocollants et étiquettes', 'bi-stickies', 5),
('Impressions Numériques', 'impressions-numeriques', 'Impressions documents, copies couleur et noir et blanc', 'bi-printer', 6),
('Tampons & Cachets', 'tampons-cachets', 'Tampons encreurs personnalisés et cachets d\'entreprise', 'bi-stamp', 7),
('Reliure & Finition', 'reliure-finition', 'Reliure, plastification, découpe et finitions', 'bi-book', 8),
('Signalétique', 'signaletique', 'Panneaux, enseignes et signalétique intérieure/extérieure', 'bi-signpost-split', 9),
('Textile & Objets', 'textile-objets', 'Impression sur t-shirts, casquettes, mugs et objets publicitaires', 'bi-bag', 10),
('Papeterie d\'Entreprise', 'papeterie-entreprise', 'En-têtes, enveloppes, chemises à rabat, facturiers', 'bi-envelope-paper', 11),
('Grands Formats', 'grands-formats', 'Roll-up, X-banner, stands et PLV', 'bi-easel', 12);

-- Produits d'exemple
INSERT IGNORE INTO produits (categorie_id, nom, slug, description, description_courte, prix_base, prix_unitaire, unite, quantite_min, delai_production, populaire, ordre) VALUES
-- Cartes de visite
(1, 'Carte de Visite Standard', 'carte-visite-standard', 'Carte de visite 85x55mm sur papier couché 350g, impression recto verso en quadrichromie.', 'Carte 85x55mm - Couché 350g - Recto/Verso', 150.00, 1.50, 'lot de 100', 1, '24-48h', 1, 1),
(1, 'Carte de Visite Premium', 'carte-visite-premium', 'Carte de visite premium avec pelliculage mat ou brillant, coins arrondis disponibles.', 'Premium - Pelliculage - Coins arrondis', 250.00, 2.50, 'lot de 100', 1, '48-72h', 1, 2),
(1, 'Carte de Visite Luxe', 'carte-visite-luxe', 'Carte de visite sur papier texturé 400g avec dorure à chaud ou vernis sélectif.', 'Luxe - Dorure/Vernis - Papier texturé', 450.00, 4.50, 'lot de 100', 1, '3-5 jours', 0, 3),

-- Flyers
(2, 'Flyer A5', 'flyer-a5', 'Flyer format A5 (148x210mm) sur papier couché 135g, impression recto ou recto/verso.', 'A5 - Couché 135g', 200.00, 0.40, 'lot de 500', 1, '24-48h', 1, 1),
(2, 'Flyer A4', 'flyer-a4', 'Flyer format A4 (210x297mm) sur papier couché 135g.', 'A4 - Couché 135g', 350.00, 0.70, 'lot de 500', 1, '24-48h', 1, 2),
(2, 'Dépliant 2 Volets A4', 'depliant-2-volets-a4', 'Dépliant 2 volets format A4 ouvert, pli central.', '2 volets - A4 ouvert - Couché 170g', 500.00, 1.00, 'lot de 500', 1, '48-72h', 0, 3),
(2, 'Dépliant 3 Volets A4', 'depliant-3-volets-a4', 'Dépliant 3 volets format A4 ouvert, pli roulé ou accordéon.', '3 volets - A4 ouvert - Couché 170g', 600.00, 1.20, 'lot de 500', 1, '48-72h', 0, 4),

-- Affiches
(3, 'Affiche A3', 'affiche-a3', 'Affiche format A3 (297x420mm) impression jet d\'encre haute qualité.', 'A3 - Jet d\'encre HD', 25.00, 25.00, 'pièce', 1, '24h', 1, 1),
(3, 'Affiche A2', 'affiche-a2', 'Affiche format A2 (420x594mm) impression jet d\'encre.', 'A2 - Jet d\'encre HD', 45.00, 45.00, 'pièce', 1, '24h', 0, 2),
(3, 'Affiche A1', 'affiche-a1', 'Affiche grand format A1 (594x841mm).', 'A1 - Jet d\'encre HD', 75.00, 75.00, 'pièce', 1, '24h', 0, 3),
(3, 'Affiche A0', 'affiche-a0', 'Affiche très grand format A0 (841x1189mm).', 'A0 - Jet d\'encre HD', 120.00, 120.00, 'pièce', 1, '24-48h', 0, 4),

-- Banderoles
(4, 'Banderole PVC', 'banderole-pvc', 'Banderole PVC 500g/m² avec impression grand format, œillets inclus.', 'PVC 500g - Œillets inclus', 80.00, 80.00, 'm²', 1, '48h', 1, 1),
(4, 'Bâche Micro-Perforée', 'bache-micro-perforee', 'Bâche micro-perforée pour vitrine ou façade, laisse passer la lumière.', 'Micro-perforée - Vitrine', 120.00, 120.00, 'm²', 1, '48-72h', 0, 2),
(4, 'Kakémono / Roll-Up', 'kakemono-roll-up', 'Roll-up 85x200cm avec structure aluminium et housse de transport.', '85x200cm - Structure incluse', 350.00, 350.00, 'pièce', 1, '48h', 1, 3),

-- Stickers
(5, 'Stickers Vinyle', 'stickers-vinyle', 'Stickers en vinyle adhésif, découpe à la forme, usage intérieur/extérieur.', 'Vinyle adhésif - Découpe forme', 30.00, 30.00, 'm²', 1, '24-48h', 1, 1),
(5, 'Étiquettes Produit', 'etiquettes-produit', 'Étiquettes adhésives pour produits, différentes formes et tailles.', 'Adhésives - Multi-formes', 150.00, 0.30, 'lot de 500', 1, '48h', 0, 2),
(5, 'Stickers Vitrine', 'stickers-vitrine', 'Stickers pour vitrine en vinyle micro-perforé ou transparent.', 'Vitrine - Vinyle', 60.00, 60.00, 'm²', 1, '48h', 0, 3),

-- Impressions numériques
(6, 'Impression A4 Couleur', 'impression-a4-couleur', 'Impression numérique A4 couleur sur papier 80g.', 'A4 Couleur - 80g', 1.50, 1.50, 'page', 1, 'Immédiat', 1, 1),
(6, 'Impression A4 N&B', 'impression-a4-nb', 'Impression numérique A4 noir et blanc sur papier 80g.', 'A4 N&B - 80g', 0.50, 0.50, 'page', 1, 'Immédiat', 1, 2),
(6, 'Impression A3 Couleur', 'impression-a3-couleur', 'Impression numérique A3 couleur.', 'A3 Couleur - 80g', 3.00, 3.00, 'page', 1, 'Immédiat', 0, 3),
(6, 'Impression Photo', 'impression-photo', 'Impression photo haute qualité sur papier photo brillant ou mat.', 'Photo HD - Papier photo', 5.00, 5.00, 'pièce', 1, 'Immédiat', 1, 4),

-- Tampons
(7, 'Tampon Encreur Automatique', 'tampon-encreur-auto', 'Tampon encreur automatique personnalisé avec votre logo et texte.', 'Automatique - Personnalisé', 80.00, 80.00, 'pièce', 1, '24-48h', 1, 1),
(7, 'Cachet Rond', 'cachet-rond', 'Cachet rond officiel avec texte circulaire et logo central.', 'Rond - Officiel', 120.00, 120.00, 'pièce', 1, '24-48h', 0, 2),
(7, 'Tampon Dateur', 'tampon-dateur', 'Tampon dateur automatique avec texte personnalisé.', 'Dateur - Automatique', 150.00, 150.00, 'pièce', 1, '48h', 0, 3),

-- Reliure
(8, 'Reliure Spirale', 'reliure-spirale', 'Reliure spirale plastique ou métal pour documents et rapports.', 'Spirale - Plastique/Métal', 15.00, 15.00, 'pièce', 1, 'Immédiat', 1, 1),
(8, 'Reliure Thermique', 'reliure-thermique', 'Reliure thermique professionnelle pour mémoires et thèses.', 'Thermique - Professionnelle', 25.00, 25.00, 'pièce', 1, '1-2h', 0, 2),
(8, 'Plastification A4', 'plastification-a4', 'Plastification brillante ou mate format A4.', 'A4 - Brillant/Mat', 5.00, 5.00, 'pièce', 1, 'Immédiat', 1, 3),
(8, 'Plastification A3', 'plastification-a3', 'Plastification brillante ou mate format A3.', 'A3 - Brillant/Mat', 10.00, 10.00, 'pièce', 1, 'Immédiat', 0, 4),

-- Signalétique
(9, 'Panneau Forex', 'panneau-forex', 'Panneau en PVC expansé (Forex) avec impression directe.', 'Forex - Impression directe', 150.00, 150.00, 'm²', 1, '48-72h', 1, 1),
(9, 'Panneau Dibond', 'panneau-dibond', 'Panneau aluminium composite (Dibond) haute résistance.', 'Dibond - Haute résistance', 250.00, 250.00, 'm²', 1, '3-5 jours', 0, 2),
(9, 'Plaque Plexiglas', 'plaque-plexiglas', 'Plaque en plexiglas avec impression et/ou gravure.', 'Plexiglas - Impression/Gravure', 300.00, 300.00, 'm²', 1, '3-5 jours', 0, 3),

-- Textile & Objets
(10, 'T-Shirt Personnalisé', 'tshirt-personnalise', 'T-shirt 100% coton avec impression sérigraphie ou transfert.', 'Coton - Sérigraphie/Transfert', 60.00, 60.00, 'pièce', 10, '3-5 jours', 1, 1),
(10, 'Casquette Personnalisée', 'casquette-personnalisee', 'Casquette brodée ou imprimée avec votre logo.', 'Broderie/Impression', 45.00, 45.00, 'pièce', 20, '5-7 jours', 0, 2),
(10, 'Mug Personnalisé', 'mug-personnalise', 'Mug en céramique avec impression sublimation haute qualité.', 'Céramique - Sublimation', 35.00, 35.00, 'pièce', 10, '3-5 jours', 1, 3),
(10, 'Stylo Personnalisé', 'stylo-personnalise', 'Stylo à bille avec gravure ou impression de votre logo.', 'Gravure/Impression logo', 8.00, 8.00, 'pièce', 50, '5-7 jours', 0, 4),

-- Papeterie d'entreprise
(11, 'En-tête A4', 'entete-a4', 'Papier en-tête A4 avec votre identité visuelle complète.', 'A4 - Couché 120g', 300.00, 0.60, 'lot de 500', 1, '48-72h', 1, 1),
(11, 'Enveloppe Personnalisée', 'enveloppe-personnalisee', 'Enveloppes personnalisées avec logo, différents formats.', 'Multi-formats - Logo', 400.00, 0.80, 'lot de 500', 1, '48-72h', 0, 2),
(11, 'Chemise à Rabat', 'chemise-rabat', 'Chemise à rabat personnalisée en carton couché 350g.', 'Carton 350g - Personnalisée', 15.00, 15.00, 'pièce', 50, '3-5 jours', 0, 3),
(11, 'Facturier / Bon de Commande', 'facturier', 'Carnet autocopiant 2 ou 3 feuillets, personnalisé.', 'Autocopiant - 2/3 feuillets', 50.00, 50.00, 'carnet', 5, '3-5 jours', 1, 4),

-- Grands formats
(12, 'Roll-Up Standard', 'roll-up-standard', 'Roll-up 80x200cm avec impression HD et structure aluminium.', '80x200cm - Alu - HD', 300.00, 300.00, 'pièce', 1, '48h', 1, 1),
(12, 'X-Banner', 'x-banner', 'X-Banner 60x160cm avec structure et impression.', '60x160cm - Structure incluse', 200.00, 200.00, 'pièce', 1, '48h', 0, 2),
(12, 'Totem Carton', 'totem-carton', 'Totem publicitaire en carton avec impression recto/verso.', 'Carton - Recto/Verso', 250.00, 250.00, 'pièce', 1, '3-5 jours', 0, 3);

-- Villes de livraison au Maroc
INSERT IGNORE INTO villes_livraison (nom, frais_livraison, delai_livraison) VALUES
('Casablanca', 0.00, '24h'),
('Rabat', 25.00, '24-48h'),
('Marrakech', 30.00, '24-48h'),
('Fès', 30.00, '24-48h'),
('Tanger', 35.00, '48h'),
('Agadir', 35.00, '48h'),
('Meknès', 30.00, '24-48h'),
('Oujda', 40.00, '48-72h'),
('Kénitra', 25.00, '24-48h'),
('Tétouan', 35.00, '48h'),
('Salé', 20.00, '24h'),
('Mohammedia', 15.00, '24h'),
('El Jadida', 25.00, '24-48h'),
('Béni Mellal', 35.00, '48h'),
('Nador', 40.00, '48-72h'),
('Settat', 25.00, '24-48h'),
('Khouribga', 30.00, '24-48h'),
('Safi', 30.00, '48h'),
('Essaouira', 35.00, '48h'),
('Errachidia', 45.00, '72h'),
('Autres villes', 50.00, '72-96h');

-- Paramètres par défaut
INSERT IGNORE INTO parametres (cle, valeur, groupe) VALUES
('nom_entreprise', 'BERRADI PRINT', 'general'),
('slogan', 'Services d\'Impression Professionnels', 'general'),
('email', 'contact@berradiprint.ma', 'general'),
('telephone', '+212 6XX-XXXXXX', 'general'),
('whatsapp', '+212 6XX-XXXXXX', 'general'),
('adresse', 'Maroc', 'general'),
('ville', 'Casablanca', 'general'),
('horaires', 'Lundi - Samedi: 9h00 - 19h00', 'general'),
('tva_active', '1', 'fiscal'),
('tva_taux', '20', 'fiscal'),
('ice', '', 'fiscal'),
('rc', '', 'fiscal'),
('if_fiscal', '', 'fiscal'),
('livraison_gratuite_min', '500', 'livraison'),
('frais_livraison_defaut', '30', 'livraison'),
('devise', 'MAD', 'general'),
('symbole_devise', 'DH', 'general'),
('facebook', '', 'social'),
('instagram', '', 'social'),
('whatsapp_url', '', 'social'),
-- SEO Meta
('seo_meta_title', '', 'seo'),
('seo_meta_description', '', 'seo'),
('seo_meta_keywords', '', 'seo'),
('seo_og_title', '', 'seo'),
('seo_og_description', '', 'seo'),
('seo_og_image', '', 'seo'),
('seo_twitter_card', 'summary', 'seo'),
('seo_canonical_url', '', 'seo'),
('seo_robots_index', 'index', 'seo'),
('seo_robots_follow', 'follow', 'seo'),
('seo_google_analytics', '', 'seo'),
('seo_google_tag_manager', '', 'seo'),
('seo_custom_head', '', 'seo'),
('seo_custom_body', '', 'seo'),
-- Schema.org
('seo_schema_type', 'LocalBusiness', 'seo'),
('seo_schema_name', 'BERRADI PRINT', 'seo'),
('seo_schema_description', '', 'seo'),
('seo_schema_phone', '', 'seo'),
('seo_schema_address', '', 'seo'),
('seo_schema_city', '', 'seo'),
('seo_schema_country', 'MA', 'seo'),
('seo_schema_postal_code', '', 'seo'),
('seo_schema_price_range', '$$', 'seo'),
('seo_schema_logo_url', '', 'seo'),
-- Webmaster Verifications
('seo_gsc_verification', '', 'seo'),
('seo_gsc_active', '', 'seo'),
('seo_bing_verification', '', 'seo'),
('seo_bing_active', '', 'seo'),
('seo_yandex_verification', '', 'seo'),
('seo_pinterest_verification', '', 'seo'),
('seo_baidu_verification', '', 'seo'),
-- Meta Pixel (Facebook)
('pixel_meta_id', '', 'pixel'),
('pixel_meta_token', '', 'pixel'),
('pixel_meta_active', '', 'pixel'),
('pixel_meta_pageview', '1', 'pixel'),
('pixel_meta_viewcontent', '1', 'pixel'),
('pixel_meta_addtocart', '1', 'pixel'),
('pixel_meta_purchase', '1', 'pixel'),
('pixel_meta_lead', '1', 'pixel'),
('pixel_meta_contact', '1', 'pixel'),
-- TikTok Pixel
('pixel_tiktok_id', '', 'pixel'),
('pixel_tiktok_active', '', 'pixel'),
('pixel_tiktok_pageview', '1', 'pixel'),
('pixel_tiktok_viewcontent', '1', 'pixel'),
('pixel_tiktok_addtocart', '1', 'pixel'),
('pixel_tiktok_purchase', '1', 'pixel'),
('pixel_tiktok_contact', '1', 'pixel'),
-- Snapchat Pixel
('pixel_snap_id', '', 'pixel'),
('pixel_snap_active', '', 'pixel'),
('pixel_snap_pageview', '1', 'pixel'),
('pixel_snap_viewcontent', '1', 'pixel'),
('pixel_snap_addtocart', '1', 'pixel'),
('pixel_snap_purchase', '1', 'pixel');

-- Catégories de dépenses par défaut
INSERT IGNORE INTO depenses (categorie_depense, description, montant, date_depense) VALUES
('Matière première', 'Stock initial papier et encres', 0.00, CURDATE());
