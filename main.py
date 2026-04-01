"""CafePOS - Application POS pour coffee shop marocain.

Point d'entrée de l'application.
"""

import os
import sys

from PyQt5.QtCore import Qt
from PyQt5.QtWidgets import QApplication

from config import RESOURCES_DIR
from controllers.auth import AuthController
from db import init_db
from models.settings import get_setting


def load_theme(app):
    """Charge le thème depuis les paramètres."""
    theme = get_setting("theme") or "light"
    qss_path = os.path.join(RESOURCES_DIR, f"{theme}.qss")
    if os.path.exists(qss_path):
        with open(qss_path, "r", encoding="utf-8") as f:
            app.setStyleSheet(f.read())


def main():
    # Initialisation de la base de données
    init_db()

    # Création de l'application Qt
    app = QApplication(sys.argv)
    app.setApplicationName("CafePOS")

    # Attribut pour écrans haute résolution
    if hasattr(Qt, "AA_EnableHighDpiScaling"):
        QApplication.setAttribute(Qt.AA_EnableHighDpiScaling, True)

    # Charger le thème
    load_theme(app)

    # Contrôleur d'authentification partagé
    auth = AuthController()

    # Importer les vues ici pour éviter les imports circulaires
    from views.login import LoginWindow
    from views.main_window import MainWindow

    # Fenêtres
    login_window = LoginWindow(auth)
    main_window = None

    def on_login_success(user):
        """Callback après connexion réussie."""
        nonlocal main_window
        login_window.hide()

        # Recréer la fenêtre principale à chaque connexion
        # pour adapter l'interface au rôle
        if main_window is not None:
            main_window.close()
            main_window.deleteLater()

        main_window = MainWindow(auth)
        main_window.update_user_info()
        main_window.logout_requested.connect(on_logout)
        main_window.showMaximized()

    def on_logout():
        """Callback après déconnexion."""
        nonlocal main_window
        if main_window is not None:
            main_window.close()
            main_window.deleteLater()
            main_window = None

        # Recharger le thème au cas où il a changé
        load_theme(app)

        login_window.reset()
        login_window.show()

    # Connexion des signaux
    login_window.login_success.connect(on_login_success)

    # Afficher la fenêtre de connexion
    login_window.show()

    sys.exit(app.exec_())


if __name__ == "__main__":
    main()
