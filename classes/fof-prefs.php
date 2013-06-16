<?php
/*
 * This file is part of FEED ON FEEDS - http://feedonfeeds.com/
 *
 * fof-prefs.php - Preferences class
 *
 *
 * Copyright (C) 2004-2007 Stephen Minutillo
 * steve@minutillo.com - http://minutillo.com/steve/
 *
 * Distributed under the GPL - see LICENSE
 *
 */

class FoF_Prefs
{
    var $user_id;
    var $prefs;
    var $admin_prefs;

    function FoF_Prefs($user_id)
    {
        $this->user_id = $user_id;

        $prefs = fof_db_prefs_get($user_id);
        if ( ! is_array($prefs))
            $prefs = array();
        $this->prefs = $prefs;

        if ($user_id != 1)
        {
            $admin_prefs = fof_db_prefs_get(1);
            if ( ! is_array($admin_prefs))
                $admin_prefs = array();
            $this->admin_prefs = $admin_prefs;
        }
        else
        {
            $this->admin_prefs = $prefs;
        }

        $this->populate_defaults();

        if($user_id == 1)
        {
            $this->prefs = array_merge($this->prefs, $this->admin_prefs);
        }
    }

    public static function &instance()
    {
        static $instance;
        if(!isset($instance)) $instance = new FoF_Prefs(fof_current_user());

        return $instance;
    }

    function populate_defaults()
    {
        $defaults = array(
            "favicons" => true,
            "keyboard" => false,
            "direction" => "desc",
            "howmany" => 50,
            "sharing" => "no",
            "feed_order" => "feed_title",
            "feed_direction" => "asc",
        );

        $admin_defaults = array(
            "purge" => 30,
            "autotimeout" => 30,
            "manualtimeout" => 15,
            "logging" => false,
            "match_similarity" => "",
            "dynupdates" => false,
        );

        $this->stuff_array($this->prefs, $defaults);
        $this->stuff_array($this->admin_prefs, $admin_defaults);
    }

    function stuff_array(&$array, $defaults)
    {
        foreach($defaults as $k => $v)
        {
            if(!isset($array[$k])) $array[$k] = $v;
        }
    }

    function get($k)
    {
        if (isset($this->prefs[$k]))
            return $this->prefs[$k];
        return null;
    }

    function set($k, $v)
    {
        $this->prefs[$k] = $v;
    }

    function save()
    {
        fof_db_save_prefs($this->user_id, $this->prefs);
    }
}

?>
