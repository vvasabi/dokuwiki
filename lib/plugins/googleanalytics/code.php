<?php
/**
 * Google Analytics for DokuWiki
 * 
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Terence J. Grant<tjgrant@tatewake.com>
 */

$ga_loaded = 0;

function ga_load()
{
	global $ga_loaded, $ga_settings;

	$ga_file = dirname(__FILE__).'/local_pref.php';

	if ($ga_loaded == 0)
	{
		if(file_exists($ga_file))
		{
			include($ga_file);

			$ga_loaded = 1;
		}
	}
}

function ga_write($fp, $name, $val)
{
	fwrite($fp, '$ga_settings[\''.$name.'\'] = \''.$val.'\';'."\n");
}

function ga_save()
{
	global $ga_loaded, $ga_settings;

	$ga_file = dirname(__FILE__).'/local_pref.php';

	if (is_writable($ga_file) || is_writable(dirname(__FILE__)))
	{
		$fp = fopen($ga_file, "w");
		fwrite($fp, '<?php'."\n// This file is automatically generated\n");
		ga_write($fp, 'code', $ga_settings['code']);
		ga_write($fp, 'dontcountadmin', $ga_settings['dontcountadmin']);
		ga_write($fp, 'dontcountusers', $ga_settings['dontcountusers']);
		fclose($fp);

		ptln('<div class="success">'.'Google analytics pref saved successfully.'.'</div>');
	}
	else
	{
		ptln('<div class="error">'.'Google analytics pref is not writable by the server.'.'</div>');
	}
}

//HTTPS support by Matjaz Slak (matjaz.slak@atol.si) - 02/23/2007
function ga_google_analytics_code()
{
        global $ga_loaded, $ga_settings, $conf;

        if ($ga_settings['code'])
        {
                if ($ga_settings['dontcountadmin'] && $_SERVER['REMOTE_USER'] == $conf['superuser']) return;
                if ($ga_settings['dontcountusers'] && $_SERVER['REMOTE_USER']) return;
               
                if ( $_SERVER['HTTPS'] == "on" ) {
                        ptln('<script src="https://ssl.google-analytics.com/urchin.js" type="text/javascript">');
                        ptln('</script>');
                        ptln('<script type="text/javascript">');
                        ptln('_uacct = "'.$ga_settings['code'].'";');
                        ptln('urchinTracker();');
                        ptln('</script>');                
                } else {
                        ptln('<script src="http://www.google-analytics.com/urchin.js" type="text/javascript">');
                        ptln('</script>');
                        ptln('<script type="text/javascript">');
                        ptln('_uacct = "'.$ga_settings['code'].'";');
                        ptln('urchinTracker();');
                        ptln('</script>');
                }
        }
}

//Load settings
ga_load();