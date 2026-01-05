# CommoServe2Assembly64

This script helps to get the full Assembly64 search that is present in the U64 Boards with every C64 Ultimate.

## What it does:

It basically acts as a proxy between the C64 Ultimate and the assembly64 database and chances the `X-client-Id` header to make the database think that the request is coming from a U64 Board.

It also provides help and is able to switch back to CommoServe if desired.

Searching for name=`?` will return help.

Searching for name=`-` will switch back to CommoServe.

Searching for name=`+` will switch to Assembly64.

It sets the result-Limit to 40 (20 is default) to provide more results.
The Client can't handle more than 40. Building a more complex paging system is possible, but not implemented yet.

## Usage

Install `index.php` onto a php enabled webserver that is configured to:

- serve the domain hackerswithstyle.se (http)
- is configured to always server /index.php no matter what path is used, eg. by using `mod_Rewrite` in apache or `try_files` in nginx.
- make sure your C64U's network configuration is pointing it to a DNS-Server that resolves hackerswithstyle.se to your webserver's IP.

- maybe change the configuration part ontop of the script.
    - There are 3 modes:
        - single-user: the services stores its configuration (currently just which repo is used) as if there is only one oser, any change will reflect for everyone using it
        - multi-user: the service stores its configuration per user, identified by the IP-Address the Webserver is connected from.
        - ulti-only: no configuration is stored, the service always uses Assembly64.
    - storagePath: in mode single-user and multi-user configuration is stored ad files, this path must be writable by the webserver.
        - it will be created if not present
        - this path can be accessible via the webbrowser, it's a php file, it will not expose any data.
    - removeFilesAfterSeconds: configuration files older than this value (in seconds) will be removed eventually.
        - set to 0 to disable automatic removal.
- Enjoy!
