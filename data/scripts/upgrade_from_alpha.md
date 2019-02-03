Mise à jour depuis des versions de développement (< 3.1.0)
==========================================================


Cette procédure a été réalisée sur Omeka S 1.3.0.

1e étape
--------

- Préparer les modules dans le dossier `modules/`
  - Annotate commit 3403e41
  - Cartography commit 247af05 (HEAD, tag: 3.0.6-beta)
  - Supprimer le dossier Oronce Fine (le déplacer en dehors du dossier des modules : il dépend d’une version récente de Annotate).
- Base de données
  - SAUVEGARDER LA BASE
- Interface Omeka
  - Mise à jour d’Annotate
  - Puis mise à jour de Cartography.
- Faire une sauvegarde intermédiaire

2e étape
--------

- Préparer les modules dans le dossier `modules/`
  - Annotate : 0cb674e (tag: 3.1.0, origin/master)
  - Cartography 31c3409 (HEAD, tag: 3.0.9-beta)
- Interface Omeka
  - Mise à jour d’Annotate
  - Puis mise à jour de Cartography.
- Faire une sauvegarde intermédiaire

3e étape
--------

- Préparer les modules dans le dossier `modules/`
  - Cartography 1cccd84 (HEAD, tag: 3.0.11-beta)
- Interface Omeka
  - Activer le template par défaut pour "Templates to use for Describe" et "Templates to use for Locate" dans Admin > Paramètres généraux (Admin > Settings)
  - Mise à jour de Cartography
- Faire une sauvegarde intermédiaire

4e étape
--------

- Préparer les modules dans le dossier `modules/`
  - DataTypeGeometry (master, 3.1.0)
  - Cartography (master, 3.1.0)
- Interface Omeka
  - Installer DataTypeGeometry
    (ne pas lancer de process dans la page de configuration avant la mise à jour de Cartography ci-dessous)
  - Mise à jour de Cartography

5e étape
--------

Les annotations cartographiques sont désormais indexées dans deux tables spécifiques aux géometries (wkt/wkb) :  data_type_geometry et data_type_geography. Ce sont juste des index, les annotations demeurent des propriétés dans la table Omeka value. Il peut donc être reconstruit à tout moment via le module DataTypeGeometry.

Pour finaliser la mise à niveau, lancer la tâche `Upgrade old base`, qui lance toutes les sous-tâches nécessaires automatiquement. Vérifier dans les logs qu’elle est terminée (via Tâches > Tâche > Log) ou via le module Log.
