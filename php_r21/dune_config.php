<?php
///////////////////////////////////////////////////////////////////////////

const FEW_BUTTONS_REMOTE_TYPE_NONE = 0;
const FEW_BUTTONS_REMOTE_TYPE_OVAL = 1;
const FEW_BUTTONS_REMOTE_TYPE_SMALL = 2;
const FEW_BUTTONS_REMOTE_TYPE_ARROWS = 3;

function dune_config_get_few_buttons_remote_type()
{
    return DuneSystem::$properties['few_buttons_remote_type'];
}

function dune_config_with_color_buttons()
{
     $type = dune_config_get_few_buttons_remote_type();
     switch ($type)
     {
         case FEW_BUTTONS_REMOTE_TYPE_SMALL:
         case FEW_BUTTONS_REMOTE_TYPE_ARROWS:
             return false;
         default:
             return true;
     }
}

function dune_config_show_pop_up_icon_only()
{
     $type = dune_config_get_few_buttons_remote_type();
     switch ($type)
     {
         case FEW_BUTTONS_REMOTE_TYPE_OVAL:
         case FEW_BUTTONS_REMOTE_TYPE_SMALL:
         case FEW_BUTTONS_REMOTE_TYPE_ARROWS:
             return true;
         default:
             return false;
     }
}

///////////////////////////////////////////////////////////////////////////
?>
