# Moteur de prix – règles, ordre d'application et arrondis

Code : `app/Services/Pricing/PricingEngine.php`, `Totals.php`, `DefinitionValidator.php`.

## Principes
* **Le serveur calcule toujours.** Le navigateur n'envoie que des choix (codes d'options, quantité, dimensions). Tout montant envoyé par le navigateur est ignoré. Le panier ne stocke aucun prix : il est recalculé à chaque affichage, au checkout et à la création d'un devis/commande.
* **Calcul décimal exact** (`brick/math` BigDecimal), jamais de nombres flottants.
* **Versions** : chaque produit a des grilles `pricing_rule_sets` versionnées (`draft` → `published` → `archived`). On modifie un brouillon, on le teste dans l'**aperçu**, puis on publie. Chaque ligne de devis/commande enregistre `rule_set_id`, `rule_version`, les libellés exacts des options, les dimensions, la quantité et le **détail figé** du prix (`breakdown`). Les documents émis sont figés dans `documents.snapshot`.

## Ordre déterministe (une ligne)
1. **Quantité** : valeur prédéfinie, ou quantité libre si autorisée (min/max/pas).
2. **Dimensions** : format prédéfini ou sur mesure ; cm → mm ; arrondi **au-dessus** au pas `rounding_mm` ; contrôle min/max. Dimensions sur mesure absentes = minimum autorisé.
3. **Options** dans l'ordre défini. `visible_if` / `available_if` ne peuvent référencer que `size` ou des options **déclarées avant** (vérifié à l'enregistrement). Option masquée = ignorée. Choix explicite indisponible = erreur expliquée (« … n'est pas compatible »). Choix absent = défaut, sinon premier disponible.
4. **Prix de base**
   * `tiers` (paliers) : parmi les paliers dont la condition `match` est satisfaite, on garde les **plus spécifiques** (le plus de conditions). Quantité exacte → prix du palier ; sinon prix unitaire du plus grand palier ≤ quantité × quantité (en dessous du premier palier : prix unitaire du premier palier).
   * `area` (m²) : surface par pièce arrondie au-dessus au pas `area_step_m2` (défaut 0,01 m²), plancher `min_area_m2` ; surface totale = × quantité ; tarif = tranche de plus grand `min_m2` ≤ surface totale (tranches filtrées par `match` comme pour les paliers).
5. **Suppléments des choix**, dans cet ordre de type : `per_m2` (× surface totale), `per_unit` (× quantité), `per_thousand` (× quantité / 1000), `fixed`, `setup` ; puis `setup_fee` du produit.
6. **Minimum de facturation** : si le sous-total < `min_charge`, une ligne « ajustement minimum » complète.
7. **Pourcentages** (`percent`, ex. express +35 %) calculés sur le sous-total après l'étape 6, **sans cumul** entre eux.
8. **Création graphique** (parcours « HO CREATIVE crée mon design ») : forfait du produit.

**Arrondi** : chaque composante est arrondie à 2 décimales (demi supérieur, HALF_UP) ; le total de la ligne est la **somme des composantes arrondies** (le détail affiché se recompose donc exactement). Prix unitaire = total / quantité à 4 décimales.

## Ordre au niveau du document (panier, devis, commande)
9. Sous-total HT = Σ lignes (après remises de ligne autorisées, motif obligatoire).
10. Code promo : pourcentage des lignes éligibles ou montant fixe plafonné.
11. Remise manuelle (rôle autorisé + motif, tracée dans l'audit).
12. Livraison : prix de la zone ; gratuite si le montant après remise ≥ seuil ; retrait atelier = 0.
13. TVA = taux × (HT après remises + livraison si « livraison taxable »), arrondie à 2 décimales.
14. Total TTC = HT après remises + livraison + TVA.

Taux de TVA, libellé et assujettissement de la livraison se règlent dans *Paramètres*. **Le taux par défaut (20 %) doit être validé par votre comptable.**

## Format de définition (JSON, éditeur visuel dans *Produits → Options & prix*)
```json
{
  "mode": "tiers | area",
  "quantity": {"presets": [100, 250], "custom": true, "min": 100, "max": 20000, "step": 50},
  "dimensions": {"enabled": true, "unit": "mm|cm", "custom": true, "min_w": 300, "max_w": 5000, "min_h": 300, "max_h": 50000, "rounding_mm": 10,
                 "presets": [{"code": "std", "label": {"fr": "85 × 55 mm", "ar": "…"}, "w": 85, "h": 55}]},
  "tiers": [{"qty": 100, "price": "90.00", "match": {"sides": ["rv"]}}],
  "area": {"area_step_m2": "0.01", "min_area_m2": "0.5", "rates": [{"min_m2": "0", "rate": "95"}, {"min_m2": "10", "rate": "80", "match": {"material": ["frontlit"]}}]},
  "setup_fee": "0", "min_charge": "150",
  "production": {"days": 3},
  "artwork": {"back_side_if": {"sides": ["rv"]}},
  "options": [{
    "code": "lamination", "label": {"fr": "Pelliculage", "ar": "التغليف"}, "display": "cards|select", "required": true, "default": "none",
    "visible_if": {"paper": ["couche350"]},
    "choices": [{"code": "mat", "label": {"fr": "Mat"}, "image": "/storage/…", "available_if": {"paper": ["couche350"]},
                 "modifiers": [{"type": "per_unit", "amount": "0.05"}, {"type": "fixed", "amount": "20"}],
                 "production_days": 1, "extra_days": 2}]
  }]
}
```
`production_days` remplace le délai (ex. express), `extra_days` s'ajoute (ex. vernis +2 j).

## Exemples vérifiés par les tests (`tests/Unit/PricingEngineTest.php`)
* 500 cartes recto-verso, pelliculage mat, express : 270,00 + 25,00 + 20,00 + 35 % × 315,00 (110,25) = **425,25 HT**.
* Bâche 123,4 × 45,6 cm → 1240 × 460 mm → 0,58 m² × 95 = 55,10 ; minimum 150 → **150,00 HT**.
* 3 bâches 4 × 2 m = 24 m² (tranche ≥ 10 m² à 80) + œillets 12/m² = **2 208,00 HT**.

« À partir de » = le plus petit total valide parmi formats × quantités prédéfinis × combinaisons d'options (borné à 3 000 calculs), recalculé à chaque publication.
