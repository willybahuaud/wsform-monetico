=== Paiement Monetico pour WS Form ===
Contributors: willybahuaud
Tags: ws form, monetico, payment, credit card, cic
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Card payments with Monetico (Crédit Mutuel, CIC) for WS Form PRO forms.

== Description ==

This plugin adds a "Paiement Monetico" action to WS Form PRO. When a form is submitted, the visitor is sent to the Monetico payment page, and the submission gets its payment status from the Monetico notification (IPN).

* Payment status stored on each submission: pending, paid, failed.
* Monetico seal (HMAC v3) checked on every notification, TPE and amount checked against the submission.
* Replayed notifications change nothing and send no duplicate email.
* Several Monetico accounts (TPE) on the same site, one per form if needed.
* Keys stored outside forms: in the plugin settings, or in wp-config.php.
* Confirmation emails for the payer and the team.
* Status block and shortcode for the return pages.

WS Form PRO is required. This plugin is not affiliated with WS Form, Monetico, Crédit Mutuel or CIC.

== Installation ==

1. Install and activate WS Form PRO.
2. Install and activate this plugin.
3. In WS Form > Monetico, add your TPE, company code and security key. You can also declare them in wp-config.php with the WSFORM_MONETICO_ACCOUNTS constant, which takes priority.
4. Give Monetico the notification URL shown on that page.
5. In your form, add the "Save to Submissions" action, then the "Paiement Monetico" action, and pick the account and the amount field.

== Frequently Asked Questions ==

= How do I declare accounts in wp-config.php? =

`define( 'WSFORM_MONETICO_ACCOUNTS', array( '1234567' => array( 'company' => 'mycompany', 'key' => 'your 40 characters key' ) ) );`

= Where are the logs? =

In wp-content/uploads/wsform-monetico/logs/, one file per day, kept 30 days. File names end with a secret so that they cannot be guessed, and seals, keys, emails and IP addresses are never written.

== Changelog ==

= 1.0.1 =
* Updates: requirements read from a manifest attached to the release, release notes rendered from Markdown, no false compatibility warning within a tested branch.

= 1.0.0 =
* First public release.
