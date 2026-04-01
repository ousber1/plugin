"""Configuration et constantes de l'application CafePOS."""

import os

# Chemins
BASE_DIR = os.path.dirname(os.path.abspath(__file__))
DATA_DIR = os.path.join(BASE_DIR, "data")
RESOURCES_DIR = os.path.join(BASE_DIR, "resources")
DB_PATH = os.path.join(DATA_DIR, "cafepos.db")

# Catégories de produits
CATEGORIES = ["Café", "Boissons", "Snacks", "Repas"]

# Devise
CURRENCY = "MAD"
CURRENCY_SYMBOL = "MAD"

# Admin par défaut
DEFAULT_ADMIN_USERNAME = "admin"
DEFAULT_ADMIN_PASSWORD = "admin123"

# Paramètres par défaut du ticket
DEFAULT_SETTINGS = {
    "cafe_name": "Mon Café",
    "cafe_address": "123 Rue Example, Casablanca",
    "cafe_phone": "05XX-XXXXXX",
    "ticket_footer": "Merci pour votre visite !",
    "theme": "light",
}

# Produits de démonstration
DEFAULT_PRODUCTS = [
    ("Espresso", "Café", 12.00),
    ("Café au lait", "Café", 15.00),
    ("Cappuccino", "Café", 18.00),
    ("Café américain", "Café", 14.00),
    ("Thé à la menthe", "Boissons", 10.00),
    ("Jus d'orange", "Boissons", 15.00),
    ("Eau minérale", "Boissons", 5.00),
    ("Limonade", "Boissons", 12.00),
    ("Croissant", "Snacks", 8.00),
    ("Pain au chocolat", "Snacks", 10.00),
    ("Msemen", "Snacks", 5.00),
    ("Baghrir", "Snacks", 6.00),
    ("Tajine poulet", "Repas", 35.00),
    ("Sandwich mixte", "Repas", 25.00),
    ("Salade composée", "Repas", 20.00),
    ("Panini", "Repas", 22.00),
]

# Largeur du ticket (caractères)
TICKET_WIDTH = 48

# Couleurs pour les thèmes
COLORS_LIGHT = {
    "bg": "#FFFFFF",
    "surface": "#F5F0EB",
    "primary": "#4E342E",
    "accent": "#8D6E63",
    "text": "#212121",
    "text_secondary": "#757575",
    "success": "#2E7D32",
    "danger": "#C62828",
    "border": "#D7CCC8",
}

COLORS_DARK = {
    "bg": "#121212",
    "surface": "#1E1E1E",
    "primary": "#8D6E63",
    "accent": "#A1887F",
    "text": "#E0E0E0",
    "text_secondary": "#9E9E9E",
    "success": "#43A047",
    "danger": "#EF5350",
    "border": "#424242",
}
