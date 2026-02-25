# Raff-All Competitions

**Raff-All Competitions** is a WordPress plugin that extends WooCommerce to run paid and free-entry competitions. Features include a three-option validation gate, ticket allocation, instant-win support, winners publishing, countdown and tickets-sold progress, admin Flatpickr date/time picker with timezone support, audit logging, and automated GDPR anonymization after two years.

---

## Requirements

- **WordPress** 5.8 or later  
- **PHP** 8.0 or later  
- **WooCommerce** installed and active  
- Ability to install plugins and edit products in the WordPress admin

---

## Installation

1. **Prepare plugin folder**  
   Place the folder `raffall-premium-competitions` in `wp-content/plugins/`.

2. **Verify files**  
   Ensure the following structure exists:
   raffall-premium-competitions/
    ├─ raffall-premium-competitions.php
   └─ assets/
   ├─ raffall-frontend.js
   ├─ raffall-frontend.css
   └─ admin-raff-draw.js

3. **Install via ZIP**  
- Zip the `raffall-premium-competitions` folder.  
- In WordPress go to **Plugins → Add New → Upload Plugin**, upload the ZIP, then **Install Now** and **Activate**.

4. **Permissions**  
Ensure the web server can read plugin files and write to the database (standard WP setup).

---

## Configuration

### Create a competition product
1. **Products → Add New** → create a WooCommerce product.  
2. In the **Product Data** panel:
- **Is competition**: enable competition features.  
- **Has instant wins**: enable if you will seed instant-win ticket numbers.  
- **Show free entry info**: show free-entry instructions on the product.  
- **Validation question**: enter the question text.  
- **Option 1 / Option 2 / Option 3**: enter the three choices.  
- **Correct option**: select which option is correct (1, 2, or 3).  
- **Draw date and time**: use the Flatpickr field and choose a timezone.  
- **Instant wins seed CSV**: optional; one line per entry `ticket_number,prize`.  
- **Next ticket number**: starting ticket number (defaults to 1).  
- **Total ticket cap**: set total tickets available (used to calculate % sold).

### Shortcodes
- **Homepage listing**: add `[raffall_home]` to a page to show competitions, instant wins, and free-entry sections.  
- **Winners page**: add `[raffall_winners]` to a page to show **Recent Winners**.

### Account endpoints
- Adds **My tickets** and **Instant wins** to the WooCommerce My Account menu.

---

## Usage

- **Frontend flow**
- Product page shows countdown, tickets-sold progress, and multiple-choice validation radio buttons.
- Users must select the correct option to add tickets to the cart.
- Tickets are assigned after payment; ticket numbers are stored in order item meta.
- Instant wins are checked at assignment and logged if won.

- **Publishing winners**
- Use the **Winners** custom post type to publish winners.
- Add winner name, prize, ticket number, and featured image (photo) with consent recorded in meta.

- **Audit and verification**
- The plugin logs key events (validation attempts, ticket assignment, instant wins, admin actions) to an audit table for review.

---

## GDPR and Data Retention

- **Auto-anonymization**: personal data for users and guest orders older than **2 years** is anonymized automatically (daily scheduled job) unless the user has `raff_retain_personal_data=yes`.  
- **Anonymized fields**: names, billing address fields, phone; email is hashed for audit purposes.  
- **Right to erasure**: admins can remove or retain data via user meta; winner consent for photos is recorded.  
- **Audit logs**: non-personal audit entries are retained to preserve draw integrity.

---

## Troubleshooting

- **Countdown or progress bar missing**: confirm `assets/raffall-frontend.js` and `assets/raffall-frontend.css` are present and enqueued.  
- **Flatpickr not loading in admin**: confirm `assets/admin-raff-draw.js` is present and you are on the product edit screen.  
- **Ticket counts incorrect**: verify **Total ticket cap**, **Next ticket number**, and WooCommerce product stock settings.  
- **Testing**: use a staging site and a test payment gateway to validate ticket assignment and instant-win behavior.

---

