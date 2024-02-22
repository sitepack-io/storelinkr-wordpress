=== Storelinkr ===
Contributors: storelinkr, petervw
Tags: connect, woocommerce, cyclesoftware, wilmar, storelinkr
Requires at least: 6.3
Tested up to: 6.4
Stable tag: 2.0.2
License: GPLv2 or later

De storelinkr plugin is ontworpen om je WordPress website te integreren met het kassasysteem, waardoor je de producten kunt synchroniseren en bestellingen kunt terugplaatsen naar het kassasysteem. Deze plugin werkt met kassasystemen zoals CycleSoftware en Wilmar.

== Description ==

Koppel het storelinkr platform met je WordPress website.

== Installation ==

Upload de plugin-bestanden naar de /wp-content/plugins/ map.
Activeer de plugin via het 'Plugins' menu in WordPress.
Configureer de plugin-instellingen via het 'StoreLinkr'-menu in WordPress.

== Configuration ==

Ga naar het 'StoreLinkr'-menu in WordPress.
Genereer de API sleutel en secret (geheim)
Open het Storelinkr portaal en maak een nieuwe worklfow aan met bovenstaande API gegevens
Configureer de synchronisatie-instellingen voor producten en bestellingen.
De producten en categorieen worden binnen korte tijd automatisch aangemaakt.

== Frequently Asked Questions ==

= Welke kassasystemen worden ondersteund door deze plugin? =

Deze plugin werkt met kassasystemen zoals CycleSoftware en Wilmar.

= Moet ik een Storelinkr-account hebben om deze plugin te gebruiken? =

Ja, je hebt een Storelinkr-account nodig om deze plugin te gebruiken.

= Hoe kan ik mijn Storelinkr API-sleutel vinden? =

Je kan de Storelinkr API-sleutel vinden in de WordPress omgeving door op de linkerkant op het SitePack menuitem te klikken. Daar staat de API sleutel en geheim die nodig is in Connect.

= Kan ik mijn producten handmatig synchroniseren? =

Nee, wij updaten de producten meerdere malen per dag vanuit Storelinkr. Het is vaak niet nodig om dit nog handmatig uit te voeren.

= Hoe vaak worden producten automatisch bijgewerkt? =

Producten worden regelmatig bijgewerkt, de interval hangt af van het gekozen [Storelinkr abbonnement](https://storelinkr.com).

= Ik heb een andere vraag, die hier niet bijstaat =

Wellicht kan onze [online helpdesk](https://storelinkr.com) je verder helpen. Anders staat onze helpdesk ook voor je klaar om je vraag te beantwoorden.

== Developers ==

If you want to contribute, please take a look at our [Github Repository](https://github.com/sitepack-io/storelinkr-wordpress).

== Changelog ==

= 2.0.4 =

Release date: 2024-02-22

#### Enhancements

None

#### Bugfixes

* REST API authentication issue fixed

= 2.0.3 =

Release date: 2024-02-22

#### Enhancements

None

#### Bugfixes

* Callback permission to public, return true in register REST API endpoints

= 2.0.2 =

Release date: 2024-02-14

#### Enhancements

None

#### Bugfixes

* WP_Filesystem include when class is not defined

= 2.0.1 =

Release date: 2024-02-08

#### Enhancements

None

#### Bugfixes

* Fixed WordPress compatibility with the plugin check plugin from the WordPress plugin team

= 2.0.0 =

Release date: 2024-01-27

#### Enhancements

* Initial Storelinkr plugin for WordPress release

#### Bugfixes

None
