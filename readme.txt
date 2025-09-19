=== Strategicli Family Money ===
Contributors: strategicli
Tags: allowance, family, money, dashboard, finance, children, child, transactions
Requires at least: 5.8
Tested up to: 6.5
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later

A simple, standalone WordPress widget for tracking family allowance and managing individual child 'banks'.

== Description ==

Strategicli Family Money is part of the Strategicli Family suite of dashboard widgets.
This plugin works independently and doesn't require any other plugins to function.

It provides a robust way for parents to track allowance deposits and withdrawals for each child, offering transparency and accountability.

Features:
* **Child Management:** Easily add, edit, and remove children via the admin settings.
* **Transaction Tracking:** Record deposits and withdrawals with amounts and descriptions.
* **Custom Post Type:** Transactions are stored as custom post types in WordPress, allowing easy management and correction from the admin area.
* **Balance Overview:** A dashboard widget (via shortcode) displays the current balance for each child.
* **Transaction History:** Click on a child's balance to view a detailed history of their recent transactions in a pop-up modal.
* **Frontend Transaction Form:** A separate shortcode provides a form for parents to easily log new transactions, ideal for placement on a password-protected page.
* **Dark Mode Support:** Integrates seamlessly with dark mode themes.
* **Clean & Intuitive UI:** Designed for ease of use by parents.

== Installation ==

1.  Upload the plugin folder `strategicli-family-money` to your `/wp-content/plugins/` directory.
2.  Activate the plugin through the 'Plugins' menu in WordPress.
3.  **Configure Children:** Go to `Settings > Family Money` in your WordPress admin to add the children you wish to track.
4.  **Display Money Dashboard:** Add the shortcode `[sfm_money_dashboard]` to any page or post where you want to display the children's balances.
5.  **Add Transaction Form:** Add the shortcode `[sfm_money_form]` to a *separate, password-protected page* (e.g., a page visible only to parents) where you can easily log new transactions.

== Shortcodes ==

* `[sfm_money_dashboard]` - Displays the main dashboard widget with child balances.
* `[sfm_money_dashboard theme="dark"]` - Displays the dashboard widget in dark mode.
* `[sfm_money_form]` - Displays the form for recording new allowance transactions.

== Changelog ==

= 1.0.0 =
* Initial release of Strategicli Family Money.