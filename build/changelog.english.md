### Upcoming version

### Version 4.1.784
- Additional functions for TV Team API
- Added additional XMLTV source for Yosso TV
- Fixed a bug with clearing indexes after updating the XMLTV source
- Fixed a minor bug with playlist loading
- Minor improvements for storing session/token files
- Updated translation

### Version 4.1.770
- Reducing the number of authorization requests for TV Team, so that the provider does not block when entering incorrect access data.
- More information is provided for playlist download or token request errors.
- Additional fields in the TV Team account information
- Changed the format of some requests in the plugin configuration
- Most requests to servers of IPTV providers for information/list of servers, etc., are now cached.
- Fixed a bug with checking the position database for XMLTV for Dune HD on Android.
- Tokens/session IDs are no longer saved in the settings, in order to avoid problems with transferring to another Dune HD
- Fixed a bug with setting the status of viewed/unviewed in the VOD

### Version 4.0.758
- Fixed a bug with duplicate provider when creating a new playlist

### Version 4.0.756
- API support for the TV Team provider. Subscription Information. Changing the server from the plugin. (access data has changed! Now this is the login and password to the user's account)
- Fixed a bug with duplicate provider after editing access data

### Version 4.0.746
- Fixed XMLTV indexing for Sigma devices

### Version 4.0.744
- Added support for Peak TV provider (https://peaktv.info)
- Optimize XMLTV indexing and storage
- Added the ability to select the source of icons when using the 'Internet EPG Server' engine
- Fixed a bug with resetting the default provider settings after editing access data
- Update translation

### Version 4.0.730
- VOD support for the provider TVIZI (access data for media library support has changed! now it is a login and password to the user account on the site)
- Editing of hidden channels has been moved to the popup menu of the channels list
- Plugin settings are now available via the popup menu on the categories and channels screen

### Version 4.0.722
- Fixed translation for some strings
- Added checking the correctness of playlist settings.
- Slightly redesigned the display of information about XMLTV channels/playlists/sources
- Editing XMLTV sources added to the plugin popup menu
- The blue button on the category screen now shows a list of changes
- The changelog is now downloaded up-to-date from GitHub. Without updating the plugin, you can view the list of current changes.

### Version 4.0.718
- Fixed translation for some strings
- Added an additional popup menu item to change the source of channel icons on the category screen (only if the XMLTV engine is selected)
- Changed the maximum number of menu items when changing the playlist (a large list was displayed incorrectly on Sigma devices)

### Version 4.0.704
- Provider settings are not initialized after a "warm" restart of the plugin

### Version 4.0.704
- Channel sorting menu is now available within a category
- Added the ability to add your own playlist (from the account) for the iEdem/iLook provider (through the popup menu - provider settings). Access data is used from the playlist!
- Added additional diagnostics to find the reason for resetting playlist settings (icons/sorting/favorites)

### Version 4.0.692
- Fix wrong rename category settings for some providers

### Version 4.0.688
- Changed the maximum number of elements in the popup menu for selecting XMLTV sources
- Category settings disappeared after switching from the provider’s playlist to a regular playlist

### Version 4.0.684
- If list of playlists is empty, then the window for adding playlists is shown, not the plugin settings
- Popup menu for adding an IPTV provider has been replaced with a new selection screen
- When adding an IPTV provider, you can see a link to its website or follow the QR code

### Version 4.0.678
- Added executable php-cgi for ARM (87xx) platform
- Correctly clearing VOD cache

### Version 4.0.674
- Added English version of the Changelog
- Update translation
- Minor fix for Glanz and SharaClub VOD

### Version 4.0.666
- Added support for the Uspeh TV provider (https://uspeh.tv)
- Minor optimization of downloading the VOD for Glanz (for older Dune HD)
- Fixed an error in defining an empty category in M3U

### Version 4.0.660
- The algorithm for determining channel ID for Yosso (as in IPTV Channel Editor) has been changed to correctly determine all channels. Unfortunately, your Favorites and Browsing History will/can be lost. Applause to Yosso admins.
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
- When changing the XMLTV source, the cache of the selected source was mistakenly cleared
- When calling editing playlists/XMLTV EPG from the popup menu, it did not work correctly

### Version 3.3.578
- The 'Force Dune detect stream' setting was displayed incorrectly

### Version 3.3.578
- Added support for TopIPTV provider (https://topiptv.info/)
- Fixed php warning in the log if dune_params is not set
- The name of the icon library has been corrected to zedoar (at the request of the author)
- The icon library is downloaded and unpacked only if it is selected.
- XMLTV source was not updated when selecting the "Refresh" menu item
- The 'Force Dune detect stream' parameter has been moved to the playback settings

### Version 3.3.570
- The plugin requests additional confirmation to clear playlists/xmltv sources/restore changed groups/channels
- Added a crutch for providers who do not know that according to the standard there should be only one EXTM3U tag.
- Added menu items on the category screen for quick access to editing playlists/xmltv sources
- Fixed an error in reviewing SMB sources on the previous generation of Android consoles
- Added information in the cbilling/antifriz subscription to display the subscription to the media library

### Version 3.3.556
- Added 'Internet source' EPG for 101film provider
- Added 'Internet source' EPG for provider smile
- EPG settings not used when selecting 'Internet source' are no longer shown
- EPG caching time for 'Internet source' 1 hour (not configurable)
- Updated icon pack from Xemu
- Fixed a bug that sometimes caused the plugin to crash when changing the EPG engine
- Minor UI fixes for Dune HD Pro One 8K Plus (showing some menu items disabled for Homatics/Boxy)

### Version 3.3.546
- Added icon pack from zedoar
- Fixed an error in the prompt for adding new playlists/providers
- Fixed a bug that prevented sending the plugin log from Homatics/Boxy if the dune_plugin_logs folder is configured

### Version 3.3.540
- Fixed minor interface errors
- By default, JSON engine EPG is used for providers
- Updated link to the playlist of the provider russkoe.tv
- Fixed bug showing viewing history for VOD
- Improved EPG fuzzy search mechanism
- Links to the playlist for Edem have been changed from it999.ru to epg.one (old links will stop working on May 15)

### Version 3.3.530
- Added support for TV series for the Sharavoz library

### Version 3.3.528
- Added media library support for Sharavoz
- Menu for editing hidden categories is available on any category
- Fixed browsing history error for media libraries
- The category identifier in the Sharavoz media library was interpreted incorrectly

### Version 3.3.522
- Fixed exit from plugin settings if they were called from the shell
- Enabled forced stream detection for the internal player when selecting MPEG-TS in the provider
- Added a context menu for going to a channel in a category from Favorites/Browsing History/Changed Channels in the classic interface

### Version 3.3.508
- Disabled the ability to change the server for CRD TV
- Changed some translation strings
- Fixed script for displaying channel information for Pro One 8K Plus
- When loading a playlist, the default XMLTV source is taken only from the playlist. External ones must be set manually
- EPG settings. If an external XMLTV source is selected, pressing the green button on it again will disable it
- EPG settings. The list of external XMLTV sources shows the date of the last download

### Version 3.3.492
- Added mpeg extension (in addition to .ts and mpegts) when recognizing mpeg-ts streams using the link to add the ts:// prefix for the internal Dune HD player
- Added setting to force the stream to be checked by the internal Dune HD player (the ts:// prefix sent in the link) before playback. Playlist settings -> Additional options. Disabled by default. For links that are not recognized by the standard link detection algorithm
- Added checking the encoding of curved playlists which, according to the standard, should be in UTF8 encoding
- Fixed an error in generating descriptions in the "Changed Channels" category

### Version 3.3.480
- Added a setting to protect settings with a password from the main player menu.
- Improvement to support the new Dune HD Pro One 8K console.
- Support for CRD TV provider (https://crdtv.net/)

### Version 3.3.480
- Small clarifications for catchup="append" type archives

### Version 3.3.478
- Added setting to replace https links with http. After recent changes to Glanz. Disabled by default. But you need to take into account that not all stream providers support both protocols. The plugin doesn't do any transformations, it just replaces the text.
- Added a setting to protect settings with a password (in the additional settings of the plugin).

### Version 3.3.474
- Fixed bug with fuzzy EPG ID search in XMLTV source for Dune HD based on Android
- Removed forced clearing of the current source cache after changing the XMLTV source
- Added definition of the new Dune HD Pro One 8K Plus model

### Version 3.3.470
- Added RU TV provider (https://rutv.vip/)
- Added the ability to change the stream type for the Satq TV provider
- Fixed a bug in parsing titles for M3U media libraries
- Minor optimization of working with icon libraries
- Removed the EPG ID fuzzy search setting, now it is always used

### Version 3.3.464
- Added the ability to update icon libraries without restarting the player or plugin using the “yellow button”
- Changed some icons

### Version 3.3.462
- Added support for libraries for changing category icons. Thanks Xemu (https://forum.hdtv.ru/index.php?showtopic=19426) for his provided library. Anyone who wants to participate in adding libraries - write to the author

### Version 3.3.457
- Added Satq TV provider (https://satq.tv/)
- Lost support for setting the archive type (flussonic) in the provider configuration
- A playlist loading error was erroneously shown when adding a provider if the active provider was unable to load the playlist or had incorrect viewing data.

### Version 3.3.452
- Added KLI Media provider
- For files in the list of playlists/XMLTV sources, the corresponding icons are shown
- For well-known providers, their logos are included in the plugin
- Fixed showing an error message when changing provider viewing parameters
- Fixed detection of flussonic archive types for some links
- Fixed encoding of some types of UserAgent when transferring to internal player
- Fixed copying browsing history to another folder
- When loading the playlist for the first time, the XMLTV source from the playlist was not always picked up
- When deleting a domain in Edem, the changes were not applied immediately

### Version 3.3.442
- Added support for changing the stream in the provider settings for 1OTT, Glanz, IPTV Online, ITV Live, IPStream/iShara, LightIPTV, SharaClub, Sharavoz, TVIZI, TV Team, VipLime
- Added TVIZI NET provider
- Added the ability to change server for IPTV Online

### Version 3.2.438
- An attempt to speed up the responsiveness of the interface when using an XMLTV source. If the source of icons is a playlist, then downloading and indexing of XMLTV is carried out in the background.
- Minor changes to integrate the background indexing log into the main plugin log
- The archive link is generated only when the archive is launched. This made the playlist processing a little faster
- Updated translation

### Version 3.2.431
- Improved XMLTV parser. Performance increased from 2 to 3.5 times depending on the console
- Added verification of entered viewing data

### 3.2.426
- The movie was not deleted from the library’s viewing history
- Fixed plugin crash when changing server in provider account settings

### 3.2.422
- Fixed an error when restoring from a backup if the current playlist did not match the one stored in the backup.
- Corrected translation
- Fixed editing the current provider account. Settings name was lost

### 3.2.418
- Correct checking of provider configuration version
- Fixed a bug in importing playlist links from a link file
- The playlist loading error message was not shown in full
- The wrong plugin title was specified if there was an error loading the playlist

### Version 3.2.410
- Added support for JSON versions of EPG downloaded from the provider's server or EPG provider (epg.drm-play.com) for well-known IPTV providers (the same as in IPTV Channel Editor). For older consoles, I recommend switching to the faster JSON version. Updating the EPG in this case is entirely up to the server.
- For new Dune HD boxes for the XMLTV engine, SQLite is always used, for old ones based on Sigma processors - Classic
- The settings for the selected EPG engine are no longer global, but specific for each provider.
- To quickly change the engine, a new item has been added to the popup menu on the categories screen
- When selecting the JSON engine (Web server), the display of some settings is disabled ("Channel icon source", "EPG cache time", "EPG fuzzy search") since such information is not available to it.
- When choosing a JSON engine (Web server), channel icons are taken only from the playlist!
- For the 1cent provider, XMLTV sources are registered because the provider did not bother to register the correct links in the playlist.
- Slightly improved M3U parser for parsing the playlist title
- The script for collecting information about channel flows has been redesigned
- Added support for changing the server from the plugin for Antifriz/Cbilling, Filmax TV, SharaClub, Sharavoz, TV Club, Vidok
- Added a new item to the popup menu for editing the account parameters of the current provider
- Added support for changing playlist domain for Glanz
- Fixed a bug if all channels were hidden in a category
- Added minimal playlist checking when importing from a file or folder
- Added verification of the correctness of the xmltv source packed in zip
- Added check if playlist contains JTV EPG instead of XMLTV
- Fixed epg manager initialization error causing the plugin to crash in some cases
- Fixed error in determining channel ID for TV Club
- Fixed playlist link error on Filmax TV

### Version 3.1.372
- Again an error checking subscription information blocked access to the Media Library for Cbilling

### Version 3.1.370
- Added media library support for IPStream/iShare
- Fixed minor errors in updating filter/search screens for VOD
- For some providers, media library availability will be checked based on subscription information
- Improved display of subscription information for each provider that supports issuing subscription information
- Changed the icon for the 1ott provider
- Added the ability to control channel ID determination for regular playlists based on M3U attributes in the playlist settings. If the provider has not specified the correct attributes for the channel ID, then when the link changes, the channel ID changes. Now the plugin can specify which attributes it can use as a channel ID.

### Version 3.1.358
- Added the ability to change the media library icon
- An error checking subscription information blocked access to the Media Library for Cbilling

### Version 3.1.356
- Fixed a bug that prevented having different account settings for one provider
- Changelog was not copied into the plugin

### Version 3.1.355
- Added support for media libraries for Antifriz/Cbilling/Edem/Glanz/Sharaclub
- For edem, a new list import format. edem@ottkey|vportal_key
- Redesigned display of subscription information
- Added display of last download time of XMLTV source
- Added display of the current playlist/XMLTV source in the plugin settings
- Added the ability to change the current playlist/XMLTV source in the plugin settings (list editing)

### Version 3.1.341
- Removed the need to restart the plugin after restoring a backup
- Added support for media libraries in M3U format for Fox/Ping/101film/Smile providers

### Version 3.0.337
- For iEdem/iLook, the need to enter a domain has been removed
- Added the ability to select channel icons from a playlist or xmltv source
- For the Sharaclub provider, the ability to top up the balance via a QR code has been added
- For Light IPTV, the detection of channel IDs has been fixed

### Version 3.0.328
- Added support for popular providers, all that support IPTV Channel Editor
- Added the ability to hide a group of channels using ready-made templates and a custom string
- Added the ability to import provider account settings through a list of playlists using a special syntax (provider_id@login:password, provider_id@password, for iEdem/iLook edem@domain:ottkey). The provider ID can be viewed when adding a provider through the menu; it is indicated in parentheses. For example Fox IPTV (fox), Glanz (glanz), IPTV Online (iptvonline)
- Editing the playlist is called by the Ok button
- Added xmltv sources for providers that do not provide them in the playlist
- All downloads (playlists and xmltv sources) are done via https proxy script
- If there is a download error, more information is provided.
- Fixed a bug in the m3u parser that caused a memory leak
- Fixed error checking playlist cache and xmltv source
- Fixed a bug with handling install/update plugin events
- Work with categories and channels has been optimized so that the plugin slows down less
- Fixed an error parsing the channel name in the m3u playlist
- Fixed backup. The new version did not copy all settings
- Information about the video/audio stream is also written into the channel information (except for Homatics/Boxy)
- Forced recording of changes in the location of categories/channels, etc. using the STOP button
- Support displaying subscription information for Cbilling, TV Team, ITV Live, Sharaclub, Vidok
- Removed the 'Shutdown Dune HD' menu item. This still doesn't really work on Android based Dune HD.
- The provider's playlist parser was not initialized correctly after turning on the Dune HD
- Fixed minor errors in updating screens and NewUI
- Updated translation into English and German

### Version 2.4
- Corrected regular expressions for generating flussonic archives (relevant for YOSSO)
- Internal changes regarding the storage of settings and maximum portability of backups between media players.

### Version 2.3
- Erasing the User-Agent line in the settings resets it to the default value. So as not to make a separate button.
- Added additional intervals for XMLTV cache lifetime
- Minor optimization of unpacking XMLTV archives
- Items for updating the current playlist/XMLTV source have been added to the popup menu in the playlist/source selection sections
- Items for updating the current playlist/XMLTV source have been removed from the settings as unnecessary
- Added QR codes for donation in the plugin
- Fixed a bug with editing previously hidden categories that are no longer present in the playlist
- Fixed a bug with restoring hidden categories if there was only one category

### Version 2.2
- Optimization of indexing XMLTV sources. Indexing time has been reduced by an average of 10-15 seconds for different types of sources
- For the SQLite engine, the program can be available much earlier than the indexing of the XMLTV source is completed due to the fact that it is a database. For the classic engine - only after indexing is completed
- Configured fuzzy EPG search. Disabled by default. In early versions the algorithm was enabled, in later versions it was disabled because it slowed down the search. Now it's up to the user. Principle: if the epg id specified in the playlist is not found in the XMLTV source, the plugin tries to find data by channel name. Made specifically for home-made playlists, where the epg id is written crookedly and does not correspond to the XMLTV EPG source data. If the EPG ID is not in the playlist, the plugin always searches by channel name. In cases where the channel name does not correspond to the XMLTV source (hello Sharavoz), I can’t do anything, write to Sportloto.
- Using the User-Agent setting to download playlists and XMLTV sources
- Force the use of the User-Agent setting (if it is not the default) for threads, unless one is specified for them.
- EPG ID from the playlist is shown in the information in the channel description (when channels are displayed accordingly or using the Info button)
- Setting to enable the generation of a list of pseudo program guides (TV program 1/2, etc.) if the EPG ID for the channel is not found, but the archive is present (different from 0). Disabled by default.
- Scripts for downloading and indexing have been redesigned
- Disabled downloading of XMLTV EPG source immediately after adding
- Fixed not working disabling special categories
- Fixed a crash when trying to display an error
- Fixed display of some icons in the file selection window
- Fixed selection of files on SMB folders
- Fixed adding a link to a playlist or XMLTV EPG source
- Clarification of the xmltv source signature definition.
- Synchronization of code changes with IPTV Channel Editor

### Version 2.1
- Fixed a bug with setting the cache folder
- Fixed a bug with selecting an EPG source when switching playlist

### Version 2.0
- Solved the problem with https links to playlists/EPG sources
- Fixed sorting of channels in the 'All channels' category
- Added the ability to edit links to playlist/EPG source
- Added a new EPG cache management mode for Android consoles. For them, the new SQLite engine is selected by default. The classic mode creates a large number of files to manage the cache, the new one stores everything in one database. With the new mode, clearing the cache does not take much time. The new mode is not available on Sigma player, thanks to Dune HD for turning off sqlite support.
- Ability to select the cache mode in case you need to have a shared cache with older Dune HD boxes
- Restored the ability to reset wallpaper to default
- Fixed selection of picons for channels
- Correctly clearing settings after restoring from a backup
- The channel order was not saved after sorting or resetting the sorting

### Version 1.8
- Search is available within all groups using the Popup menu
- Ability to disable channel scaling for a playlist
- EPG indexing operation runs in the background, downloading is now faster, but EPG is not immediately available
- Allowed placement of EPG cache on network folders. I am not responsible for glitches, use at your own peril and risk
- For channels that have an archive, but do not have a program guide, an hourly list is shown
- Added support for the 'dune-params' attribute to the #EXTVLCOPT tag for the ability to assign dune_params to each channel
- Increased timeout for downloading XMLTV source
- Fixed indexing script for Homatics/Boxy

### Version 1.7
- The "Changed Channels" category is also available in NewUI.
- Added internal storage support for all Android-based Dune HD
- The missing menu item "Edit hidden channels" has been restored
- Fixed some icons
- Fixed localization files
- Fixed minor bugs

### Version 1.6
- New category "Changed channels". Shows added/removed changes from the last saved playlist. Changes are valid until the list of changed channels is cleared
- Additional checks for EPG downloads
- Corrected design of views for Favorites
- Enabled internal storage support for all androids

### Version 1.5
- When the "All Channels" group was disabled, the internal player stopped working
- Removed slight slowdown when starting a channel
- The design of the views has been corrected

### Version 1.4
- Changed the name of the menu item to restore hidden categories/channels
- Fixed sorting in the channel browser.

### Version 1.3
- Added internal storage support for Homatics/Boxy
- Added a separator in the menu for EPG sources from the playlist and external ones
- Added the ability to reset channel sorting in categories
- The EPG cache folder is always reset to default after restoring a backup!
- An incorrect plugin version was specified (error in the build script)
- Sorting channels into categories did not work

### Version 1.2
- The EPG update button in the settings did not work
- Changing the icon for Favorites and Browsing History did not work
- Resetting sorting did not update the screen

### Version 1.1
- The algorithm for working with external EPG sources has been reworked. Now they are global for the plugin.
- Quickly switch playlists/EPG sources using the popup menu
- Added display of the current playlist in the header of Classic UI or by Popup Menu in NewUI
- Added advanced logging setting for problem analysis.
- Setting up management of the browsing history storage folder has been moved to additional settings
- The setting for changing wallpaper has been moved to the interface settings
- Added the ability to create/restore a backup copy of settings
- Full paths to icons are not written in the settings for compatibility of transfer to another console
- Added the ability to set a name for the playlist or EPG source
- Hint in case of empty list of playlists or EPG sources changed for Homatics/Boxy
- Minor fixes to window views
- Archive delay setting did not work
- Fixed processing of the 'group-logo' attribute

### Version 1.0 (First Release)
- The plugin does not support Dune HD with firmware revision less than r11 (101/102/301/Lite 53D/Base/BD Prime, etc.)
- Added the ability to reset the sorting to the original one from the playlist
- Using network shares to import a playlist from a file or folder is disabled. Enabled only for import from link list
- Additional category display options
- Quickly change the playlist in the classic UI using the popup-menu
- Added a hint on how to add a playlist/xmltv source if the list is empty
- Allowed to change EPG cache location for Homatics/Boxy
- File names of category icons and wallpapers are now tied to the playlist to avoid mixing with other files
- Fixed updating the playlist settings screen
- Fixed broken browsing history
- Minor fixes and designs in NewUI

### Version 0.97 (beta)
- Added the ability to change the icon for categories: Favorites, Watch history, All channels
- Added preview of icons when selecting
- The square icons setting has been removed for the classic UI.
- Fixed processing of the 'group-logo' attribute
- Fixed a crash when selecting a category icon
- Fixed processing of the 'group-logo' attribute
- Fixed dune_params processing

### Version 0.96 (beta)
- Added detailed display of channel information
- The selected playlist was not saved

### Version 0.95 (beta)
- Added the ability to change the “wallpaper” of the plugin
- Added the ability to change the category icon
- Changed the scheme for saving settings (the plugin lost settings after a reboot)
- If the playlist does not have any data about the archive type, but the archive days attribute is present, then 'shift' is used
- Setting up internal Dune HD player for .ts .mp4 streams
- The scaling setting is shown in a separate menu
- NewUI context menu allows you to change the proportions of icons.
- The 'Use square icons' setting is only available for NewUI.
- The playlist or xmltv source in the settings shows only the file name (for now it’s necessary)
- Fixed error in determining the 'default' archive type
- Fixed crashes in some cases.

### Version 0.93 (beta)
- Added remembering to use an external player for each channel
- Change history was not recorded in the plugin

### Version 0.92 (beta)
- Added information about channel scaling
- Fixed a bug with displaying files when selecting a playlist or xmlt
- Fixed crashes in some situations
- Fixed display of dune_params

### Version 0.91 (beta)
- Reworked m3u parser to support additional tags
- Added information about the number of archive days in the channel information
- Added support for custom User-Agent for downloading playlists and playing channels
- Added support for dune_params (special parameters for playing channels)
- Added support for #EXTHTTP and #EXTVLCOPT tags for using User-Agent
- Added support for the 'group-logo' attribute to define a group icon
- Colored buttons are disabled depending on the state of the list
- EPG font size setting was not saved
- EPG cache lifetime setting was not saved
- Fixed translation string for SMB settings
- Fixed plugin crash if a channel was removed from the playlist
