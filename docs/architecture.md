# Organisation du projet

Le code PHP est organisé par fonctionnalité dans `src/`. `Kernel.php` reste à la racine.

| Module | Responsabilité |
| --- | --- |
| Accueil | Tableau de bord et entrée de l'application |
| Organisation | Sociétés, réseaux, centres, stations et équipements |
| Utilisateur | Comptes, connexion, activation, mots de passe et périmètres d'accès |
| Personnel | Salariés et rattachements |
| ParcAutomobile | Véhicules et certificats de cession |
| Encours | Encours bancaires et montants annuels |
| Organigramme | Documents, import et publication des organigrammes |
| Notification | Notifications et destinataires |
| Import | Acquisition CSV/SFTP, données brutes, historique et orchestration quotidienne |
| SuiviActivite | Synthèses, analyses, indicateurs et requêtes de suivi |
| ReleveLavage | Relevés journaliers, recettes et transactions de portiques importées |
| OutilsReseaux | Catalogue des liens et outils réseaux |
| Support | Formulaire de contact support |
| Shared | Mécanismes techniques communs et composition Twig de l'interface globale |

## Structure d'un module

Créer uniquement les sous-dossiers utiles : `Controller`, `Entity`, `Repository`, `Form`, `Service`, `Command`, `Enum`, `ViewModel` et, si nécessaire, `Contract`.

Le namespace suit le chemin : `src/Encours/Entity/EncoursBancaire.php` correspond à `App\Encours\Entity\EncoursBancaire`.

Les entités et leurs repositories appartiennent au même module. Le fait qu'une classe soit utilisée par plusieurs modules ne justifie pas son déplacement dans `Shared`. Par exemple, `Centre` reste dans `Organisation`, et `UserScopeResolver` dans `Utilisateur/Security`.

Les modules peuvent utiliser les services et les entités de leur propriétaire via l'injection de dépendances. Éviter les cycles entre modules et les abstractions sans besoin concret. `Shared/List`, `Shared/Pagination`, `Shared/Money` et `Shared/Database` regroupent les mécanismes indépendants du métier. `Shared/Twig/AppExtension` assemble les données globales de l'interface à partir des services des modules ; les traitements métier restent dans ces services.

## Ajouter une fonctionnalité

1. Choisir un module existant selon la responsabilité métier, ou créer un nouveau module si elle est distincte.
2. Conserver la correspondance PSR-4 `App\` vers `src/` déjà présente dans `composer.json`.
3. Les services sont découverts dans tout `src/` par `config/services.yaml`. Les routes par attributs utilisent `routing.controllers` dans `config/routes.yaml`.
4. Pour un module contenant des entités, déclarer son répertoire et son préfixe dans `config/packages/doctrine.yaml`, sur le modèle des autres modules.
5. Placer les tests dans `tests/Unit/<Module>/` ou `tests/Integration/<Module>/`.

MakerBundle accepte un namespace absolu pour générer directement un contrôleur dans un module (exemple PowerShell) :

```powershell
php bin/console make:controller '\App\Encours\Controller\ExempleController'
```

Les templates, assets, migrations et configurations restent à la racine du projet. Les templates sont regroupés par module comme le code PHP ; les noms et URLs des routes restent inchangés.

## Templates

Chaque module utilise son dossier dans `templates/` : `accueil`, `utilisateur`, `organisation`, `personnel`, `parc_automobile`, `encours`, `organigramme`, `import`, `suivi_activite`, `releve_lavage`, `outils_reseaux` et `support`.

- `organisation/centres`, `organisation/societes` et `organisation/stations` contiennent les pages de gestion correspondantes.
- `releve_lavage` contient les pages et fragments des relevés, ainsi que leurs résumés et graphiques de recettes. Les pages de gestion des stations peuvent inclure ces fragments sans les dupliquer.
- `utilisateur/security`, `utilisateur/reset_password` et `utilisateur/emails` regroupent la connexion, la réinitialisation et les emails liés aux comptes.
- `import/cts`, `import/astikoto` et `import/health` distinguent les deux parcours d'import et leur suivi.
- Les pages d'impression restent avec leur fonctionnalité. Les deux mises en page communes sont `shared/print/_list_base.html.twig` et `shared/print/_analytics_base.html.twig`.
- `shared` contient la navigation, les styles communs et les icônes SVG. Les filtres spécifiques au suivi restent dans `suivi_activite`.
- `base.html.twig` reste à la racine des templates.

Dans les appels `render()`, les emails `htmlTemplate()` et les inclusions/héritages Twig, utiliser le chemin du module propriétaire. Un fragment ne rejoint pas automatiquement `shared` parce qu'une autre fonctionnalité le réutilise.

## Vérifications

```powershell
php vendor/phpunit/phpunit/phpunit
php bin/console lint:container
php bin/console lint:yaml config
php bin/console debug:router
```

Les tests de persistance utilisent une base MySQL distincte, suffixée `_test` par la configuration Doctrine. Les tests de relevés ouvrent une transaction et l'annulent après chaque test. Les tests d'import utilisent une base SQLite en mémoire, et les tests SFTP un répertoire local temporaire.

Pour initialiser une base de test neuve :

```powershell
php bin/console doctrine:database:create --env=test
php bin/console doctrine:schema:create --env=test
```

Un changement de namespace impose de reconstruire les caches utilisés, notamment le mode sans débogage employé par les sous-commandes du traitement quotidien :

```powershell
php bin/console cache:clear --env=dev
php bin/console cache:clear --env=dev --no-debug
```

En production, reconstruire le cache de l'environnement `prod`. Le déplacement de `User` nécessite une nouvelle connexion pour les sessions qui contiennent encore l'ancien nom de classe.
