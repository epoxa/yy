window.findPreviousFocusable = function (elem, skip) {
  if (!elem) return null;
  var oldTop = $(elem).offset().top;
  var newFocus = elem;
  while (true) {
    if (newFocus.previousSibling) {
      newFocus = newFocus.previousSibling;
      while (newFocus.lastChild) newFocus = newFocus.lastChild;
    } else {
      newFocus = newFocus.parentNode;
    }
    if (!newFocus) break;
    if (newFocus.nodeType == 3) continue;
    if (newFocus == document.body) break;
    if (newFocus.tabIndex < 0) continue;
    var nf = $(newFocus);
    if (nf.is(':hidden') || nf.css('visibility') == 'hidden') continue;
    if (!skip) break;
    if (nf.hasClass('yy-skip')) continue;
    if (nf.offset().top >= oldTop) continue;
    break;
  }
  if (newFocus.focus && newFocus != document.body) return newFocus;
  else return null;
};

window.findNextFocusable = function (elem, skip) {
  if (!elem) return null;
  var oldTop = $(elem).offset().top;
  var newFocus = elem;
  while (true) {
    if (newFocus.firstChild)
      newFocus = newFocus.firstChild;
    else if (newFocus.nextSibling)
      newFocus = newFocus.nextSibling;
    else {
      while (newFocus.parentNode && !newFocus.nextSibling && newFocus != document.body) newFocus = newFocus.parentNode;
      if (newFocus.nextSibling) newFocus = newFocus.nextSibling;
      else newFocus = null;
    }
    if (!newFocus) break;
    if (newFocus.nodeType == 3) continue;
    if (newFocus.tabIndex < 0) continue;
    var nf = $(newFocus);
    if (nf.is(':hidden') || nf.css('visibility') == 'hidden') continue;
    if (!skip) break;
    if (nf.hasClass('yy-skip')) continue;
    if (nf.offset().top <= oldTop) continue;
    break;
  }
  if (newFocus && newFocus.focus) return newFocus;
  else return null;
};

function ensureFocus() {
  if (!controlToFocus) {
    setFocusElement(findNextFocusable(document.body, true));
  }
}

function getCaretPosition(obj) {
  var cursorPos = null;
  if (document.selection) {
    var range = document.selection.createRange();
    range.moveStart('textedit', -1);
    cursorPos = range.text.length;
  } else {
    cursorPos = obj.selectionStart;
  }
  return cursorPos;
}

window.setNewFocus = function (oldFocus, newFocus, forward) {
  if (!newFocus) return;
  if (!$(newFocus).closest('.field-holder').is($(oldFocus).closest('.field-holder'))) {
    var dir = forward ? 'first' : 'last';
    var table = $(newFocus).closest('table.child');
    var el;
    if (table.length) {
      if (table.hasClass('selectable')) {
        var row = table.find('tr.selected:first');
        if (!row.length) {
          row = table.find('tr.selectable:first');
        }
        el = row.find('input[tabindex="1"]:' + dir);
        if (!el.length) {
          el = row;
        }
      } else {
        el = table.find('input[tabindex="1"]:' + dir);
      }
      if (el.length) {
        newFocus = el.get(0);
      }
    }
  }
  console.log('focus: ' + newFocus.tagName);
  newFocus.focus();
};

$(document).keypress(function (event) {
  var target = event.target;
  var newFocus;
  var vertical;
  var n1, n2, n3;
  //noinspection FallthroughInSwitchStatementJS
  switch (event.keyCode) {
    //case 13:
    //  if (target.tagName == 'INPUT' && target.type == 'text' || target.tagName == 'TEXTAREA') {
    //    var next = findNextFocusable(target);
    //    if (next && next.tagName == 'A') {
    //      event.preventDefault();
    //      next.focus();
    //      setTimeout(function () {
    //        next.click()
    //      }, 0);
    //    }
    //  }
    //  break;
    case 37:
      if (target.tagName == 'INPUT' && target.type == 'text' || target.tagName == 'TEXTAREA') {
        if (getCaretPosition(target) > 0) return;
      }
    case 38:
      vertical = event.keyCode == 38;
      newFocus = findPreviousFocusable(target, vertical);
      if (newFocus) {
        n1 = $(target);
        n2 = $(newFocus);
        n3 = n2.parents('.field-holder');
        if (n3.is(n1.parents('.field-holder'))) { // inside field holder
          if (!vertical) {
            if (n2.offset().top < n1.offset().top) {
              newFocus = findPreviousFocusable(n3.get(0), false);
            }
          }
        } else if (vertical) {
          newFocus = findPreviousFocusable(newFocus, true);
          if (newFocus) {
            newFocus = findNextFocusable(newFocus, false);
          } else {
            newFocus = findNextFocusable(document.body, true);
          }
        }
      }
      setNewFocus(target, newFocus, false);
      event.preventDefault();
      return false;
    case 39:
      if (target.tagName == 'INPUT' && target.type == 'text' || target.tagName == 'TEXTAREA') {
        if (getCaretPosition(target) < $(target).val().length) return;
      }
    case 40:
      vertical = event.keyCode == 40;
      newFocus = findNextFocusable(target, vertical);
      if (newFocus) {
        if (!vertical) {
          n1 = $(target);
          n2 = $(newFocus);
          n3 = n2.parents('.field-holder');
          if (n3.is(n1.parents('.field-holder'))) {
            if (n2.offset().top > n1.offset().top) {
              newFocus = n3.next().get(0);
              newFocus = findNextFocusable(newFocus, false);
            }
          }
        }
      }
      setNewFocus(target, newFocus, true);
      event.preventDefault();
      return false;
  }
});
