# Change log

### 1.1.0 (2026-10-08)

* The `be_login` template is no longer overridden; logo, background image and text are injected via the `outputBackendTemplate` hook (fixes missing passkey/SSO login and password toggle)
* Login screen settings now also apply to the two-factor login page
* The login text is now plain text only (HTML is stripped and output escaped)
* The stylesheet is only loaded on the login screens (injected by the hook instead of `$GLOBALS['TL_CSS']`)
* Translations moved to Symfony YAML (de, en)
* Hook errors no longer break the login screen; missing anchors no longer drop the frontend link
* Requires Contao 5.7+ and PHP 8.3+

### 1.0.0 (2024-07-12)

* Initial release
