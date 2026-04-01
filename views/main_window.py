"""Fenêtre principale avec navigation par sidebar."""

import os

from PyQt5.QtCore import Qt, pyqtSignal
from PyQt5.QtWidgets import (
    QApplication,
    QButtonGroup,
    QHBoxLayout,
    QLabel,
    QMainWindow,
    QPushButton,
    QStackedWidget,
    QVBoxLayout,
    QWidget,
)

from config import RESOURCES_DIR
from controllers.auth import AuthController
from models.settings import get_setting, set_setting
from views.cashiers import CashierManagerWidget
from views.dashboard import DashboardWidget
from views.pos import POSWidget
from views.products import ProductManagerWidget
from views.sales import SalesHistoryWidget
from views.settings_view import SettingsWidget


class MainWindow(QMainWindow):
    """Fenêtre principale de l'application après connexion."""

    logout_requested = pyqtSignal()

    def __init__(self, auth_controller: AuthController):
        super().__init__()
        self.auth = auth_controller
        self._setup_ui()

    def _setup_ui(self):
        self.setWindowTitle("CafePOS")
        self.setMinimumSize(1200, 750)

        central = QWidget()
        self.setCentralWidget(central)
        main_layout = QHBoxLayout(central)
        main_layout.setContentsMargins(0, 0, 0, 0)
        main_layout.setSpacing(0)

        # --- Sidebar ---
        self.sidebar = QWidget()
        self.sidebar.setObjectName("sidebar")
        sidebar_layout = QVBoxLayout(self.sidebar)
        sidebar_layout.setContentsMargins(0, 0, 0, 0)
        sidebar_layout.setSpacing(0)

        # Titre sidebar
        title = QLabel("CafePOS")
        title.setObjectName("sidebar_title")
        sidebar_layout.addWidget(title)

        # Info utilisateur
        self.lbl_user = QLabel()
        self.lbl_user.setObjectName("sidebar_user")
        sidebar_layout.addWidget(self.lbl_user)

        sidebar_layout.addSpacing(10)

        # Boutons de navigation
        self.nav_group = QButtonGroup(self)
        self.nav_group.setExclusive(True)
        self.nav_buttons = []

        # Définition des pages par rôle
        self._pages_config = []
        if self.auth.is_admin:
            self._pages_config = [
                ("Tableau de bord", DashboardWidget),
                ("Point de vente", None),  # POSWidget - traité séparément
                ("Produits", ProductManagerWidget),
                ("Caissiers", CashierManagerWidget),
                ("Historique ventes", SalesHistoryWidget),
                ("Paramètres", SettingsWidget),
            ]
        else:
            self._pages_config = [
                ("Point de vente", None),
            ]

        for i, (label, _) in enumerate(self._pages_config):
            btn = QPushButton(f"  {label}")
            btn.setCheckable(True)
            btn.setCursor(Qt.PointingHandCursor)
            self.nav_group.addButton(btn, i)
            self.nav_buttons.append(btn)
            sidebar_layout.addWidget(btn)

        sidebar_layout.addStretch()

        # Bouton thème
        self.btn_theme = QPushButton()
        self.btn_theme.setObjectName("btn_theme")
        self.btn_theme.setCursor(Qt.PointingHandCursor)
        self._update_theme_button()
        self.btn_theme.clicked.connect(self._toggle_theme)
        sidebar_layout.addWidget(self.btn_theme)

        sidebar_layout.addSpacing(5)

        # Bouton déconnexion
        btn_logout = QPushButton("  Déconnexion")
        btn_logout.setObjectName("btn_danger")
        btn_logout.setCursor(Qt.PointingHandCursor)
        btn_logout.clicked.connect(self._on_logout)
        sidebar_layout.addWidget(btn_logout)

        sidebar_layout.addSpacing(10)

        main_layout.addWidget(self.sidebar)

        # --- Zone de contenu ---
        self.stack = QStackedWidget()
        main_layout.addWidget(self.stack)

        # Création des pages
        for i, (label, widget_class) in enumerate(self._pages_config):
            if label == "Point de vente":
                widget = POSWidget(self.auth)
            else:
                widget = widget_class()
            self.stack.addWidget(widget)

        # Connexion navigation
        self.nav_group.buttonClicked[int].connect(self._on_nav_clicked)

        # Sélection initiale
        if self.nav_buttons:
            self.nav_buttons[0].setChecked(True)
            self.stack.setCurrentIndex(0)

    def update_user_info(self):
        """Met à jour l'affichage de l'utilisateur."""
        role_label = "Administrateur" if self.auth.is_admin else "Caissier"
        self.lbl_user.setText(f"{self.auth.username} ({role_label})")

    def _on_nav_clicked(self, index):
        """Change la page affichée."""
        self.stack.setCurrentIndex(index)
        # Rafraîchir la page si elle a une méthode refresh
        widget = self.stack.widget(index)
        if hasattr(widget, "refresh"):
            widget.refresh()

    def _toggle_theme(self):
        """Bascule entre thème clair et sombre."""
        current = get_setting("theme") or "light"
        new_theme = "dark" if current == "light" else "light"
        set_setting("theme", new_theme)
        self._apply_theme(new_theme)
        self._update_theme_button()

    def _update_theme_button(self):
        """Met à jour le texte du bouton thème."""
        current = get_setting("theme") or "light"
        if current == "light":
            self.btn_theme.setText("  Mode sombre")
        else:
            self.btn_theme.setText("  Mode clair")

    def _apply_theme(self, theme_name):
        """Applique un thème QSS globalement."""
        qss_path = os.path.join(RESOURCES_DIR, f"{theme_name}.qss")
        if os.path.exists(qss_path):
            with open(qss_path, "r", encoding="utf-8") as f:
                QApplication.instance().setStyleSheet(f.read())

    def _on_logout(self):
        """Gère la déconnexion."""
        self.auth.logout()
        self.logout_requested.emit()

    def showEvent(self, event):
        """Appelé quand la fenêtre est affichée."""
        super().showEvent(event)
        self.update_user_info()
