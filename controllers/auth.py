"""Contrôleur d'authentification et gestion de session."""

from models.user import authenticate


class AuthController:
    """Gère la session utilisateur courante."""

    def __init__(self):
        self._current_user = None

    def login(self, username, password):
        """Tente de connecter un utilisateur. Retourne le dict user ou None."""
        user = authenticate(username, password)
        if user:
            self._current_user = user
        return user

    def logout(self):
        """Déconnecte l'utilisateur courant."""
        self._current_user = None

    @property
    def current_user(self):
        """Retourne l'utilisateur connecté."""
        return self._current_user

    @property
    def is_admin(self):
        """Vérifie si l'utilisateur est admin."""
        return self._current_user and self._current_user["role"] == "admin"

    @property
    def user_id(self):
        """Retourne l'ID de l'utilisateur connecté."""
        return self._current_user["id"] if self._current_user else None

    @property
    def username(self):
        """Retourne le nom de l'utilisateur connecté."""
        return self._current_user["username"] if self._current_user else ""
