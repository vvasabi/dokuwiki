/* javascript function to create highlight toolbar in dokuwiki */
/* see http://wiki.splitbrain.org/plugin:highlight for more info */

var plugin_highlight_colors = {

  "Yellow":      "#ffff00",
  "Red":         "#ff0000",
  "Orange":      "#ffa500",
  "Salmon":      "#fa8072",
  "Pink":        "#ffc0cb",
  "Plum":        "#dda0dd",
  "Purple":      "#800080",
  "Fuchsia":     "#ff00ff",
  "Silver":      "#c0c0c0",
  "Aqua":        "#00ffff",
  "Teal":        "#008080",
  "Cornflower":  "#6495ed",
  "Sky Blue":    "#87ceeb",
  "Aquamarine":  "#7fffd4",
  "Pale Green":  "#98fb98",
  "Lime":        "#00ff00",
  "Green":       "#008000",
  "Olive":       "#808000"

};

if (isUndefined(user_highlight_colors)) {
  var user_highlight_colors = { };
}

function plugin_highlight_make_color_button(name, value) {

  var btn = document.createElement('button');

  btn.className = 'pickerbutton';
  btn.value = ' ';
  btn.title = name;
  btn.style.height = '2em';
  btn.style.padding = '1em';
  btn.style.backgroundColor = value;

  var open = "<hi " + value + ">";
  var close ="<\/hi>";
  var sample = name + " Highlighted Text";

  eval("btn.onclick = function(){ insertTags( '"
    + jsEscape('wiki__text') + "','"
    + jsEscape(open) + "','"
    + jsEscape(close) + "','"
    + jsEscape(sample) + "'); return false; } "
  );

  return(btn);

}

function plugin_highlight_toolbar_picker() {

  // Check that we are editing the page - is there a better way to do this?
  var edbtn = document.getElementById('edbtn__save');
  if (!edbtn) return;
  
  var toolbar = document.getElementById('tool__bar');
  if (!toolbar) return;

  // Create the picker button
  var p_id = 'picker_plugin_highlight';	// picker id that we're creating
  var p_ico = document.createElement('img');
  p_ico.src = DOKU_BASE + 'lib/plugins/highlight/images/toolbar_icon.png';
  var p_btn = document.createElement('button');
  p_btn.className = 'toolbutton';
  p_btn.title = 'Highlight Text';
  p_btn.appendChild(p_ico);
  eval("p_btn.onclick = function() { showPicker('" 
    + p_id + "',this); return false; }");

  // Create the picker <div>
  var picker = document.createElement('div');
  picker.className = 'picker';
  picker.id = p_id;
  picker.style.position = 'absolute';
  picker.style.display = 'none';

  // Add a button to the picker <div> for each of the colors
  for( var color in plugin_highlight_colors ) {
    if (!isFunction(plugin_highlight_colors[color])) {
      var btn = plugin_highlight_make_color_button(color,
          plugin_highlight_colors[color]);
      picker.appendChild(btn);
    }
  }
  for( var color in user_highlight_colors ) {
    if (!isFunction(user_highlight_colors[color])) {
      var btn = plugin_highlight_make_color_button(color,
	  user_highlight_colors[color]);
      picker.appendChild(btn);
    }
  }

  var body = document.getElementsByTagName('body')[0];
  body.appendChild(picker);	// attach the picker <div> to the page body
  toolbar.appendChild(p_btn);	// attach the picker button to the toolbar
}
addInitEvent(plugin_highlight_toolbar_picker);

//Setup VIM: ex: et ts=2 sw=2 enc=utf-8 :
