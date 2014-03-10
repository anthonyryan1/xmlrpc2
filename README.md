# xmlrpc2

This constitutes an attempt to create a drop in replacement for rutorrent's `php/xmlrpc.php` with the goal of improving performance, while it succeeded at improving performance it introduced enough hard to find bugs that we abandoned this project and considered it a failure as a whole.

It is still possible to greatly improve the performance of rutorrent using this, but you run a higher risk of encountering bugs we will not be able to assist with. Pull requests are still welcome if you believe you can improve this further.

## Warnings

* This is not compatible with any official PHP release (this means it will not work on non-Whatbox servers).
* Whatbox staff do not provide support for bugs on customized rutorrent installations.

## Installation

1. Follow the instructions found [on our wiki](https://whatbox.ca/wiki/rutorrent) to allow modification of rutorrent.
2. Replace `~/.config/rutorrent/webui/php/xmlrpc.php` with this `xmlrpc.php`.
3. Apply the `php_rtorrent.diff` patch.