# PrintFlow Pro — Complete Plugin Specification

## Plugin Name: **PrintFlow Pro**
### Tagline: *Gestion complète de votre imprimerie — du devis à la livraison*

---

# 1. Project Vision

## Problem Statement

Printing businesses in Morocco face a fragmented operational landscape. Owners typically juggle multiple disconnected tools: a WooCommerce store for online orders, spreadsheets for pricing, WhatsApp for customer communication, paper ledgers for finances, and manual processes for production tracking. This fragmentation leads to:

- Lost orders and miscommunication
- Inaccurate pricing that erodes margins
- No visibility into raw material consumption
- Inability to track production status in real time
- Financial blind spots—no clear picture of profit per order or per product
- Poor customer experience due to lack of order tracking
- Wasted time on repetitive manual tasks

## Who Uses It

| Role | Usage |
|------|-------|
| **Business Owner** | Full dashboard, financial reports, strategic decisions |
| **Sales Agent** | Quote creation, order intake, customer management |
| **Designer** | Artwork review, file management, design task queue |
| **Production Staff** | Job cards, production queue, material tracking |
| **Accountant** | Income/expense tracking, invoices, profit reports |
| **Delivery Staff** | Delivery assignments, route tracking, confirmation |
| **Customer** | Online ordering, file upload, order tracking |

## Why a Custom Plugin

Using 10+ disconnected plugins (WooCommerce + inventory plugin + CRM plugin + accounting plugin + etc.) creates:
- Data silos with no cross-module intelligence
- Plugin conflicts and update nightmares
- Inconsistent UX across different plugin interfaces
- No printing-industry-specific logic (material consumption, artwork workflow, dynamic pricing by print specs)
- Higher total cost of ownership

**PrintFlow Pro** unifies everything into a single, purpose-built system that understands the printing business domain natively.

## End-to-End Value Chain

```
Customer Request → Quote → Order → File Upload → Design Review →
Production → Quality Check → Finishing → Packaging → Delivery →
Invoice → Payment → Financial Reports
```

Every step is tracked, automated, and visible from one dashboard.

---

# 2. Plugin Overview

## Architecture Decision: Core Plugin + Modular Architecture

**Recommended approach: Single plugin with internal modular architecture.**

### Rationale:
- A single plugin simplifies installation, updates, and licensing
- Internal modules can be enabled/disabled via settings
- Shared services (database, authentication, notifications) are centralized
- No inter-plugin dependency headaches
- Future add-ons can extend the core via hooks and filters

### Structure:
```
printflow-pro/                    (single plugin directory)
├── Core engine                   (always active)
├── Module: Dashboard             (always active)
├── Module: Products              (can be toggled)
├── Module: Pricing Engine        (can be toggled)
├── Module: File Management       (can be toggled)
├── Module: Quotes                (can be toggled)
├── Module: Orders                (always active)
├── Module: Production            (can be toggled)
├── Module: Inventory             (can be toggled)
├── Module: Suppliers             (can be toggled)
├── Module: Distributors          (can be toggled)
├── Module: Finance               (can be toggled)
├── Module: CRM                   (can be toggled)
├── Module: Delivery              (can be toggled)
├── Module: Notifications         (can be toggled)
├── Module: Reports               (can be toggled)
└── Module: Settings              (always active)
```

---

# 3. Functional Modules — Detailed Design

## Module 1: Dashboard

**Purpose:** Central command center providing real-time business overview.

**Main Features:**
- Today's orders count and revenue
- Monthly sales chart (line chart)
- Production pipeline status (kanban-style counts)
- Low stock material alerts
- Pending quotes count
- Top 5 best-selling products
- Top 5 customers by revenue
- Delayed orders list
- Quick action buttons (new order, new quote, add expense)
- Financial summary (revenue vs expenses vs profit)

**Admin Screens:**
- `PrintFlow Pro → Tableau de bord` (main dashboard page)

**User Actions:**
- View KPIs, click through to detailed views, filter by date range

**Database Entities:** Aggregates from all other modules (no own tables)

**WooCommerce Integration:**
- Pulls order data via `wc_get_orders()`
- Revenue from WooCommerce order totals
- Product sales from order line items

---

## Module 2: Product Management

**Purpose:** Extend WooCommerce products with printing-specific attributes and auto-generation.

**Main Features:**
- Auto-create 20 predefined product types during setup
- Printing-specific attributes (paper type, size, finish, sides)
- Product templates for quick duplication
- Material consumption mapping per product
- Production lead time configuration
- File upload requirement toggle per product
- Design service upsell option
- Bulk product operations

**Admin Screens:**
- `PrintFlow Pro → Produits → Tous les produits`
- `PrintFlow Pro → Produits → Ajouter un produit`
- `PrintFlow Pro → Produits → Catégories`
- `PrintFlow Pro → Produits → Modèles de produits`
- `PrintFlow Pro → Produits → Générateur automatique`

**User Actions:**
- Create/edit/delete products, configure attributes, map materials, set pricing rules

**Database Entities:**
- Uses WooCommerce product post type (`product`)
- Custom meta fields for print-specific data
- `pfp_product_materials` table (product-to-material mapping)
- `pfp_product_templates` table

**WooCommerce Integration:**
- Extends product data panels
- Adds custom product tabs
- Hooks: `woocommerce_product_data_tabs`, `woocommerce_product_data_panels`, `woocommerce_process_product_meta`

---

## Module 3: Print Product Variations

**Purpose:** Manage complex print product variations that go beyond standard WooCommerce variations.

**Main Features:**
- Auto-generate variations from print attribute combinations
- Size-based variations (A4, A5, A3, custom)
- Material-based variations
- Quantity-tier variations
- Finishing option variations
- Bulk variation management
- Variation-specific pricing rules
- Variation-specific material consumption

**Admin Screens:**
- Integrated into WooCommerce product edit screen
- `PrintFlow Pro → Produits → Variations en masse`

**User Actions:**
- Define attribute sets, auto-generate variations, bulk edit prices

**Database Entities:**
- WooCommerce product variations
- `pfp_variation_rules` table
- Custom meta on variations

**WooCommerce Integration:**
- `woocommerce_product_after_variable_attributes`
- `woocommerce_save_product_variation`
- Custom variation display on frontend

---

## Module 4: Dynamic Pricing Engine

**Purpose:** Calculate print product prices based on multiple configurable parameters.

**Main Features:**
- Rule-based pricing engine
- Price calculation by: size, quantity, material, paper type, thickness, sides (recto/recto-verso), finishing, lamination, urgency, design service
- Quantity tier discounts
- Markup/margin configuration
- Cost-plus pricing model
- Admin pricing rule builder (visual)
- Price preview/calculator on product pages
- Bulk pricing table display
- Quote-specific pricing overrides

**Admin Screens:**
- `PrintFlow Pro → Tarification → Règles de prix`
- `PrintFlow Pro → Tarification → Grilles tarifaires`
- `PrintFlow Pro → Tarification → Simulateur`

**User Actions:**
- Create pricing rules, set base costs, configure multipliers, test prices

**Database Entities:**
- `pfp_pricing_rules` table
- `pfp_pricing_tiers` table
- `pfp_pricing_modifiers` table

**WooCommerce Integration:**
- `woocommerce_product_get_price` filter
- `woocommerce_get_price_html` filter
- Custom price display on product pages
- Cart price calculation via `woocommerce_before_calculate_totals`

### Pricing Formula:

```
Final Price = (Base Material Cost × Size Multiplier × Quantity Factor
              + Finishing Cost + Lamination Cost + Design Fee)
              × Urgency Multiplier × Margin Multiplier
```

### Example Pricing Rules:

| Factor | Option | Multiplier/Cost |
|--------|--------|----------------|
| Size | A5 | ×0.5 |
| Size | A4 | ×1.0 |
| Size | A3 | ×2.0 |
| Quantity | 100 | ×1.0 |
| Quantity | 500 | ×0.85 |
| Quantity | 1000 | ×0.70 |
| Paper | Couché 135g | +0.50 MAD/unit |
| Paper | Couché 250g | +1.20 MAD/unit |
| Sides | Recto | ×1.0 |
| Sides | Recto-verso | ×1.8 |
| Finishing | Sans | +0 |
| Finishing | Pelliculage mat | +0.30 MAD/unit |
| Finishing | Pelliculage brillant | +0.35 MAD/unit |
| Finishing | Vernis UV | +0.50 MAD/unit |
| Urgency | Standard (5j) | ×1.0 |
| Urgency | Express (48h) | ×1.5 |
| Urgency | Urgent (24h) | ×2.0 |
| Design | Client fournit fichier | +0 |
| Design | Service design | +150 MAD fixe |

---

## Module 5: File Upload / Artwork Management

**Purpose:** Handle customer file uploads for print-ready artwork.

**Main Features:**
- Drag-and-drop file upload on product pages
- Supported formats: PDF, AI, EPS, PSD, PNG, JPG, TIFF
- File size validation (up to 500MB)
- File format validation
- Automatic file preview generation
- File versioning (customer can re-upload)
- Internal annotation/comment system
- Design team review queue
- File approval/rejection workflow
- Artwork archive per order
- Secure file storage with restricted access

**Admin Screens:**
- `PrintFlow Pro → Fichiers → Tous les fichiers`
- `PrintFlow Pro → Fichiers → En attente de validation`
- `PrintFlow Pro → Fichiers → Archive`

**User Actions:**
- Upload files, view preview, resubmit, approve/reject artwork

**Database Entities:**
- `pfp_artwork_files` table
- `pfp_artwork_comments` table
- WordPress media library integration

**WooCommerce Integration:**
- File upload field on product pages
- File attached to order items
- `woocommerce_before_add_to_cart_button` hook for upload UI
- Order item meta for file references

---

## Module 6: Quote Request Management

**Purpose:** Handle custom quote requests for non-standard print jobs.

**Main Features:**
- Quote request form on frontend
- Admin quote builder with pricing engine
- Quote-to-order conversion
- Quote versioning (revisions)
- Quote expiry dates
- PDF quote generation
- Email quote to customer
- Quote status tracking (Nouveau, En cours, Envoyé, Accepté, Refusé, Expiré)
- Quote templates for common jobs

**Admin Screens:**
- `PrintFlow Pro → Devis → Tous les devis`
- `PrintFlow Pro → Devis → Nouveau devis`
- `PrintFlow Pro → Devis → Modèles`

**User Actions:**
- Create quotes, send to customer, convert to order

**Database Entities:**
- `pfp_quotes` table
- `pfp_quote_items` table
- `pfp_quote_history` table

**WooCommerce Integration:**
- Convert accepted quote to WooCommerce order
- Use WooCommerce products as quote line items

---

## Module 7: Order Management

**Purpose:** Extended order management with printing-specific workflow states.

**Main Features:**
- Extended order statuses for print production
- Order detail view with production info
- Artwork files linked to orders
- Material consumption per order
- Production assignment
- Internal notes and timeline
- Order priority levels
- Batch order processing
- Reorder functionality

**Admin Screens:**
- `PrintFlow Pro → Commandes → Toutes les commandes`
- `PrintFlow Pro → Commandes → En production`
- `PrintFlow Pro → Commandes → À livrer`

**User Actions:**
- View orders, update status, assign production, track progress

**Database Entities:**
- WooCommerce orders (extended with custom meta)
- `pfp_order_production` table
- `pfp_order_timeline` table

**WooCommerce Integration:**
- Custom order statuses via `wc_register_order_status()`
- `woocommerce_order_status_changed` hook
- Extended order admin panels
- Custom order list columns

---

## Module 8: Production Workflow Management

**Purpose:** Track the production lifecycle of each print job.

**Main Features:**
- Production job cards
- Kanban board view of production pipeline
- Staff assignment per job
- Machine/equipment assignment
- Estimated vs actual production time
- Technical specifications per job
- Quality control checklist
- Production notes
- Timeline/history of stage transitions
- Batch production grouping
- Production calendar view

**Admin Screens:**
- `PrintFlow Pro → Production → Tableau Kanban`
- `PrintFlow Pro → Production → Liste des travaux`
- `PrintFlow Pro → Production → Calendrier`
- `PrintFlow Pro → Production → Fiche de travail` (individual job card)

**User Actions:**
- Move jobs through stages, assign staff, add notes, mark checklists

**Database Entities:**
- `pfp_production_jobs` table
- `pfp_production_stages` table
- `pfp_production_assignments` table
- `pfp_production_checklists` table
- `pfp_production_logs` table

**WooCommerce Integration:**
- Created from WooCommerce orders
- Status sync back to order status
- `woocommerce_order_status_changed` triggers production job creation

### Production Statuses:
1. **Nouveau** — Order received, not yet processed
2. **En attente de validation** — Awaiting file/design approval
3. **Fichier reçu** — Customer file received, pending review
4. **En cours de design** — Design team working on artwork
5. **En cours d'impression** — On the press
6. **En finition** — Post-press finishing (cutting, lamination, binding)
7. **Prêt pour livraison** — Complete, awaiting dispatch
8. **Livré** — Delivered to customer
9. **Annulé** — Cancelled

---

## Module 9: Inventory / Raw Materials Management

**Purpose:** Track all raw materials, consumption, and stock levels.

**Main Features:**
- Material catalog with categories
- Stock in / stock out tracking
- Auto-deduction on order production
- Low stock alerts (configurable thresholds)
- Material consumption mapping to products
- Inventory movement logs
- Manual stock adjustment with reason codes
- Periodic stock audit support
- Material cost tracking
- Reorder point calculation

**Admin Screens:**
- `PrintFlow Pro → Inventaire → Matières premières`
- `PrintFlow Pro → Inventaire → Mouvements de stock`
- `PrintFlow Pro → Inventaire → Alertes stock bas`
- `PrintFlow Pro → Inventaire → Audit de stock`

**User Actions:**
- Add materials, record stock in/out, adjust quantities, run audits

**Database Entities:**
- `pfp_materials` table
- `pfp_material_categories` table
- `pfp_stock_movements` table
- `pfp_material_product_map` table

**WooCommerce Integration:**
- Auto-deduct materials when order status changes to "En cours d'impression"
- `woocommerce_order_status_changed` hook

### Predefined Materials:

| Material | Code | Unit | Min Alert |
|----------|------|------|-----------|
| Papier couché 135g | PAP-COU-135 | Rame (500 feuilles) | 10 |
| Papier couché 250g | PAP-COU-250 | Rame (500 feuilles) | 10 |
| Papier offset 80g | PAP-OFF-080 | Rame (500 feuilles) | 20 |
| Papier kraft | PAP-KRA-001 | Rame (500 feuilles) | 5 |
| Vinyle adhésif blanc | VIN-ADH-BLA | Rouleau (50m) | 3 |
| Vinyle adhésif transparent | VIN-ADH-TRA | Rouleau (50m) | 2 |
| Encre noire | ENC-NOI-001 | Cartouche | 5 |
| Encre cyan | ENC-CYA-001 | Cartouche | 5 |
| Encre magenta | ENC-MAG-001 | Cartouche | 5 |
| Encre jaune | ENC-JAU-001 | Cartouche | 5 |
| Film pelliculage mat | FIN-PEL-MAT | Rouleau (100m) | 2 |
| Film pelliculage brillant | FIN-PEL-BRI | Rouleau (100m) | 2 |
| T-shirts vierges blancs | TEX-TSH-BLA | Pièce | 50 |
| T-shirts vierges noirs | TEX-TSH-NOI | Pièce | 30 |
| Casquettes vierges | TEX-CAS-001 | Pièce | 20 |
| Tote bags vierges | TEX-TOT-001 | Pièce | 20 |
| Carton emballage | EMB-CAR-001 | Pièce | 30 |
| Sacs papier kraft | EMB-SAC-KRA | Pièce | 50 |
| Bâche PVC 500g | BAC-PVC-500 | Mètre carré | 20 |
| Toile roll-up | BAC-ROL-001 | Pièce | 5 |

---

## Module 10: Supplier Management

**Purpose:** Manage relationships and transactions with raw material suppliers.

**Main Features:**
- Supplier directory
- Supplier-material linking
- Purchase order creation
- Purchase order tracking
- Price agreement management
- Payment tracking per supplier
- Supplier performance rating
- Transaction history
- Contact management

**Admin Screens:**
- `PrintFlow Pro → Fournisseurs → Tous les fournisseurs`
- `PrintFlow Pro → Fournisseurs → Ajouter un fournisseur`
- `PrintFlow Pro → Fournisseurs → Bons de commande`
- `PrintFlow Pro → Fournisseurs → Historique`

**Database Entities:**
- `pfp_suppliers` table
- `pfp_purchase_orders` table
- `pfp_purchase_order_items` table
- `pfp_supplier_payments` table

---

## Module 11: Distributor / Reseller Management

**Purpose:** Manage B2B distribution channels and reseller partnerships.

**Main Features:**
- Distributor/reseller directory
- Territory/city assignment
- Special pricing tiers for resellers
- Order tracking per distributor
- Commission management
- Performance dashboards
- Payment tracking

**Admin Screens:**
- `PrintFlow Pro → Distributeurs → Tous les distributeurs`
- `PrintFlow Pro → Distributeurs → Ajouter un distributeur`
- `PrintFlow Pro → Distributeurs → Commissions`

**Database Entities:**
- `pfp_distributors` table
- `pfp_distributor_orders` table
- `pfp_distributor_commissions` table

---

## Module 12–15: Financial Management (Income, Expenses, Profit, Invoicing)

**Purpose:** Complete financial tracking for the printing business.

**Main Features:**
- Automatic income recording from WooCommerce orders
- Manual income entry for offline/cash orders
- Expense categories (materials, shipping, operations, salaries, rent, utilities)
- Expense recording with receipt attachment
- Cost of Goods Sold calculation per order
- Profit per order / per product / per month / per year
- Invoice generation (PDF)
- Payment status tracking (Payé, En attente, En retard)
- Payment method tracking (Espèces, Virement, Contre remboursement, Carte)
- Profit & Loss summary
- Break-even estimation
- Financial dashboard with charts
- Export to Excel (CSV) and PDF

**Admin Screens:**
- `PrintFlow Pro → Finances → Tableau de bord financier`
- `PrintFlow Pro → Finances → Revenus`
- `PrintFlow Pro → Finances → Dépenses`
- `PrintFlow Pro → Finances → Factures`
- `PrintFlow Pro → Finances → Profit & Pertes`
- `PrintFlow Pro → Finances → Export`

**Database Entities:**
- `pfp_income` table
- `pfp_expenses` table
- `pfp_expense_categories` table
- `pfp_invoices` table
- `pfp_invoice_items` table
- `pfp_payments` table

**WooCommerce Integration:**
- Auto-create income record on order completion
- `woocommerce_order_status_completed` hook
- COGS from material consumption data

---

## Module 16: CRM / Customer Management

**Purpose:** Understand and nurture customer relationships.

**Main Features:**
- Customer profiles (extends WordPress user)
- Order history per customer
- Total lifetime spending
- Customer segmentation (new, active, VIP, inactive)
- Top customers ranking
- Communication log (notes, calls, emails)
- Coupon/discount management
- Loyalty points system
- Repeat order support (reorder button)
- Quote-to-order conversion tracking
- WhatsApp integration link
- Customer groups/tags

**Admin Screens:**
- `PrintFlow Pro → Clients → Tous les clients`
- `PrintFlow Pro → Clients → Profil client`
- `PrintFlow Pro → Clients → Segments`
- `PrintFlow Pro → Clients → Programme fidélité`

**Database Entities:**
- WordPress users (extended with meta)
- `pfp_customer_notes` table
- `pfp_loyalty_points` table
- `pfp_customer_segments` table

**WooCommerce Integration:**
- Extends WooCommerce customer data
- Order history from WooCommerce orders
- `woocommerce_created_customer` hook

---

## Module 17: Delivery Management

**Purpose:** Track deliveries from dispatch to customer receipt.

**Main Features:**
- Delivery assignment to staff
- Delivery status tracking
- Delivery zones (cities in Morocco)
- Delivery cost calculation
- Proof of delivery (photo upload)
- Delivery notes
- Delivery calendar
- Integration with tracking references

**Admin Screens:**
- `PrintFlow Pro → Livraisons → Toutes les livraisons`
- `PrintFlow Pro → Livraisons → Planification`
- `PrintFlow Pro → Livraisons → Zones de livraison`

**Database Entities:**
- `pfp_deliveries` table
- `pfp_delivery_zones` table
- `pfp_delivery_logs` table

---

## Module 18: Notifications

**Purpose:** Automated communication with customers and staff.

**Main Features:**
- Email notifications on order status changes
- WhatsApp notification links
- Admin alerts (low stock, new order, delayed production)
- Customer notifications (order confirmation, file approved, ready for delivery, delivered)
- Weekly summary email to admin
- Monthly report email
- Notification templates (French)
- Notification log

**Admin Screens:**
- `PrintFlow Pro → Notifications → Modèles`
- `PrintFlow Pro → Notifications → Historique`
- `PrintFlow Pro → Notifications → Paramètres`

**Database Entities:**
- `pfp_notification_templates` table
- `pfp_notification_log` table

**WooCommerce Integration:**
- Extends WooCommerce email system
- `woocommerce_email_classes` filter

---

## Module 19: Reporting & Analytics

**Purpose:** Business intelligence and data-driven decision making.

**Main Features:**
- Sales reports (daily, weekly, monthly, yearly)
- Product performance reports
- Customer analytics
- Material consumption reports
- Supplier spend reports
- Production efficiency reports
- Financial reports (P&L, cash flow)
- Custom date range filtering
- Chart visualizations (Chart.js)
- Export to CSV/PDF
- Scheduled report generation

**Admin Screens:**
- `PrintFlow Pro → Rapports → Ventes`
- `PrintFlow Pro → Rapports → Produits`
- `PrintFlow Pro → Rapports → Clients`
- `PrintFlow Pro → Rapports → Production`
- `PrintFlow Pro → Rapports → Finances`
- `PrintFlow Pro → Rapports → Inventaire`

**Dashboard Widgets:**

| Widget | Chart Type | Data Source |
|--------|-----------|-------------|
| Commandes aujourd'hui | Counter | Orders |
| CA aujourd'hui | Counter | Orders |
| CA ce mois | Line chart | Orders |
| Profit mensuel | Bar chart | Finance |
| Produits les plus vendus | Horizontal bar | Products |
| Meilleurs clients | Table | Customers |
| Matières les plus consommées | Pie chart | Inventory |
| Alertes stock bas | Alert list | Inventory |
| Commandes en retard | Table | Orders |
| État de production | Stacked bar | Production |
| Performance fournisseurs | Rating table | Suppliers |

---

## Module 20: Settings / Configuration

**Purpose:** Global plugin configuration.

**Main Features:**
- Business information (name, address, ICE, RC, CNSS)
- Currency settings (MAD - Dirham marocain)
- Default language (French)
- Module enable/disable toggles
- Email settings
- File upload settings (max size, allowed formats)
- Pricing defaults
- Production workflow customization
- User role management
- Backup & export
- License management

**Admin Screens:**
- `PrintFlow Pro → Réglages → Général`
- `PrintFlow Pro → Réglages → Modules`
- `PrintFlow Pro → Réglages → Email`
- `PrintFlow Pro → Réglages → Fichiers`
- `PrintFlow Pro → Réglages → Production`
- `PrintFlow Pro → Réglages → Rôles`

---

# 4. Front-End Website Structure (French)

## Page: Accueil (Home)

**Purpose:** Showcase the print shop's capabilities and drive conversions.

**Main Sections:**
- Hero banner with tagline: "Votre partenaire impression au Maroc"
- Featured product categories (grid with images)
- "Comment ça marche" (3-step process: Choisir → Personnaliser → Commander)
- Best sellers carousel
- Testimonials section
- Call-to-action: "Demander un devis gratuit"
- Trust badges (quality, fast delivery, satisfaction guarantee)

**UX Goals:** Immediate understanding of services, easy navigation to products, trust building

**Key CTAs:** "Commander maintenant", "Demander un devis", "Voir nos produits"

**Plugin Functions:** Product category display, featured products shortcode

---

## Page: Boutique (Shop)

**Purpose:** Browse all print products.

**Main Sections:**
- Category filter sidebar
- Product grid with thumbnails
- Price range filter
- Sort options (price, popularity, newest)
- Quick view functionality
- Pagination

**UX Goals:** Easy product discovery, clear pricing visibility

**Key CTAs:** "Voir le produit", "Ajouter au panier", "Demander un devis"

**Plugin Functions:** WooCommerce shop page, custom product display, dynamic price preview

---

## Page: Page Produit (Product Page)

**Purpose:** Detailed product view with customization and pricing.

**Main Sections:**
- Product images/mockups
- Product title and description
- Interactive pricing calculator (size, quantity, material, options)
- Live price update
- File upload area
- "Ajouter le service design" checkbox
- Quantity selector
- Add to cart button
- Production lead time display
- Related products

**UX Goals:** Clear pricing, easy customization, confident purchase decision

**Key CTAs:** "Ajouter au panier", "Télécharger votre fichier", "Demander un devis personnalisé"

**Plugin Functions:** Dynamic pricing engine, file upload handler, variation selector

---

## Page: Demande de Devis (Quote Request)

**Purpose:** Allow customers to request custom quotes.

**Main Sections:**
- Quote request form (product type, quantity, specifications, description)
- File upload for reference artwork
- Contact information fields
- Preferred delivery date
- Submission confirmation message

**UX Goals:** Simple form, clear expectations on response time

**Key CTAs:** "Envoyer ma demande de devis"

**Plugin Functions:** Quote module form handler, email notification

---

## Page: Panier (Cart)

**Purpose:** Review selected items before checkout.

**Main Sections:**
- Cart items with thumbnails, specs summary, uploaded file reference
- Quantity adjustment
- Price per item and subtotal
- Coupon code field
- Cart totals
- Proceed to checkout

**UX Goals:** Clear order summary, easy modifications

**Key CTAs:** "Passer la commande", "Continuer vos achats"

**Plugin Functions:** WooCommerce cart with custom fields display

---

## Page: Paiement (Checkout)

**Purpose:** Complete the order with billing and payment.

**Main Sections:**
- Billing information
- Shipping/delivery information
- Delivery zone selection (Morocco cities)
- Order summary
- Payment method selection
- Terms and conditions
- Place order button

**UX Goals:** Fast, trustworthy checkout experience

**Key CTAs:** "Confirmer la commande"

**Plugin Functions:** WooCommerce checkout, custom delivery zones, payment gateway integration

---

## Page: Mon Compte (My Account)

**Purpose:** Customer self-service portal.

**Main Sections:**
- Dashboard overview
- Order history with status tracking
- Re-order button for past orders
- File management (uploaded artworks)
- Quotes and their statuses
- Account details / password change
- Addresses
- Loyalty points balance

**UX Goals:** Full visibility into orders and account, self-service capability

**Key CTAs:** "Suivre ma commande", "Recommander", "Voir mes devis"

**Plugin Functions:** Extended WooCommerce My Account, custom endpoints

---

## Page: Suivi de Commande (Order Tracking)

**Purpose:** Real-time order status tracking.

**Main Sections:**
- Order number input (for non-logged users)
- Visual progress tracker (step indicators)
- Current status with description
- Estimated delivery date
- Production stage details
- Delivery tracking info

**UX Goals:** Transparency, reduce support inquiries

**Key CTAs:** "Suivre une autre commande", "Contacter le support"

**Plugin Functions:** Order tracking shortcode, production status display

---

## Pages: À propos, Contact, Blog, Pages légales

Standard pages with appropriate content. The plugin provides:
- Contact form integration
- Blog with printing tips content category
- Legal pages: Mentions légales, Politique de confidentialité, CGV (Conditions générales de vente)

---

# 5. Automatic Product Creation

## Product Catalog (20 Products)

### 1. Cartes de visite
- **Nom:** Cartes de visite professionnelles
- **Description courte:** Cartes de visite sur mesure, impression haute qualité
- **Description longue:** Faites une première impression mémorable avec nos cartes de visite professionnelles. Disponibles en plusieurs formats, papiers et finitions. Impression recto ou recto-verso en quadrichromie. Choisissez parmi nos options de pelliculage mat ou brillant pour un rendu premium.
- **SKU:** CDV-001
- **Catégorie:** Supports commerciaux
- **Attributs:** Format (85×55mm standard, 90×50mm, 85×55mm coins arrondis), Papier (Couché 300g, Couché 350g, Créatif texturé), Impression (Recto, Recto-verso), Finition (Sans, Pelliculage mat, Pelliculage brillant, Vernis UV sélectif)
- **Variations:** Combinaison de tous les attributs
- **Prix de base:** 150 MAD (250 pcs, standard)
- **Pricing logic:** Base × paper_multiplier × sides_multiplier × finish_cost + quantity_tier_discount
- **File upload:** Oui (PDF, AI)
- **Design service:** Oui (+150 MAD)
- **Délai:** 3-5 jours ouvrables
- **Material mapping:** Papier couché 300g/350g, Encre CMYK

### 2. Flyers
- **Nom:** Flyers publicitaires
- **Description courte:** Flyers personnalisés pour vos campagnes marketing
- **Description longue:** Communiquez efficacement avec nos flyers publicitaires haute qualité. Idéaux pour les promotions, événements et campagnes marketing. Impression en quadrichromie sur papier couché pour des couleurs éclatantes.
- **SKU:** FLY-001
- **Catégorie:** Supports marketing
- **Attributs:** Format (A5, A4, A3, DL), Papier (Couché 135g, Couché 170g, Couché 250g), Impression (Recto, Recto-verso), Finition (Sans, Pelliculage mat, Pelliculage brillant)
- **Prix de base:** 200 MAD (500 pcs A5)
- **Délai:** 3-5 jours
- **Material mapping:** Papier couché, Encre CMYK

### 3. Brochures
- **Nom:** Brochures d'entreprise
- **Description courte:** Brochures professionnelles pour présenter votre activité
- **SKU:** BRO-001
- **Catégorie:** Supports commerciaux
- **Attributs:** Format (A4, A5), Pages (8, 12, 16, 24, 32), Papier couverture (Couché 250g, Couché 300g), Papier intérieur (Couché 135g, Couché 170g), Reliure (Piqûre à cheval, Dos collé), Finition (Sans, Pelliculage mat, Pelliculage brillant)
- **Prix de base:** 1500 MAD (100 pcs, 8 pages A4)
- **Délai:** 5-7 jours

### 4. Dépliants
- **Nom:** Dépliants pliés
- **Description courte:** Dépliants 2 ou 3 volets pour vos communications
- **SKU:** DEP-001
- **Catégorie:** Supports marketing
- **Attributs:** Format ouvert (A4, A3), Pliage (2 volets, 3 volets, Accordéon, Portefeuille), Papier (Couché 135g, Couché 170g, Couché 250g), Impression (Recto-verso), Finition (Sans, Pelliculage mat)
- **Prix de base:** 300 MAD (500 pcs, A4 3 volets)
- **Délai:** 3-5 jours

### 5. Affiches
- **Nom:** Affiches grand format
- **Description courte:** Affiches publicitaires et décoratives grand format
- **SKU:** AFF-001
- **Catégorie:** Grand format
- **Attributs:** Format (A3, A2, A1, A0, Personnalisé), Papier (Couché 135g, Couché 170g, Photo brillant, Photo mat), Finition (Sans, Pelliculage mat, Pelliculage brillant)
- **Prix de base:** 25 MAD (1 pc A3)
- **Délai:** 2-3 jours

### 6. Stickers
- **Nom:** Stickers et autocollants
- **Description courte:** Stickers personnalisés en vinyle ou papier
- **SKU:** STK-001
- **Catégorie:** Étiquetage
- **Attributs:** Forme (Rectangulaire, Rond, Ovale, Découpe personnalisée), Taille (50×30mm, 100×50mm, 100×100mm, Personnalisé), Matière (Papier adhésif, Vinyle blanc, Vinyle transparent), Finition (Brillant, Mat)
- **Prix de base:** 200 MAD (100 pcs, 100×50mm vinyle)
- **Délai:** 3-5 jours

### 7. Étiquettes
- **Nom:** Étiquettes produits
- **Description courte:** Étiquettes autocollantes pour vos produits
- **SKU:** ETQ-001
- **Catégorie:** Étiquetage
- **Attributs:** Forme (Rectangulaire, Rond, Ovale), Taille, Matière (Papier adhésif, Vinyle, Polyester), Finition
- **Prix de base:** 150 MAD (100 pcs)
- **Délai:** 3-5 jours

### 8. Bannières
- **Nom:** Bannières publicitaires
- **Description courte:** Bannières intérieures et extérieures
- **SKU:** BAN-001
- **Catégorie:** Grand format
- **Attributs:** Taille (100×200cm, 150×300cm, Personnalisé), Matière (Bâche PVC, Tissu polyester), Finition (Oeillets, Ourlets, Poches pour barres)
- **Prix de base:** 150 MAD/m²
- **Délai:** 2-4 jours

### 9. Roll-up
- **Nom:** Roll-up publicitaires
- **Description courte:** Kakémonos roll-up avec structure incluse
- **SKU:** RUP-001
- **Catégorie:** PLV (Publicité sur lieu de vente)
- **Attributs:** Taille (80×200cm, 85×200cm, 100×200cm, 120×200cm), Qualité structure (Standard, Premium), Impression (Recto)
- **Prix de base:** 350 MAD (85×200cm standard)
- **Délai:** 2-3 jours

### 10. Bâches publicitaires
- **Nom:** Bâches publicitaires grand format
- **Description courte:** Bâches PVC pour affichage extérieur
- **SKU:** BAC-001
- **Catégorie:** Grand format
- **Attributs:** Taille (Personnalisé, m²), Matière (PVC 500g, PVC 300g micro-perforée), Finition (Oeillets tous les 50cm, Ourlets renforcés)
- **Prix de base:** 120 MAD/m²
- **Délai:** 2-4 jours

### 11. Menus de restaurant
- **Nom:** Menus de restaurant
- **Description courte:** Cartes de menu professionnelles pour restaurants
- **SKU:** MEN-001
- **Catégorie:** Supports commerciaux
- **Attributs:** Format (A4, A3 plié, DL), Pages (2, 4, 6), Papier (Couché 250g, Couché 300g, Création texturé), Finition (Pelliculage mat, Pelliculage brillant)
- **Prix de base:** 500 MAD (50 pcs, A4 2 pages)
- **Délai:** 3-5 jours

### 12. Tampons
- **Nom:** Tampons personnalisés
- **Description courte:** Tampons encreurs avec votre logo et informations
- **SKU:** TAM-001
- **Catégorie:** Accessoires bureau
- **Attributs:** Type (Auto-encreur, Bois, Dateur), Taille (30×10mm, 47×18mm, 58×22mm, 70×30mm), Couleur encre (Bleu, Noir, Rouge)
- **Prix de base:** 80 MAD (auto-encreur 47×18mm)
- **Délai:** 2-3 jours

### 13. Enveloppes personnalisées
- **Nom:** Enveloppes imprimées
- **Description courte:** Enveloppes avec votre identité visuelle
- **SKU:** ENV-001
- **Catégorie:** Papeterie d'entreprise
- **Attributs:** Format (DL 110×220mm, C5 162×229mm, C4 229×324mm), Papier (Offset 80g, Offset 100g), Impression (Recto, Recto-verso), Fenêtre (Avec, Sans)
- **Prix de base:** 300 MAD (250 pcs DL)
- **Délai:** 3-5 jours

### 14. Chemises cartonnées
- **Nom:** Chemises à rabats personnalisées
- **Description courte:** Chemises de présentation avec votre branding
- **SKU:** CHE-001
- **Catégorie:** Papeterie d'entreprise
- **Attributs:** Format (A4), Papier (Couché 300g, Couché 350g, Carton 400g), Rabats (1 rabat, 2 rabats), Finition (Pelliculage mat, Pelliculage brillant), Porte-carte (Avec, Sans)
- **Prix de base:** 1000 MAD (100 pcs)
- **Délai:** 5-7 jours

### 15. T-shirts imprimés
- **Nom:** T-shirts personnalisés
- **Description courte:** T-shirts avec impression de votre design
- **SKU:** TSH-001
- **Catégorie:** Textile publicitaire
- **Attributs:** Taille (S, M, L, XL, XXL), Couleur (Blanc, Noir, Gris, Bleu marine, Rouge), Technique (Sérigraphie, Transfert, Sublimation, DTF), Zones impression (Poitrine, Dos, Les deux)
- **Prix de base:** 60 MAD/pièce (min 10 pcs, sérigraphie 1 couleur)
- **Délai:** 5-7 jours

### 16. Casquettes
- **Nom:** Casquettes personnalisées
- **Description courte:** Casquettes brodées ou imprimées avec votre logo
- **SKU:** CAS-001
- **Catégorie:** Textile publicitaire
- **Attributs:** Type (Baseball, Trucker, Snapback), Couleur, Technique (Broderie, Impression transfert), Zones (Face, Côté, Arrière)
- **Prix de base:** 45 MAD/pièce (min 20 pcs)
- **Délai:** 5-10 jours

### 17. Tote bags
- **Nom:** Tote bags personnalisés
- **Description courte:** Sacs en tissu réutilisables avec votre design
- **SKU:** TOT-001
- **Catégorie:** Textile publicitaire
- **Attributs:** Matière (Coton naturel, Coton coloré, Non-tissé), Taille (38×42cm standard, 40×45cm), Technique (Sérigraphie, Transfert), Impression (Recto, Recto-verso)
- **Prix de base:** 25 MAD/pièce (min 50 pcs)
- **Délai:** 5-7 jours

### 18. Packaging personnalisé
- **Nom:** Emballages personnalisés
- **Description courte:** Boîtes et emballages sur mesure pour vos produits
- **SKU:** PKG-001
- **Catégorie:** Packaging
- **Attributs:** Type (Boîte pliante, Boîte rigide, Sleeve), Taille (Personnalisé), Carton (Blanc 300g, Kraft, Couché), Impression (Extérieur, Intérieur + extérieur), Finition (Pelliculage, Vernis, Gaufrage)
- **Prix de base:** Sur devis (min 100 pcs)
- **Délai:** 7-10 jours

### 19. Boîtes produits
- **Nom:** Boîtes produits sur mesure
- **Description courte:** Boîtes d'emballage pour vos produits commerciaux
- **SKU:** BOX-001
- **Catégorie:** Packaging
- **Attributs:** Forme, Dimensions (Personnalisé), Carton (Micro-cannelure, Carton plat), Impression, Finition
- **Prix de base:** Sur devis
- **Délai:** 7-10 jours

### 20. Sacs papier personnalisés
- **Nom:** Sacs en papier personnalisés
- **Description courte:** Sacs papier kraft ou couché avec votre identité
- **SKU:** SAC-001
- **Catégorie:** Packaging
- **Attributs:** Taille (Petit 18×24cm, Moyen 26×32cm, Grand 32×42cm), Papier (Kraft brun, Kraft blanc, Couché), Poignées (Torsadées, Plates, Ruban), Impression (1 à 4 couleurs)
- **Prix de base:** 5 MAD/pièce (min 200 pcs, kraft moyen 1 couleur)
- **Délai:** 7-10 jours

## Auto-Generation System

The plugin supports automatic product creation via:

1. **Setup Wizard** — During first-time plugin activation, the wizard offers to auto-create all 20 product types with predefined data
2. **Product Generator** — Admin screen to select which products to create/recreate at any time
3. **CSV Import** — Export/import product templates via CSV for backup and migration
4. **Predefined Templates** — Internal JSON templates that define each product's structure

---

# 6. Dynamic Pricing Logic (Detailed)

## Pricing Formula

```
Unit_Cost = Base_Material_Cost
            × Size_Multiplier
            × Sides_Multiplier
            + Finishing_Cost_Per_Unit
            + Lamination_Cost_Per_Unit

Subtotal = Unit_Cost × Quantity

Quantity_Discount = Subtotal × Tier_Discount_Percentage

Design_Fee = (if design_service) Fixed_Design_Fee else 0

Urgency_Fee = Subtotal × (Urgency_Multiplier - 1)

Total_Cost = (Subtotal - Quantity_Discount) + Design_Fee + Urgency_Fee

Margin_Amount = Total_Cost × Margin_Percentage

Final_Price = Total_Cost + Margin_Amount
```

## Admin Configuration

The pricing rule builder provides:
- **Base costs per material type** (editable table)
- **Size multiplier matrix** (size → multiplier mapping)
- **Quantity tier table** (quantity ranges → discount percentages)
- **Finishing costs** (per finishing type → cost per unit)
- **Urgency multipliers** (delivery speed → multiplier)
- **Design service fees** (per product type → fixed fee)
- **Margin settings** (global or per-category margin percentage)

## WooCommerce Price Display

- Product page shows an interactive price calculator
- Price updates dynamically as customer selects options (via AJAX)
- Cart shows final calculated price per line item
- Price breakdown tooltip available

---

# 7. Database Design

## Custom Tables

### Core Tables

```sql
-- Materials
pfp_materials (
    id, name, code, category_id, unit, quantity, min_alert_qty,
    purchase_cost, supplier_id, status, created_at, updated_at
)

pfp_material_categories (
    id, name, slug, parent_id, description
)

pfp_stock_movements (
    id, material_id, type [in|out|adjustment], quantity,
    reference_type, reference_id, reason, user_id, created_at
)

pfp_material_product_map (
    id, material_id, product_id, quantity_per_unit, unit
)

-- Suppliers
pfp_suppliers (
    id, name, company, phone, email, city, address,
    relationship_type, payment_terms, performance_rating,
    status, created_at, updated_at
)

pfp_purchase_orders (
    id, supplier_id, status, total_amount, notes,
    ordered_at, received_at, created_at, updated_at
)

pfp_purchase_order_items (
    id, purchase_order_id, material_id, quantity, unit_price, total_price
)

pfp_supplier_payments (
    id, supplier_id, purchase_order_id, amount, method,
    reference, paid_at, created_at
)

-- Distributors
pfp_distributors (
    id, name, company, phone, email, city, address,
    territory, commission_rate, status, created_at, updated_at
)

-- Pricing
pfp_pricing_rules (
    id, name, product_category, rule_type, conditions JSON,
    multiplier, fixed_amount, priority, status, created_at
)

pfp_pricing_tiers (
    id, rule_id, min_qty, max_qty, discount_percentage
)

pfp_pricing_modifiers (
    id, name, type [size|material|finishing|urgency|sides],
    option_value, modifier_type [multiplier|fixed],
    modifier_value, created_at
)

-- Quotes
pfp_quotes (
    id, customer_id, status, total_amount, valid_until,
    notes, converted_order_id, created_at, updated_at
)

pfp_quote_items (
    id, quote_id, product_id, description, quantity,
    unit_price, total_price, specifications JSON
)

-- Production
pfp_production_jobs (
    id, order_id, order_item_id, status, assigned_to,
    machine, priority, estimated_time, actual_time,
    technical_notes, started_at, completed_at, created_at
)

pfp_production_checklists (
    id, job_id, item_text, is_checked, checked_by, checked_at
)

pfp_production_logs (
    id, job_id, from_status, to_status, user_id, notes, created_at
)

-- Artwork
pfp_artwork_files (
    id, order_id, order_item_id, customer_id, file_path,
    original_filename, file_type, file_size, version,
    status [pending|approved|rejected], reviewed_by,
    reviewed_at, created_at
)

pfp_artwork_comments (
    id, artwork_id, user_id, comment, created_at
)

-- Finance
pfp_income (
    id, order_id, amount, payment_method, reference,
    category, notes, received_at, created_at
)

pfp_expenses (
    id, category_id, amount, description, payment_method,
    reference, receipt_file, expense_date, created_at
)

pfp_expense_categories (
    id, name, slug, parent_id
)

pfp_invoices (
    id, order_id, customer_id, invoice_number, total_amount,
    tax_amount, status [draft|sent|paid|overdue|cancelled],
    due_date, paid_at, created_at
)

pfp_payments (
    id, invoice_id, amount, method, reference, paid_at, created_at
)

-- CRM
pfp_customer_notes (
    id, customer_id, user_id, note, type, created_at
)

pfp_loyalty_points (
    id, customer_id, points, type [earned|redeemed],
    reference, created_at
)

-- Delivery
pfp_deliveries (
    id, order_id, assigned_to, status, delivery_zone_id,
    tracking_ref, delivery_cost, notes, proof_photo,
    scheduled_at, delivered_at, created_at
)

pfp_delivery_zones (
    id, name, city, region, base_cost, estimated_days, status
)

-- Notifications
pfp_notification_templates (
    id, event_type, channel [email|sms|whatsapp],
    subject, body, is_active, created_at
)

pfp_notification_log (
    id, template_id, recipient, channel, status [sent|failed],
    sent_at, error_message
)
```

### WordPress Native Storage

| Data | Storage Method |
|------|---------------|
| Products | WooCommerce post type `product` with custom meta |
| Orders | WooCommerce orders (HPOS-compatible) |
| Customers | WordPress users with custom meta |
| Product categories | WooCommerce taxonomies |
| Plugin settings | `wp_options` table |
| User roles/caps | WordPress capabilities system |

---

# 8. User Roles

| Role | Slug | Permissions | Screens |
|------|------|-------------|---------|
| Super Admin | `pfp_super_admin` | Everything | All screens |
| Manager | `pfp_manager` | All except settings deletion, role management | Dashboard, Orders, Production, Finance, Reports, CRM |
| Sales Agent | `pfp_sales_agent` | Create quotes, manage orders, CRM | Quotes, Orders, CRM, Products (view) |
| Designer | `pfp_designer` | Artwork management, production design stage | Files, Production (design stage only) |
| Production Staff | `pfp_production_staff` | Production workflow management | Production, Inventory (view) |
| Accountant | `pfp_accountant` | Financial management | Finance, Invoices, Reports (financial) |
| Delivery Staff | `pfp_delivery_staff` | Delivery management | Deliveries assigned to them |
| Customer | `customer` | WooCommerce customer + order tracking, file upload | Frontend My Account, Order Tracking |

---

# 9. Setup Wizard Flow

1. **Bienvenue** — Welcome screen, plugin overview
2. **Informations entreprise** — Business name, address, ICE, RC, phone, email, logo
3. **Paramètres régionaux** — Currency (MAD), language (French), timezone (Africa/Casablanca)
4. **Modules** — Enable/disable modules
5. **Catégories produits** — Create default product categories
6. **Génération produits** — Auto-create 20 product types (select which ones)
7. **Matières premières** — Setup initial material catalog
8. **Tarification** — Configure base pricing rules and margins
9. **Fournisseurs** — Add first supplier(s)
10. **Notifications** — Configure email settings, notification preferences
11. **Terminé** — Setup complete, link to dashboard

---

# 10. Plugin Architecture

## Folder Structure

```
printflow-pro/
├── printflow-pro.php                 (main plugin file)
├── uninstall.php                     (cleanup on uninstall)
├── composer.json
├── package.json
├── webpack.config.js
├── readme.txt
│
├── includes/
│   ├── class-printflow-pro.php       (main plugin class)
│   ├── class-pfp-activator.php       (activation logic)
│   ├── class-pfp-deactivator.php     (deactivation logic)
│   ├── class-pfp-installer.php       (DB tables, defaults)
│   ├── class-pfp-loader.php          (hook/filter loader)
│   ├── class-pfp-i18n.php            (internationalization)
│   ├── class-pfp-roles.php           (roles & capabilities)
│   │
│   ├── modules/
│   │   ├── class-pfp-dashboard.php
│   │   ├── class-pfp-products.php
│   │   ├── class-pfp-pricing-engine.php
│   │   ├── class-pfp-file-manager.php
│   │   ├── class-pfp-quotes.php
│   │   ├── class-pfp-orders.php
│   │   ├── class-pfp-production.php
│   │   ├── class-pfp-inventory.php
│   │   ├── class-pfp-suppliers.php
│   │   ├── class-pfp-distributors.php
│   │   ├── class-pfp-finance.php
│   │   ├── class-pfp-crm.php
│   │   ├── class-pfp-delivery.php
│   │   ├── class-pfp-notifications.php
│   │   ├── class-pfp-reports.php
│   │   └── class-pfp-settings.php
│   │
│   ├── admin/
│   │   ├── class-pfp-admin.php       (admin hooks & menus)
│   │   ├── class-pfp-admin-menu.php
│   │   └── views/                    (admin page templates)
│   │       ├── dashboard.php
│   │       ├── products/
│   │       ├── pricing/
│   │       ├── quotes/
│   │       ├── orders/
│   │       ├── production/
│   │       ├── inventory/
│   │       ├── suppliers/
│   │       ├── distributors/
│   │       ├── finance/
│   │       ├── crm/
│   │       ├── delivery/
│   │       ├── notifications/
│   │       ├── reports/
│   │       ├── settings/
│   │       └── setup-wizard/
│   │
│   ├── frontend/
│   │   ├── class-pfp-frontend.php
│   │   ├── class-pfp-shortcodes.php
│   │   └── views/                    (frontend templates)
│   │       ├── pricing-calculator.php
│   │       ├── file-upload.php
│   │       ├── quote-form.php
│   │       ├── order-tracking.php
│   │       └── my-account/
│   │
│   ├── api/
│   │   ├── class-pfp-rest-api.php
│   │   ├── class-pfp-rest-pricing.php
│   │   ├── class-pfp-rest-production.php
│   │   └── class-pfp-rest-inventory.php
│   │
│   ├── woocommerce/
│   │   ├── class-pfp-wc-integration.php
│   │   ├── class-pfp-wc-order-statuses.php
│   │   ├── class-pfp-wc-product-fields.php
│   │   ├── class-pfp-wc-checkout.php
│   │   └── class-pfp-wc-emails.php
│   │
│   ├── data/
│   │   ├── products-catalog.json     (predefined products)
│   │   ├── materials-catalog.json    (predefined materials)
│   │   └── pricing-defaults.json     (default pricing rules)
│   │
│   └── traits/
│       ├── trait-pfp-singleton.php
│       └── trait-pfp-has-meta.php
│
├── assets/
│   ├── css/
│   │   ├── admin.css
│   │   └── frontend.css
│   ├── js/
│   │   ├── admin/
│   │   │   ├── dashboard.js
│   │   │   ├── pricing-rules.js
│   │   │   ├── production-kanban.js
│   │   │   └── reports-charts.js
│   │   └── frontend/
│   │       ├── pricing-calculator.js
│   │       ├── file-upload.js
│   │       └── order-tracking.js
│   └── images/
│       └── logo.png
│
├── languages/
│   ├── printflow-pro-fr_FR.po
│   └── printflow-pro-fr_FR.mo
│
├── templates/
│   ├── emails/
│   │   ├── order-confirmation.php
│   │   ├── order-status-changed.php
│   │   ├── quote-sent.php
│   │   ├── file-approved.php
│   │   └── low-stock-alert.php
│   └── pdf/
│       ├── invoice.php
│       └── quote.php
│
└── vendor/                           (Composer dependencies)
```

## Technical Patterns

- **OOP with service-based architecture** — Each module is a self-contained service class
- **Singleton pattern** for core services (DB, Settings)
- **WordPress hooks/filters** for all integrations
- **AJAX handlers** with nonce verification for all admin/frontend interactions
- **REST API** for dynamic pricing calculator and production board
- **WP Cron** for scheduled tasks (reports, stock alerts, notification digests)
- **Prepared statements** for all database queries (SQL injection prevention)
- **Capability checks** on every admin page and AJAX handler
- **Nonce verification** on all forms
- **Data sanitization/escaping** following WordPress VIP standards

---

# 11. MVP vs Advanced Version

## MVP (Phase 1-2)

| Feature | Priority |
|---------|----------|
| Plugin core, activation, DB setup | Critical |
| Setup wizard | Critical |
| Product management + auto-creation | Critical |
| Dynamic pricing engine (basic) | Critical |
| File upload (basic) | Critical |
| WooCommerce integration (orders, statuses) | Critical |
| Dashboard (basic widgets) | High |
| Order management with custom statuses | High |
| Inventory / materials management | High |
| Basic financial tracking (income from orders) | High |
| Basic notifications (email on status change) | High |
| French translations for frontend | Critical |

## Advanced (Phase 3-4)

| Feature | Priority |
|---------|----------|
| Production kanban board | Medium |
| Full CRM with loyalty program | Medium |
| Quote management with PDF generation | Medium |
| Supplier management with purchase orders | Medium |
| Distributor/reseller management | Medium |
| Advanced financial reports & P&L | Medium |
| Delivery management with tracking | Medium |
| Advanced reporting with charts | Low |
| WhatsApp integration | Low |
| Scheduled report emails | Low |
| Advanced automation rules | Low |

---

# 12. Development Roadmap

## Phase 1: Foundation (Core + E-commerce)
- Plugin bootstrap, activation, DB schema
- Setup wizard
- Product management + auto-creation of 20 products
- WooCommerce integration (custom fields, statuses)
- Basic pricing engine
- File upload on product pages
- French frontend labels
- Basic admin dashboard

## Phase 2: Operations
- Full pricing engine with rule builder
- Order management with production statuses
- Inventory/materials management
- Stock movements and auto-deduction
- Basic income tracking from orders
- Email notifications
- Customer management (basic)

## Phase 3: Business Intelligence
- Production workflow (kanban board, job cards)
- Quote management with PDF
- Supplier management
- Expense tracking
- Full financial dashboard (P&L, margins)
- Advanced reporting with charts
- Delivery management

## Phase 4: Growth
- Full CRM with loyalty program
- Distributor management
- Advanced automation engine
- WhatsApp notifications
- Scheduled reports
- API for third-party integrations
- Performance optimization
- Multi-site support consideration

---

# 13. Automation Design

| Automation | Trigger | Action | WordPress Mechanism |
|-----------|---------|--------|---------------------|
| Auto-create products | Plugin setup wizard | Create WooCommerce products from JSON templates | Activation hook + WC API |
| Auto-generate variations | Product save | Generate variations from attribute combinations | `save_post_product` hook |
| Auto-calculate price | Product page load / AJAX | Calculate price from selected options | REST API + JS |
| Auto-deduct materials | Order status → "En cours d'impression" | Reduce material quantities | `woocommerce_order_status_changed` |
| Auto-create invoice | Order completed | Generate invoice record | `woocommerce_order_status_completed` |
| Auto-notify customer | Order status change | Send email notification | `woocommerce_order_status_changed` |
| Auto-alert low stock | Material quantity < threshold | Send admin email | WP Cron (hourly check) |
| Auto-weekly report | Every Monday 8:00 AM | Generate and email summary | WP Cron (weekly) |
| Auto-monthly report | 1st of month | Generate financial summary | WP Cron (monthly) |

---

# 14. Final Recommendations

## Architecture
- **Single plugin with modular internal architecture** is the best approach for maintainability and user experience
- Use WordPress coding standards and WooCommerce best practices
- Build with HPOS (High-Performance Order Storage) compatibility from day one

## WooCommerce Strategy
- Extend, don't replace WooCommerce functionality
- Use WooCommerce hooks and filters rather than overriding templates where possible
- Store printing-specific data in custom meta fields on WooCommerce entities
- Custom tables only for data that doesn't map to WooCommerce concepts (materials, suppliers, production jobs)

## Morocco-Specific Considerations
- Currency: MAD (Dirham marocain) — ensure WooCommerce currency is set
- Language: All frontend in French, admin labels in French with English fallback
- Tax: Morocco VAT (TVA) at 20% standard rate — configurable
- Legal: ICE number on invoices, RC (Registre de Commerce) display
- Delivery zones: Major Moroccan cities pre-configured
- Payment: Support for cash on delivery (Contre remboursement) — very common in Morocco
- WhatsApp: Primary communication channel for many Moroccan businesses

## Success Factors
1. Start with MVP — get the core e-commerce and pricing working first
2. Train staff on the system before going live
3. Use the setup wizard to reduce time-to-value
4. Monitor material consumption accuracy and adjust mappings
5. Build customer loyalty through order tracking transparency
6. Use reports to make data-driven pricing decisions
