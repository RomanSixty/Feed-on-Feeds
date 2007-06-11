var curWidth = 0;
var curPos = 0;
var drag = false;
var what;
var when;

var firstItem;
var item;
var itemElement;

function keyboard(e)
{
    if (!e) e = window.event;
    
    if (e.keyCode) keycode=e.keyCode;
    else keycode=e.which;
    
    if(e.shiftKey || e.ctrlKey || e.altKey || e.metaKey) return true;
    
    key = String.fromCharCode(keycode); 
    
    if(key == "n")
    {
        if(itemElement)
        {
            Element.removeClassName(itemElement, 'selected');
            
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
            
            Element.addClassName(itemElement, 'selected');
            
            y = itemElement.y ? itemElement.y : itemElement.offsetTop;
            window.scrollTo(0, y);
            
            return false;
        }
        else
        {
            item = firstItem;
            itemElement = $(item);
            Element.addClassName(itemElement, 'selected');
            
            y = itemElement.y ? itemElement.y : itemElement.offsetTop;
            window.scrollTo(0, y);
            
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
    new Ajax.Updater($('sidebar'), url, {method: 'get', parameters: params });
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

        new Ajax.Updater('items', 'add-single.php', {
            method: 'get',
            parameters: 'url=' + encodeURIComponent(f['url']),
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
