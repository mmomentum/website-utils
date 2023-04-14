# website-utils
A selection of small utility plugins used on my wordpress website;

### Bulk EDD Discount Codes
Used for mass generation of a bunch of Easy Digital Downloads discount codes at 100% off. Name & Product restriction is available. The admin interface for using this can be found inside of the Easy Digital downloads menu.

### Username Restriction
Stores a selection of "Forbidden Terms" that, when provided as a registration username, will return an error and not create the account. Used as a *very* basic anti-spam mechanism. The interface for using this can be found inside of Wordpress' settings menu.

### EDD / Mailpoet Tagging Integration
Interface between Easy Digital Downloads' purchase hook & Mailpoet's subscribed user tags system. Tags users subscribed to mailing lists based on what they have purchased, and also implements a "whale" (high value) user tag based on a lifetime value threshold.
