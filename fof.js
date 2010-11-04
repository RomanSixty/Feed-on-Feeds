var curWidth = 0;
var curPos = 0;
var drag = false;
var what;
var when;

var firstItem;
var item;
var itemElement;
var itemElements;

// magic from http://peter.michaux.ca/article/3556

var getScrollY = function() {

    if (typeof window.pageYOffset == 'number') {

        getScrollY = function() {
            return window.pageYOffset;
        };

    } else if ((typeof document.compatMode == 'string') &&
               (document.compatMode.indexOf('CSS') >= 0) &&
               (document.documentElement) &&
               (typeof document.documentElement.scrollTop == 'number')) {

        getScrollY = function() {
            return document.documentElement.scrollTop;
        };

    } else if ((document.body) &&
               (typeof document.body.scrollTop == 'number')) {

      getScrollY = function() {
          return document.body.scrollTop;
      }

    } else {

      getScrollY = function() {
          return NaN;
      };

    }

    return getScrollY();
}

var getY = function(e)
{
    var y = NaN;
    
    if (e.offsetParent) {
        y = e.offsetTop
        while (e = e.offsetParent) {
            y += e.offsetTop
        }
    }
    
    return y;
}

function getWindowHeight()
{    
    if( typeof( window.innerHeight ) == 'number' ) {
        //Non-IE
        return window.innerHeight;
    } else if( document.documentElement && document.documentElement.clientHeight ) {
        //IE 6+ in 'standards compliant mode'
        return document.documentElement.clientHeight;
    } else if( document.body && document.body.clientHeight ) {
        //IE 4 compatible
        return document.body.clientHeight;
    }
    
    return NaN;
}

function embed_odeo(link) {
	document.writeln('<embed src="http://odeo.com/flash/audio_player_fullsize.swf" pluginspage="http://www.macromedia.com/go/getflashplayer" type="application/x-shockwave-flash" quality="high" width="440" height="80" wmode="transparent" allowScriptAccess="any" flashvars="valid_sample_rate=true&external_url='+link+'"></embed>');
}

function embed_quicktime(type, bgcolor, width, height, link, placeholder, loop) {
	if (placeholder != '') {
		document.writeln('<embed type="'+type+'" style="cursor:hand; cursor:pointer;" href="'+link+'" src="'+placeholder+'" width="'+width+'" height="'+height+'" autoplay="false" target="myself" controller="false" loop="'+loop+'" scale="aspect" bgcolor="'+bgcolor+'" pluginspage="http://www.apple.com/quicktime/download/"></embed>');
	}
	else {
		document.writeln('<embed type="'+type+'" style="cursor:hand; cursor:pointer;" src="'+link+'" width="'+width+'" height="'+height+'" autoplay="false" target="myself" controller="true" loop="'+loop+'" scale="aspect" bgcolor="'+bgcolor+'" pluginspage="http://www.apple.com/quicktime/download/"></embed>');
	}
}

function embed_flash(bgcolor, width, height, link, loop, type) {
	document.writeln('<embed src="'+link+'" pluginspage="http://www.macromedia.com/go/getflashplayer" type="'+type+'" quality="high" width="'+width+'" height="'+height+'" bgcolor="'+bgcolor+'" loop="'+loop+'"></embed>');
}

function embed_flv(width, height, link, placeholder, loop, player) {
	document.writeln('<embed src="'+player+'" pluginspage="http://www.macromedia.com/go/getflashplayer" type="application/x-shockwave-flash" quality="high" width="'+width+'" height="'+height+'" wmode="transparent" flashvars="file='+link+'&autostart=false&repeat='+loop+'&showdigits=true&showfsbutton=false"></embed>');
}

function embed_wmedia(width, height, link) {
	document.writeln('<embed type="application/x-mplayer2" src="'+link+'" autosize="1" width="'+width+'" height="'+height+'" showcontrols="1" showstatusbar="0" showdisplay="0" autostart="0"></embed>');
}

function itemClicked(event)
{
    if(!event) event = window.event; 
    target = window.event ? window.event.srcElement : event.target;
     
    if(event.altKey)
    {
		Event.stop(event);
		
		unselect(itemElement);
		while(target.parentNode)
		{
			if(Element.hasClassName(target, "item"))
			{
				break;
			}
			target = target.parentNode;
		}
		
		if(itemElement == target)
		{
			itemElement = null;
			return false;
		}
		
	    Element.addClassName(target, 'selected');
		itemElement = target;

	    i = itemElements.indexOf(target);

	    if(i == -1)
	    {
	        // in case page was partially loaded when itemElements was initialized
	        itemElements = $$('.item');
	        i = itemElements.indexOf(target);
	    }

	    n = itemElements.length;
	    i++;

	    document.title = "Feed on Feeds - " + i + " of " + n;
		return false;
	}
	
	return true;
}

function checkbox(event)
{
    if(!event) event = window.event; 
    target = window.event ? window.event.srcElement : event.target;
     
    if(!event.shiftKey)
        return true;
    
    flag_upto(target.id);
    
    return true;
}

function select(item)
{
    Element.addClassName(item, 'selected');
    
    y = getY(item);
    bar = $('item-display-controls').getHeight();
    window.scrollTo(0, y - (bar + 10));
    
    i = itemElements.indexOf(item);
    
    if(i == -1)
    {
        // in case page was partially loaded when itemElements was initialized
        itemElements = $$('.item');
        i = itemElements.indexOf(item);
    }
    
    n = itemElements.length;
    i++;
    
    document.title = "Feed on Feeds - " + i + " of " + n;
}

function unselect(item)
{
    Element.removeClassName(item, 'selected');
    document.title = "Feed on Feeds";

}

function show_enclosure(e)
{
    if (!e) e = window.event;    
    target = window.event ? window.event.srcElement : e.target;
    Element.extend(target);
    div = target.nextSiblings().first();
    Element.show(div);
    Element.hide(target);
        
    return false;
}

function keyboard(e)
{
    if (!e) e = window.event;
    
    target = window.event ? window.event.srcElement : e.target;

    if(target != null && target.type != null && (target.type == "textarea" || target.type=="text" || target.type=="password"))
    {
        return true;
    }

    if (e.keyCode) keycode=e.keyCode;
    else keycode=e.which;
    
    if(e.ctrlKey || e.altKey || e.metaKey) return true;
    
    key = String.fromCharCode(keycode); 
    
    if(!itemElements) itemElements = $$('.item');
    
    windowHeight = getWindowHeight();

    if(key == "H")
    {
        itemElements.each(
            function(i) {
                Element.toggleClassName(i, "shown");
                Element.toggleClassName(i, "hidden");
            }
            );
        
        if(itemElement)
            select(itemElement);
        
        return false;
    }
    
    if(key == "h")
    {        
        if(itemElement)
        {
            Element.toggleClassName(itemElement, "shown");
            Element.toggleClassName(itemElement, "hidden");
            select(itemElement);
            return false;
        }
    }
    
    if(key == "s")
    {
        if(itemElement)
        {
            toggle_favorite(itemElement.id.substring(1));
            select(itemElement);
            return false;
        }
    }
    
    if(key == "f")
    {
        if(itemElement)
        {
            checkbox = ($('c' + itemElement.id.substring(1)));
            checkbox.checked = true;
            return false;
        }
    }
    
    if(key == "F")
    {
        itemElements.each(
            function(i) {
                if(itemElement)
                {
                    if(itemElements.indexOf(i) > itemElements.indexOf(itemElement))
                        return;
                }
                checkbox = ($('c' + i.id.substring(1)));
                checkbox.checked = true;
            }
            );
        
        return false;
    }

    if(key == "U")
    {
        itemElements.each(
            function(i) {
                checkbox = ($('c' + i.id.substring(1)));
                checkbox.checked = false;
            }
            );
        
        return false;
    }

    if(key == "j")
    {
        if(itemElement)
        {
            // is the next element visible yet?  scroll if not.  
            
            if(itemElement.nextSibling.id && itemElement.nextSibling.id != "end-of-items")
            {
                nextElement = itemElement.nextSibling;
                scrollHeight = getScrollY();
                y = getY(nextElement);
                
                if(y > scrollHeight + windowHeight)
                {
                    window.scrollTo(0, scrollHeight + (.8 * windowHeight));
                    return false;
                }
            }

            unselect(itemElement);
            checkbox = ($('c' + itemElement.id.substring(1)));
            checkbox.checked = true;

            next = itemElement.nextSibling;
            
            if(next.id && next.id != "end-of-items")
            {
                itemElement = next;
            }
            else
            {
                scrollHeight = getScrollY();

                e = $('end-of-items');
                
                if (e.offsetParent) {
                    y = e.offsetTop
                    while (e = e.offsetParent) {
                        y += e.offsetTop
                    }
                }
                
                if(y - 10 > scrollHeight + windowHeight)
                {
                    window.scrollTo(0, scrollHeight + (.8 * windowHeight));
                    return false;
                }
                else
                {
                    if(confirm("No more items!  Mark flagged as read?"))
                    {
                        mark_read();
                    }
                    else
                    {
                        item = firstItem;
                        itemElement = $(item);
                        select(itemElement);                    
                        return false;
                    }
                }
            }
            
            item = itemElement.id;
            itemElement = $(item);
            
            select(itemElement);
            
            return false;
        }
        else
        {
            item = firstItem;
            itemElement = $(item);
            itemElements = $$('.item');
            
            select(itemElement);
            
            return false;
        }
    }
    
    if(key == "J")
    {
        if(itemElement)
        {
            unselect(itemElement);
            checkbox = ($('c' + itemElement.id.substring(1)));
            checkbox.checked = true;

            next = itemElement.nextSibling;
            
            if(next.id)
            {
                itemElement = next;
            }
            else
            {
                if(confirm("No more items!  Mark flagged as read?"))
                {
                    mark_read();
                }
                else
                {
                    item = firstItem;
                    itemElement = $(item);
                }
            }
            
            item = itemElement.id;
            itemElement = $(item);
            
            select(itemElement);
            
            return false;
        }
        else
        {
            item = firstItem;
            itemElement = $(item);
            itemElements = $$('.item');
            
            select(itemElement);
            
            return false;
        }
    }

    if(key == "n")
    {
        if(itemElement)
        {
            unselect(itemElement);
            
            next = itemElement.nextSibling;
            
            if(next.id)
            {
                itemElement = next;
            }
            else
            {
                item = firstItem;
                itemElement = $(item);
            }
            
            item = itemElement.id;
            itemElement = $(item);
            
            select(itemElement);
            
            return false;
        }
        else
        {
            item = firstItem;
            itemElement = $(item);
            itemElements = $$('.item');
            
            select(itemElement);
            
            return false;
        }
    }
    
    if(key == "N")
    {
        if(itemElement) unselect(itemElement);
            
        item = itemElements.last().id;
        itemElement = $(item);
        
        select(itemElement);
        
        return false;
    }
    
    if(key == "P")
    {
        if(itemElement) unselect(itemElement);

        item = firstItem;
        itemElement = $(item);
        itemElements = $$('.item');
        
        select(itemElement);
        
        return false;
    }
    
    if(key == "p")
    {
        if(itemElement)
        {
            unselect(itemElement);
            
            next = itemElement.previousSibling;
            
            if(next.id)
            {
                itemElement = next;
            }
            else
            {
                item = itemElements.last().id;
                itemElement = $(item);
            }
            
            item = itemElement.id;
            itemElement = $(item);
            
            select(itemElement);
            
            return false;
        }
        else
        {
            itemElements = $$('.item');
            item = itemElements.last().id;
            itemElement = $(item);
            
            select(itemElement);
            
            return false;
        }
    }
    
    return true;
}



function startResize(e)
{
    if (!e) e = window.event;
    
    Event.stop(e);
    
    drag = true;
    curPos=e.clientX;
    curWidth=$('sidebar').offsetWidth;

    return false;
}

function dragResize(e)
{
    if (!e) e = window.event;
    
    if(drag)
    {
        Event.stop(e);
        
        newPos=e.clientX;
        var x=newPos-curPos;
        var w=curWidth+x;
        newWidth=(w<5?5:w);
        
        $('handle').style.left=newWidth+'px';

        return false;
    }
}

function completeDrag(e)
{
    if (!e) e = window.event;

    if(drag)
    {
        Event.stop(e);
        
        drag = false;
        
        newPos=e.clientX;
        var x=newPos-curPos;
        var w=curWidth+x;
        newWidth=(w<5?5:w);
        
        $('sidebar').style.width=newWidth+'px';
        $('handle').style.left=newWidth+'px';
        $('items').style.marginLeft=(newWidth+20)+'px';
        $('item-display-controls').style.left=(newWidth+10)+'px';

        if(isIE)
        {
            tables = $$('#sidebar table');
            for(i=0;i<tables.length;i++){
                tables[i].style.width=(newWidth-20)+'px';
            }
        }       
        var today = new Date();
        var expire = new Date();
        expire.setTime(today.getTime() + 3600000*24*100);
        document.cookie = "fof_sidebar_width="+newWidth+ "; expires="+expire.toGMTString()+";";
        
        return false;
    }
    
}

function hide_all()
{
    items = $A(document.getElementsByClassName("item", "items"));
    items.each( function(e) { e.className = "item hidden"; });
}

function show_all()
{
    items = $A(document.getElementsByClassName("item", "items"));
    items.each( function(e) { e.className = "item shown"; });
}

function hide_body(id)
{
    $('i' + id).className = 'item hidden';
}

function show_body(id)
{
    $('i' + id).className = 'item shown';
}

function flag_upto(id)
{
    elements = $A(Form.getInputs('itemform', 'checkbox'));
    
    for(i=0; i<elements.length; i++)
    {
        elements[i].checked = true;
        
        if(elements[i].name == id)
        {
            break;
        }
    }
}

function toggle_highlight()
{
    if(document.body.className == '')
    {
        document.body.className = 'highlight-on';
    }
    else
    {
        document.body.className = '';
    }
}

function flag_all()
{
    elements = $A(Form.getInputs('itemform', 'checkbox'));  
    elements.each( function(e) { e.checked = true; });
}


function toggle_all()
{
    elements = $A(Form.getInputs('itemform', 'checkbox'));
    elements.each( function(e) { e.checked = !e.checked; });
}

function unflag_all()
{
    elements = $A(Form.getInputs('itemform', 'checkbox'));
    elements.each( function(e) { e.checked = false; });
}


function mark_read()
{
    document.items['action'].value = 'read';
    document.items['return'].value = escape(location);
    document.items.submit();
}

function mark_unread()
{
    document.items['action'].value = 'unread';
    document.items['return'].value = escape(location);
    document.items.submit();
}

function mark_feed_read(id)
{
    throb();
    
    var url = "view-action.php";
    var params = "feed=" + id;
    var complete = function () { refreshlist(); };
    var options = { method: 'post', parameters: params, onComplete: complete };
    
    new Ajax.Request(url, options);
    
    return false;
}

function add_tag(id, tag)
{
    throb();
    
    var url = "add-tag.php";
    var params = "tag=" + tag + "&item=" + id;
    var complete = function () { refreshlist(); refreshitem(id); };
    var options = { method: 'get', parameters: params, onComplete: complete };
    
    new Ajax.Request(url, options);
    
    return false;
}

function remove_tag(id, tag)
{
    throb();
    
    var url = "add-tag.php";
    var params = "remove=true&tag=" + tag + "&item=" + id;
    var complete = function () { refreshlist(); refreshitem(id); };
    var options = { method: 'get', parameters: params, onComplete: complete };
    
    new Ajax.Request(url, options);
    
    return false;
}

function delete_tag(tag)
{
    throb();
    
    var url = "view-action.php";
    var params = "deltag=" + tag;
    var complete = function () { refreshlist(); };
    var options = { method: 'get', parameters: params, onComplete: complete };
    
    new Ajax.Request(url, options);
    
    return false;
}

function change_feed_order(order, direction)
{
    throb();
    
    var url = "set-prefs.php";
    var params = "feed_order=" + order + "&feed_direction=" + direction;
    var complete = function () { refreshlist(); };
    var options = { method: 'post', parameters: params, onComplete: complete };
    
    new Ajax.Request(url, options);
    
    return false;

}

function toggle_favorite(id)
{
    throb();
    
    var image = $('fav' + id);    
    
    var url = "add-tag.php?tag=star";
    var params = "&item=" + id;
    image.src = 'image/star-pending.gif';
	
    if(image.star)
    {
        params += "&remove=true";
        var complete = function()
		{
			image.src='image/star-off.gif';
			image.star = false;
			starred--;
			if(starred)
			{
				$('starredcount').update('(' + starred + ')');
			}
			else
			{
				$('starredcount').update('');
			}
			unthrob();
		};
    }
    else
    {
        var complete = function()
		{
			image.src='image/star-on.gif';
			image.star = true;
			starred++;
			if(starred)
			{
				$('starredcount').update('(' + starred + ')');
			}
			else
			{
				$('starredcount').update('');
			}
			unthrob();
		};
    }
    
    var options = { method: 'get', parameters: params, onComplete: complete };  
    new Ajax.Request(url, options);
    
    return false;
}

function refreshitem(id)
{
    throb();
    
    var url = 'item.php';
    var params = 'id=' + id;
    new Ajax.Updater($("i"+id), url, {method: 'get', parameters: params });
}


function refreshlist()
{
    throb();
    
    var url = 'sidebar.php';
    var params = "what=" + what + "&when=" + when;
        
    new Ajax.Updater($('sidebar'), url, {method: 'get', parameters: params, evalScripts: true });
}

function throb()
{
    Element.show('throbber');
}

function unthrob()
{
    Element.hide('throbber');
}

// this fancy bit of computer science from Aristotle Pagaltzis @ http://plasmasturm.org/log/311/
function iterate( iterable ) {
    var i = -1;
    var getter = function() { return i < 0 ? null : i < iterable.length ? iterable[ i ] : null; };
    return function() { return ++i < iterable.length ? getter : null };
}

function continueupdate()
{
    if(feed = feedi())
    {
        f = feed();
        new Insertion.Bottom($('items'), 'Updating  ' + f['title'] + "... ");
        $('items').childElements().last().scrollTo();

        new Ajax.Updater('items', 'update-single.php', {
            method: 'get',
            parameters: 'feed=' + f['id'],
            insertion: Insertion.Bottom,
            onComplete: continueupdate
        });
    }
    else
    {
        new Insertion.Bottom($('items'), '<br>Update complete!');
        refreshlist();
    }
}

function continueadd()
{    
    if(feed = feedi())
    {
        f = feed();
        new Insertion.Bottom($('items'), 'Adding  ' + f['url'] + "... ");
        $('items').childElements().last().scrollTo();

        parameters = 'url=' + encodeURIComponent(f['url']) + "&unread=" + document.addform.unread.value;

        new Ajax.Updater('items', 'add-single.php', {
            method: 'get',
            parameters: parameters,
            insertion: Insertion.Bottom,
            onComplete: continueadd
        });
    }
    else
    {
        new Insertion.Bottom($('items'), '<br>Done!');
        refreshlist();
    }
}

function ajaxupdate()
{
    throb();
    feedi = iterate(feedslist);
    setTimeout(continueupdate,100);
    setTimeout(continueupdate,100);
    setTimeout(continueupdate,100);
    setTimeout(continueupdate,100);
    setTimeout(continueupdate,100);
}

function ajaxadd()
{
    throb();
    feedi = iterate(feedslist);
    continueadd();
}

