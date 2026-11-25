# CommoServe2Assembly64

This script helps to get the full Assembly64 search that is present in the U64 Boards with every C64 Ultimate.

## What it does:

It basically acts as a proxy between the C64 Ultimate and the assembly64 database and chances the `X-client-Id` header to make the database think that the request is coming from a U64 Board.

The search form always gets the Assembly64 presets, with an extra `CommoServe` repository added by the proxy.
Searching with repository `CommoServe` is sent to CommoServe, every other search goes to Assembly64.
Downloads always use the Assembly64 client id, which serves CommoServe items as well.

Searching for name=`?` will return help.

Adding `#<pagenr>` to the name (e.g. `giana#2`) returns that page of the results.

It sets the result-Limit to 40 (20 is default) to provide more results.
The Client can't handle more than 40. Building a more complex paging system is possible, but not implemented yet.

## Usage

Install `index.php` onto a php enabled webserver that is configured to:

- serve the domain hackerswithstyle.se (http)
- is configured to always server /index.php no matter what path is used, eg. by using `mod_Rewrite` in apache or `try_files` in nginx.
- make sure your C64U's network configuration is pointing it to a DNS-Server that resolves hackerswithstyle.se to your webserver's IP.

- maybe change the configuration part ontop of the script.
    - logRequests: set to `true` to append every request (client IP, original and forwarded URI) to `requests.log` in `logPath`.
    - logPath: directory for the request log, taken from the environment variable `COMMO2ASSEMBLY_LOG_PATH` (default `../app_data/`, relative to the document root). The Docker image sets it to `/var/log/commo2assembly/`, which is a volume. It must be writable by the webserver and should not be served by it. It will be created if not present.
- Enjoy!
