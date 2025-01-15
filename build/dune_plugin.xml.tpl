<dune_plugin>
    <type>php</type>
    <name>proiptv</name>
    <caption>ProIPTV</caption>
    <icon_url>plugin_file://icons/default_logo.png</icon_url>
    <background>plugin_file://icons/default_bg.png</background>
    <version>0.0.0</version>
    <version_index>0</version_index>
    <release_date>2023.08.01</release_date>
    <global_actions>
        <boot_end>
            <type>plugin_system</type>
            <data>
                <run_string>bin/update_suppliers.sh</run_string>
            </data>
        </boot_end>
        <install>
            <type>plugin_handle_user_input</type>
            <params>
                <handler_id>entry_handler</handler_id>
                <control_id>plugin_entry</control_id>
                <action_id>install</action_id>
            </params>
        </install>
        <uninstall>
            <type>plugin_handle_user_input</type>
            <params>
                <handler_id>entry_handler</handler_id>
                <control_id>plugin_entry</control_id>
                <action_id>uninstall</action_id>
            </params>
        </uninstall>
        <update>
            <type>plugin_handle_user_input</type>
            <params>
                <handler_id>entry_handler</handler_id>
                <control_id>plugin_entry</control_id>
                <action_id>update</action_id>
            </params>
        </update>
    </global_actions>
    <params>
        <program>starnet.php</program>
    </params>
    <embeddable_plugin_folders>
        <enabled>yes</enabled>
        <update_action>
            <type>plugin_handle_user_input</type>
            <data>
                <show_dialog_delay>10000</show_dialog_delay>
            </data>
            <params>
                <handler_id>entry_handler</handler_id>
                <control_id>plugin_entry</control_id>
                <action_id>update_epfs</action_id>
            </params>
        </update_action>
        <mapping>
            <tv>proiptv</tv>
        </mapping>
    </embeddable_plugin_folders>
    <entry_points>
        <entry_point>
            <parent_media_url>root://tv</parent_media_url>
            <media_url>tv_groups</media_url>
            <caption>ProIPTV</caption>
            <icon_url>plugin_file://icons/default_logo.png</icon_url>
			<small_icon_url>plugin_file://icons/default_logo_small.png</small_icon_url>
            <actions>
                <key_enter>
                    <type>plugin_handle_user_input</type>
                    <params>
                        <handler_id>entry_handler</handler_id>
                        <control_id>plugin_entry</control_id>
                        <action_id>launch</action_id>
                        <mandatory_playback>0</mandatory_playback>
                    </params>
                </key_enter>
                <key_play>
                    <type>plugin_handle_user_input</type>
                    <params>
                        <handler_id>entry_handler</handler_id>
                        <control_id>plugin_entry</control_id>
                        <action_id>launch</action_id>
                        <mandatory_playback>1</mandatory_playback>
                    </params>
                </key_play>
            </actions>
            <popup_menu>
                <menu_items>
                    <menu_item>
                        <caption>%tr%entry_setup</caption>
                        <icon_url>gui_skin://small_icons/setup.aai</icon_url>
                        <action>
                            <type>plugin_handle_user_input</type>
                            <params>
                                <handler_id>entry_handler</handler_id>
                                <control_id>call_setup</control_id>
                            </params>
                        </action>
                    </menu_item>
                    <menu_item>
                        <caption>%tr%setup_channels_src_edit_playlists</caption>
                        <icon_url>gui_skin://small_icons/playlist_file.aai</icon_url>
                        <action>
                            <type>plugin_handle_user_input</type>
                            <params>
                                <handler_id>entry_handler</handler_id>
                                <control_id>call_playlist_setup</control_id>
                            </params>
                        </action>
                    </menu_item>
                    <menu_item>
                        <caption>%tr%setup_edit_xmltv_list</caption>
                        <icon_url>gui_skin://small_icons/sources.aai</icon_url>
                        <action>
                            <type>plugin_handle_user_input</type>
                            <params>
                                <handler_id>entry_handler</handler_id>
                                <control_id>call_xmltv_setup</control_id>
                            </params>
                        </action>
                    </menu_item>
                    <menu_item>
                        <caption>%tr%entry_epg_cache_clear_all</caption>
                        <icon_url>gui_skin://small_icons/subtitles_settings.aai</icon_url>
                        <action>
                            <type>plugin_handle_user_input</type>
                            <params>
                                <handler_id>entry_handler</handler_id>
                                <control_id>call_clear_epg</control_id>
                            </params>
                        </action>
                    </menu_item>
                    <menu_item>
                        <caption>%tr%entry_reboot</caption>
                        <icon_url>gui_skin://small_icons/service_file.aai</icon_url>
                        <action>
                            <type>plugin_handle_user_input</type>
                            <params>
                                <handler_id>entry_handler</handler_id>
                                <control_id>call_reboot</control_id>
                            </params>
                        </action>
                    </menu_item>
                    <menu_item>
                        <caption>%tr%entry_send_log</caption>
                        <icon_url>gui_skin://small_icons/web_browser.aai</icon_url>
                        <action>
                            <type>plugin_handle_user_input</type>
                            <params>
                                <handler_id>entry_handler</handler_id>
                                <control_id>call_send_log</control_id>
                            </params>
                        </action>
                    </menu_item>
                </menu_items>
            </popup_menu>
            <ip_address_required>yes</ip_address_required>
            <show_cookie_name>show_tv</show_cookie_name>
            <show_by_default>yes</show_by_default>
        </entry_point>
        <entry_point>
            <parent_media_url>root://tv</parent_media_url>
            <media_url>launch_vod</media_url>
            <caption>%tr%plugin_vod</caption>
            <icon_url>plugin_file://icons/default_logo_vod.png</icon_url>
            <small_icon_url>plugin_file://icons/default_logo_vod_small.png</small_icon_url>
            <actions>
                <key_enter>
                    <type>plugin_handle_user_input</type>
                    <params>
                        <handler_id>entry_handler</handler_id>
                        <control_id>plugin_entry</control_id>
                        <action_id>launch_vod</action_id>
                    </params>
                </key_enter>
            </actions>
            <ip_address_required>yes</ip_address_required>
            <show_cookie_name>show_vod_icon</show_cookie_name>
            <show_by_default>no</show_by_default>
        </entry_point>
        <entry_point>
            <parent_media_url>setup://applications</parent_media_url>
            <media_url>setup</media_url>
            <caption>ProIPTV</caption>
            <icon_url>plugin_file://icons/default_logo.png</icon_url>
            <actions>
                <key_enter>
                    <type>plugin_open_folder</type>
                </key_enter>
            </actions>
        </entry_point>
    </entry_points>
    <auto_resume>
        <enable>yes</enable>
        <action>
            <type>plugin_handle_user_input</type>
            <params>
                <handler_id>entry_handler</handler_id>
                <control_id>plugin_entry</control_id>
                <action_id>auto_resume</action_id>
                <mandatory_playback>1</mandatory_playback>
            </params>
        </action>
        <ip_address_required>yes</ip_address_required>
        <valid_time_required>yes</valid_time_required>
    </auto_resume>
    <check_update>
        <schema>2</schema>
        <url>http://iptv.esalecrm.net/update/update_proiptv.xml</url>
        <timeout>0</timeout>
        <required>no</required>
        <auto>false</auto>
    </check_update>
    <operation_timeout>
        <default>240</default>
        <get_epg_day>30</get_epg_day>
		<handle_user_input>120</handle_user_input>
    </operation_timeout>
</dune_plugin>
