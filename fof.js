var curWidth = 0;
var curPos = 0;
var drag = false;
var what;
var when;
var search;
var feed;

var firstItem;
var item;
var itemElement;
var itemElements;

var pendingUpdates = [];
pendingUpdates.inFlight = 0;
pendingUpdates.add = function(items) { this.push.apply(this, items); this.work(); }
pendingUpdates.work = function() {
    var that = this;
    var url = "feed-action.php";
    var complete = function() {
        that.inFlight -= 1;
        if (that.inFlight == 0 && that.length == 0)
            unthrob();
    };
    while (that.length) {
        var id = that.shift();
        that.inFlight += 1;

        var feed_element = $$("#sidebar #feeds #f" + id)[0];
        var feed_icon_element = $$("#sidebar #feeds #f" + id + " img.feed-icon")[0];
        feed_icon_element.replace("<img class=\"feed-icon\" src=\"image/spinner.gif\" title=\"update pending\" />");
        // FIXME: assetize busy spinner

        var params = { "update_feedid": id };
        var options = { method: "post", parameters: params, onComplete: complete };
        new Ajax.Updater( { success: feed_element, failure: feed_icon_element }, url, options);
    }
    if (that.inFlight == 0 && that.length == 0)
        unthrob();
}

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

	    document.title = "Feed on Feeds - item " + i + " selected, of " + n + " displayed";
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

    document.title = "Feed on Feeds - item " + i + " selected, of " + n + " displayed";
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

    if(key == "H") // toggle all item foldings
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

    if(key == "h") // toggle current item folding
    {
        if(itemElement)
        {
            Element.toggleClassName(itemElement, "shown");
            Element.toggleClassName(itemElement, "hidden");
            select(itemElement);
            return false;
        }
    }

    if(key == "s") // toggle starred status of current item
    {
        if(itemElement)
        {
            toggle_favorite(itemElement.id.substring(1));
            select(itemElement);
            return false;
        }
    }

    if(key == "f") // flag current item
    {
        if(itemElement)
        {
            checkbox = ($('c' + itemElement.id.substring(1)));
            checkbox.checked = true;
            return false;
        }
    }

    if(key == "F") // flag current and all previous items
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

    if(key == "U") // unflag all items
    {
        itemElements.each(
            function(i) {
                checkbox = ($('c' + i.id.substring(1)));
                checkbox.checked = false;
            }
            );

        return false;
    }

    if(key == "j") // scroll current item or move to next item, flag current item
    {
        if(itemElement)
        {
            // is the next element visible yet?  scroll if not.

            if(itemElement.nextElementSibling.id && itemElement.nextElementSibling.id != "end-of-items")
            {
                nextElement = itemElement.nextElementSibling;
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

            next = itemElement.nextElementSibling;

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

    if(key == "J") // flag item, move to next
    {
        if(itemElement)
        {
            unselect(itemElement);
            checkbox = ($('c' + itemElement.id.substring(1)));
            checkbox.checked = true;

            next = itemElement.nextElementSibling;

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

    if(key == "n") // skip to next item
    {
        if(itemElement)
        {
            unselect(itemElement);

            next = itemElement.nextElementSibling;

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

    if(key == "N") // skip to last item
    {
        if(itemElement) unselect(itemElement);

        item = itemElements.last().id;
        itemElement = $(item);

        select(itemElement);

        return false;
    }

    if(key == "P") // skip to first item
    {
        if(itemElement) unselect(itemElement);

        item = firstItem;
        itemElement = $(item);
        itemElements = $$('.item');

        select(itemElement);

        return false;
    }

    if(key == "p") // skip to previous item
    {
        if(itemElement)
        {
            unselect(itemElement);

            next = itemElement.previousElementSibling;

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

    if (key == "r") { // refresh sidebar
        refreshlist();
        return false;
    }

    if (key == "?") { // show help pane
        $('keyboard-legend').toggle();
        return false;
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
        $('items').style.marginLeft=(newWidth+10)+'px';
        $('item-display-controls').style.left=(newWidth+10)+'px';
        $('welcome').style.width=(newWidth-30)+'px';

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
    items.each( function(e) { hide_body(e.id.substring(1)); });
}

function show_all()
{
    items = $A(document.getElementsByClassName("item", "items"));
    items.each( function(e) { show_body(e.id.substring(1)); });
}

function hide_body(id)
{
	throb();

	var url = "view-action.php";
	var params = { "fold": id };
	var complete = function () { $('i'+id).className = 'item hidden'; unthrob(); };
	var options = { method: 'post', parameters: params, onComplete: complete };

	new Ajax.Request(url, options);
}

function show_body(id)
{
	throb();

	var url = "view-action.php";
	var params = { "unfold": id };

	var complete = function () { $('i'+id).className = 'item shown'; unthrob(); };
	var options = { method: 'post', parameters: params, onComplete: complete };

	new Ajax.Request(url, options);
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

function ajax_mark_read(id)
{
    throb();

    var url = "view-action.php";
    var params = { "mark_read": id };
    var complete = function () {
        refreshlist();

        item = $('i'+id);

        // scroll to start of next item if it flips out of the viewport
        // by removing the read item

        y = getY(item);
        scrollHeight = getScrollY();

        item.remove();

        if(y < scrollHeight) {
            bar = $('item-display-controls').getHeight();

            window.scrollTo(0, y - (bar + 10));
        }
    };
    var options = { method: 'post', parameters: params, onComplete: complete };

    new Ajax.Request(url, options);

    return false;
}

function mark_feed_read(id)
{
    throb();

    var url = "view-action.php";
    var params = { "feed": id };
    var complete = function () { refreshlist(); };
    var options = { method: 'post', parameters: params, onComplete: complete };

    new Ajax.Request(url, options);

    return false;
}

function untag_all()
{
    items = $$('.untag');
    items.each( function(e) { e.onclick(); });
}

function add_tag(id, tag)
{
    throb();

    var url = "add-tag.php";
    var params = { "tag": tag, "item": id };
    var complete = function () { refreshlist(); refreshitem(id); };
    var options = { method: 'post', parameters: params, onComplete: complete };

    new Ajax.Request(url, options);

    return false;
}

function remove_tag(id, tag)
{
    throb();

    var url = "add-tag.php";
    var params = { "remove": "true", "tag": tag, "item": id };
    var complete = function () { refreshlist(); refreshitem(id); };
    var options = { method: 'post', parameters: params, onComplete: complete };

    new Ajax.Request(url, options);

    return false;
}

function delete_tag(tag)
{
    throb();

    var url = "view-action.php";
    var params = { "deltag": tag };
    var complete = function () { refreshlist(); };
    var options = { method: 'post', parameters: params, onComplete: complete };

    new Ajax.Request(url, options);

    return false;
}

function change_feed_order(order, direction)
{
    throb();

    var url = "set-prefs.php";
    var params = { "feed_order": order, "feed_direction": direction };
    var complete = function () { refreshlist(); };
    var options = { method: 'post', parameters: params, onComplete: complete };

    new Ajax.Request(url, options);

    return false;

}

function toggle_favorite(id)
{
    throb();

    var image = $('fav' + id);

    var url = "add-tag.php";
    var params = { "tag": "star", "item": id };
    image.src = 'image/star-pending.gif';

    if(image.className == 'starred')
    {
        params['remove'] = "true";
        var complete = function()
		{
			image.src='image/star-off.gif';
			image.className='unstarred';
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
			image.className='starred';
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

    var options = { method: 'post', parameters: params, onComplete: complete };
    new Ajax.Request(url, options);

    return false;
}

function refreshitem(id)
{
    throb();

    var url = 'item.php';
    var params = { "id": id };
    new Ajax.Updater($("i"+id), url, {method: 'get', parameters: params });
}


function refreshlist()
{
    throb();

    var params = {}; // persist view details
    if (feed !== null) params["feed"] = feed;
    if (what !== null) params["what"] = what;
    if (when !== null) params["when"] = when;
    if (search !== null) params["search"] = search;

    new Ajax.Updater($("sidebar"), "sidebar.php", {method: "get", parameters: params, evalScripts: true });
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
        var update_feed_id = 'feed_id_' + f['id'];
        $(update_feed_id).scrollTo();
//        $('items').childElements().last().scrollTo();

        new Ajax.Updater(update_feed_id, 'update-single.php', {
            method: 'post',
            parameters: { "feed": f["id"] },
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
        var new_feed_id = 'feed_index_' + f['idx'];
//        $(new_feed_id).scrollTo();

        parameters = { "url": f['url'], "unread": document.addform.unread.value };

        new Ajax.Updater(new_feed_id, 'add-single.php', {
            method: 'post',
            parameters: parameters,
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
    for (var i=0; i<Math.min(feedslist.length, 5); i++)
        setTimeout(continueupdate,50);
}

function ajaxadd()
{
    throb();
    feedi = iterate(feedslist);
    continueadd();
}

function itemTagAddShow(id, link) {
    document.getElementById('addtag' + id).style.display = '';
    link.style.display = 'none';
    return false;
}

function itemTagAdd(id, key) {
    if (key == null || key == 13)
        return add_tag(id, document.getElementById('tag' + id).value);
    return false;
}

function sb_read_conf(title, id) {
    if (confirm('Mark all [' + title + '] items as read -- are you SURE?')) {
        mark_feed_read(id);
    }
    return false
}

function sb_del_tag_conf(tagname) {
    if (confirm('Untag all [' + tagname + '] items -- are you SURE?')) {
         delete_tag(tagname);
    }
    return false;
};

function sb_unsub_conf(title) {
    return confirm('Unsubscribe [' + title + '] -- are you SURE?');
}

function sb_mark_tag_read(tagname) {
    if (confirm('Mark all [' + tagname + '] items as read -- are you SURE?')) {
        throb();

        var url = "view-action.php";
        var params = { 'tag_read': tagname };
        var complete = function () { refreshlist(); };
        var options = { method: 'post', parameters: params, onComplete: complete };

        new Ajax.Request(url, options);
    }
    return false;
}

function sb_update_feed(id) {
    throb();

    var url = "feed-action.php";
    var params = { "update_feedid": id };
    var complete = function() { unthrob(); };
    var options = { method: "post", parameters: params, onSuccess: complete };
    var feed_element = $$("#sidebar #feeds #f" + id)[0];
    var feed_icon_element = $$("#sidebar #feeds #f" + id + " img.feed-icon")[0];

    /* show in-progress state */
    feed_icon_element.replace("<img class=\"feed-icon\" src=\"image/spinner.gif\" title=\"update pending\" />");

    /* success replaces the whole table row, failure just replaces the icon */
    new Ajax.Updater( { success: feed_element, failure: feed_icon_element }, url, options);
}

function sb_update_tag_sources(tagname) {
    throb();

    var url = "feed-action.php";
    var params = { "update_tag_sources": tagname };
    var options = { method: "post", parameters: params };
    new Ajax.Request(url, options);
}

function sb_readall_feed(id) {
    throb();

    var url = "feed-action.php";
    var params = { "read_feed": id };
    var complete = function() { unthrob(); };
    var options = { method: "post", parameters: params, onComplete: complete };
    var feed_element = $$("#sidebar #feeds #f" + id)[0];
    var feed_icon_element = $$("#sidebar #feeds #f" + id + " img.feed-icon")[0];
    feed_icon_element.replace("<img class=\"feed-icon\" src=\"image/spinner.gif\" title=\"update pending\" />");
    new Ajax.Updater( { success: feed_element, failure: feed_icon_element }, url, options);
}

function view_order_set(what,feed,order) {
	throb();
	var url = "view-action.php";
	var params = { "view_order": order, "view_feed": feed, "view_what": what };
	var options = { method: "post", parameters: params, onComplete: unthrob };
	new Ajax.Updater($("view_settings_button"), url, options);
}
