<?php
/**
 * Plugin Outdent: Removes one level of indenting.
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Christopher Smith <chris@jalakai.co.uk>
 */
 
if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../').'/');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');
 
/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class syntax_plugin_outdent extends DokuWiki_Syntax_Plugin {
 
    function getInfo(){
      return array(
        'author' => 'Christopher Smith',
        'email'  => 'chris@jalakai.co.uk',
        'date'   => '2008-08-13',
        'name'   => 'Outdent Plugin',
        'desc'   => 'Remove one level of indenting
                     Syntax: ==',
        'url'    => 'http://wiki.splitbrain.org/plugin:outdent',
      );
    }
 
    function getType() { return 'baseonly'; }
    function getSort() { return 50; }                       /* same as header */
 
    function connectTo($mode) {
        $this->Lexer->addSpecialPattern('\n[ \t]*==[ \t]*(?=\n)',$mode,'plugin_outdent');
    }
 
    function handle($match, $state, $pos, &$handler){
      $level=0;
      if ($state == DOKU_LEXER_SPECIAL) {
        $level = $this->_getLevel($handler->calls);
        if ($level > 1) {
            $handler->_addCall('section_close', array(), $pos);
            $handler->_addCall('section_open', array($level-1), $pos);
        }
      }
 
      return false;
    }
 
    function render($mode, &$renderer, $data) {
 
      return false;
    }
 
    function _getLevel(&$calls) {
 
      for ($i=count($calls); $i >= 0; $i--) {
          if ($calls[$i][0] == 'header') return $calls[$i][1][1];
        if ($calls[$i][0] == 'section_open') return $calls[$i][1][0];
      }
 
      return 0;
    }
}
