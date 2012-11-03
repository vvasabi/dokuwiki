<?php
/**
 * Highlight Plugin: Allows user-defined colored highlighting
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Joseph Nahmias <joe@nahmias.net>
 * @link       http://wiki.splitbrain.org/plugin:highlight
 * @version    3.1b
 */
 
if(!defined('DOKU_INC')) define('DOKU_INC',realpath(dirname(__FILE__).'/../../').'/');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');
 
/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class syntax_plugin_highlight extends DokuWiki_Syntax_Plugin {
 
    function getInfo(){  // return some info
        return array(
            'author' => 'Joseph Nahmias',
            'email'  => 'joe@nahmias.net',
            'date'   => '2007-09-11',
            'name'   => 'Color Highlight Plugin',
            'desc'   => 'Highlight text with a specific color
                         Syntax: <hi color>highlighted content</hi>',
            'url'    => 'http://wiki.splitbrain.org/plugin:highlight',
        );
    }
 
     // What kind of syntax are we?
    function getType(){ return 'formatting'; }
	
    // What kind of syntax do we allow (optional)
    function getAllowedTypes() {
        return array('formatting', 'substition', 'disabled');
    }
   
   // What about paragraphs? (optional)
   function getPType(){ return 'normal'; }
 
    // Where to sort in?
    function getSort(){ return 90; }
 
 
    // Connect pattern to lexer
    function connectTo($mode) {
      $this->Lexer->addEntryPattern('(?i)<hi(?: .+?)?>(?=.+</hi>)',$mode,'plugin_highlight');
    }
    function postConnect() {
      $this->Lexer->addExitPattern('(?i)</hi>','plugin_highlight');
    }
 
 
    // Handle the match
    function handle($match, $state, $pos, &$handler){
        switch ($state) {
          case DOKU_LEXER_ENTER : 
            preg_match("/(?i)<hi (.+?)>/", $match, $color); // get the color
            if ( $this->_isValid($color[1]) ) return array($state, $color[1]);
            break;
          case DOKU_LEXER_MATCHED :
            break;
          case DOKU_LEXER_UNMATCHED :
            return array($state, $match);
            break;
          case DOKU_LEXER_EXIT :
            break;
          case DOKU_LEXER_SPECIAL :
            break;
        }
        return array($state, "#ff0");
    }
 
    // Create output
    function render($mode, &$renderer, $data) {
        if($mode == 'xhtml'){
          list($state, $color) = $data;
          switch ($state) {
            case DOKU_LEXER_ENTER : 
              $renderer->doc .= "<span style=\"background-color: $color\">";
              break;
            case DOKU_LEXER_MATCHED :
              break;
            case DOKU_LEXER_UNMATCHED :
              $renderer->doc .= $renderer->_xmlEntities($color);
              break;
            case DOKU_LEXER_EXIT :
              $renderer->doc .= "</span>";
              break;
            case DOKU_LEXER_SPECIAL :
              break;
          }
          return true;
        }
        return false;
    }

    // validate color value $c
    // this is cut price validation - only to ensure the basic format is
    // correct and there is nothing harmful
    // three basic formats  "colorname", "#fff[fff]", "rgb(255[%],255[%],255[%])"
    function _isValid($c) {
        $c = trim($c);
        
        $pattern = "/
            ([a-zA-z]+)|                                #colorname - not verified
            (\#([0-9a-fA-F]{3}|[0-9a-fA-F]{6}))|        #colorvalue
            (rgb\(([0-9]{1,3}%?,){2}[0-9]{1,3}%?\))     #rgb triplet
            /x";
        
        if (preg_match($pattern, $c)) return true;
        
        return false;
    }
}
 
//Setup VIM: ex: et ts=4 sw=4 enc=utf-8 :
