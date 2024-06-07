## Version history

### Version {latest_version}
- Added support for the Uspeh TV provider (https://uspeh.tv)
- Minor optimization of downloading the media library for Glanz (for older consoles)
- Fixed an error in defining an empty category in M3U

### Version 4.0.660
- The algorithm for determining channel ID for Yosso (as in IPTV Channel Editor) has been changed to correctly determine all channels. Unfortunately, your Favorites and Browsing History will be lost. Applause to Yosso admins.
- Removed support for changing servers for Yosso. Now, when changing servers, you will have to stomp your feet into the office.
- Additional message in case of parameter mismatch when sending the log to the developer.
- Fixed a bug in restoring hidden channels

### Version 4.0.652
- Added the ability to edit the list of playlists from the popup menu of the plugin
- Fixed bug with settings backup
- Fixed a plugin crash when selecting the quality menu in the Edem media library

### Version 4.0.642
- Added the ability to sort the list of playlists
- Setting the default playlist from the colored button has been moved to the popup menu
- Editing playlists and xmltv sources has been removed from the settings, only through the popup menu on the categories screen
- Fixed the operation of filters in the IPTV Online media library
- Minor changes in displaying information about the movie in media libraries

### Version 4.0.642
- Added handling of cases when playlist settings contained old or incorrect data
- Fixed error adding provider

### Version 4.0.636
- Added media library support for IPTV Online
- The access data used for IPTV Online is now different (login/password for the provider’s website) due to the support of the media library
- Added account information for IPTV Online
- Provider access settings and additional parameters are separated (access via popup menu on the category screen)
- Added support for additional provider playlists (if such a choice is available in the account or there are playlist options like in Edem). Now implemented for Glanz, IPTV Online, Edem
- Added support for changing icon proportions for iEdem/iLook in a playlist
- Optimized interface speed (reduced number of settings entries)
- Channel information in hidden categories is no longer saved to improve loading speed
- Downloading all information is now done via https proxy script (relevant for old Dune HD)
- In case of errors in downloading a playlist or media, more information is provided if possible

### Version 3.3.620
- Fixed error loading M3U media library

### Version 3.3.618
- Fixed a bug that prevented VPortal data from being erased
- Reworked https_proxy script for more flexible download settings
- Requests to download playlist and VOD data now always go through https proxy

### Version 3.3.614
- Added verification of correctness of VPortal data entry
- Disabled sending logs to developers from unsupported consoles
- Additional logging of playlist downloads. To analyze download errors
- Changes in provider configuration (does not require plugin update):
 Yosso server descriptions have been updated.
 The link for downloading playlists and EPGs for TV Team has been changed.
 Changed the channel ID definition format for Yosso
 Replaced links to Icon Pack from Xemu so that the author could change them himself

### Version 3.3.606
- Added a special case for determining the playlist archive type (hi to ITV Live admins)
- Fixed an error in selecting settings in the plugin for the playlist archive type. The archive type is selected by decreasing priority: specified for the stream in the playlist -> setting in the playlist plugin -> specified in the playlist or for the provider -> default (shift for HLS, flussonic for mpegts)

### Version 3.3.604
- Category display settings have been moved to a separate settings screen "Category display settings"
- Fixed error parsing M3U header
- Fixed bug with double / in paths

### Version 3.3.600
- Setting the display of the media library icon did not work correctly on older Dune HD models
- The number of items in each column of the 'Add IPTV provider' popup menu has been increased to 17 (older models do not support the column separator correctly)
- Fixed a small error when selecting the provider logo
- The buttons in the plugin settings have been slightly moved up (so that the last button does not go beyond the boundaries on Sigma Dune HD models)

### Version 3.3.596
- Forced disabling of the settings for displaying the media library icon when the option is disabled or there is no media library support

### Version 3.3.594
- The setting for displaying the media library icon has been inverted.

### Version 3.3.592
- In the interface settings, the ability to display the media library as a separate application has been added
- Minor bugs fixed

### Version 3.3.590
- XMLTV EPG source from the playlist is now shown in the edit list
- Added support for IPTV Best provider (https://ip-tv.best/) (via configuration)
- Servers for IPTV Online have been updated, now they are shared with Tvizi/IPTV Best (via configuration)
- When changing the XMLTV source, the selection cache was mistakenly cleared
