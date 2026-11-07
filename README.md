# Paiement Monetico pour WS Form

Ce plugin ajoute une action « Paiement Monetico » à WS Form PRO. La personne remplit le formulaire, paie par carte sur la page Monetico, et la soumission passe en `paid` quand Monetico confirme le paiement au site (l'IPN, ou « interface retour » dans leur doc).

Il existe parce que WS Form n'a aucune passerelle bancaire française dans son catalogue. Si ton client est au Crédit Mutuel ou au CIC, c'est Monetico.

Le plugin n'est affilié ni à WS Form, ni à Monetico, ni au Crédit Mutuel ou au CIC.

## Ce qu'il te faut

- WordPress 6.0+, PHP 7.4+
- WS Form PRO (la version gratuite n'a pas les actions personnalisées)
- un contrat Monetico : numéro de TPE, code société et clé de sécurité (40 caractères hexadécimaux, la « clé HMAC » du back-office Monetico)

## Mise en place

1. Installe et active le plugin.
2. Déclare ton compte dans **WS Form > Monetico** : TPE, code société, clé. La clé enregistrée n'est jamais réaffichée, laisse le champ vide pour la garder.
3. Sur la même page, copie l'URL de l'interface retour (`https://ton-site.tld/wp-json/wsform-monetico/v1/ipn`) et donne-la à Monetico.
4. Dans ton formulaire, ajoute l'action « Save to Submissions », puis « Paiement Monetico ». Choisis le compte, le champ qui contient le montant et le champ e-mail.
5. Laisse l'environnement sur « Test » tant que tu n'as pas fait un paiement complet avec une carte de test.

Tu peux aussi déclarer les comptes dans `wp-config.php`. Pour un même TPE, la constante l'emporte sur la page de réglages :

```php
define( 'WSFORM_MONETICO_ACCOUNTS', array(
	'1234567' => array(
		'company' => 'masociete',
		'key'     => '0123456789ABCDEF0123456789ABCDEF01234567',
	),
) );
```

Plusieurs comptes peuvent cohabiter, un par formulaire par exemple.

## Ce qui se passe pendant un paiement

1. À la soumission, l'action enregistre sur la soumission la référence (`WSF-{formulaire}-{soumission}-{horodatage}`), le montant, le TPE et un jeton d'accès aléatoire. Le statut passe en `pending`.
2. WS Form redirige vers une page intermédiaire (`/?wsform_monetico_redirect=…&monetico_key=…`) qui poste le formulaire signé vers Monetico. Il faut cette page parce que WS Form ne sait rediriger qu'en GET, et Monetico attend un POST.
3. Monetico appelle l'interface retour. Le plugin vérifie le sceau avec la clé du TPE reçu, puis vérifie que le TPE et le montant correspondent à ceux enregistrés à l'étape 1. La soumission passe en `paid` ou `failed`.
4. La personne revient sur tes URL de retour, si tu en as mis, avec la référence et le jeton dans l'URL. Le bloc de statut s'en sert pour afficher le bon paiement.

Le statut ne bouge qu'avec l'IPN. Le retour du navigateur ne prouve rien : la personne peut fermer l'onglet avant.

## Les choix de sécurité, et pourquoi

- **Aucune clé dans les formulaires.** WS Form recopie les réglages des actions dans l'export JSON, les clones, la version publiée du formulaire, et dans **chaque** ligne de soumission. Une clé rangée là finirait partout. L'action ne garde donc que le numéro de TPE.
- **Le montant attendu est figé à la soumission.** L'IPN est comparée à ce montant enregistré, jamais à une valeur relue dans le formulaire.
- **Une IPN rejouée ne change rien.** Un statut `paid` ne bouge plus, et le hook (donc les e-mails) ne part que si le statut change réellement. Un verrou par soumission empêche deux IPN simultanées de passer toutes les deux. Un `failed` peut encore devenir `paid`, parce que la personne peut réessayer avec une autre carte.
- **La page intermédiaire exige le jeton** et ne modifie jamais un paiement. Elle refuse de relancer un paiement déjà payé.
- **Les journaux** sont dans `wp-content/uploads/wsform-monetico/logs/`, un fichier par jour, gardés 30 jours. Comme un `.htaccess` ne protège rien sur nginx, le nom de chaque fichier se termine par un secret tiré des clés de salage du site (même principe que WooCommerce). Le sceau, les clés, les e-mails et les IP ne sont jamais écrits.

## Hooks

```php
/**
 * Se déclenche une seule fois par changement de statut, jamais sur une IPN rejouée.
 *
 * @param int    $submission_id   ID de la soumission WS Form.
 * @param string $new_status      "paid" ou "failed".
 * @param array  $fields          Champs de l'IPN, sans le sceau.
 * @param string $previous_status Statut avant l'IPN.
 */
do_action( 'wsform_monetico_payment_updated', $submission_id, $new_status, $fields, $previous_status );
```

Le préfixe `wsform_monetico_` est celui du plugin. Le préfixe `wsf_`, lui, appartient à WS Form : je ne l'utilise pas, pour ne pas entrer en collision avec un hook qu'ils ajouteraient un jour.

Pour les e-mails : `wsform_monetico_email_subject` et `wsform_monetico_email_content` reçoivent le texte, l'ID de soumission, les réglages de l'action et `email` (payeur) ou `admin_email` (équipe).

Les variables des e-mails : `{reference}`, `{amount}`, `{date}`, `{form_name}`, `{submission_id}`, et `{field:ID}` pour n'importe quel champ. Les valeurs saisies dans le formulaire sont échappées dans le corps HTML.

## Bloc et shortcode de statut

Le bloc « Statut de paiement Monetico » se pose sur tes pages de retour. Sans réglage, il affiche le paiement de la personne qui revient de Monetico (référence et jeton lus dans l'URL). Le shortcode fait la même chose :

```
[wsform_monetico_status showdetails="true" messagetype="auto"]
```

`messagetype` accepte `auto`, `success`, `failure` ou `pending`.

## Développer

```bash
composer install
composer lint      # PHPCS, règles WordPress
composer test      # tests unitaires (sceau, montants), sans WordPress
composer build     # dist/wsform-monetico-X.Y.Z.zip, sans les tests ni les outils de dev
```

Les tests d'intégration ont besoin d'un WordPress avec WS Form PRO, ce qu'un runner de CI ne peut pas installer. Ils tournent sur un site local avec WP-CLI :

```bash
wp eval-file wp-content/plugins/wsform-monetico/tests/integration/run.php
```

Ils créent leurs propres comptes fictifs et simulent les IPN en calculant le sceau avec `hash_hmac`, indépendamment du code du plugin. Aucun identifiant Monetico réel n'est nécessaire.

N'importe quel WordPress local fait l'affaire (ddev, Local, wp-env) tant que le plugin y est actif avec WS Form PRO. Ne teste pas une mise à jour du plugin sur un site où le dossier du plugin est ton dépôt : la mise à jour le remplacerait par le contenu du zip.

Le calcul du sceau a été repris du kit PHP de Monetico. Les tests unitaires contiennent des sceaux calculés par ce kit avec des clés fictives, pour vérifier que le résultat est identique.

La version 1.0.0 a été testée en simulation, sans paiement réel sur le bac à sable Monetico. Le code d'origine, lui, a tourné en production. Si tu l'installes, fais d'abord un paiement en environnement « Test » avec une carte de test, et dis-moi si quelque chose coince.

## Mises à jour et versions

Le plugin se met à jour depuis les releases GitHub (en-tête `Update URI`). Ça ne marche que si le dépôt est public : le plugin n'embarque aucun jeton. Les réponses de GitHub sont gardées en cache 12 h, et les échecs 1 h.

Pour publier une version : mets à jour la version dans `wsform-monetico.php`, `includes/class-plugin.php` et le `Stable tag` de `readme.txt`, puis pousse un tag `vX.Y.Z`. Le workflow refuse un tag qui ne correspond pas, lance PHPCS et les tests unitaires, construit l'archive et publie la release.

Ce mécanisme est interdit sur WordPress.org. Une version publiée là-bas devra le retirer.

## Licence

GPL-2.0-or-later.
