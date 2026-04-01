"""Modèle pour les paramètres de l'application."""

from db import get_connection


def get_setting(key):
    """Récupère la valeur d'un paramètre."""
    conn = get_connection()
    row = conn.execute("SELECT value FROM settings WHERE key = ?", (key,)).fetchone()
    return row["value"] if row else None


def set_setting(key, value):
    """Définit la valeur d'un paramètre."""
    conn = get_connection()
    conn.execute(
        "INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)",
        (key, str(value)),
    )
    conn.commit()


def get_all_settings():
    """Récupère tous les paramètres sous forme de dictionnaire."""
    conn = get_connection()
    rows = conn.execute("SELECT key, value FROM settings").fetchall()
    return {row["key"]: row["value"] for row in rows}
