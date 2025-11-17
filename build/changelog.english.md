### Version 7.0.1448
- Added the ability to use multiple images of the TV show from xmltv (if exists) in the Ext EPG
- Adjust the start of the day depending on the time zone (DuneHD recommendations)
- Added font size change setting for groups/channels in the window in the Play mode
- Added a warning when selecting the XMLTV engine about the absence of selected sources
- Resume play is now available for VOD as well. That is, instead of the TV channel, the movie will resume if it was turned off while watching
- Added a Sleep timer when watching a show or movie. NOT available for ATV devices (based on Homatics)!
- A yellow button in the Play mode calls a window with a fixed selection of the sleep time.
- Green button in the Play mode - manual sleep timer mode. The step can be changed in the plugin settings, the default is 1 minute.
- Red button in the Play mode - disabling the timer. You can also disable it by selecting "No" as the sleep timer dialog called by yellow button.
- 2 minutes before sleep, OSD with a countdown is shown, the countdown start time can also be set in the plugin settings
- When you exit from Play mode of TV/movie, the sleep timer turns off!
- A new mechanism for notifying the plugin when XMLTV indexing is complete, now the plugin does not check the state of the process every second and does not waste resources on it. NOT available for ATV devices (based on Homatics)!
- Fixed a spelling error in the Sharaclub subscription window
- Fixed an incorrect plugin error message on the EPG setup page
- Fixed a bug with calling the internal timer in NewUI
- Configuration: added the ability to change the domain for the API for providers IPTVOnline/Tvizi (for previous versions of 7.x, you may need to go to the provider settings and select the desired domain)
- Configuration: VivaMax provider added
- Configuration: Updated configuration for Peak TV

### Version 7.0.1422
- Fixed a bug that caused the plugin to crash if an error message was displayed
- Fixed a bug that caused the plugin to crash when calling the settings password dialog
- Removed support for caching playlists at the web server level. Link shorteners have problems with it

### Version 7.0.1416
- Changed the organization of settings. Now they are all grouped by the settings of the plugin itself and separately the playlist. Access to the settings is also available from the context menu on the plugin icon
- Added the ability to use links or a file from the account for provider templates (Playlist Settings -> Provider Settings). The correctness of the playlist format is not checked. Since many providers have a "zoo" of playlists of varying degrees of information. As a rule, the most suitable is m3u8 for Ott Navigator
- For provider templates, it is now possible to have a common "Favorites" for all provider playlists. When changing the settings, the option to copy favorites to/from the general from/to the current one is requested
- Personalized caching settings for each playlist of the provider template
- Returned the ability to add a playlist that is located on a network drive for all consoles except "certified"
- Added the ability to "Do not check cache" of the playlist. That is, it can only be updated forcibly from the "Update playlist" context menu
- Added caching setting for VOD m3u playlists
- Slightly changed the mechanism of the "Watch History", now the plugin will not try to update the EPG for all channels in the history at startup (when updating NewUI files).
- In NewUI, channels in the "Watch History" are signed with the date and time when watching the program ended (if there is an archive)
- Hidden channels that have been added to "Favorites" before are marked with a special icon.
- Made a global channel search by name, not just in one specific category
- The last value of the search is now remembered
- Added clearing of VOD "Playlist" from movies that have disappeared from the media library
- Added display of information about the availability of a VOD for a playlist or when adding a new provider template
- Changed some translation strings
- Fixed a bug that caused the playlist cache to be cleared when quickly switching by hot buttons
- Configuration: Updated template settings for TVIZI, TV Team, SatQ, Skaz, 1USD, FoxTV
- Configuration: Added online EPG sources for RU. TV and Uspeh
- Configuration: Removed HNMedia provider, now KLI Media
- Configuration: Removed unsupported XMLTV sources from tv.team

### Version 6.2.1358
- Fixed a bug with caching links to regular playlists

### Version 6.2.1356
- Clicking the 'Play' button starts processing the XMLTV source in the XLMLV source list
- Added connection and download timeout settings in the advanced plugin settings
- Cache of Internet sources moved to the plugin folder from a temporary folder so that they do not disappear after the plugin is reloaded
- Playlist cache moved to the plugin folder from a temporary folder so that they don't disappear after the plugin is reloaded
- Information about hidden channels on the category screen is updated after changes
- Added an additional script to hide channels 'Hide SD channels if HD is present'
- Reworked the download mechanism to improve error handling. Now timeout errors do not lead to incorrect processing of partially downloaded playlist
- Fixed a bug with incorrect clearing of the EPG cache for XMLTV and Internet sources
- Updated API for IPTV Online provider. To solve blocking problems
- Improved the window for displaying channel information
- Due to frequent problems with downloading a playlist template from epg.one (for Edem and his clones), a playlist mirror selection setting has been added. Look into provider settings

### Version 6.2.1334
- Added the ability to configure the playlist caching time. From 1 hour to 7 days. Look into Playlist settings
- Added the ability to configure the caching time of the Internet EPG source (for provider templates). From 1 to 12 hours. Look into EPG Settings
- Configuration: Added additional domains for Sharavoz
- Configuration: Removed EPG source from Sharavoz. It looks like dead. Only epg.drm-play remains
- Fixed a bug with showing the name of the program in NewUI
- Fixed a bug that could not restore hidden categories

### Version 6.2.1320
- Added the ability to export playlists and external XMLTV sources to a text file for quick import later
- Returned the ability to clear the browsing history with the 'clear' key or through the popup menu from the category screen
- Expanded the ability to adjust the EPG shift for each channel individually with an accuracy of 5 minutes and a range of +/- 23 hours
- The dialog for showing the current EPG on the channel screen is supplemented with an indication of the elapsed time since the start of the program
- Fixed a crash when calling up advanced channel settings

### Version 6.2.1310
- Added the ability to configure the EPG shift for each channel individually
- External player playback, zoom and EPG shift settings are placed in a separate dialog
- The media library shows additional information and a poster for the season (if any)
- Added a 'fix' for playlist "compilers" who don't know that the playlist should have a tag #EXTM3U
- Display an error message when manually starting XMLTV processing
- When adding an IPTV provider, additional information (ID and type of access parameters) is shown according to the template. This information is useful when adding through a playlist list
- Configuration: Added Skaz TV provider (http://skaz.tv/)
- Configuration: Added additional domains for Sharavoz
- Configuration: Changed KLI Media settings
- Configuration: Changed playlist link for RusskoeTV
- Configuration: Changed Internet source for PeakTV
- Configuration: Added the ability to change the stream type (HLS/MPEG-TS) for ReflexTV
- Fixed a bug that did not allow you to change the Internet EPG source if it was removed from the configuration

### Version 6.2.1294
- Minor changes in working with the database
- Fixed a bug adding a link to a playlist

### Version 6.2.1290
- Due to some changes, drm-play has updated the algorithm for generating links to requests to the EPG server
- Added a current link to the EPG Internet source to the channel information

### Version 6.2.1280
- Converting html tags into XMLTV, which are sometimes found in the text
- Additional view of icons in VOD (5x3)
- The 'Select or Edit Playlists' item in the context menu has been moved to the top of the list.
- Correct indication of the name of the playlist (if different from the name of the provider)
- In the message about sending the log, the model of device is indicated. For information to the user
- Fixed a bug with getting some attributes in the M3U parser
- Fixed a bug with launching the plugin from a separate VOD icon
- Configuration: BlinkTV replaced by ReflexTV (renamed)
- Configuration: 2tv Provider Support (https://2tv.biz/)
- Configuration: Additional Internet EPG source for Sharavoz
- Configuration: Additional domain for Sharavoz

### Version 6.2.1264
- Time zone is taken from Android settings, old PHP code "compatible" with Sigma set-top boxes removed
- Added program time information when calling the current EPG for a channel in Classic
- When patching/restoring the palette, a full reboot of the console is no longer needed. Dune Shell reboot only.
- Fixed a bug that did not allow you to select a file with a list of playlist links

### Version 6.2.1260
- Added ability to create internal playlists for M3U-based VOD (e.g. for watching TV shows)
- Backup/Restore moved to the plugin context menu, removed from the settings
- The History setting has been moved to a separate settings screen
- Added an additional setting to fix the text color palette (Air and Silver skins or skins based on these skins mess up the default palette for unused colors).

### Version 6.1.1240
- NewUI: TV program time was calculated incorrectly for the current time zone

### Version 6.1.1238
- Fixed incorrect line ending (CRLF instead of LF) in scripts for ext_epg
- Configuration: Additional settings for Cbilling/Antifriz have been reverted back

### Version 6.1.1234
- Due to problems on the provider side has disabled additional settings for Cbilling/Antifriz
- Fixed a bug with showing categories for M3U-based VODs

### Версия 6.1.1230
- Clarifications for calculating the time zone for NewUI (for Classic, it is calculated differently)
- The sorting order of categories in the OSD player did not coincide with the real sorting of categories.

### Version 6.1.1226
- NewUI: Added the ability to disable the continuous channel flow mode. In this case, NewUI slows down a little less
- NewUI: Optimizing data generation for NewUI. Now it's a little faster
- NewUI: For adult channels, the preview does not ask for a password, but simply does not show the picture. Unless the password is disabled.
- NewUI: After clearing History/Favorites/Changed channels, the screen did not update
- Fixed a bug if the channel ID detection was selected in the playlist properties
- Handling situations when there was a comma in the channel name in the m3u file, which is forbidden by the standard, but who care?
- Added DST handling to correctly display XMLTV download time (hi there to the ancient PHP used by Dune API)
- Optimize SQL queries for faster synchronization of playlist changes. Now on VERY large playlists, the plugin does not fall with the operation time exceeding
- The data in the information on the playlist screen did not update after editing it
- Secure file generation for ext_epg, sometimes ext_epg blocked data from being written by the plugin

### Version 6.1.1216
- Correct time zone and daylight conversions for ext_epg

### Version 6.1.1214
- Another update to XMLTV indexing for "too smart" contributors
- Added the ability to copy an XMLTV source from a playlist to the list of external sources
- If the XMLTV source is present in the playlist and it is shown as a separate icon in the external list
- NewUI: added the ability to show the number of channels in the category name
- Configuration: New domain for Glanz
- Configuration: Support for IPTV provider Shurik TV (https://schuriktv.nethouse.ru/)
- Added display of subscription information for Shurik TV
- Category icons are updated if they have changed in the playlist and are not manually set by the user

### Версия 6.1.1202
- The API method of determining a full-size remote control for Google like devices did not work. Added an item in the advanced settings to enable the capabilities of the full-size remote control

### Version 6.1.1200
- Toggle shortcuts are available for premier/homatics/boxy boxes with a full-size remote
- Slightly reworked XMLTV handling to reduce the number of locks during indexing
- Reduced the frequency of data search queries in an XMLTV file to get an EPG channel
- Allowed editing caching parameters for XMLTV sources specified in the configuration
- Sources with incorrect parameters (error updating old settings) are deleted automatically
- If the playlist file does not exist (it is a file, not a link), then it is marked in the playlist list
- Improved batch processing of adding playlists
- The old password for adult channels or settings protection is no longer asked if it has been disabled
- Deleting a playlist or XMLTV source is bound to the CLEAR button
- Added display of all information about the XMLTV source by the INFO button or through the popup menu, some of the information has been removed from the right column in this dialog
- Implemented return to the list of categories when calling the list of playlists on the plugin icon and selecting a new playlist
- When starting VOD from Watch History, the movie info window is no longer shown. Go directly to the list of episodes or episodes
- Optimized some playlist database queries for faster execution
- Updated translation for strings that have not been translated (Channel Information)
- Fixed VOD playback from the last viewed position
- Returned correct handling of the Play key on the plugin when Autoplay is disabled
- Clarifications to determine the Channel ID. Empty values were not taken into account when counting duplicates and, as a result, choosing the wrong method
- Fixed some error messages when downloading a playlist
- Fixed some bugs updating old settings dune_params
- Can't change playlist name
- New channels from the playlist were simply added and not listed in the Modified category
- Channel name, category and adult status are updated if they change in the playlist
- Incorrectly reset sorts and channel numbering
- Information for extended EPG (ext_epg) could not be written correctly if the channel name with Cyrillic or other non-Latin letters was used as the channel ID.

### Version 6.0.1154
- Playlist shortcuts work on the Channels/Favorites/History and NewUI screen
- Fixed a bug with replacing the selected XMLTV source when adding a new link
- Fixed a bug with processing serials for VOD
- The Use dune_params setting was not disabled
- In some cases, XMLTV was processed twice when the plugin was loaded
- Sometimes the EPG cache was not cleared correctly

### Version 6.0.1146
- Fixed another bug when adding the Edem provider
- Auto start and Auto play settings did not work
- Non-standard Favorites icon was not shown in the OSD player
- Channel icons in the OSD player were taken only from the playlist
- When restoring a backup, the settings folder was not cleared
- Fixed a bug when displaying channel information if the M3U playlist did not contain an infomation header
- Unnecessary error message when uninstalling a plugin
- Fixed a bug with adding/editing XMLTV sources
- UserAgent configuration was not remembered
- NewUI: The channel number has always been 0. The channel number now matches the order in the playlist

### Version 6.0.1130
- Fixed a bug with adding the Edem provider
- Fixed a bug with channel search
- Fixed a bug with changing the settings for displaying special categories (Favorites/Watch history)
- Fixed a bug adding a link to a playlist
- Fixed a bug with updating the plugin background when changing the playlist
- Setting up EPG emulation was not enabled
- The history folder was not created during a clean plugin installation
- Minor fixes in the M3U parser
- Added a check for Extended EPG support for the current Dune HD firmware
- Favorites/Browsing history/Changed always used playlist icons, regardless of icon source settings
- XMLTV sources from the plugin configuration were not saved in the general list
- Clearing Favorites and Browsing History has been removed from the context menu of the category screen

### Version 6.0.1110
- Support for set-top boxes with revisions less than r21 has been discontinued and this update is not available for them (Sigma r11 and new set-top boxes with r19 and r20 firmware). For new devices, I recommend updating the firmware to the current r22 or r21
- Completely redesigned internal storage engine to reduce processing time and memory consumption. Everything is transferred to the sqlite database engine. Non-sqlite devices are not supported (mainly on Sigma processors)
- All settings will be converted to the new format. When updating plugins versions earlier than 6.0, it is suggested to make a backup copy of the settings
- Added support for Extended EPG, enable/disable settings in the Playback Settings setup screen
- Added editing playlist parameters from Playlist screen
- Added editing of link parameters from the XMLTV list screen
- Expanded the list of supported XMLTV tags for use in Extended EPG
- Redesigned popup menu on the category screen.
- Icon selection options for a playlist have been moved to playlist settings
- Added a point to select the best option for the Channel ID
- Added setting to hide categories for adults
- Added the ability to assign a shortcut to switch to a playlist (only for full-size remotes, keys 0-9)
- Settings dune_params, UserAgent, etc. moved to a separate playlist settings screen
- Playlist name is no longer shown in the header (the name of the current playlist can be viewed in the context menu)
- Removed https -> http conversion as irrelevant for new devices
- NewUI: Fixed icon for channels without icons if square icons aspect ratio mode is selected

### Version 5.1.1014
- Added confirmations when clearing Favorites/History/Changed channels
- Fixed bug getting ETag for xmltv cache in case of redirect to another link
- Fixed a bug with removing a deleted channel from Favorites
- Fixed a bug with moving the playlist to the end/beginning of the list
- Stalled XMLTV indexing flags were not always removed
- Fixed a bug with searching for icons in XMLTV sources if one of the sources was not indexed
- Added HN Media provider (via configuration)

### Version 5.1.1000
- Added display EPG on the SUB button or via the context menu in the channel list
- Faster processing of deletion TV history
- Minor improvements in channel ID detection when adding a playlist
- NewUI: Slight optimization of EPFS generation

### Версия 5.1.988
- Updated XMLTV processing engine to fix new bugs of EPG providers (Yosso).
- XMLTV processing on Android devices is speed-up by 1.5-2 times
- Information about the XMLTV source is specified more accurately.

### Version 5.1.984
- NewUI: Added channel preview for the History category
- NewUI: Minor changes in the context menu (Playlist refres moved to the first menu item)
- NewUI: Removed EPG fan art processing error if it is not present in the EPG source
- Minor version checks
- Update translation

- ### Version 5.1.976
- Added Velestore provider (via configuration)
- If no XMLTV EPG source is selected, the corresponding message will be shown instead of the TV program
- Fixed a bug where it was impossible to select the detection of the channel ID, the source of channel icons, the type of archive
- A slight clarification of the M3U parser to determine the channel name

### Version 5.1.966
- Added additional channel/favorites views (for square icons), thanks to igores
- Settings for views categories/channels/etc. are remembered for each playlist
- Fixed a bug with parsing M3U playlists in the VOD
- Updated icon library by Viktor (thanks to igores)

### Version 5.1.962
- Faster response times for manual channel/category sorting
- Updated Filmax, RU TV configurations
- Provider Uspeh support has been returned (only for playlists from the Telegram bot!)

### Version 5.1.954
- Default setting: Show VOD category was not saved correctly
- Soeed up exit from the plugin.

### Version 5.1.952
- Default settings were not applied when creating a new playlist

- ### Version 5.1.950
- Added checking and setting default settings for plugin configuration
  and the received settings of the provider's account in case of changes in important parameters and updates of old settings.

### Version 5.1.946
- Added a secondary internet source for TV Team provider
- By the default display of the VOD category could be turned off
- Additional provider parameters were not always saved

### Version 5.1.942
- Updated configurations for 101Film, BCU-Media, Sharavoz, Peak TV providers
- Removed support for the Uspeh provider. Until their admins straighten their arms.
- Added setting to disable the display of the VOD category
- Category display settings are now personal for the playlist, not global for the plugin
- Caching settings have been removed from the plugin settings and moved to the properties of xmltv sources.
  Now they are individual for each link. The setting is available through the pop-up menu in the list of XMLTV sources
- In the xmltv source information, information has been added to indicate whether this source supports automatic cache configuration.
  If not, then manually set the lifetime of the source xmltv cache, in order to avoid constant downloading and indexing
  every time you run the plugin.
- Removed the ability to add xmtlv sources as files. Now only the links and the list of links
- Fixed bugs related to the support of automatic caching of xmltv sources

### Version 5.1.924
- Fixed a bug that did not allow you to get into the additional settings of the provider
- Enabled VOD for PikTV

### Version 5.1.922
- NewUI: Added a mode to disable the display of the channel name
- NewUI: Settings are now playlist-specific instead of global for the plugin
- Fixed EPG configuration for TV Team

### Version 5.1.916
- NewUI: Added mode with 6 and 5 columns in a row
- All settings for NewUI have been moved to a separate item in the plugin settings
- Fixed caption for adding/removing to Favorites for the blue button in NewUI
- Fixed a request error for the EPG Internet source for TV Club

### Version 5.1.908
- Added the ability to select the type of playlist (link or file) for IPTV or VOD.
- Fixed a bug with adding/removing a channel to Favorites in NewUI

### Version 5.1.900
- Fixed selection of active XMLTV sources when changing playlist
- Minor fixes to the HTTP ETag caching mechanism
- If the EPG contains images of a TV program, they are shown in NewUI in the preview window
- Playlist/XMLTV source selection is now by the Enter button, and editing by the Blue button
- Fixed a small bug in Korona's VOD

### Version 5.1.890
- Fixed a plugin crash if there are no playlists in the list

### Version 5.1.886
- Added support for the Korona provider (https://korona-tv.top/)
- Added support for Pik TV (http://pa.piktv.top/)
- Added support for IPTV 360 (https://iptv360.ru/) provider, including for plugin versions 5.0.x
- Updated the configuration for Sharavoz (due to the blocking of some of their domains), including for the 5.0.x versions of the plugin
- Added an automatic algorithm for determining the Channel ID for links and M3U files based on playlist attributes. For existing playlists, you can change the algorithm only in the settings.
- Added the ability to move a category/channel to the beginning or end of the list. Toggle the navigation mode by pressing the SELECT button.
- Added the Hide Category/Channel action to the CLEAR button.
- Added additional XMLTV information on the source list editing screen.
- Added indication of the XMLTV download or indexing process on the source list editing screen.
- Added the ability to select an alternative Internet EPG source (not available for all providers)
- Removed the context menu for changing the playlist/xmltv source (if there are a large number of entries Dune UI glitch).
- Fixed the translation of some strings
- Fixed some minor bugs in NewUI

### Version 5.0.852
- Changes to the access parameters for the iEdem/iLook provider were not saved

### Version 5.0.850
- Fixed the media library filter when selecting the year for IPTV Online/Tvizi

### Версия 5.0.846
- Accelerated algorithm for searching icons in xmltv source
- Added checking and removal of hung xmltv indexing flags
- Updated translation

### Version 5.0.836
- Improved icon search mechanism in xmltv source. It is advisable to completely clear the weight of the EPG cache after the first run, because there have been changes in the database for storing information about icons.
- Removed erroneous clearing of the EPG cache when updating NewUI
- EPG for the channel was not updated during viewing after indexing ended

### Версия 5.0.832
- Fixed a bug with duplicating icons in the 'XMLTV' icon source mode

### Version 5.0.830
- Added a new mode to find channel icons 'Playlist + XMLTV'. If no channel icons are found in the playlist, the search will continue in the selected XMLTV sources
- Added UI setting for NewUI to change the location of the channel number on the preview screen
- An additional icon on the 'Edit XMLTV sources' screen indicating that the source is indexed
- Small fixes for NewUI (so that text does not overlaps the preview area)
- Fixed the order of the list of channels in a category for NewUI (the order set by the user was not taken into account)
- Fixed a bug with detecting the end of indexing process of an XMLTV source

### Version 5.0.820
- Fixed a bug for the "Use icons from XMLTV" setting, which breaks the loading of the channel list

### Version 5.0.816
- Added support for multiple XMLTV sources for a single playlist. Unfortunately, processing XMLTV is still a heavy task for older devices and low-end models, so it is not recommended to include all available sources. The order in which the EPG is searched is determined by the order of the sources in the list.
- Ability to manually start indexing the XMLTV source in the source list
- Added support for Blink TV (https://blinktv.cc/) provider
- API update for Sharaclub
- Fixed a bug with getting information about a movie in the IPTV Online VOD

### Version 4.2.802
- Added a new XMLTV cache management mode. Enabled by default. Most providers support the use of ETag. For those providers who can't properly configure their server or Cloudflare to use ETag, there is an option to switch to the old "manual" mode in the plugin settings.
- Changes in API IPTV Online

### Version 4.1.790
- Added support for the provider Nasharu TV (https://nasharu.tv/)
- Added support for Ott Pub provider (https://ott.pub/)
- Added support for the global catchup-days parameter
- Fixed a bug with displaying categories in Changed channels
- Updated preset settings for EPG web sources

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
