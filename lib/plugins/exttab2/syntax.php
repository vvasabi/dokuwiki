<?php
/**
 * exttab2-Plugin: Parses extended tables (like MediaWiki) 
 *
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     disorde chang <disorder.chang@gmail.com>
 * @date       2007-10-04
 */
 
// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();
 
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'syntax.php');
 
/**
 * All DokuWiki plugins to extend the parser/rendering mechanism
 * need to inherit from this class
 */
class syntax_plugin_exttab2 extends DokuWiki_Syntax_Plugin {
 
  var $isRowStart = false;
  var $isCellStart = false;
  var $isCellHeadStart = false;
 
  /**
   * return some info
   */
  function getInfo(){
    return array(
          'author' => 'Disorder Chang',
          'email'  => 'disorder.chang@gmail.com',
          'date'   => '2007-10-04',
          'name'   => 'exttab2 Plugin',
          'desc'   => 'parses MediaWiki-like tables',
          'url'    => 'http://wiki.splitbrain.org/users:disorder:exttab2',
    );
  }
 
  /**
  * What kind of syntax are we?
  */
  function getType(){
    return 'protected';
  }
 
  /**
   * What kind of plugin are we?
  */
  function getPType(){
    return 'block';
  }
 
  function getAllowedTypes() { 
    return array('formatting', 'substition', 'disabled', 'protected'); 
  }
 
  function getSort(){ 
    return 158; 
  }
 
  function connectTo($mode) { 
     $this->Lexer->addEntryPattern('\{\|[^\n]*',$mode,'plugin_exttab2'); 
  }
 
  function postConnect() { 
    $patternAttr = '&a-z0-9=\: "\'\(\)%#;-';
 
    $this->Lexer->addPattern('\|\|['.$patternAttr.']*\|(?=[^\|])','plugin_exttab2'); // compact cell with style
    $this->Lexer->addPattern('\|\|','plugin_exttab2'); // compact cell
    $this->Lexer->addPattern('!!','plugin_exttab2'); // compact head
    $this->Lexer->addPattern('\n\|\+[^\n]*','plugin_exttab2'); // caption
    $this->Lexer->addPattern('\n\|\-[^\n]*','plugin_exttab2'); // row
    $this->Lexer->addPattern('\n!['.$patternAttr.']*\|(?=[^\|])','plugin_exttab2'); // head with style
    $this->Lexer->addPattern('\n!','plugin_exttab2'); // head
    $this->Lexer->addPattern('\n\|['.$patternAttr.']*\|(?=[^\|])','plugin_exttab2'); // cell with style
    $this->Lexer->addPattern('\n\|(?=[^}])','plugin_exttab2'); // cell
    $this->Lexer->addExitPattern('\n\|\}','plugin_exttab2'); // close
  }
 
 
  /**
   * Handle the match
   */
  function handle($match, $state, $pos, &$handler){
    switch ($state) {
      case DOKU_LEXER_ENTER :
        $postart = 3;
        $arg = (strlen($match)<$postart)?"":substr($match, $postart);
        return array($state, "table_start", $arg);
        break;
      case DOKU_LEXER_UNMATCHED :  
        return array($state, "", $match);
        break;
      case DOKU_LEXER_MATCHED:
        $func = "unknow";
        $arg  = "";
        if(preg_match ( '/\n\|\+(.*)/', $match, $m)){ // caption
          $func = "table_caption";
          $arg  = $m[1];
        }
        else if($match == '!!'){ // compact head
          $func = "tableheadcell_open";
          $arg = "";
        }
        else if($match == '||'){ // compact cell
          $func = "tablecell_open";
          $arg = "";
        }
        else if(preg_match ( '/\|\|([^\|]*)\|/', $match, $m)){ // compact cell with style
          $func = "tablecell_open";
          $arg = $m[1];
        }
        else if(preg_match ( '/\n\|\-(.*)/', $match, $m)){ //row
          $func = "tablerow_open";
          $arg = $m[1];
        }
        else if(preg_match ( '/\n!([^\|\n]*)\|/', $match, $m)){ //head with style
          $func = "tableheadcell_open";
          $arg = $m[1];
        }
        else if(preg_match ( '/\n\|([^\|\n]*)\|/', $match, $m)){ //cell with style
          $func = "tablecell_open";
          $arg = $m[1];
        }
        else if(trim($match) == "|"){//cell
          $func = "tablecell_open";
        }
        else if(trim($match) == "!"){//head
          $func = "tableheadcell_open";
        }
        return array($state, $func, $arg);
        break;
      case DOKU_LEXER_EXIT : 
        return array($state, "table_close");
    }
    return array();
  }
 
  /**
   * Create output
   */
  function render($mode, &$renderer, $data) {
    if($mode == 'xhtml'){
      list($state, $func, $arg) = $data;
      switch ($state) {
        case DOKU_LEXER_ENTER :
          $renderer->doc .= $this->$func($arg); 
          break; 
        case DOKU_LEXER_UNMATCHED :  
          $renderer->doc .= $renderer->_xmlEntities($arg); 
          break;
        case DOKU_LEXER_MATCHED:
          $renderer->doc .= $this->$func($arg); 
          break;
        case DOKU_LEXER_EXIT :       
          $renderer->doc .= $this->$func($arg); 
          break;
      }
      return true;
    }
    return false;
  }
 
  function table_start($arg=NULL){
    $r = "<table".$this->_attrString($arg).">". DOKU_LF;
    return $r;
  }
 
  function table_close($arg=NULL){
    $r = "";
    $r .= $this->closeCell();
    $r .= $this->closeRow();
    $r .= "</table>". DOKU_LF;
    return $r;
  }
 
  function tableheadcell_open($arg=NULL){
    $r = "";
    $r .= $this->closeCell();
    if(!$this->isRowStart) {
      $r .= $this->tablerow_open();
    }
    $r .= DOKU_TAB . '<th'.$this->_attrString($arg).'>' ;
    $this->isCellHeadStart = true;
    return $r;
  }
 
  function tablecell_open($arg=NULL){
    $r = "";
    $r .= $this->closeCell();
    if(!$this->isRowStart) {
      $r .= $this->tablerow_open();
    }
    $r .= DOKU_TAB . '<td'.$this->_attrString($arg).'>' ;
    $this->isCellStart = true;
    return $r;
  }
 
  function tablerow_open($arg=NULL){
    $r = "";
    $r .= $this->closeCell();
    $r .= $this->closeRow();
    $r .= DOKU_TAB . '<tr'.$this->_attrString($arg).'>' . DOKU_LF ;
    $this->isRowStart = true;
    return $r;
  }
 
  function table_caption($arg=NULL){
    $caption = $this->_attrString($arg, "");
    $r = DOKU_TAB . "<caption>$caption</caption>". DOKU_LF;
    return $r;
  }
 
  function _attrString($attr="", $before=" "){
    if(is_null($attr) || trim($attr)=="") $attr = "";
    else $attr = $before.trim($attr);
    return $attr;
  }
 
  function closeRow(){
    $r = "";
    if($this->isRowStart){
      $r = DOKU_TAB . '</tr>' . DOKU_LF ;
      $this->isRowStart = false;
    }
    return $r;
  }
 
  function closeCell(){
    $r = "";
    if($this->isCellStart){
      $r = '</td>' . DOKU_LF ;
      $this->isCellStart = false;
    }
    else if($this->isCellHeadStart){
      $r = '</th>' . DOKU_LF ;
      $this->isCellHeadStart = false;
    }
    return $r;
  }
 
  //for debuging
 
  function unknow($arg=NULL){
    $r = "<del>$arg</del>\n";
    return $r;
  }    
}
 
?>