"""Gestion de la base de données SQLite."""

import os
import sqlite3

import bcrypt

from config import (
    DATA_DIR,
    DB_PATH,
    DEFAULT_ADMIN_PASSWORD,
    DEFAULT_ADMIN_USERNAME,
    DEFAULT_PRODUCTS,
    DEFAULT_SETTINGS,
)

_connection = None


def get_connection():
    """Retourne une connexion singleton à la base de données."""
    global _connection
    if _connection is None:
        os.makedirs(DATA_DIR, exist_ok=True)
        _connection = sqlite3.connect(DB_PATH)
        _connection.row_factory = sqlite3.Row
        _connection.execute("PRAGMA foreign_keys = ON")
    return _connection


def init_db():
    """Crée les tables et insère les données par défaut."""
    conn = get_connection()
    cursor = conn.cursor()

    # Création des tables
    cursor.executescript("""
        CREATE TABLE IF NOT EXISTS users (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            username    TEXT    NOT NULL UNIQUE,
            password    TEXT    NOT NULL,
            role        TEXT    NOT NULL CHECK(role IN ('admin', 'caissier')),
            created_at  TEXT    NOT NULL DEFAULT (datetime('now', 'localtime'))
        );

        CREATE TABLE IF NOT EXISTS products (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            name        TEXT    NOT NULL,
            price       REAL    NOT NULL CHECK(price >= 0),
            category    TEXT    NOT NULL CHECK(category IN ('Café', 'Boissons', 'Snacks', 'Repas')),
            image_path  TEXT,
            active      INTEGER NOT NULL DEFAULT 1,
            created_at  TEXT    NOT NULL DEFAULT (datetime('now', 'localtime'))
        );

        CREATE TABLE IF NOT EXISTS orders (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id     INTEGER NOT NULL,
            total       REAL    NOT NULL,
            amount_paid REAL    NOT NULL,
            change_due  REAL    NOT NULL,
            created_at  TEXT    NOT NULL DEFAULT (datetime('now', 'localtime')),
            FOREIGN KEY (user_id) REFERENCES users(id)
        );

        CREATE TABLE IF NOT EXISTS order_items (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id     INTEGER NOT NULL,
            product_id   INTEGER NOT NULL,
            product_name TEXT    NOT NULL,
            unit_price   REAL    NOT NULL,
            quantity     INTEGER NOT NULL CHECK(quantity > 0),
            subtotal     REAL    NOT NULL,
            FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
            FOREIGN KEY (product_id) REFERENCES products(id)
        );

        CREATE TABLE IF NOT EXISTS settings (
            key   TEXT PRIMARY KEY,
            value TEXT NOT NULL
        );
    """)

    # Insertion de l'admin par défaut
    existing = cursor.execute(
        "SELECT id FROM users WHERE username = ?", (DEFAULT_ADMIN_USERNAME,)
    ).fetchone()
    if not existing:
        hashed = bcrypt.hashpw(
            DEFAULT_ADMIN_PASSWORD.encode("utf-8"), bcrypt.gensalt()
        ).decode("utf-8")
        cursor.execute(
            "INSERT INTO users (username, password, role) VALUES (?, ?, ?)",
            (DEFAULT_ADMIN_USERNAME, hashed, "admin"),
        )

    # Paramètres par défaut
    for key, value in DEFAULT_SETTINGS.items():
        cursor.execute(
            "INSERT OR IGNORE INTO settings (key, value) VALUES (?, ?)",
            (key, value),
        )

    # Produits de démonstration
    existing_products = cursor.execute("SELECT COUNT(*) FROM products").fetchone()[0]
    if existing_products == 0:
        for name, category, price in DEFAULT_PRODUCTS:
            cursor.execute(
                "INSERT INTO products (name, category, price) VALUES (?, ?, ?)",
                (name, category, price),
            )

    conn.commit()
