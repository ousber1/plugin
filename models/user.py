"""Modèle pour la gestion des utilisateurs."""

import bcrypt

from db import get_connection


def authenticate(username, password):
    """Authentifie un utilisateur. Retourne le dict utilisateur ou None."""
    conn = get_connection()
    row = conn.execute(
        "SELECT id, username, password, role FROM users WHERE username = ?",
        (username,),
    ).fetchone()
    if row and bcrypt.checkpw(password.encode("utf-8"), row["password"].encode("utf-8")):
        return {"id": row["id"], "username": row["username"], "role": row["role"]}
    return None


def create_user(username, password, role="caissier"):
    """Crée un nouvel utilisateur. Retourne True si succès, False si doublon."""
    conn = get_connection()
    try:
        hashed = bcrypt.hashpw(password.encode("utf-8"), bcrypt.gensalt()).decode("utf-8")
        conn.execute(
            "INSERT INTO users (username, password, role) VALUES (?, ?, ?)",
            (username, hashed, role),
        )
        conn.commit()
        return True
    except Exception:
        return False


def list_cashiers():
    """Retourne la liste des caissiers."""
    conn = get_connection()
    rows = conn.execute(
        "SELECT id, username, created_at FROM users WHERE role = 'caissier' ORDER BY username"
    ).fetchall()
    return [dict(row) for row in rows]


def delete_user(user_id):
    """Supprime un utilisateur par son ID."""
    conn = get_connection()
    conn.execute("DELETE FROM users WHERE id = ? AND role = 'caissier'", (user_id,))
    conn.commit()


def change_password(user_id, new_password):
    """Change le mot de passe d'un utilisateur."""
    conn = get_connection()
    hashed = bcrypt.hashpw(new_password.encode("utf-8"), bcrypt.gensalt()).decode("utf-8")
    conn.execute("UPDATE users SET password = ? WHERE id = ?", (hashed, user_id))
    conn.commit()
