<?php
/**
 * Google Analytics for DokuWiki
 * 
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Terence J. Grant<tjgrant@tatewake.com>
 */
 
if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../').'/');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'admin.php');

//--- Exported code
include_once(DOKU_PLUGIN.'googleanalytics/code.php');
//--- Exported code

/**
 * All DokuWiki plugins to extend the admin function
 * need to inherit from this class
 */
class admin_plugin_googleanalytics extends DokuWiki_Admin_Plugin
{
var $state = 0;
var $googleanalytics = '';

	/**
	 * Constructor
	 */
	function admin_plugin_googleanalytics()
	{
		$this->setupLocale();
	}

	/**
	 * return some info
	 */
	function getInfo()
	{
		return array(
			'author' => 'Terence J. Grant',
			'email'  => 'tjgrant@tatewake.com',
			'date'   => '2007-02-23',
			'name'   => 'Google Analytics Plugin',
			'desc'   => 'Plugin to embed your google analytics code for your site.',
			'url'    => 'http://tatewake.com/wiki/projects:google_analytics_for_dokuwiki',
		);
	}

	/**
	 * return sort order for position in admin menu
	 */
	function getMenuSort()
	{
		return 999;
	}
	
	/**
	 *  return a menu prompt for the admin menu
	 *  NOT REQUIRED - its better to place $lang['menu'] string in localised string file
	 *  only use this function when you need to vary the string returned
	 */
	function getMenuText()
	{
		return 'Google Analytics';
	}

	/**
	 * handle user request
	 */
	function handle()
	{
		$this->state = 0;
	
		if (!isset($_REQUEST['cmd'])) return;   // first time - nothing to do

		if (!is_array($_REQUEST['cmd'])) return;

		$this->googleanalytics = $_REQUEST['googleanalytics'];

		if (is_array($this->googleanalytics))
		{
			$this->state = 1;
		}
	}

	/**
	 * output appropriate html
	 */
	function html()
	{
		global $conf;
		global $ga_loaded, $ga_settings;

		if ($this->state != 0)	//If we are to save now...
		{
			$ga_settings['code'] = $this->googleanalytics['code'];
			$ga_settings['dontcountadmin'] = $this->googleanalytics['dontcountadmin'] == 'on' ? 'checked' : '';
			$ga_settings['dontcountusers'] = $this->googleanalytics['dontcountusers'] == 'on' ? 'checked' : '';

			ga_save();
		}

		print $this->plugin_locale_xhtml('intro');

		ptln("<form action=\"".wl($ID)."\" method=\"post\">");
		ptln('  <input type="hidden" name="do"   value="admin" />');
		ptln('  <input type="hidden" name="page" value="'.$this->getPluginName().'" />');
		ptln('  <input type="hidden" name="cmd[googleanalytics]" value="true" />');
		print '<center><table class="inline">';
		print '	<tr><th> '.$this->getLang('ga_item_type').' </th><th> '.$this->getLang('ga_item_option').' </th></tr>';
		print '	<tr><td> '.$this->getLang('ga_googleanalytics_code').' </td><td><input type="text" name="googleanalytics[code]" value="'.$ga_settings['code'].'"/></td></tr>';
		print '	<tr><td> '.$this->getLang('ga_dont_count_admin').' </td><td><input type="checkbox" name="googleanalytics[dontcountadmin]" '.$ga_settings['dontcountadmin'].'/></td></tr>';
		print '	<tr><td> '.$this->getLang('ga_dont_count_users').' </td><td><input type="checkbox" name="googleanalytics[dontcountusers]" '.$ga_settings['dontcountusers'].'/></td></tr>';
		print '</table>';
		print '<br />';
		print '<p><input type="submit" value="'.$this->getLang('ga_save').'"></p></center>';
		print '</form>';
	}
}

