<?php

###############################################################################
#
# OSD Components Factory
#
# Author: Brigadir (forum.mydune.ru)
# Date: 04-11-2018
# Latest update: 30-01-2021
#
###############################################################################

require_once 'lib/dune_stb_api.php';
require_once 'lib/action_factory.php';

///////////////////////////////////////////////////////////////////////////////

class OSD_Component_Factory
{
    const    DUNE_BASE_SKIN_PATH = '/firmware/skin';

    ///////////////////////////////////////////////////////////////////////////

    private static $instance;

    ///////////////////////////////////////////////////////////////////////////

    private $dots;
    private $skin_path;

    # Paths to specific dir
    protected $osd_glass_path;
    protected $osd_glass_center;
    protected $weather_glass_path;
    protected $weather_glass_center;

    # Arrays of cut image manifest
    protected $osd_glass_manifest;
    protected $weather_glass_manifest;

    ///////////////////////////////////////////////////////////////////////////

    /**
     * @throws Exception
     */
    public static function init()
    {
        if (is_null(self::$instance))
            self::$instance = new self();

        clearstatcache();

        self::$instance->skin_path = get_active_skin_path();

        $dots_path = get_paved_path(get_temp_path() . '/osd_components/dots/');

        self::$instance->osd_glass_path = (file_exists(self::$instance->skin_path . '/cut_images/osd_glass/osd_glass.txt') ? self::$instance->skin_path : self::DUNE_BASE_SKIN_PATH) . '/cut_images/osd_glass';
        self::$instance->osd_glass_manifest = parse_ini_file(self::$instance->osd_glass_path . '/osd_glass.txt');
        $osd_glass_center_icon = 'osd_glass_center' . self::$instance->osd_glass_manifest['ext'];

        if (isset(self::$instance->osd_glass_manifest['has_center_icon']) && self::$instance->osd_glass_manifest['has_center_icon'] && file_exists(self::$instance->osd_glass_path . "/$osd_glass_center_icon")) {
			self::$instance->osd_glass_center = self::$instance->osd_glass_path . "/$osd_glass_center_icon";
		} else if (isset(self::$instance->osd_glass_manifest['center_color'])) {
            $center_color = str_replace('#', '0x', strtolower(trim(self::$instance->osd_glass_manifest['center_color'])));
            self::$instance->osd_glass_center = $dots_path . "/$center_color.aai";

            if (!file_exists(self::$instance->osd_glass_center)) {
                $argb = str_split($center_color, 2);

                if (false === file_put_contents(self::$instance->osd_glass_center, pack("V2C4", 1, 1, hexdec($argb[4]), hexdec($argb[3]), hexdec($argb[2]), hexdec($argb[1]))))
                    throw new Exception(get_class(self::$instance) . ': Attempt to write to the system drive failed!');
            }
        }

        self::$instance->weather_glass_path = (file_exists(self::$instance->skin_path . '/cut_images/weather_glass/weather_glass.txt') ? self::$instance->skin_path : self::DUNE_BASE_SKIN_PATH) . '/cut_images/weather_glass';
        self::$instance->weather_glass_manifest = parse_ini_file(self::$instance->weather_glass_path . '/weather_glass.txt');
        $weather_glass_center_icon = 'weather_glass_center' . self::$instance->weather_glass_manifest['ext'];

        if (isset(self::$instance->weather_glass_manifest['has_center_icon']) && self::$instance->weather_glass_manifest['has_center_icon'] && file_exists(self::$instance->weather_glass_path . "/$weather_glass_center_icon")) {
			self::$instance->weather_glass_center = self::$instance->osd_glass_path . "/$weather_glass_center_icon";
		}
        else if (isset(self::$instance->weather_glass_manifest['center_color'])) {
            $center_color = str_replace('#', '0x', strtolower(trim(self::$instance->weather_glass_manifest['center_color'])));
            self::$instance->weather_glass_center = $dots_path . "/$center_color.aai";

            if (!file_exists(self::$instance->weather_glass_center)) {
                $argb = str_split($center_color, 2);

                if (false === file_put_contents(self::$instance->weather_glass_center, pack("V2C4", 1, 1, hexdec($argb[4]), hexdec($argb[3]), hexdec($argb[2]), hexdec($argb[1])))) {
					throw new Exception(get_class(self::$instance) . ': Attempt to write to the system drive failed!');
				}
            }
        }

    }

    /**
     * Предзагрузка OSD компонент (кэширование картинок)
     * @param $post_action
     * @return array
     * @throws Exception
     */
    public static function get_caching_osd_images_action($post_action)
    {
        if (is_null(self::$instance))
            self::init();

        $path = rtrim(self::$instance->osd_glass_path, '/');
        $ext = self::$instance->osd_glass_manifest['ext'];
        Action_Factory::add_osd_image($comps, "$path/osd_glass_top_left.$ext", 0, 0, 1, 1);
        Action_Factory::add_osd_image($comps, "$path/osd_glass_top_right.$ext", 0, 0, 1, 1);
        Action_Factory::add_osd_image($comps, "$path/osd_glass_top.$ext", 0, 0, 1, 1);
        Action_Factory::add_osd_image($comps, "$path/osd_glass_bottom.$ext", 0, 0, 1, 1);
        Action_Factory::add_osd_image($comps, "$path/osd_glass_left.$ext", 0, 0, 1, 1);
        Action_Factory::add_osd_image($comps, "$path/osd_glass_right.$ext", 0, 0, 1, 1);
        Action_Factory::add_osd_image($comps, "$path/osd_glass_bottom_left.$ext", 0, 0, 1, 1);
        Action_Factory::add_osd_image($comps, "$path/osd_glass_bottom_right.$ext", 0, 0, 1, 1);
        Action_Factory::add_osd_image($comps, self::$instance->osd_glass_center, 0, 0, 1, 1);

        if (!empty(self::$instance->weather_glass_path)) {
            $path = rtrim(self::$instance->weather_glass_path, '/');
            $ext = self::$instance->weather_glass_manifest['ext'];
            Action_Factory::add_osd_image($comps, "$path/weather_glass_top_left.$ext", 0, 0, 1, 1);
            Action_Factory::add_osd_image($comps, "$path/weather_glass_top_right.$ext", 0, 0, 1, 1);
            Action_Factory::add_osd_image($comps, "$path/weather_glass_top.$ext", 0, 0, 1, 1);
            Action_Factory::add_osd_image($comps, "$path/weather_glass_bottom.$ext", 0, 0, 1, 1);
            Action_Factory::add_osd_image($comps, "$path/weather_glass_left.$ext", 0, 0, 1, 1);
            Action_Factory::add_osd_image($comps, "$path/weather_glass_right.$ext", 0, 0, 1, 1);
            Action_Factory::add_osd_image($comps, "$path/weather_glass_bottom_left.$ext", 0, 0, 1, 1);
            Action_Factory::add_osd_image($comps, "$path/weather_glass_bottom_right.$ext", 0, 0, 1, 1);
            Action_Factory::add_osd_image($comps, self::$instance->weather_glass_center, 0, 0, 1, 1);
        }

        foreach (func_get_args() as $idx => $value) {
            if (empty($idx)) continue;

            Action_Factory::add_osd_image($comps, $value, 0, 0, 1, 1);
        }

        return Action_Factory::update_osd($comps, $post_action);
    }

    /**
     * @param $comps
     * @param $dx
     * @param $dy
     * @param $width
     * @param $height
     * @throws Exception
     */
    public static function add_widget_box(&$comps, $dx, $dy, $width, $height)
    {
        if (is_null(self::$instance))
            self::init();

        if (empty(self::$instance->weather_glass_path)) {
			self::$instance->add_osd_box($comps, $dx + 2, $dy - 31, $width - 5, $height + 31);
		} else {
			$dx -= self::$instance->weather_glass_manifest['left_extent'];
            $dy -= self::$instance->weather_glass_manifest['top_extent'];
            $width += self::$instance->weather_glass_manifest['right_extent'] + self::$instance->weather_glass_manifest['left_extent'];
            $width = max($width, self::$instance->weather_glass_manifest['left'] + self::$instance->weather_glass_manifest['right']);
            $height += self::$instance->weather_glass_manifest['bottom_extent'] + self::$instance->weather_glass_manifest['top_extent'];
            $height = max($height, self::$instance->weather_glass_manifest['top'] + self::$instance->weather_glass_manifest['bottom']);
            $path = self::$instance->weather_glass_path;
            $ext = self::$instance->weather_glass_manifest['ext'];
            Action_Factory::add_osd_image($comps, "$path/weather_glass_top_left.$ext", $dx, $dy);
            Action_Factory::add_osd_image($comps, "$path/weather_glass_top_right.$ext", $dx + $width - self::$instance->weather_glass_manifest['right'], $dy);

            if ($width <= (self::$instance->weather_glass_manifest['left'] + self::$instance->weather_glass_manifest['right'])) {
				$need_fill_flag = false;
			} else {
                Action_Factory::add_osd_image($comps,
                    "$path/weather_glass_top.$ext",
                    $dx + self::$instance->weather_glass_manifest['left'],
                    $dy,
                    $width - self::$instance->weather_glass_manifest['left'] - self::$instance->weather_glass_manifest['right']
                );

                Action_Factory::add_osd_image($comps,
                    "$path/weather_glass_bottom.$ext",
                    $dx + self::$instance->weather_glass_manifest['left'],
                    $dy + $height - self::$instance->weather_glass_manifest['bottom'],
                    $width - self::$instance->weather_glass_manifest['left'] - self::$instance->weather_glass_manifest['right']
                );
                $need_fill_flag = true;
			}

            if ($height > (self::$instance->weather_glass_manifest['top'] + self::$instance->weather_glass_manifest['bottom'])) {
                Action_Factory::add_osd_image($comps,
                    "$path/weather_glass_left.$ext",
                    $dx,
                    $dy + self::$instance->weather_glass_manifest['top'],
                    0,
                    $height - self::$instance->weather_glass_manifest['top'] - self::$instance->weather_glass_manifest['bottom']
                );

                Action_Factory::add_osd_image($comps,
                    "$path/weather_glass_right.$ext",
                    $dx + $width - self::$instance->weather_glass_manifest['right'],
                    $dy + self::$instance->weather_glass_manifest['top'],
                    0,
                    $height - self::$instance->weather_glass_manifest['top'] - self::$instance->weather_glass_manifest['bottom']
                );

                if ($need_fill_flag) {
                    Action_Factory::add_osd_image($comps,
                        self::$instance->weather_glass_center,
                        $dx + self::$instance->weather_glass_manifest['left'],
                        $dy + self::$instance->weather_glass_manifest['top'],
                        $width - self::$instance->weather_glass_manifest['right'] - self::$instance->weather_glass_manifest['left'],
                        $height - self::$instance->weather_glass_manifest['top'] - self::$instance->weather_glass_manifest['bottom']
                    );
                }
            }

            Action_Factory::add_osd_image($comps,
                "$path/weather_glass_bottom_left.$ext",
                $dx,
                $dy + $height - self::$instance->weather_glass_manifest['bottom']
            );

            Action_Factory::add_osd_image($comps,
                "$path/weather_glass_bottom_right.$ext",
                $dx + $width - self::$instance->weather_glass_manifest['right'],
                $dy + $height - self::$instance->weather_glass_manifest['bottom']
            );
        }
    }

    /**
     * @param $comps
     * @param $dx
     * @param $dy
     * @param $width
     * @param $height
     * @return void
     * @throws Exception
     */
    public static function add_content_box(&$comps, $dx, $dy, $width, $height)
    {
        if (is_null(self::$instance))
            self::init();

        $dx -= self::$instance->osd_glass_manifest['left_extent'];
        $dy -= self::$instance->osd_glass_manifest['top_extent'];
        $width += self::$instance->osd_glass_manifest['right_extent'] + self::$instance->osd_glass_manifest['left_extent'];
        $width = max($width, self::$instance->osd_glass_manifest['left'] + self::$instance->osd_glass_manifest['right']);
        $height += self::$instance->osd_glass_manifest['bottom_extent'] + self::$instance->osd_glass_manifest['top_extent'];
        $height = max($height, self::$instance->osd_glass_manifest['top'] + self::$instance->osd_glass_manifest['bottom']);
        $path = self::$instance->osd_glass_path;
        $ext = self::$instance->osd_glass_manifest['ext'];
        Action_Factory::add_osd_image($comps, "$path/osd_glass_top_left.$ext", $dx, $dy);
        Action_Factory::add_osd_image($comps, "$path/osd_glass_top_right.$ext", $dx + $width - self::$instance->osd_glass_manifest['right'], $dy);

        if ($width <= (self::$instance->osd_glass_manifest['left'] + self::$instance->osd_glass_manifest['right'])) {
			$need_fill_flag = false;
		} else {
            Action_Factory::add_osd_image($comps,
                "$path/osd_glass_top.$ext",
                $dx + self::$instance->osd_glass_manifest['left'],
                $dy,
                $width - self::$instance->osd_glass_manifest['left'] - self::$instance->osd_glass_manifest['right']
            );

            Action_Factory::add_osd_image($comps,
                "$path/osd_glass_bottom.$ext",
                $dx + self::$instance->osd_glass_manifest['left'],
                $dy + $height - self::$instance->osd_glass_manifest['bottom'],
                $width - self::$instance->osd_glass_manifest['left'] - self::$instance->osd_glass_manifest['right']
            );
            $need_fill_flag = true;
        }

        if ($height > (self::$instance->osd_glass_manifest['top'] + self::$instance->osd_glass_manifest['bottom'])) {
            Action_Factory::add_osd_image($comps,
				"$path/osd_glass_left.$ext",
				$dx,
				$dy + self::$instance->osd_glass_manifest['top'],
				0,
				$height - self::$instance->osd_glass_manifest['top'] - self::$instance->osd_glass_manifest['bottom']
			);

            Action_Factory::add_osd_image($comps,
				"$path/osd_glass_right.$ext",
				$dx + $width - self::$instance->osd_glass_manifest['right'],
				$dy + self::$instance->osd_glass_manifest['top'],
				0,
				$height - self::$instance->osd_glass_manifest['top'] - self::$instance->osd_glass_manifest['bottom']
			);

            if ($need_fill_flag) {
				Action_Factory::add_osd_image($comps,
					self::$instance->osd_glass_center,
					$dx + self::$instance->osd_glass_manifest['left'],
					$dy + self::$instance->osd_glass_manifest['top'],
					$width - self::$instance->osd_glass_manifest['right'] - self::$instance->osd_glass_manifest['left'],
					$height - self::$instance->osd_glass_manifest['top'] - self::$instance->osd_glass_manifest['bottom']
				);
			}
        }

        Action_Factory::add_osd_image($comps,
			"$path/osd_glass_bottom_left.$ext",
			$dx,
			$dy + $height - self::$instance->osd_glass_manifest['bottom']
			);

        Action_Factory::add_osd_image($comps,
			"$path/osd_glass_bottom_right.$ext",
			$dx + $width - self::$instance->osd_glass_manifest['right'],
			$dy + $height - self::$instance->osd_glass_manifest['bottom']
			);
    }
}

