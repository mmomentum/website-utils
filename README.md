# website-utils
A selection of small utility plugins used on my wordpress website;

### Bulk EDD Discount Codes
Used for mass generation of a bunch of Easy Digital Downloads discount codes at 100% off. Name & Product restriction is available. The admin interface for using this can be found inside of the Easy Digital downloads menu.

### EDD Actions Extensions
Implements extra EDD URL actions, such as an `add_bundle_to_cart` method that allows for multiple products to be added to the cart at once instead of only one (used primarily for doing direct links from somewhere else to your store where specific items already need to be added to the cart, like reseller platforms).

### EDD / Mailpoet Tagging Integration
Interface between Easy Digital Downloads' purchase hook & Mailpoet's subscribed user tags system. Tags users subscribed to mailing lists based on what they have purchased, and also implements a *"whale"* (high value) user tag based on a lifetime value threshold.

### Username Restriction
Stores a selection of "Forbidden Terms" that, when provided as a registration username, will return an error and not create the account. Used as a *very* basic anti-spam mechanism. The interface for using this can be found inside of Wordpress' settings menu.

### Temporary Download Shortcode
Creates a temporarily download to a file located somewhere on the server and returns an HTML `<a></a>` link to it. Requires Easy Digital Downloads to function.

### Elementor Mailpoet Confirm Action
Adds a new custom Elementor form post-submit action; Subscribes a user to a mailpoet list based on the provided email and auto-confirms them regardless of settings. Does not send any notifications to the admin or user.

### EDD / Mailpoet Unsubscriber
Auto unsubscribes people from specific lists based on a table which is adjustable in the admin menu.

### MailPoet Discount Shortcode
Generates a multi-use shortcode right inside of a mailpoet email

### Elementor Action Tracker
Tracks how many times a form has been submitted. Implemented as a "post-submit action"