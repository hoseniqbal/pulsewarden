=== PulseWarden: Store Health Alerts for WooCommerce ===
Contributors: iqbal511138
Tags: woocommerce, monitoring, alerts, orders, checkout
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Emails you when orders stop, payments start failing, checkout throws errors or background tasks get stuck, and again when all is well.

== Description ==

Uptime monitors only check that your home page loads. A store can be "up" and still sell nothing for hours: a plugin update breaks the checkout, the payment gateway starts declining every card, or the background queue that sends order emails stops. Most owners find out from an angry customer the next day.

PulseWarden watches the things that actually make you money and sends one clear email when something is wrong, and one when it is fixed.

= What it watches =

* **Order heartbeat.** PulseWarden learns when your store normally sells, hour by hour across the week. When orders stop for much longer than usual, you get an email. A quiet night never triggers it, because quiet nights are normal.
* **Failed payments.** When a large share of orders fail payment within an hour, you hear about it. That is usually a broken gateway or a card testing attack.
* **Checkout errors.** Counts errors on the classic and the block checkout and alerts you when they jump. Bots stopped by OrderWarden, CODWarden or BlockWarden are not counted.
* **Scheduled tasks.** Alerts you when WooCommerce background actions or WordPress cron events are stuck for over an hour.

= Made to be quiet =

* One email when a problem starts and one when it is over. No floods.
* Thresholds you can change in plain sentences.
* A dashboard with the last order time, orders today against a normal day, and the state of every check.
* Learns from your last four weeks of orders the moment you activate it, so busy stores are covered from day one.

= Privacy =

PulseWarden keeps only order numbers and times, plus short error codes. No customer details. It makes no external requests; alerts are sent with your site's own email.

== Installation ==

1. Install and activate PulseWarden. WooCommerce must be active.
2. Open PulseWarden in the admin menu and press "Send a test email" to make sure alerts can reach you.
3. Optional: add more email addresses in Settings.

== Frequently Asked Questions ==

= My store is new. Will it work? =

Payment, checkout and task checks work right away. The order heartbeat starts after it has seen 7 days and 10 orders, so it knows what normal looks like.

= I got no test email. =

Your site cannot send email reliably. Install an SMTP plugin; then alerts and your order emails will both arrive.

= The checks did not run for a while. =

WordPress cron runs when someone visits the site. On quiet sites ask your host to call wp-cron.php every 5 minutes. The dashboard warns you when this happens.

= Does it send data anywhere? =

No.

== Screenshots ==

1. Overview with the health checks.
2. Alert history.
3. Settings in plain sentences.

== Changelog ==

= 1.0.0 =
* First release: order heartbeat, failed payment alerts, checkout error alerts, stuck task alerts, alert history and dashboard.

== Upgrade Notice ==

= 1.0.0 =
First release.
