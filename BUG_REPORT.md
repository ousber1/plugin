# BERRADI PRINT - Security & Bug Report

## Executive Summary
Found **11 critical CSRF vulnerabilities**, **1 data integrity issue**, **1 authentication issue**, and **2 data loss issues**.

---

## Critical Issues

### 1. MISSING CSRF TOKENS ON GET-BASED ACTIONS

#### **[CRITICAL]** Admin Login - Missing CSRF Protection
- **File**: [admin/pages/login.php](admin/pages/login.php#L1)
- **Impact**: CSRF attack allowing unauthorized admin account compromise
- **Lines**: 8-23 (POST handler with NO verifyCsrf call)
- **Issue**: Admin login form accepts POST requests without CSRF token validation
- **Fix**: Add `verifyCsrf();` call at line 8 in the POST handler

---

### 2. GET-BASED DESTRUCTIVE OPERATIONS WITHOUT CSRF TOKENS

These operations modify/delete data via GET requests with only ID validation, vulnerable to:
- Session fixation attacks
- Automatic form submission attacks
- Malicious link clicks

#### **Product Management** - [admin/pages/produits.php](admin/pages/produits.php)
- **Lines 8-9**: Deactivate product - `action=supprimer`
- **Lines 14-15**: Activate product - `action=activer`  
- **Lines 21-22**: Toggle popular - `action=populaire`
- **Issue**: No CSRF token on any GET action
- **Fix**: Convert to POST with CSRF tokens OR add CSRF token validation

#### **Quotes/Devis** - [admin/pages/devis.php](admin/pages/devis.php#L8)
- **Lines 8-15**: Change quote status - `action=envoye|accepte|refuse`
- **Issue**: Status changes via GET without CSRF protection
- **Fix**: Add CSRF token validation before status update

#### **Cities/Delivery** - [admin/pages/villes.php](admin/pages/villes.php#L27)
- **Line 27**: Delete city - `DELETE FROM villes_livraison`
- **Issue**: Permanent deletion via GET without CSRF
- **Fix**: Add CSRF token verification

#### **Pages Management** - [admin/pages/pages.php](admin/pages/pages.php)
- **Lines 25-26**: Delete page - `DELETE FROM pages WHERE id = ?`
- **Lines 32-34**: Toggle active status - GET action
- **Issue**: Both operations lack CSRF protection
- **Fix**: Move to POST with CSRF tokens

#### **Notifications** - [admin/pages/notifications.php](admin/pages/notifications.php)
- **Lines 8-10**: Mark single notification as read
- **Lines 12-19**: Mark all notifications as read
- **Issue**: Database updates via GET without CSRF
- **Fix**: Add CSRF token validation

#### **Stock Movement** - [admin/pages/stock.php](admin/pages/stock.php#L59)
- **Line 59**: Toggle stock tracking - `action=toggle_stock`
- **Issue**: No CSRF protection on stock status change
- **Fix**: Add CSRF token verification

#### **Expenses** - [admin/pages/depenses.php](admin/pages/depenses.php#L18)
- **Line 18-19**: Delete expense - `DELETE FROM depenses`
- **Issue**: Permanent deletion via GET without CSRF
- **Fix**: Add CSRF token validation

---

## Authentication & Authorization Issues

### 3. Cart Data Not Persisted for Logged-in Users
- **File**: [pages/panier.php](pages/panier.php#L1)
- **Issue**: Cart items stored ONLY in session `$_SESSION['panier']`, never persisted to database
- **Risk**: Cart lost if session expires; no multi-device cart sync
- **Recommendation**: 
  - Add `client_cart` or `panier_items` table
  - Save cart to DB when user adds items
  - Restore cart from DB on login
  - Lines affected: Functions `getPanier()`, `ajouterAuPanier()` in [includes/functions.php](includes/functions.php#L159)

---

## Data Integrity Issues

### 4. Ville Field Not Updated During Checkout
- **File**: [pages/commander.php](pages/commander.php#L48)
- **Lines 48, 53**: Empty string `''` stored in `clients.ville` field instead of actual city
- **Current Code**: 
  ```php
  $db->prepare("UPDATE clients SET nom=?, prenom=?, email=?, adresse=?, ville=?, code_postal=? WHERE id=?")
     ->execute([$nom, $prenom, $email, $adresse, '', $code_postal, $client_id]);
  ```
- **Impact**: Client city information lost after order; can't query by city
- **Fix**: Replace `''` with actual city name (or ID if schema changed)

---

## Form Validation & Field Issues

### 5. Inconsistent Field Naming in Devis Form
- **File**: [admin/pages/devis_nouveau.php](admin/pages/devis_nouveau.php)
- **Issue**: Form uses `prenom` and `nom` separately, but other pages use different conventions
- **Recommendation**: Standardize field names across all forms

---

## Devis/Quote Form Issues

### 6. Pre-fill Not Complete for Logged-in Users
- **File**: [pages/devis.php](pages/devis.php#L44)
- **Issue**: Entreprise field pre-filled with `nom_entreprise` (individual field) instead of actual company name
- **Line 44**: `value="<?= htmlspecialchars($_POST['entreprise'] ?? ($_client_dv['nom_entreprise'] ?? '')) ?>"`
- **Recommendation**: Verify `nom_entreprise` exists in DB schema or use correct field

---

## SEO Admin Pages - Status Check

### ✅ SEO CRUD Operations - PROPERLY SECURED
- **File**: [admin/pages/seo_dynamique.php](admin/pages/seo_dynamique.php)
- **Status**: All CREATE/UPDATE operations properly use CSRF tokens
- **Lines**: 16, 176, 209 - All have `<?= csrfField() ?>`
- **No issues found**

### ✅ SEO Kit Configuration
- **File**: [admin/pages/seo_kit.php](admin/pages/seo_kit.php#L11)
- **Status**: POST handler includes `verifyCsrf()` at line 11
- **No issues found**

---

## Error Handling Issues

### 7. Generic Error Messages in Login
- **File**: [admin/pages/login.php](admin/pages/login.php#L23)
- **Issue**: Same error message for "user not found" and "invalid password"
- **Impact**: OK for security, but user can't distinguish issues
- **Current**: `'Email ou mot de passe incorrect.'`
- **Recommendation**: Keep for security; this is actually good practice

---

## Redirect Guard Issues

### 8. Missing Redirect Guards on Admin Pages
- **File**: [admin/index.php](admin/index.php#L18-L28)
- **Status**: Properly checks `estConnecte()` before loading pages
- **Lines 19-20**: Correctly redirects unauthenticated users to login
- **No critical issues**, but consider checking individual page access levels

---

## SQL Injection Risk Assessment

### ✅ Prepared Statements Used Correctly
- All database operations use parameterized queries
- Input sanitization via `clean()` function
- **No SQL injection vulnerabilities found**

---

## Summary by Severity

| Severity | Count | Category |
|----------|-------|----------|
| 🔴 CRITICAL | 1 | Missing CSRF on Admin Login |
| 🔴 CRITICAL | 8 | Missing CSRF on Destructive GET Actions |
| 🟠 HIGH | 1 | Cart Data Loss on Session Timeout |
| 🟠 HIGH | 1 | Ville Field Data Loss on Checkout |
| 🟡 MEDIUM | 1 | Form Validation Inconsistencies |
| 🟢 LOW | 1 | Pre-fill Field Name Mismatch |

---

## Recommended Fixes (Priority Order)

### Priority 1: CSRF Protection
```php
// All GET-based destructive actions should use POST with CSRF:
// OLD (Vulnerable):
// <a href="?page=produits&action=supprimer&id=123">Delete</a>

// NEW (Secure):
// <form method="POST" class="d-inline">
//   <?= csrfField() ?>
//   <input type="hidden" name="action" value="supprimer">
//   <input type="hidden" name="id" value="123">
//   <button type="submit">Delete</button>
// </form>
```

### Priority 2: Cart Persistence
Add database cart table and modify [includes/functions.php](includes/functions.php) functions.

### Priority 3: Data Integrity
Fix [pages/commander.php](pages/commander.php#L48) empty ville field storage.

### Priority 4: Form Validation
Standardize field names and add validation on all forms.

---

## Files Requiring Immediate Changes

1. [admin/pages/login.php](admin/pages/login.php) - Add CSRF verification
2. [admin/pages/produits.php](admin/pages/produits.php) - Convert GET to POST with CSRF
3. [admin/pages/devis.php](admin/pages/devis.php) - Add CSRF protection
4. [admin/pages/villes.php](admin/pages/villes.php) - Add CSRF protection
5. [admin/pages/pages.php](admin/pages/pages.php) - Add CSRF protection
6. [admin/pages/notifications.php](admin/pages/notifications.php) - Add CSRF protection
7. [admin/pages/stock.php](admin/pages/stock.php) - Add CSRF protection
8. [admin/pages/depenses.php](admin/pages/depenses.php) - Add CSRF protection
9. [pages/commander.php](pages/commander.php) - Fix ville field
10. [pages/devis.php](pages/devis.php) - Add cart persistence
11. [includes/functions.php](includes/functions.php) - Add cart persistence functions

---

**Report Generated**: March 5, 2026  
**Codebase**: BERRADI PRINT v1.0  
**Status**: 🔴 Multiple Critical Issues Require Immediate Attention
