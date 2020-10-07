# F-Stop
F-Stop is an experimental fork of [Aperture](https://aperture.p3k.io), a [Microsub](https://indieweb.org/Microsub) server.

## Features
The following are what currently makes F-Stop different from Aperture:
- An attempt at improved keyboard navigation, i.e., within the different settings modals
- OPML import and export; export and import your entire feed list to other feed aggregators
- Additional source settings (like a site URL)
- The ability to "fetch original content," i.e., fetch and parse complete entries, even for summary-only feeds
- A dynamically populated "Unread" channel, which groups all unread entries
- The ability to really easily move or copy sources between different channels

## Remark
Certain files were somewhat reshuffled, to bring things a bit more in line with most other Laravel projects. Also, this fork was updated to Laravel 6 (LTS), which should recieve security updates until September 3, 2022. (Official support for Laravel 5.x has ended.)

## Fetch Original Content
This feature can be enabled on a per-source basis, or triggered manually, in clients that support it, for specific posts. It supports XPath queries.

## Credits
[Aperture](https://github.com/aaronpk/Aperture) was created by Aaron Parecki and made available under the Apache 2.0 license. Aperture logo by Gregor Cresnar from the Noun Project.

## License
Copyright 2018–2020 Aaron Parecki and Jan Boddez. Available under the Apache 2.0 license.
