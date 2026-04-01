"""Modèle pour la gestion des produits."""

from db import get_connection


def list_products(category=None, active_only=True):
    """Liste les produits, optionnellement filtrés par catégorie."""
    conn = get_connection()
    query = "SELECT id, name, price, category, image_path, active FROM products"
    conditions = []
    params = []

    if active_only:
        conditions.append("active = 1")
    if category:
        conditions.append("category = ?")
        params.append(category)

    if conditions:
        query += " WHERE " + " AND ".join(conditions)
    query += " ORDER BY category, name"

    rows = conn.execute(query, params).fetchall()
    return [dict(row) for row in rows]


def get_product(product_id):
    """Récupère un produit par son ID."""
    conn = get_connection()
    row = conn.execute(
        "SELECT id, name, price, category, image_path, active FROM products WHERE id = ?",
        (product_id,),
    ).fetchone()
    return dict(row) if row else None


def create_product(name, category, price, image_path=None):
    """Crée un nouveau produit. Retourne l'ID du produit."""
    conn = get_connection()
    cursor = conn.execute(
        "INSERT INTO products (name, category, price, image_path) VALUES (?, ?, ?, ?)",
        (name, category, price, image_path),
    )
    conn.commit()
    return cursor.lastrowid


def update_product(product_id, name, category, price, image_path=None):
    """Met à jour un produit existant."""
    conn = get_connection()
    conn.execute(
        "UPDATE products SET name = ?, category = ?, price = ?, image_path = ? WHERE id = ?",
        (name, category, price, image_path, product_id),
    )
    conn.commit()


def delete_product(product_id):
    """Désactive un produit (soft delete)."""
    conn = get_connection()
    conn.execute("UPDATE products SET active = 0 WHERE id = ?", (product_id,))
    conn.commit()


def restore_product(product_id):
    """Réactive un produit."""
    conn = get_connection()
    conn.execute("UPDATE products SET active = 1 WHERE id = ?", (product_id,))
    conn.commit()
