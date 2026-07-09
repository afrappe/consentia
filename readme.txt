=== Consentia — Cookies & Consent Mode v2 ===
Contributors: integraservices
Tags: cookies, consent, gdpr, consent mode, wp consent api
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Banner de consentimiento de cookies compatible con la WP Consent API (Google
Site Kit) y con Google Consent Mode v2.

== Descripción ==

Consentia es un plugin gestor de consentimiento (CMP) que:

* Muestra un banner de cookies (Aceptar / Rechazar / Preferencias).
* Modal de preferencias por categoría (necesarias, preferencias, estadísticas,
  estadísticas anónimas, marketing).
* Se integra con la **WP Consent API**, de modo que **Google Site Kit**
  reconoce el consentimiento y configura Consent Mode v2 automáticamente.
* Opcionalmente emite **Google Consent Mode v2** directo (gtag consent) para
  etiquetas de Google que no gestione Site Kit.
* Panel de ajustes (textos, colores, categorías, posición).
* Registro de consentimientos con IP cifrada y exportación CSV (prueba de
  cumplimiento).
* Consentimiento denegado por defecto (opt-in), para todos los visitantes.

== Cómo funciona con Site Kit ==

Google Site Kit **requiere** el plugin gratuito «WP Consent API». El flujo:

1. Instala «WP Consent API» (Consentia te avisa y te da el enlace).
2. Instala y activa Consentia (este plugin) como gestor de consentimiento.
3. En Site Kit activa su «Consent Mode».
4. Deja en Consentia la opción «Emitir Consent Mode directo» en OFF (Site Kit
   ya emite las señales; evitas duplicarlas).

Cuando el visitante elige en el banner, Consentia llama a `wp_set_consent()`
por cada categoría; Site Kit lo detecta y actualiza las señales de Google
(`analytics_storage`, `ad_storage`, `ad_user_data`, `ad_personalization`).

Mapeo de categorías → señales de Google:
* statistics → analytics_storage
* marketing → ad_storage, ad_user_data, ad_personalization
* preferences → personalization_storage
* functional → functionality_storage, security_storage (siempre concedidas)

== Uso sin Site Kit ==

Si no usas Site Kit pero sí etiquetas de Google (gtag/GTM), activa «Emitir
Consent Mode directo» en los ajustes: Consentia pondrá el default denegado
antes de tus etiquetas y lo actualizará al consentir.

== Changelog ==

= 1.0.0 =
* Versión inicial: banner, preferencias, WP Consent API, Consent Mode v2
  directo, ajustes y registro de consentimientos.
