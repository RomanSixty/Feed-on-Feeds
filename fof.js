var curWidth = 0;
var curPos = 0;
var drag = false;
var what;
var when;

var firstItem;
var item;
var itemElement;
var itemElements;

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
    Element.addClassName(itemElement, 'selected');
    
    y = itemElement.y ? itemElement.y : itemElement.offsetTop;
    window.scrollTo(0, y);
    
    n = itemElements.length;
    i = itemElements.indexOf(itemElement);
    
    if(i == -1)
    {
        // in case page was partially loaded when itemElements was initialized
        itemElements = $$('.item');
        i = itemElements.indexOf(itemElement);
    }
    
    i++;
    
    document.title = "Feed on Feeds - " + i + " of " + n;
}

function unselect(item)
{
    Element.removeClassName(item, 'selected');
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
            
            if(itemElement.nextSibling.id)
            {
                nextElement = itemElement.nextSibling;
                scrollHeight = document.body.scrollTop ? document.body.scrollTop : pageYOffset;
                
                if (nextElement.offsetParent) {
                    y = nextElement.offsetTop
                    while (nextElement = nextElement.offsetParent) {
                        y += nextElement.offsetTop
                    }
                }
                
                if( typeof( window.innerHeight ) == 'number' ) {
                    //Non-IE
                    windowHeight = window.innerHeight;
                } else if( document.documentElement && document.documentElement.clientHeight ) {
                    //IE 6+ in 'standards compliant mode'
                    windowHeight = document.documentElement.clientHeight;
                } else if( document.body && document.body.clientHeight ) {
                    //IE 4 compatible
                    windowHeight = document.body.clientHeight;
                }
                                
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
            
            if(next.id)
            {
                itemElement = next;
            }
            else
            {
                if( typeof( window.innerHeight ) == 'number' ) {
                    //Non-IE
                    windowHeight = window.innerHeight;
                } else if( document.documentElement && document.documentElement.clientHeight ) {
                    //IE 6+ in 'standards compliant mode'
                    windowHeight = document.documentElement.clientHeight;
                } else if( document.body && document.body.clientHeight ) {
                    //IE 4 compatible
                    windowHeight = document.body.clientHeight;
                }

                scrollHeight = document.body.scrollTop ? document.body.scrollTop : pageYOffset;

                e = $('end-of-items');
                
                if (e.offsetParent) {
                    y = e.offsetTop
                    while (e = e.offsetParent) {
                        y += e.offsetTop
                    }
                }
                
                if(y > scrollHeight + windowHeight)
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
    items = document.getElementsByClassName("item", "items");
    items.each( function(e) { e.className = "item hidden"; });
}

function show_all()
{
    items = document.getElementsByClassName("item", "items");
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
    
    image = $('fav' + id);    
    
    var url = "add-tag.php?tag=star";
    var params = "&item=" + id;
    var complete = function () { refreshlist(); refreshitem(id); };
    var options = { method: 'get', parameters: params, onComplete: complete };
    
    if(image.star)
    {
        params += "&remove=true";
        var complete = function() { image.src='image/star-off.gif'; image.star = false; refreshlist(); };
    }
    else
    {
        var complete = function() { image.src='image/star-on.gif'; image.star = true; refreshlist(); };
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
    continueupdate();
}

function ajaxadd()
{
    throb();
    feedi = iterate(feedslist);
    continueadd();
}
