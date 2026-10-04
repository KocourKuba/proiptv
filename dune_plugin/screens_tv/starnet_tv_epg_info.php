<?php
/**
 * The MIT License (MIT)
 *
 * @Author: sharky72 (https://github.com/KocourKuba)
 * Idea and key handling from the custom EPG window of the home_tv plugin (Brigadir, forum.mydune.ru),
 * GComps layout from the ext_epg plugin of the firmware
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to
 * deal in the Software without restriction, including without limitation the
 * rights to use, copy, modify, merge, publish, distribute, sublicense
 * of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included
 * in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
 * THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
 * DEALINGS IN THE SOFTWARE.
 */

require_once 'lib/abstract_screen.php';
require_once 'lib/user_input_handler_registry.php';
require_once 'lib/epg/ext_epg_program.php';
require_once 'lib/epfs/gcomps_factory.php';
require_once 'lib/epfs/gcomp_geom.php';

/**
 * EPG info screen opened by the INFO key over TV playback.
 * It is a GComps screen like the ext_epg plugin of the firmware: the program image keeps its aspect ratio
 * and the text is laid out and wrapped by the firmware.
 *
 * LEFT/RIGHT browse the programs of the channel, UP/DOWN and P+/P- scroll the description,
 * ENTER replays the shown program from the archive, PLAY returns to the live program,
 * SELECT toggles the technical info of the stream.
 */
class Starnet_Tv_Epg_Info extends Abstract_Screen
{
    const ID = 'tv_epg_info';

    const ACTION_CLOSE = 'epg_info_close';

    // ids of the panels changed while the screen is shown
    const PANE_HEADER = 'epg_header';
    const PANE_STREAM = 'epg_stream';
    const PANE_PROGRAM = 'epg_program';
    const PANE_DETAILS = 'epg_details';
    const PANE_HELP = 'epg_help';

    // layout of the 1920x1080 screen
    const SCREEN_LEFT = 80;
    const SCREEN_WIDTH = 1760;
    const HEADER_TOP = 40;
    const HEADER_HEIGHT = 90;
    const STREAM_TOP = 150;
    const STREAM_HEIGHT = 60;
    const PROGRAM_TOP = 225;
    const PROGRAM_HEIGHT = 750;
    // top of the image and the details inside the program panel
    const BODY_TOP = 190;
    // the body follows the title of one or two lines, it must fit the program panel after two lines
    const BODY_GAP = 20;
    const BODY_HEIGHT = 540;
    const IMAGE_WIDTH = 480;
    // the whole height of the body, a portrait poster fills it
    const IMAGE_HEIGHT = self::BODY_HEIGHT;
    const HELP_TOP = 990;
    const HELP_HEIGHT = 60;
    const ROW_GAP = 8;

    const HEADER_TEXT_SIZE = 44;
    const TITLE_TEXT_SIZE = 52;
    const PROGRAM_TEXT_SIZE = 40;
    const TEXT_SIZE = 36;
    const SMALL_TEXT_SIZE = 30;

    // scroll of the details by UP/DOWN and P+/P-
    const SCROLL_STEP = 60;
    const SCROLL_PAGE = 480;

    // average width of a char of TEXT_SIZE, used only to start the playback bar after the stream info
    const STREAM_CHAR_WIDTH = 20;
    // the playback bar ends before the playback time at the right side of the stream row
    const STREAM_BAR_END = 1460;
    const STREAM_BAR_MIN_WIDTH = 300;
    const STREAM_BAR_HEIGHT = 12;

    const FUTURE_EPG_DAYS = 7;

    // colors are RGBA
    const COLOR_TEXT = '#FFFFE0FF';
    const COLOR_VALUE = '#AFAFA0FF';
    const COLOR_GENRE = '#808080FF';
    const COLOR_SILVER = '#C0C0C0FF';
    const COLOR_NUMBER = '#5080FFFF';
    const COLOR_CHANNEL = '#FFFF00FF';
    const COLOR_TIME = '#50FF50FF';
    const COLOR_REPLAY = '#FF4040FF';
    const COLOR_ARCHIVE = '#FF5030FF';
    const COLOR_HEADER_BG = '#000000A0';
    // shade over the video, the last pair is the opacity: 00 - none, FF - black
    const COLOR_SHADE = '#000000A0';
    const COLOR_BAR_BG = '#FFFFFF40';
    const COLOR_BAR = '#FFFFFFFF';
    // window behind the screen and the transparency of the video under it, as in the ext_epg of home_tv
    const WINDOW_COLOR = '#101010';
    const PLAYBACK_BG_ALPHA = 180;

    /**
     * @var string
     */
    protected $channel_id;

    /**
     * @var string|null
     */
    protected $group_id;

    /**
     * @var bool
     */
    protected $is_favorite = false;

    /**
     * number, title, icon, archive (days)
     * @var array
     */
    protected $channel = array();

    /**
     * loaded day epg, day start => programs
     * @var array
     */
    protected $days = array();

    /**
     * @var array|null
     */
    protected $program;

    /**
     * @var bool
     */
    protected $show_tech_info = false;

    /**
     * firmware translation keys of the people labels (one person, several persons) => english default
     * @var array
     */
    protected static $people_labels = array(
        PluginTvExtEpgProgram::actor => array('osd_epg_label_actor' => 'Actor:', 'osd_epg_label_actors' => 'Actors:'),
        PluginTvExtEpgProgram::director => array('osd_epg_label_director' => 'Director:', 'osd_epg_label_directors' => 'Directors:'),
        PluginTvExtEpgProgram::editor => array('osd_epg_label_editor' => 'Editor:', 'osd_epg_label_editors' => 'Editors:'),
        PluginTvExtEpgProgram::writer => array('osd_epg_label_writer' => 'Scenario:'),
        // osd_epg_label_producer(s) exist only on r25
        PluginTvExtEpgProgram::producer => array('osd_tag_producer__1' => 'Producer:'),
        PluginTvExtEpgProgram::composer => array('osd_epg_label_composer' => 'Composer:', 'osd_epg_label_composers' => 'Composers:'),
        PluginTvExtEpgProgram::presenter => array('osd_epg_label_presenter' => 'Presenter:', 'osd_epg_label_presenters' => 'Presenters:'),
    );

    ///////////////////////////////////////////////////////////////////////

    /**
     * Open the screen for the channel being played
     *
     * @param object $user_input
     * @return array|null
     */
    public function show($user_input)
    {
        hd_debug_print(null, true);

        if (!isset($user_input->plugin_tv_channel_id)) {
            return null;
        }

        $group_id = isset($user_input->plugin_tv_group_id) ? (string)$user_input->plugin_tv_group_id : null;
        $is_favorite = !empty($user_input->plugin_tv_is_favorite) || empty($group_id);
        if (!$this->init_channel((string)$user_input->plugin_tv_channel_id, $group_id, $is_favorite)) {
            return null;
        }

        return Action_Factory::open_folder(
            MediaURL::encode(array(PARAM_SCREEN_ID => static::ID, PARAM_CHANNEL_ID => $this->channel_id)));
    }

    ///////////////////////////////////////////////////////////////////////
    // Screen interface

    /**
     * @inheritDoc
     */
    public function get_folder_view(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);

        // the firmware may ask for the screen again without the INFO key, e.g. after a return to it
        $channel_id = safe_get_value($media_url, PARAM_CHANNEL_ID);
        if ($channel_id !== null && (string)$channel_id !== $this->channel_id) {
            $this->init_channel((string)$channel_id, null, true);
        }

        // closed over the playback the screen only hides and stays in the folder stack, the firmware shows it again
        // when the playback is stopped. Nothing is drawn then and the timer that fires at once closes it
        if (self::is_navigator()) {
            return array(
                PluginFolderView::multiple_views_supported => false,
                PluginFolderView::archive => null,
                PluginFolderView::view_kind => PLUGIN_FOLDER_VIEW_GCOMPS,
                PluginFolderView::data => array(
                    PluginGCompsFolderView::window_def => GComps_Factory::get_window_def(array(), null, null, self::WINDOW_COLOR),
                    PluginGCompsFolderView::sel_state => null,
                    PluginGCompsFolderView::actions => $this->get_action_map($media_url, $plugin_cookies),
                    PluginGCompsFolderView::timer => $this->get_timer(),
                ),
            );
        }

        $defs = array(
            // dims the video under the whole screen, the text stays readable on a bright picture
            GComps_Factory::get_rect_def(GComp_Geom::place_top_left(1920, 1080), null, self::COLOR_SHADE),
            GComps_Factory::get_panel_def(self::PANE_HEADER,
                GComp_Geom::place_top_left(self::SCREEN_WIDTH, self::HEADER_HEIGHT, self::SCREEN_LEFT, self::HEADER_TOP),
                null, $this->get_header_defs()),
            GComps_Factory::get_panel_def(self::PANE_STREAM,
                GComp_Geom::place_top_left(self::SCREEN_WIDTH, self::STREAM_HEIGHT, self::SCREEN_LEFT, self::STREAM_TOP),
                null, $this->get_stream_defs()),
            GComps_Factory::get_panel_def(self::PANE_PROGRAM,
                GComp_Geom::place_top_left(self::SCREEN_WIDTH, self::PROGRAM_HEIGHT, self::SCREEN_LEFT, self::PROGRAM_TOP),
                null, $this->get_program_defs()),
            GComps_Factory::get_panel_def(self::PANE_HELP,
                GComp_Geom::place_top_left(self::SCREEN_WIDTH, self::HELP_HEIGHT, self::SCREEN_LEFT, self::HELP_TOP),
                null, $this->get_help_defs()),
        );

        return array(
            PluginFolderView::multiple_views_supported => false,
            PluginFolderView::archive => null,
            PluginFolderView::view_kind => PLUGIN_FOLDER_VIEW_GCOMPS,
            PluginFolderView::data => array(
                PluginGCompsFolderView::window_def => GComps_Factory::get_window_def($defs, null, null,
                    self::WINDOW_COLOR, null, false, self::PLAYBACK_BG_ALPHA),
                PluginGCompsFolderView::sel_state => null,
                PluginGCompsFolderView::actions => $this->get_action_map($media_url, $plugin_cookies),
                PluginGCompsFolderView::timer => $this->get_timer(),
            ),
        );
    }

    /**
     * @inheritDoc
     */
    public function get_action_map(MediaURL $media_url, &$plugin_cookies)
    {
        return $this->do_get_action_map();
    }

    /**
     * @inheritDoc
     */
    public function get_timer()
    {
        // without the playback a zero delay fires at once, the timer handler closes the screen
        return Action_Factory::timer(self::is_navigator() ? 0 : 1000);
    }

    /**
     * No playback, the screen is shown again after the playback under it is stopped
     *
     * @return bool
     */
    protected static function is_navigator()
    {
        return safe_get_value(get_player_state_assoc(), PLAYER_STATE) === PLAYER_STATE_NAVIGATOR;
    }

    ///////////////////////////////////////////////////////////////////////
    // User_Input_Handler interface

    /**
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        if (!isset($user_input->control_id)) {
            return null;
        }

        $control_id = $user_input->control_id;
        hd_debug_print("control_id: $control_id", true);

        // the playback may be stopped under the screen, the firmware then shows the screen again without the video
        $is_playing = safe_get_value($user_input, 'play_mode') === 'plugin_tv';

        switch ($control_id) {
            case self::ACTION_CLOSE:
                return Action_Factory::close_and_run($is_playing ? $this->plugin->iptv->get_playback_behaviour() : null);

            case GUI_EVENT_TIMER:
                // the timer fires once, each tick sets it again. A zero delay does not stop it, it fires at once.
                // nothing to follow without the playback: leave the screen without setting the timer again
                if (!$is_playing) {
                    return Action_Factory::close_and_run();
                }

                // clock and playback position, the played program may have changed meanwhile
                return $this->change_panes(array(self::PANE_HEADER, self::PANE_STREAM, self::PANE_HELP),
                    Action_Factory::change_behaviour($this->do_get_action_map(), 1000));

            case GUI_EVENT_KEY_SELECT:
                // tech info belongs to the played program only
                if (!$this->show_tech_info && !$this->is_played_program()) {
                    return null;
                }
                $this->show_tech_info = !$this->show_tech_info;
                return $this->change_panes(array(self::PANE_PROGRAM, self::PANE_HELP));
        }

        if ($this->show_tech_info) {
            return null;
        }

        switch ($control_id) {
            case GUI_EVENT_KEY_LEFT:
                if ($this->program !== null) {
                    $program = $this->find_program($this->program[PluginTvEpgProgram::start_tm_sec] - 1);
                    $min_ts = time() - (max(1, $this->channel['archive']) + 1) * 86400;
                    if ($program !== null && $program[PluginTvEpgProgram::end_tm_sec] > $min_ts) {
                        $this->program = $program;
                        return $this->change_panes(array(self::PANE_STREAM, self::PANE_PROGRAM, self::PANE_HELP));
                    }
                }
                break;

            case GUI_EVENT_KEY_RIGHT:
                if ($this->program !== null) {
                    $program = $this->find_program($this->program[PluginTvEpgProgram::end_tm_sec]);
                    $max_ts = time() + self::FUTURE_EPG_DAYS * 86400;
                    if ($program !== null && $program[PluginTvEpgProgram::start_tm_sec] < $max_ts) {
                        $this->program = $program;
                        return $this->change_panes(array(self::PANE_STREAM, self::PANE_PROGRAM, self::PANE_HELP));
                    }
                }
                break;

            case GUI_EVENT_KEY_UP:
                return $this->scroll_details(-self::SCROLL_STEP);

            case GUI_EVENT_KEY_DOWN:
                return $this->scroll_details(self::SCROLL_STEP);

            case GUI_EVENT_KEY_P_PLUS:
                return $this->scroll_details(-self::SCROLL_PAGE);

            case GUI_EVENT_KEY_P_MINUS:
                return $this->scroll_details(self::SCROLL_PAGE);

            case GUI_EVENT_KEY_ENTER:
                if ($this->program !== null && $this->can_replay($this->program)) {
                    return $this->get_play_action($this->program[PluginTvEpgProgram::start_tm_sec]);
                }
                break;

            case GUI_EVENT_KEY_PLAY:
                // nothing to play for a program that has not started yet
                if ($this->program !== null && $this->program[PluginTvEpgProgram::start_tm_sec] > time()) {
                    break;
                }

                $live_program = $this->find_program(time());
                if ($live_program !== null && ($this->program === null
                        || $this->program[PluginTvEpgProgram::start_tm_sec] != $live_program[PluginTvEpgProgram::start_tm_sec])) {
                    $this->program = $live_program;
                    return $this->change_panes(array(self::PANE_STREAM, self::PANE_PROGRAM, self::PANE_HELP));
                }

                if ($this->is_archive_playback()) {
                    return $this->get_play_action(-1);
                }
                break;
        }

        return null;
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param string $channel_id
     * @param string|null $group_id
     * @param bool $is_favorite
     * @return bool
     */
    protected function init_channel($channel_id, $group_id, $is_favorite)
    {
        $channel_row = $this->plugin->get_channel_info($channel_id, false);
        if (empty($channel_row)) {
            return false;
        }

        $this->channel_id = $channel_id;
        $this->group_id = $group_id;
        $this->is_favorite = $is_favorite;
        $this->channel = array(
            'number' => $channel_row[COLUMN_CH_NUMBER],
            'title' => $channel_row[COLUMN_TITLE],
            'icon' => $this->plugin->get_channel_picon($channel_row, true),
            'archive' => (int)$channel_row[COLUMN_ARCHIVE],
        );

        $this->days = array();
        $this->show_tech_info = false;
        $this->program = $this->find_program($this->get_playback_ts());

        return true;
    }

    /**
     * @return array
     */
    protected function do_get_action_map()
    {
        $close = User_Input_Handler_Registry::create_action($this, self::ACTION_CLOSE);
        $actions = array(
            GUI_EVENT_KEY_RETURN => $close,
            GUI_EVENT_KEY_INFO => $close,
            GUI_EVENT_KEY_STOP => $close,
            GUI_EVENT_KEY_TOP_MENU => $close,
        );

        $keys = array(GUI_EVENT_TIMER, GUI_EVENT_KEY_SELECT, GUI_EVENT_KEY_LEFT, GUI_EVENT_KEY_RIGHT,
            GUI_EVENT_KEY_UP, GUI_EVENT_KEY_DOWN, GUI_EVENT_KEY_P_PLUS, GUI_EVENT_KEY_P_MINUS,
            GUI_EVENT_KEY_ENTER, GUI_EVENT_KEY_PLAY);
        foreach ($keys as $key) {
            $actions[$key] = User_Input_Handler_Registry::create_action($this, $key);
        }

        return $actions;
    }

    /**
     * Rebuild the content of the panels
     *
     * @param string[] $ids
     * @param array|null $post_action
     * @return array
     */
    protected function change_panes($ids, $post_action = null)
    {
        $change_defs = array();
        foreach ($ids as $id) {
            switch ($id) {
                case self::PANE_HEADER:
                    $children = $this->get_header_defs();
                    break;
                case self::PANE_STREAM:
                    $children = $this->get_stream_defs();
                    break;
                case self::PANE_PROGRAM:
                    $children = $this->get_program_defs();
                    break;
                default:
                    $children = $this->get_help_defs();
            }
            $change_defs[] = GComps_Factory::get_change_def($id, null, null, null,
                false, $children, GCOMP_TRANSITION_NONE);
        }

        return Action_Factory::change_gcomps($change_defs, $post_action);
    }

    /**
     * @param int $offset pixels, scroll down when positive
     * @return array
     */
    protected function scroll_details($offset)
    {
        return Action_Factory::change_gcomps(array(
            GComps_Factory::get_change_def(self::PANE_DETAILS, null, null,
                GComps_Factory::get_view_position_def(0, $offset))
        ));
    }

    /**
     * @param int $archive_tm -1 for live
     * @return array
     */
    protected function get_play_action($archive_tm)
    {
        $media_url = MediaURL::decode();
        $media_url->{PARAM_CHANNEL_ID} = $this->channel_id;
        $media_url->{PARAM_GROUP_ID} = $this->is_favorite ? null : $this->group_id;
        $media_url->{PARAM_IS_FAVORITE} = $this->is_favorite;
        $media_url->{PARAM_ARCHIVE_TM} = $archive_tm;

        // run from this screen: after a close the playback OSD does not recognize plugin_tv_play and drops it
        return Action_Factory::tv_play($media_url);
    }

    /**
     * @return bool
     */
    protected function is_archive_playback()
    {
        $playback = $this->plugin->get_tv_playback();
        return !empty($playback) && $playback[PARAM_CHANNEL_ID] === $this->channel_id && $playback[PARAM_ARCHIVE_TM] > 0;
    }

    /**
     * Time of the moment being played: now for live, archive start + position for archive
     *
     * @return int
     */
    protected function get_playback_ts()
    {
        if (!$this->is_archive_playback()) {
            return time();
        }

        $playback = $this->plugin->get_tv_playback();
        $position = (int)safe_get_value(get_player_state_assoc(), PLAYBACK_POSITION, 0);
        if ($position <= 0) {
            $position = time() - $playback['start'];
        }

        return $playback[PARAM_ARCHIVE_TM] + $position;
    }

    /**
     * The shown program is the one being played (or there is no epg at all),
     * so the stream info of the player belongs to it
     *
     * @return bool
     */
    protected function is_played_program()
    {
        if ($this->program === null) {
            return true;
        }

        $playback_ts = $this->get_playback_ts();
        return $this->program[PluginTvEpgProgram::start_tm_sec] <= $playback_ts
            && $playback_ts < $this->program[PluginTvEpgProgram::end_tm_sec];
    }

    /**
     * @param array $program
     * @return bool
     */
    protected function can_replay($program)
    {
        if ($this->channel['archive'] <= 0) {
            return false;
        }

        $elapsed = time() - $program[PluginTvEpgProgram::start_tm_sec];
        $delay = (int)$this->plugin->get_setting(PARAM_ARCHIVE_DELAY_TIME, 60);
        return $elapsed > $delay && $elapsed <= $this->channel['archive'] * 86400;
    }

    /**
     * @param int $ts
     * @return array|null
     */
    protected function find_program($ts)
    {
        $day_start = strtotime(format_datetime('Y-m-d', $ts) . ' UTC');
        // a program crossing midnight can only be found in the epg of the previous day
        foreach (array($day_start, $day_start - 86400) as $day) {
            foreach ($this->load_day($day) as $program) {
                if ($ts >= $program[PluginTvEpgProgram::start_tm_sec] && $ts < $program[PluginTvEpgProgram::end_tm_sec]) {
                    return $program;
                }
            }
        }

        return null;
    }

    /**
     * @param int $day_start day start in local TZ
     * @return array
     */
    protected function load_day($day_start)
    {
        if (!isset($this->days[$day_start])) {
            $programs = $this->plugin->get_day_epg($this->channel_id, $day_start, $this->plugin->plugin_cookies);
            $ext_epg = $this->plugin->get_day_ext_epg();
            foreach ($programs as &$program) {
                $start = $program[PluginTvEpgProgram::start_tm_sec];
                $program['ext'] = isset($ext_epg[$start]) ? $ext_epg[$start] : array();
            }
            unset($program);
            $this->days[$day_start] = $programs;
        }

        return $this->days[$day_start];
    }

    ///////////////////////////////////////////////////////////////////////
    // panels

    /**
     * Channel number, picon, name, date and clock
     *
     * @return array
     */
    protected function get_header_defs()
    {
        $now = time();
        $height = self::HEADER_HEIGHT;
        $defs = array(
            GComps_Factory::get_rect_def(GComp_Geom::place_top_left(self::SCREEN_WIDTH, $height), null, self::COLOR_HEADER_BG),
            self::text(GComp_Geom::place_left_center(-1, -1, 20), str_pad($this->channel['number'], 3, '0', STR_PAD_LEFT),
                self::COLOR_NUMBER, self::HEADER_TEXT_SIZE),
        );

        if (!empty($this->channel['icon'])) {
            $defs[] = GComps_Factory::get_image_def(GComp_Geom::place_left_center(110, 70, 130), null,
                $this->channel['icon'], true);
        }

        $defs[] = self::text(GComp_Geom::place_left_center(self::SCREEN_WIDTH - 700, -1, 270), $this->channel['title'],
            self::COLOR_CHANNEL, self::HEADER_TEXT_SIZE);
        $defs[] = self::text(GComp_Geom::place_left_center(-1, -1, self::SCREEN_WIDTH - 400), format_datetime('d.m.Y', $now),
            self::COLOR_TEXT);
        $defs[] = self::text(GComp_Geom::place_left_center(-1, -1, self::SCREEN_WIDTH - 140), format_datetime('H:i', $now),
            self::COLOR_TEXT, self::HEADER_TEXT_SIZE);

        return $defs;
    }

    /**
     * Stream info of the player (video and audio type as icons, audio language, bitrate)
     * and the position of the playback within the played program
     *
     * @return array
     */
    protected function get_stream_defs()
    {
        // the player state is wrong for a program browsed by left/right
        if (!$this->is_played_program()) {
            return array();
        }

        $player_state = get_player_state_assoc();
        if (!is_array($player_state)) {
            $player_state = array();
        }

        // the info items follow each other, $x follows their width to start the playback bar after them
        $info = array();
        $x = 20;

        $rows = (int)safe_get_value($player_state, 'playback_video_height', 0);
        if ($rows > 0) {
            foreach (array(240, 360, 480, 540, 576, 720, 1080, 2160) as $def) {
                if ($rows <= $def || $def === 2160) {
                    $info[] = array(self::image("frame_def_$def.png", 55, 50), 55);
                    break;
                }
            }
        }

        $track = safe_get_value($player_state, 'audio_track');
        if ($track !== null) {
            $codec = trim(safe_get_value($player_state, "audio_track.$track.codec", ''));
            if ($codec !== '') {
                $logo = self::get_audio_codec_icon($codec);
                $info[] = ($logo === null)
                    ? array(self::text(null, strtoupper($codec), self::COLOR_SILVER), mb_strlen($codec, 'UTF-8') * self::STREAM_CHAR_WIDTH)
                    : array(self::image($logo, 88, 50), 88);
            }

            $lang = strtoupper(trim(safe_get_value($player_state, "audio_track.$track.lang", '')));
            if ($lang !== '') {
                $info[] = array(self::text(null, $lang, self::COLOR_TEXT), mb_strlen($lang, 'UTF-8') * self::STREAM_CHAR_WIDTH);
            }
        }

        if (safe_get_value($player_state, 'teletext_available')) {
            $info[] = array(self::image('teletext.png', 64, 50), 64);
        }

        $bitrate = (int)safe_get_value($player_state, 'playback_current_bitrate', 0);
        if ($bitrate > 0) {
            $text = sprintf('%.1f %s', $bitrate / 1000000, self::sys_tr('formatting_bitrate_megabits', 'Mbit/s'));
            $info[] = array(self::text(null, $text, self::COLOR_SILVER), mb_strlen($text, 'UTF-8') * self::STREAM_CHAR_WIDTH);
        }

        $defs = array();
        $now = time();
        $playback_ts = $this->get_playback_ts();

        // playback position, independent of the program browsed by left/right
        $played = $this->find_program($playback_ts);
        if ($played !== null) {
            foreach ($info as $item) {
                $x += $item[1] + 15;
            }
            $bar_x = min($x + 15, self::STREAM_BAR_END - self::STREAM_BAR_MIN_WIDTH);
            $bar_width = self::STREAM_BAR_END - $bar_x;
            $start = $played[PluginTvEpgProgram::start_tm_sec];
            $length = max(1, $played[PluginTvEpgProgram::end_tm_sec] - $start);
            $done = (int)round($bar_width * min(1, max(0, ($playback_ts - $start) / $length)));

            $defs[] = GComps_Factory::get_rect_def(GComp_Geom::place_left_center($bar_width, self::STREAM_BAR_HEIGHT, $bar_x),
                null, self::COLOR_BAR_BG);
            if ($done > 0) {
                $defs[] = GComps_Factory::get_rect_def(GComp_Geom::place_left_center($done, self::STREAM_BAR_HEIGHT, $bar_x),
                    null, self::COLOR_BAR);
            }
        }

        $defs[] = self::text(GComp_Geom::place_left_center(-1, -1, self::SCREEN_WIDTH - 280), format_datetime('H:i', $playback_ts),
            self::COLOR_TEXT);
        if ($this->is_archive_playback()) {
            $defs[] = self::text(GComp_Geom::place_left_center(-1, -1, self::SCREEN_WIDTH - 160),
                '(-' . gmdate('G:i', max(0, $now - $playback_ts)) . ')', self::COLOR_ARCHIVE);
        } else {
            $defs[] = self::text(GComp_Geom::place_left_center(-1, -1, self::SCREEN_WIDTH - 160), '(LIVE)', self::COLOR_TIME);
        }

        // placed last: each item is placed next to the previous one
        foreach ($info as $idx => $item) {
            $geom = ($idx === 0) ? GComp_Geom::place_left_center(-1, -1, 20) : GComp_Geom::place_next_right_align_center(-1, -1, 15);
            $item[0][GComponentDef::geom_def] = $geom;
            $defs[] = $item[0];
        }

        return $defs;
    }

    /**
     * Program time, title, image and details, or the technical info of the stream
     *
     * @return array
     */
    protected function get_program_defs()
    {
        if ($this->show_tech_info) {
            return array($this->get_details_pane(0, self::SCREEN_WIDTH, 0, self::PROGRAM_HEIGHT, $this->get_tech_rows(self::get_rows_width(self::SCREEN_WIDTH))));
        }

        if ($this->program === null) {
            return array(self::text(GComp_Geom::place_top_left(-1, -1, 0, self::BODY_TOP),
                self::sys_tr('osd_plugin_tv_no_program', 'Program is not available'), self::COLOR_TEXT));
        }

        $start = $this->program[PluginTvEpgProgram::start_tm_sec];
        $end = $this->program[PluginTvEpgProgram::end_tm_sec];
        $playback_ts = $this->get_playback_ts();
        $is_played = ($start <= $playback_ts && $playback_ts < $end);
        $is_live = !$this->is_archive_playback();

        if ($is_live && $is_played) {
            $date = 'LIVE';
        } else {
            $date = format_datetime('d.m.Y', $start);
            if ($date === format_datetime('d.m.Y', time())) {
                $date = self::sys_tr('osd_plugin_tv_today', 'Today');
            }
        }
        $time = sprintf('%s  %s-%s', $date, format_datetime('H:i', $start), format_datetime('H:i', $end));

        $defs = array();
        if ($this->can_replay($this->program) && !($is_live && $is_played)) {
            $defs[] = self::text(GComp_Geom::place_top_left(), 'R', self::COLOR_REPLAY, self::PROGRAM_TEXT_SIZE);
            $defs[] = self::text(GComp_Geom::place_next_right_align_top(-1, -1, 15), $time, self::COLOR_TIME, self::PROGRAM_TEXT_SIZE);
        } else {
            $defs[] = self::text(GComp_Geom::place_top_left(), $time, self::COLOR_TIME, self::PROGRAM_TEXT_SIZE);
        }

        $ext = $this->program['ext'];
        $title = trim($this->program[PluginTvEpgProgram::name]);
        $sub_title = self::ext_value($ext, PluginTvExtEpgProgram::sub_title);
        if ($sub_title !== '') {
            $title .= (preg_match('/[,.:;!?]$/', $title) ? ' ' : '. ') . $sub_title;
        }
        $defs[] = self::text(GComp_Geom::place_top_left(self::SCREEN_WIDTH, -1, 0, 60), $title,
            self::COLOR_TEXT, self::TITLE_TEXT_SIZE, 2);

        // the channel picon is used when program has no own image, it is not worth to show it again
        $image = '';
        if (!empty($ext[PluginTvEpgProgram::icon_url])) {
            $image = safe_get_value($ext, PluginTvExtEpgProgram::main_icon, $ext[PluginTvEpgProgram::icon_url]);
        } else if (!empty($ext[PluginTvExtEpgProgram::icons]) && is_array($ext[PluginTvExtEpgProgram::icons])) {
            $image = reset($ext[PluginTvExtEpgProgram::icons]);
        }

        $body = array();
        $x = 0;
        if (!empty($image)) {
            // keeps the aspect ratio of the image inside the box
            $body[] = GComps_Factory::get_image_def(
                GComp_Geom::place_top_left(self::IMAGE_WIDTH, self::IMAGE_HEIGHT), null, $image, true);
            $x = self::IMAGE_WIDTH + 40;
        }

        $width = self::SCREEN_WIDTH - $x;
        $body[] = $this->get_details_pane($x, $width, 0, self::BODY_HEIGHT,
            $this->get_detail_rows($ext, self::get_rows_width($width)));

        // right below the title, it takes one or two lines
        $defs[] = GComps_Factory::get_panel_def(null,
            GComp_Geom::place_below_left(self::SCREEN_WIDTH, self::BODY_HEIGHT, 0, self::BODY_GAP), null, $body);

        return $defs;
    }

    /**
     * Scroll pane with its scrollbar
     *
     * @param int $x
     * @param int $width
     * @param int $y
     * @param int $height
     * @param array $rows
     * @return array
     */
    protected function get_details_pane($x, $width, $y, $height, $rows)
    {
        $pane = GComps_Factory::get_vertical_spane_def(self::PANE_DETAILS,
            GComp_Geom::place_top_left(self::get_rows_width($width), $height), null, $rows);
        $scrollbar = GComps_Factory::get_scrollbar_def(GComp_Geom::place_next_right_same_height(-1, -1, 20),
            null, self::PANE_DETAILS);

        return GComps_Factory::get_panel_def(null, GComp_Geom::place_top_left($width, $height, $x, $y), null, array($pane, $scrollbar));
    }

    /**
     * Width of the rows of the scroll pane, the scrollbar takes the rest
     *
     * @param int $pane_width
     * @return int
     */
    protected static function get_rows_width($pane_width)
    {
        return $pane_width - 40;
    }

    /**
     * Genre, ratings, year, country and the people involved, each on own row,
     * labels and values in different colors like the extended epg of the firmware, then the description
     *
     * @param array $ext
     * @param int $width of the rows
     * @return array
     */
    protected function get_detail_rows($ext, $width)
    {
        $rows = array();

        $genre = self::ext_value($ext, PluginTvExtEpgProgram::main_category);
        if ($genre !== '') {
            self::add_row($rows, $width, array(self::text(GComp_Geom::center(), $genre, self::COLOR_GENRE, self::TEXT_SIZE, 0)));
        }

        // all ratings in one row: each rating is docked left of the rest, a gap separates it from the next icon
        $ratings = array(PluginTvExtEpgProgram::imdb_rating => array('imdb.png', 78), PluginTvExtEpgProgram::kp_rating => array('kp.png', 37));
        $items = array();
        foreach ($ratings as $key => $icon) {
            $value = self::ext_value($ext, $key);
            if ($value === '') continue;

            $image = GComps_Factory::get_image_def(GComp_Geom::center($icon[1], 37), null, get_image_path($icon[0]), true);
            $items[] = array(
                GComps_Factory::get_panel_def(null, GComp_Geom::left(), self::margin_right(10), array($image)),
                self::text(GComp_Geom::center(), $value, self::COLOR_VALUE),
            );
        }

        if (!empty($items)) {
            $children = array_pop($items);
            while (!empty($items)) {
                $children = array(
                    GComps_Factory::get_panel_def(null, GComp_Geom::left(), self::margin_right(40), array_pop($items)),
                    GComps_Factory::get_panel_def(null, GComp_Geom::center(), null, $children),
                );
            }
            self::add_row($rows, $width, $children);
        }

        $info = array(
            PluginTvExtEpgProgram::year => array('osd_epg_label_year' => 'Year:'),
            PluginTvExtEpgProgram::country => array('osd_epg_label_country' => 'Country:'),
        );
        foreach ($info + self::$people_labels as $key => $labels) {
            $value = self::ext_value($ext, $key);
            if ($value === '') continue;

            // the first label is for one item, the last for several
            $label_keys = array_keys($labels);
            $label_key = (strpos($value, ',') === false) ? reset($label_keys) : end($label_keys);
            self::add_labeled_row($rows, $width, self::sys_tr_label($label_key, $labels[$label_key]), $value);
        }

        $desc = trim($this->program[PluginTvEpgProgram::description]);
        if ($desc !== '' && $desc !== trim($this->program[PluginTvEpgProgram::name])) {
            self::add_row($rows, $width, array(self::text(GComp_Geom::center(), $desc, self::COLOR_TEXT, self::TEXT_SIZE, 0)), 30);
        }

        return $rows;
    }

    /**
     * Resolution, bitrate and audio tracks of the played stream
     *
     * @return array
     */
    protected function get_tech_rows($width)
    {
        $player_state = get_player_state_assoc();
        if (!is_array($player_state)) {
            $player_state = array();
        }

        $na = self::sys_tr_value('osd_bitrate_na', 'N/A');
        $rows = array();

        $video_width = (int)safe_get_value($player_state, 'playback_video_width', 0);
        $video_height = (int)safe_get_value($player_state, 'playback_video_height', 0);
        self::add_labeled_row($rows, $width, self::sys_tr('osd_file_resolution', 'Video resolution:'),
            ($video_width > 0 && $video_height > 0) ? "{$video_width}x$video_height" : $na);

        $bitrate = (int)safe_get_value($player_state, 'playback_current_bitrate', 0);
        self::add_labeled_row($rows, $width, self::sys_tr_label('osd_bitrate__1', 'Bitrate:'),
            $bitrate > 0 ? round($bitrate / 1000000, 2) . ' ' . self::sys_tr('formatting_bitrate_megabits', 'Mbit/s') : $na);

        // all tracks in one line: #1 AAC RU, #2 AAC EN
        $tracks = array();
        foreach (get_audio_tracks_description() as $track) {
            $tracks[] = '#' . (count($tracks) + 1) . ' '
                . strtoupper(trim(safe_get_value($track, 'codec', '') . ' ' . safe_get_value($track, 'lang', '')));
        }
        self::add_labeled_row($rows, $width, rtrim(self::sys_tr('osd_audio_tracks', 'Audio tracks'), ': ') . ':',
            empty($tracks) ? $na : implode(', ', $tracks));

        return $rows;
    }

    /**
     * Key hints
     *
     * @return array
     */
    protected function get_help_defs()
    {
        $items = array();

        if ($this->show_tech_info) {
            $items[] = array(array('select_btn.png'), rtrim(self::sys_tr('osd_epg_label_about_program', 'TV programme'), ': '));
        } else {
            if ($this->program !== null) {
                $items[] = array(array('arrow_left_btn.png', 'arrow_right_btn.png'),
                    self::sys_tr('navigator_action_backward', 'Backward') . ' / ' . self::sys_tr('navigator_action_forward', 'Forward'));
            }

            $is_played = $this->is_played_program();
            if ($is_played) {
                $items[] = array(array('select_btn.png'), self::sys_tr('plugin_default_tv_action_add_info', 'Tech. info'));
            }

            if ($this->program !== null) {
                if ($this->can_replay($this->program)) {
                    $items[] = array(array('enter_btn.png'),
                        self::sys_tr('play_from_the_beginning_button', 'Play from the beginning'), self::COLOR_ARCHIVE);
                }

                $is_future = $this->program[PluginTvEpgProgram::start_tm_sec] > time();
                if (!$is_future && ($this->is_archive_playback() || !$is_played)) {
                    $items[] = array(array('play_btn.png'),
                        rtrim(self::sys_tr('osd_epg_current_program', 'Current program'), ': '));
                }

                $items[] = array(array('arrow_up_btn.png', 'arrow_down_btn.png', 'page_minus_btn.png', 'page_plus_btn.png'),
                    self::sys_tr('controls_button_scroll', 'Scroll'));
            }
        }

        // each component is placed next to the previous one
        $defs = array();
        foreach ($items as $item) {
            foreach ($item[0] as $idx => $button) {
                $gap = empty($defs) ? 0 : ($idx === 0 ? 40 : 6);
                $defs[] = GComps_Factory::get_image_def(empty($defs)
                    ? GComp_Geom::place_left_center()
                    : GComp_Geom::place_next_right_align_center(-1, -1, $gap), null, get_image_path($button));
            }

            $color = isset($item[2]) ? $item[2] : self::COLOR_SILVER;
            $defs[] = self::text(GComp_Geom::place_next_right_align_center(-1, -1, 12), $item[1], $color, self::SMALL_TEXT_SIZE);
        }

        return $defs;
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * Adds a row below the previous one
     *
     * @param array $rows
     * @param int $width the row needs it: a text placed in its center is wrapped at the rest of it
     * @param array $children
     * @param int $gap
     * @return void
     */
    protected static function add_row(&$rows, $width, $children, $gap = self::ROW_GAP)
    {
        $geom = empty($rows) ? GComp_Geom::place_top_left($width) : GComp_Geom::place_below_left($width, -1, 0, $gap);
        $rows[] = GComps_Factory::get_panel_def(null, $geom, null, $children);
    }

    /**
     * 'Label: value' row, a long value is wrapped by the firmware under its first line
     *
     * @param array $rows
     * @param int $width
     * @param string $label
     * @param string $value
     * @return void
     */
    protected static function add_labeled_row(&$rows, $width, $label, $value)
    {
        self::add_row($rows, $width, array(
            GComps_Factory::get_panel_def(null, GComp_Geom::left(), self::margin_right(20),
                array(self::text(GComp_Geom::center(), $label, self::COLOR_TEXT))),
            self::text(GComp_Geom::center(), $value, self::COLOR_VALUE, self::TEXT_SIZE, 0),
        ));
    }

    /**
     * The firmware rejects the whole screen when a margins def lacks any of its four sides
     *
     * @param int $right
     * @return array GCompMarginsDef
     */
    protected static function margin_right($right)
    {
        return array(
            GCompMarginsDef::left => 0,
            GCompMarginsDef::top => 0,
            GCompMarginsDef::right => $right,
            GCompMarginsDef::bottom => 0,
        );
    }

    /**
     * @param array|null $geom
     * @param string $text
     * @param string $color RGBA
     * @param int $size
     * @param int $max_lines 0 - no limit
     * @return array
     */
    protected static function text($geom, $text, $color, $size = self::TEXT_SIZE, $max_lines = 1)
    {
        $def = GComps_Factory::label_v2($geom, null, $text, $max_lines, $color, $size, null, array('line_spacing' => 3));
        // as the labels of ext_epg: the line takes the height of the text instead of the full height of the font
        $def[GComponentDef::options] |= GCOMP_OPT_TTF_COMPACT_HEIGHT;
        return $def;
    }

    /**
     * @param string $path
     * @param int $width
     * @param int $height
     * @return array
     */
    protected static function image($path, $width, $height)
    {
        if (strpos($path, '/') === false) {
            $path = get_image_path($path);
        }

        return GComps_Factory::get_image_def(GComp_Geom::geom($width, $height), null, $path);
    }

    /**
     * String from the firmware translation of the system language, the EPG of the firmware uses the same words
     *
     * @param string $key
     * @param string $default used when the firmware has no such key
     * @return string
     */
    protected static function sys_tr($key, $default)
    {
        // the value is passed to vsprintf, a '__1' key needs its argument
        $value = (substr($key, -3) === '__1')
            ? TR::get_system_language_string_value($key, '%s')
            : TR::get_system_language_string_value($key);
        return (!is_string($value) || $value === '') ? $default : $value;
    }

    /**
     * Firmware 'Label: %s' string without the value, e.g. 'Bitrate:'
     *
     * @param string $key
     * @param string $default
     * @return string
     */
    protected static function sys_tr_label($key, $default)
    {
        return trim(str_replace('%s', '', self::sys_tr($key, $default)));
    }

    /**
     * Value part of a firmware 'Label: value' string, e.g. 'N/A' of 'Bitrate: N/A'
     *
     * @param string $key
     * @param string $default
     * @return string
     */
    protected static function sys_tr_value($key, $default)
    {
        $value = self::sys_tr($key, $default);
        $pos = strpos($value, ': ');
        return $pos === false ? $value : substr($value, $pos + 2);
    }

    /**
     * Icon of the audio codec reported by the player, null if there is no icon for it
     *
     * @param string $codec
     * @return string|null
     */
    protected static function get_audio_codec_icon($codec)
    {
        // icons of home_tv: the player reports 'AAC', 'Dolby Digital' and 'MPEG',
        // the other names cover the variants of these codecs
        $icons = array(
            'aac' => 'aac',
            'dolby' => 'dolby_digital', 'ac3' => 'dolby_digital', 'ac-3' => 'dolby_digital',
            'mpeg' => 'mpeg', 'mp2' => 'mpeg', 'mp3' => 'mpeg',
        );

        $codec = strtolower($codec);
        foreach ($icons as $needle => $icon) {
            if (strpos($codec, $needle) !== false) {
                return get_image_path("audio_codec_$icon.png");
            }
        }

        return null;
    }

    /**
     * @param array $ext
     * @param string $key
     * @return string
     */
    protected static function ext_value($ext, $key)
    {
        if (!isset($ext[$key])) {
            return '';
        }

        $value = $ext[$key];
        if (is_array($value)) {
            $value = implode(', ', $value);
        }

        return trim($value);
    }
}
