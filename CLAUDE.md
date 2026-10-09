# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

`clicksolutions/contao-login-screen` — a small Contao 5 bundle that customizes the **back end login screen** (company logo, random background image with optional blur, hint text). Settings are stored per root page. This repo is one of several bundles developed in-tree under `__bundles/` of a Contao managed-edition host project (the parent directory's `CLAUDE.md` describes the host); it has its own git repo, so commit from this directory.

There is no test suite, linter or CI. Validate PHP with `php -l <file>`. Because the host symlinks this bundle via a Composer `path` repo, edits to `src/`, `contao/` and `public/` apply immediately; after changing DCA fields run `php bin/console contao:migrate` from the host root to update the DB schema, and clear the cache (`php bin/console cache:clear`) after template/service changes. Backend CSS is served from `bundles/clicksolutionscontaologinscreen/`, so run `php bin/console assets:install` if `public/` changes aren't picked up.

## Architecture

The pieces are loosely coupled through the `cs_cls_*` field names — changing a field means touching all of them:

1. **DCA** — [contao/dca/tl_page.php](contao/dca/tl_page.php) appends a `cs_cls_login_legend` to the `root` and `rootfallback` palettes and defines `cs_cls_logo`, `cs_cls_bg_image`, `cs_cls_bg_image_blur`, `cs_cls_text` (columns on `tl_page`). Labels are Symfony translations in [translations/](translations/) (`contao_tl_page.de|en.yaml`).
2. **outputBackendTemplate hook** — [src/EventListener/Hooks/OutputBackendTemplateListener.php](src/EventListener/Hooks/OutputBackendTemplateListener.php), registered in [config/services.yaml](config/services.yaml) with the `contao.hook` tag. It acts only on the `be_login` and `be_login_two_factor` templates and edits the rendered HTML string: picks the root page (matching `dns` to the current host, else the first root page), uses `ContaoFramework` adapters and the `RequestStack` (no static Contao calls), builds logo and background HTML via the `contao.image.studio` service (rendered through the legacy `image` frontend template), then injects the background after `<body>`, the logo before `<h1>` and the escaped plain text before `</main>` (below the login providers), moves the core `fe-link` paragraph into `<main>`, and adds the `cs_cls_login` body class. If an anchor is missing nothing is injected and a warning is logged. The background picture's responsive sizes/formats are hardcoded in `generateBackgroundImageHtml()`.
3. **No template override** — Contao core's `be_login` is deliberately not copied (an old copy broke passkey/SSO login). Do not re-add one; change the injection anchors in the listener instead if core markup changes.
4. **CSS** — [public/css/contao-login-screen.css](public/css/contao-login-screen.css), injected as a `<link>` before `</head>` by the listener (via the `assets.packages` service), so it is only loaded on the login screens. Selectors are scoped to `body.cs_cls_login`.

Bundle wiring is standard: `ClickSolutionsContaoLoginScreenBundle`, `ContaoManager/Plugin.php`, and a DI extension. Note `composer.json` classmaps `contao/` (excluding `dca/`). Static analysis: `composer stan` (PHPStan level 9, `phpstan.neon`). The listener wraps all work in try/catch and returns the unchanged output on any error, so the login can never break.

Release notes go in [CHANGELOG.md](CHANGELOG.md); user-facing docs and screenshots are in [README.md](README.md) and `docs/images/`.
