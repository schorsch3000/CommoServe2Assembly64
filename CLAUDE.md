# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

A single-file PHP proxy (`index.php`) that sits between a C64 Ultimate and the Assembly64 database. The C64U's DNS resolves `hackerswithstyle.se` to the server running this script. The proxy forwards every request to the real backend at the hardcoded IP `185.187.254.229` (sending `Host: hackerswithstyle.se`) and rewrites the `Client-Id` header. `Ultimate` gives the full Assembly64 repo and `Commodore` gives CommoServe. See `Readme.md` for user-facing behavior and deployment requirements.

## Commands

There is no build step, test suite or dependency manager.

- Run locally: `php -S localhost:8080 index.php` (the built-in server uses `index.php` as the router, which mimics the catch-all rewrite), or `docker build -t commo2assembly . && docker run -p 8080:80 commo2assembly` (php:8.4-apache with mod_rewrite).
- Format: Prettier with `@prettier/plugin-php` (config in `.prettierrc.json`: 4-space indent, single quotes, trailing commas, 1tbs braces). `.onsave` runs `osv_prettier -w <file>`.
- Deploy: `./deploy` rsyncs `*.php` to the production host.

## Architecture of index.php

The whole request flow lives in one file, top to bottom:

1. **Config block** (above `//END OF CONFIGURATION`): `$mode` (`multi-user` keys settings by `REMOTE_ADDR`, `single-user` uses one shared key, `ulti-only` stores nothing and always uses defaults), `$storagePath`, and `$removeFilesAfterSeconds`. Cleanup of old settings runs on about 1% of requests.
2. **Magic search commands**: the device's search arrives as an AQL query in `$_GET['query']`. A name of `+`, `-` or `?` (matched as the literal substring `(name:"X")`) is answered locally by `sendText()`, which wraps lines as a JSON array of `{name: ...}` objects so the C64U shows them as search results.
3. **Paging rewrite**: for `/aql` search requests (but not `/aql/...`), the proxy turns the path into `/aql/{start}/40`. A `#N` suffix on the name selects page N and is stripped from the query. 40 is the most results the client can handle.
4. **Proxy via curl**: only whitelisted client headers (`$allowedHeaders`) are forwarded. The backend's hop-by-hop and length/encoding headers are dropped before the response is relayed.
5. **`ClientSettings`**: stores each key as a PHP file, `{storagePath}/{clientId}#{key}.php`, which contains `return <var_export>;` and is loaded with `include`. Storing them as PHP files means they are safe to leave web-accessible. Defaults are in `ClientSettings::DEFAULTS`.

## Other files

- `debug.php`: an experimental variant of `index.php` (uses a `QueryStore` and a small result length). It is not deployed by the Dockerfile.
- `dl`: gitignored CLI script that mirrors the whole backend (`/leet/search/aql/0/0?query=name:%25`, then `entries/` and `bin/` per item) into `./data/`.
- `.htaccess`: the Apache catch-all rewrite to `/index.php`, which the Dockerfile copies in.
- `sessions/`: runtime settings storage (gitignored).
