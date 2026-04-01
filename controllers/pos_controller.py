"""Contrôleur du panier et des opérations POS."""

from models.order import create_order


class CartController:
    """Gère le panier en mémoire et la validation des commandes."""

    def __init__(self):
        # Liste d'articles: {product_id, product_name, unit_price, quantity}
        self._items = []

    @property
    def items(self):
        """Retourne la liste des articles du panier."""
        return self._items

    def add_item(self, product):
        """Ajoute un produit au panier ou incrémente la quantité."""
        for item in self._items:
            if item["product_id"] == product["id"]:
                item["quantity"] += 1
                return
        self._items.append({
            "product_id": product["id"],
            "product_name": product["name"],
            "unit_price": product["price"],
            "quantity": 1,
        })

    def remove_item(self, index):
        """Supprime un article du panier par son index."""
        if 0 <= index < len(self._items):
            self._items.pop(index)

    def update_quantity(self, index, quantity):
        """Met à jour la quantité d'un article."""
        if 0 <= index < len(self._items):
            if quantity <= 0:
                self._items.pop(index)
            else:
                self._items[index]["quantity"] = quantity

    def get_total(self):
        """Calcule le total du panier."""
        return sum(item["unit_price"] * item["quantity"] for item in self._items)

    def clear(self):
        """Vide le panier."""
        self._items.clear()

    def is_empty(self):
        """Vérifie si le panier est vide."""
        return len(self._items) == 0

    def validate_order(self, user_id, amount_paid):
        """
        Valide et enregistre la commande.
        Retourne (order_id, change_due) ou lève une exception.
        """
        if self.is_empty():
            raise ValueError("Le panier est vide")

        total = self.get_total()
        if amount_paid < total:
            raise ValueError("Le montant payé est insuffisant")

        change_due = round(amount_paid - total, 2)
        order_id = create_order(
            user_id=user_id,
            items=self._items,
            total=total,
            amount_paid=amount_paid,
            change_due=change_due,
        )
        self.clear()
        return order_id, change_due
