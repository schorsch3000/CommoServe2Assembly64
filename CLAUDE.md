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

1. **Config block** (above `//END OF CONFIGURATION`): `$logRequests` and `$logPath`. When enabled, `logRequest()` appends one line per request (IP, original URI, chosen client id, forwarded URI, control characters escaped) to `{logPath}/requests.log`. `$logPath` comes from the env var `COMMO2ASSEMBLY_LOG_PATH` (the Dockerfile sets `/var/log/commo2assembly/`) and defaults to `../app_data/`, outside the document root. The proxy is stateless otherwise.
2. **Help command**: the device's search arrives as an AQL query in `$_GET['query']`. A name of `?` (matched as the literal substring `(name:"?")`) is answered locally by `sendText()`, which wraps lines as a JSON array of `{name: ...}` objects so the C64U shows them as search results.
3. **Repo routing**: `/aql/presets` is always fetched as `Ultimate`, and `addCommoServeRepo()` adds a pseudo repo (`aqlKey` `commoserve`) to it. An `/aql` search (not `/aql/...`) matching `$commoServeRepoPattern` (`repo:commoserve`, optionally quoted, bracketed only as a pair) has that term and its dangling `&`/`AND` stripped. A query that becomes empty is fine, CommoServe then returns everything. The search is sent as `Commodore`. Everything else, including entries/bin downloads of CommoServe items, is sent as `Ultimate`, which the backend accepts.
4. **Paging rewrite**: searches are rewritten to `/aql/{start}/40`. A `#N` suffix on the name selects page N and is stripped from the query, also for an empty name (`#2` leaves `(name:"")`, which the backend accepts). 40 is the most results the client can handle.
5. **Proxy via curl**: only whitelisted client headers (`$allowedHeaders`, compared lower-case) are forwarded. `Host` and `Client-Id` are always set by the proxy. curl negotiates and decompresses the encoding itself (`CURLOPT_ENCODING`), so the backend's `Content-Encoding`, length and hop-by-hop headers are dropped before the response is relayed. If the backend is unreachable, the client gets a 502 with the error as search results.

## Other files

- `.htaccess`: the Apache catch-all rewrite to `/index.php`, which the Dockerfile copies in.
