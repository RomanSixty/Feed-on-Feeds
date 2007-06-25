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
        global $FOF_USER_TABLE;
        
		$this->user_id = $user_id;

        $result = fof_safe_query("select user_prefs from $FOF_USER_TABLE where user_id = %d", $user_id);
        $row = mysql_fetch_array($result);
        $prefs = unserialize($row['user_prefs']);
        if(!is_array($prefs)) $prefs = array();
        $this->prefs = $prefs;
        
        if($user_id != 1)
        {
            $result = fof_safe_query("select user_prefs from $FOF_USER_TABLE where user_id = 1");
            $row = mysql_fetch_array($result);
            $admin_prefs = unserialize($row['user_prefs']);
            if(!is_array($admin_prefs)) $admin_prefs = array();
            $this->admin_prefs = $admin_prefs;
        }
        else
        {
            $this->admin_prefs = $prefs;
        }
        
        $this->populate_defaults();
    }
    
    function &instance()
    {
        static $instance;
        if(!isset($instance)) $instance =& new FoF_Prefs(fof_current_user());
        
        return $instance;
    }
    
    function populate_defaults()
    {
        $defaults = array(
            "favicons" => true,
            "keyboard" => false,
            "direction" => "desc",
            "howmany" => 50,
            );
        
        $admin_defaults = array(
            "purge" => 30,
            "autotimeout" => 30,
            "manualtimeout" => 15,
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
        return $this->prefs[$k];
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
