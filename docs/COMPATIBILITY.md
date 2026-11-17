# Compatibility

| | Supported |
|---|---|
| Zen Cart | 1.5.8, 2.0.x, 2.1.0, 2.2.x, 2.3 and 3.0.0 |
| PHP | 7.4 through 8.5, within the range each Zen Cart release itself supports (1.5.8: 7.4-8.0; 2.0/2.1: 8.0-8.3; 2.2/2.3: 8.2-8.5; 3.0.0: 8.3-8.5) |
| Templates | Any. The plugin has its own page and sidebox and changes no template file. Its markup carries Bootstrap classes that Bootstrap-based templates style, and its own stylesheet covers the rest. |

Differences by version:

- **Zen Cart 1.5.8** has no notifier for adding to the Information sidebox, so no automatic Recipes link there. Use the Recipes sidebox.
- Everything else behaves the same on every release.

The plugin uses only what all six releases share: plain SQL in its installer (the 2.x installer helpers don't exist on 1.5.8), its own plain classes loaded by path (the class loaders differ), and notifiers present from 1.5.8 to 3.0.0. Plugin language files fall back to English on every release, including 1.5.8 and 2.0.x, which don't do that for plugins themselves.

Database: seven tables named `product_recipes*` (with your table prefix), InnoDB, in your database's character set.
