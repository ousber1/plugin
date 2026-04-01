"""Modèle pour la gestion des commandes."""

from db import get_connection


def create_order(user_id, items, total, amount_paid, change_due):
    """
    Crée une commande avec ses articles.
    items: list of dict avec keys: product_id, product_name, unit_price, quantity
    Retourne l'ID de la commande.
    """
    conn = get_connection()
    cursor = conn.cursor()
    try:
        cursor.execute(
            "INSERT INTO orders (user_id, total, amount_paid, change_due) VALUES (?, ?, ?, ?)",
            (user_id, total, amount_paid, change_due),
        )
        order_id = cursor.lastrowid

        for item in items:
            subtotal = item["unit_price"] * item["quantity"]
            cursor.execute(
                """INSERT INTO order_items
                   (order_id, product_id, product_name, unit_price, quantity, subtotal)
                   VALUES (?, ?, ?, ?, ?, ?)""",
                (
                    order_id,
                    item["product_id"],
                    item["product_name"],
                    item["unit_price"],
                    item["quantity"],
                    subtotal,
                ),
            )
        conn.commit()
        return order_id
    except Exception:
        conn.rollback()
        raise


def get_orders(date_from=None, date_to=None):
    """Récupère les commandes avec filtre optionnel par date."""
    conn = get_connection()
    query = """
        SELECT o.id, o.total, o.amount_paid, o.change_due, o.created_at,
               u.username as caissier
        FROM orders o
        JOIN users u ON o.user_id = u.id
    """
    conditions = []
    params = []

    if date_from:
        conditions.append("date(o.created_at) >= ?")
        params.append(date_from)
    if date_to:
        conditions.append("date(o.created_at) <= ?")
        params.append(date_to)

    if conditions:
        query += " WHERE " + " AND ".join(conditions)
    query += " ORDER BY o.created_at DESC"

    rows = conn.execute(query, params).fetchall()
    return [dict(row) for row in rows]


def get_order_details(order_id):
    """Récupère les détails complets d'une commande."""
    conn = get_connection()
    order = conn.execute(
        """SELECT o.id, o.total, o.amount_paid, o.change_due, o.created_at,
                  u.username as caissier
           FROM orders o
           JOIN users u ON o.user_id = u.id
           WHERE o.id = ?""",
        (order_id,),
    ).fetchone()

    if not order:
        return None

    items = conn.execute(
        """SELECT product_name, unit_price, quantity, subtotal
           FROM order_items WHERE order_id = ?""",
        (order_id,),
    ).fetchall()

    return {
        "order": dict(order),
        "items": [dict(item) for item in items],
    }


def get_daily_stats():
    """Récupère les statistiques du jour."""
    conn = get_connection()
    row = conn.execute(
        """SELECT COALESCE(SUM(total), 0) as total_ventes,
                  COUNT(*) as nb_commandes
           FROM orders
           WHERE date(created_at) = date('now', 'localtime')"""
    ).fetchone()
    return {"total_ventes": row["total_ventes"], "nb_commandes": row["nb_commandes"]}


def get_top_products(limit=5):
    """Récupère les produits les plus vendus."""
    conn = get_connection()
    rows = conn.execute(
        """SELECT product_name, SUM(quantity) as total_qty,
                  SUM(subtotal) as total_revenue
           FROM order_items oi
           JOIN orders o ON oi.order_id = o.id
           WHERE date(o.created_at) = date('now', 'localtime')
           GROUP BY product_name
           ORDER BY total_qty DESC
           LIMIT ?""",
        (limit,),
    ).fetchall()
    return [dict(row) for row in rows]
