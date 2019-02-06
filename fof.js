// filled in sidebar.php
let what    = null;
let when    = null;
let search  = null;
let feed    = null;
let starred = 0;

let itemElement = null;

/**
 * update these feeds
 * @param {int[]} feeds
 * @fixme doesn't exactly work... it updates, but the spinners aren't shown
 */
function pendingUpdates(feeds) {
    throb();

    feeds.forEach(feed_id => {
        document.querySelector('#f'+feed_id+' img.feed-icon').src = "image/spinner.gif";

        fetch('feed-action.php', {
            'method': 'post',
            'headers': {'Content-Type': 'application/x-www-form-urlencoded'},
            'body': 'update_feedid='+feed_id
        }).then(function(response) {
            response.text().then(data => document.getElementById('f'+feed_id).innerHTML = data);
        });
    });

    unthrob();
}

// magic from http://peter.michaux.ca/article/3556
let getScrollY = function() {
    if (typeof window.pageYOffset === 'number') {
        getScrollY = function() {
            return window.pageYOffset;
        };
    } else if ((typeof document.compatMode === 'string') &&
        (document.compatMode.indexOf('CSS') >= 0) &&
        (document.documentElement) &&
        (typeof document.documentElement.scrollTop === 'number')) {

        getScrollY = function() {
            return document.documentElement.scrollTop;
        };
    } else if ((document.body) &&
        (typeof document.body.scrollTop === 'number')) {

        getScrollY = function() {
            return document.body.scrollTop;
        }
    } else {
        getScrollY = function() {
            return NaN;
        };
    }

    return getScrollY();
};

function loadImages(e) {
    applySelector(e, 'img[data-fof-ondemand-src]', function(img) {
        img.src = img.getAttribute('data-fof-ondemand-src');
        img.removeAttribute('data-fof-ondemand-src');
    });
    applySelector(e, 'img[data-fof-ondemand-srcset]', function(img) {
        img.srcset = img.getAttribute('data-fof-ondemand-srcset');
        img.removeAttribute('data-fof-ondemand-srcset');
    });
}

function loadVisibleItems() {
    applySelector(document.getElementById("items"), ".item", function(item) {
        if (getY(item) - getScrollY() < getWindowHeight()*3/2) {
            loadImages(item);
        }
    });
}

window.addEventListener('scroll', loadVisibleItems, false);
window.addEventListener('load',   loadVisibleItems, false);

function getY(e) {
    let y = NaN;

    if (e.offsetParent) {
        y = e.offsetTop;
        while (e = e.offsetParent) {
            y += e.offsetTop
        }
    }

    return y;
}

function getWindowHeight() {
    if (typeof(window.innerHeight) === 'number') {
        //Non-IE
        return window.innerHeight;
    } else if (document.documentElement && document.documentElement.clientHeight) {
        //IE 6+ in 'standards compliant mode'
        return document.documentElement.clientHeight;
    } else if (document.body && document.body.clientHeight) {
        //IE 4 compatible
        return document.body.clientHeight;
    }

    return NaN;
}

function itemClicked(event) {
    if (!event) event = window.event;
    let target = window.event ? window.event.srcElement : event.target;

    if (event.altKey) {
        event.stopPropagation();

        unselect(itemElement);

        while (target.parentNode) {
            if (target.classList.contains("item")) {
                break;
            }
            target = target.parentNode;
        }

        if (itemElement === target) {
            itemElement = null;
            return false;
        }

        target.classList.add('selected');
        itemElement = target;

        let items = document.querySelectorAll('.items');

        let i = items.indexOf(target);
        i++;

        document.title = "Feed on Feeds - item " + i + " selected, of " + items.length + " displayed";
        return false;
    }

    return true;
}

function checkbox(event) {
    if (!event) event = window.event;
    let target = window.event ? window.event.srcElement : event.target;

    if (!event.shiftKey)
        return true;

    flag_upto(target.id);

    return true;
}

function select(item, jump) {
    item.classList.add('selected');

    // Currrent relative top and bottom of the item
    const itemTop = getY(item) - getScrollY();
    const itemBottom = itemTop + item.offsetHeight;

    // Absolute top and bottom of the screen
    const screenTop = document.getElementById('item-display-controls').offsetHeight;
    const screenBottom = getWindowHeight();

    // Relative scroll amount to make the top of the item move to the top of the screen
    const fitTop = itemTop - screenTop;
    // Relative scroll amount to make the bottom of the item move to the bottom of the screen
    const fitBottom = itemBottom - screenBottom;

    console.log('itemtop', itemTop, 'itembottom', itemBottom, 'screentop', screenTop, 'screenbottom', screenBottom, 'fittop', fitTop, 'fitbottom', fitBottom);

    if (jump) {
        // Always scroll to the top of the item
        window.scrollBy(0, fitTop);
    } else if (itemBottom > screenBottom) {
        // Item is off the bottom of the screen; scroll down until it's contained
        window.scrollBy(0, Math.min(fitTop, fitBottom));
    } else if (itemTop < screenTop) {
        // Item is off the top of the screen; scroll up until it's contained
        window.scrollBy(0, Math.max(fitTop, fitBottom));
    }

    loadImages(item);

    const items = document.querySelectorAll('.item');

    let i = Array.from(items).indexOf(item);
    i++;

    document.title = "Feed on Feeds - item " + i + " selected, of " + items.length + " displayed";
}

function unselect(item) {
    item.classList.remove('selected');
    document.title = "Feed on Feeds";
}

function isItem(item) {
    return item && item.classList.contains('item');
}

function keyboard(e) {
    if (!e) e = window.event;

    let target = window.event ? window.event.srcElement : e.target;

    if (target != null && target.type != null && (target.type === "textarea" || target.type === "text" || target.type === "password")) {
        return true;
    }

    if (e.ctrlKey || e.altKey || e.metaKey) return true;

    const items = document.querySelectorAll('.item');

    if (itemElement === null) {
        itemElement = document.querySelector('.item');
    }

    switch (e.key) {

        // toggle all item foldings
        case "H":
            items.forEach(item => {
                item.classList.toggle("shown");
                item.classList.toggle("hidden");
            });
            setTimeout(function(){select(itemElement)},1);
            return false;

        // toggle current item folding
        case "h":
            if (itemElement) {
                itemElement.classList.toggle("shown");
                itemElement.classList.toggle("hidden");
            }
            setTimeout(function(){select(itemElement)},1);
            return false;

        // toggle starred status of current item
        case "s":
            if (itemElement) {
                toggle_favorite(itemElement.id.substring(1));
            }
            return false;

        // flag/unflag current item
        case "f":
            if (itemElement) {
                document.getElementById('c' + itemElement.id.substring(1)).checked = !document.getElementById('c' + itemElement.id.substring(1)).checked;
            }
            return false;

        // flag current and all previous items
        case "F":
            items.forEach((i, idx) => {
                if (itemElement) {
                    if (idx > Array.from(items).indexOf(itemElement))
                        return;
                }
                document.getElementById('c' + i.id.substring(1)).checked = true;
            });

            return false;

        // unflag all items
        case "U":
            items.forEach(i => {
                document.getElementById('c' + i.id.substring(1)).checked = false;
            });

            return false;

        // scroll current item or move to next item, flag current item
        case "j":
            if (itemElement) {
                document.getElementById('c' + itemElement.id.substring(1)).checked = true;

                const windowHeight = getWindowHeight();
                const itemTop = getY(itemElement);
                const itemBottom = itemTop + itemElement.clientHeight;

                const bar = document.getElementById('item-display-controls').offsetHeight;
                const scrollBottom = getScrollY() + windowHeight;

                const nextElement = itemElement.nextElementSibling;

                if (itemBottom > scrollBottom) {
                    // There is more to read, so scroll down
                    window.scrollTo(0, getScrollY() + windowHeight*0.8);
                } else if (isItem(nextElement)) {
                    // Jump to the next item
                    unselect(itemElement);
                    itemElement = nextElement;
                    select(nextElement, true);
                } else if (confirm("No more items! Mark flagged as read?")) {
                    mark_read();
                }
            } else {
                // nothing is selected, so try to do that
                itemElement = document.querySelector('.item');
                if (isItem(itemElement)) {
                    select(itemElement, true);
                }
            }

            return false;

        // flag item, move to next
        case "J":
            if (itemElement) {
                document.getElementById('c' + itemElement.id.substring(1)).checked = true;

                unselect(itemElement);

                const nextElement = itemElement.nextElementSibling;

                if (isItem(nextElement)) {
                    itemElement = nextElement;
                    select(itemElement, true);
                } else if (confirm("No more items!  Mark flagged as read?")) {
                    mark_read();
                }
            }
            return false;

        // skip to next item
        case "n":
            if (itemElement) {
                unselect(itemElement);

                let nextElement = itemElement.nextElementSibling;

                if (isItem(nextElement) && nextElement.classList.contains('item')) {
                    itemElement = nextElement;
                    select(itemElement, true);
                }
                else {
                    itemElement = document.querySelector('.item');
                }
            }
            return false;

        // skip to previous item
        case "k":
        case "p":
            if (itemElement) {

                let prevElement = itemElement.previousElementSibling;

                if (isItem(prevElement)) {
                    unselect(itemElement);
                    itemElement = prevElement;
                    select(itemElement, true);
                }
            }
            console.log(itemElement);
            return false;

        // skip to last item
        case "N":
            if (itemElement) unselect(itemElement);

            itemElement = Array.from(document.querySelectorAll('.item')).pop();

            select(itemElement);
            return false;

        // skip to first item
        case "P":
            if (itemElement) unselect(itemElement);

            itemElement = document.querySelector('.item');

            select(itemElement);
            return false;

        // refresh sidebar
        case "r":
            refreshlist();
            return false;

        // show help pane
        case "?":
            document.getElementById('keyboard-legend').classList.toggle('hide');
            return false;
    }

    return true;
}

// Sidebar resizing

let curWidth = 0;
let curPos = 0;
let drag = false;

function startResize(e) {
    if (!e) e = window.event;

    e.stopPropagation();

    drag = true;
    curPos = e.clientX;
    curWidth = document.getElementById('sidebar').offsetWidth;

    return false;
}

document.onmousemove = function(e) {
    if (drag) {
        e.stopPropagation();

        const newPos = e.clientX;
        const x = newPos - curPos;
        const w = curWidth + x;
        const newWidth = (w < 5 ? 5 : w);

        document.getElementById('handle').style.left = newWidth + 'px';

        return false;
    }
};

document.onmouseup = function(e) {
    if (drag) {
        e.stopPropagation();

        drag = false;

        const newPos = e.clientX;
        const x = newPos - curPos;
        const w = curWidth + x;
        const newWidth = (w < 5 ? 5 : w);

        document.getElementById('sidebar').style.width = newWidth + 'px';
        document.getElementById('handle').style.left = newWidth + 'px';
        document.getElementById('items').style.marginLeft = (newWidth + 10) + 'px';
        document.getElementById('item-display-controls').style.left = (newWidth + 10) + 'px';
        document.getElementById('welcome').style.width = (newWidth - 30) + 'px';

        const today = new Date();
        let  expire = new Date();

        expire.setTime(today.getTime() + 3600000 * 24 * 100);

        document.cookie = 'fof_sidebar_width=' + newWidth + '; expires=' + expire.toUTCString() + ';';

        return false;
    }
};

function hide_all() {
    document.querySelectorAll('#items .item').forEach(item => hide_body(item.id.substring(1)));
}

function show_all() {
    document.querySelectorAll('#items .item').forEach(item => show_body(item.id.substring(1)));
}

function hide_body(id) {
    throb();

    fetch('view-action.php', {
        'method': 'post',
        'headers': {'Content-Type': 'application/x-www-form-urlencoded'},
        'body': 'fold='+id
    }).then(function() {
        document.querySelector('#i'+id).className = 'item hidden';
        unthrob();
    });
}

function show_body(id) {
    throb();

    fetch('view-action.php', {
        'method': 'post',
        'headers': {'Content-Type': 'application/x-www-form-urlencoded'},
        'body': 'unfold='+id
    }).then(function() {
        document.querySelector('#i'+id).className = 'item shown';
        unthrob();
    });
}

/**
 * check all items up to the currently checked one
 * e.g. on SHIFT click or double click
 * @param id checkbox's name property
 */
function flag_upto(id) {
    const checkboxes = Array.from(document.querySelectorAll('.item h1 input'));

    for (let i = 0; i < checkboxes.length; i++) {
        checkboxes[i].checked = true;

        if (checkboxes[i].name === id) {
            break;
        }
    }
}

function toggle_highlight() {
    document.body.classList.toggle('highlight-on');
}

function flag_all() {
    document.querySelectorAll('.item h1 input').forEach(checkbox => checkbox.checked = true);
}
function unflag_all() {
    document.querySelectorAll('.item h1 input').forEach(checkbox => checkbox.checked = false);
}
function toggle_all() {
    document.querySelectorAll('.item h1 input').forEach(checkbox => checkbox.checked = !checkbox.checked);
}

function mark_read() {
    document.items['action'].value = 'read';
    document.items['return'].value = encodeURI(location);
    document.items.submit();
}
function mark_unread() {
    document.items['action'].value = 'unread';
    document.items['return'].value = encodeURI(location);
    document.items.submit();
}

function ajax_mark_read(id) {
    throb();

    fetch('view-action.php', {
        'method': 'post',
        'headers': {'Content-Type': 'application/x-www-form-urlencoded'},
        'body': 'mark_read='+id
    }).then(function() {
        refreshlist();

        const item = document.getElementById('i' + id);

        // scroll to start of next item if it flips out of the viewport
        // by removing the read item

        const y = getY(item);
        const scrollHeight = getScrollY();

        item.remove();

        if (y < scrollHeight) {
            const bar = document.getElementById('item-display-controls').offsetHeight;

            window.scrollTo(0, y - (bar + 10));
        }

        loadVisibleItems();
    });

    return false;
}

function mark_feed_read(id) {
    throb();

    fetch('view-action.php', {
        'method': 'post',
        'headers': {'Content-Type': 'application/x-www-form-urlencoded'},
        'body': 'feed='+id
    }).then(function() {
        refreshlist();
    });

    return false;
}

function untag_all() {
    document.querySelectorAll('.untag').forEach(item => item.click());
}

function add_tag(id, tag) {
    throb();

    fetch('add-tag.php', {
        'method': 'post',
        'headers': {'Content-Type': 'application/x-www-form-urlencoded'},
        'body': 'tag='+tag+'&item='+id
    }).then(function() {
        refreshlist();
        refreshitem(id);
    });

    return false;
}

function remove_tag(id, tag) {
    throb();

    fetch('add-tag.php', {
        'method': 'post',
        'headers': {'Content-Type': 'application/x-www-form-urlencoded'},
        'body': 'remove=true&tag='+tag+'&item='+id
    }).then(function() {
        refreshlist();
        document.getElementById('tag_' + id.toString() + '_' + tag).remove();
    });

    return false;
}

function delete_tag(tag) {
    throb();

    fetch('view-action.php', {
        'method': 'post',
        'headers': {'Content-Type': 'application/x-www-form-urlencoded'},
        'body': 'deltag='+tag
    }).then(function() {
        refreshlist();
    });

    return false;
}

function change_feed_order(order, direction) {
    throb();

    fetch('set-prefs.php', {
        'method': 'post',
        'headers': {'Content-Type': 'application/x-www-form-urlencoded'},
        'body': 'feed_order='+order+'&feed_direction='+direction
    }).then(function() {
        refreshlist();
    });

    return false;
}

function toggle_favorite(id) {
    throb();

    const star = document.getElementById('fav' + id);

    let data = 'tag=star&item='+id;

    if (star.className === 'starred') {
        data += '&remove=true';
    }

    fetch('add-tag.php', {
        'method': 'post',
        'headers': {'Content-Type': 'application/x-www-form-urlencoded'},
        'body': data
    }).then(function() {
        if (star.className === 'starred') {
            star.className = 'unstarred';
            starred--;
        } else {
            star.className = 'starred';
            starred++;
        }

        if (starred > 0) {
            document.getElementById('starredcount').innerHTML = '(' + starred + ')';
        } else {
            document.getElementById('starredcount').innerHTML = '';
        }
        unthrob();
    });

    return false;
}

function refreshitem(id) {
    fetch('item.php?no_img_filter=1&id='+id).then(function(response) {
        response.text().then(data => document.getElementById('i' + id).innerHTML = data);
        loadVisibleItems();
    });
}

function refreshlist() {
    throb();

    let params = {};

    if (feed   !== null) params.feed   = feed;
    if (what   !== null) params.what   = what;
    if (when   !== null) params.when   = when;
    if (search !== null) params.search = search;

    fetch('sidebar.php?'+queryStringFromJSON(params)).then(function(response) {
        response.text().then(data => {
            document.getElementById('sidebar').innerHTML = data;
            evalScripts(data);
        });
        loadVisibleItems();
    });
}

function throb() {
    document.getElementById('throbber').style.display = 'inline';
}

function unthrob() {
    document.getElementById('throbber').style.display = 'none';
}

function itemTagAddShow(id, link) {
    document.getElementById('addtag' + id).style.display = '';
    link.style.display = 'none';
    return false;
}

function itemTagAdd(id, key) {
    if (key == null || key === 'Enter') {
        return add_tag(id, document.getElementById('tag' + id).value);
    }
    return false;
}

function sb_read_conf(title, id) {
    if (confirm('Mark all [' + title + '] items as read -- are you SURE?')) {
        mark_feed_read(id);
    }
    return false;
}

function sb_del_tag_conf(tagname) {
    if (confirm('Untag all [' + tagname + '] items -- are you SURE?')) {
        delete_tag(tagname);
    }
    return false;
}

function sb_unsub_conf(title) {
    return confirm('Unsubscribe [' + title + '] -- are you SURE?');
}

function sb_mark_tag_read(tagname) {
    if (confirm('Mark all [' + tagname + '] items as read -- are you SURE?')) {
        throb();

        fetch('view-action.php', {
            'method': 'post',
            'headers': {'Content-Type': 'application/x-www-form-urlencoded'},
            'body': 'tag_read='+tagname
        }).then(function() {
            refreshlist();
        });
    }
    return false;
}

function sb_update_feed(id) {
    throb();

    document.querySelector('#f'+id+' img.feed-icon').src = "image/spinner.gif";

    fetch('feed-action.php', {
        'method': 'post',
        'headers': {'Content-Type': 'application/x-www-form-urlencoded'},
        'body': 'update_feedid='+id
    }).then(function(response) {
        unthrob();
        response.text().then(data => document.getElementById('f' + id).innerHTML = data);
    });
}

function sb_update_tag_sources(tagname) {
    throb();

    fetch('feed-action.php', {
        'method': 'post',
        'headers': {'Content-Type': 'application/x-www-form-urlencoded'},
        'body': 'update_tag_sources='+tagname
    }).then(function(response){
        refreshlist();
        response.text().then(data => eval(data));
    });
}

function sb_readall_feed(id) {
    throb();

    fetch('feed-action.php', {
        'method': 'post',
        'headers': {'Content-Type': 'application/x-www-form-urlencoded'},
        'body': 'read_feed='+id
    }).then(function(response) {
        unthrob();
        response.text().then(data => document.getElementById('f' + id).innerHTML = data);
    });
}

function sb_update_subscribed_sources() {
    throb();

    fetch('feed-action.php', {
        'method': 'post',
        'headers': {'Content-Type': 'application/x-www-form-urlencoded'},
        'body': 'update_subscribed_sources=true'
    }).then(function(response) {
        unthrob();
        response.text().then(data => eval(data));
    });
}

function view_order_set(what, feed, order) {
    throb();

    fetch('view-action.php', {
        'method': 'post',
        'headers': {'Content-Type': 'application/x-www-form-urlencoded'},
        'body': 'view_order='+order+'&view_feed='+feed+'&view_what='+what
    }).then(function(response) {
        unthrob();
        response.text().then(data => document.getElementById('view-settings-button').innerHTML = data);
    });
}

function embed_youtube ( element ) {
    const iframe = document.createElement("iframe");

    iframe.setAttribute("src", "https://www.youtube.com/embed/" + element.dataset.ytid + "?autoplay=1&rel=0");
    iframe.setAttribute("frameborder", "0");
    iframe.setAttribute("width", "560");
    iframe.setAttribute("height", "315");
    iframe.setAttribute("allowfullscreen", "1");

    element.parentNode.replaceChild(iframe, element);
}

function embed_vimeo ( element ) {
    const iframe = document.createElement("iframe");

    iframe.setAttribute("src", "https://player.vimeo.com/video/" + element.dataset.vmid);
    iframe.setAttribute("frameborder", "0");
    iframe.setAttribute("width", "560");
    iframe.setAttribute("height", "315");
    iframe.setAttribute("allowfullscreen", "1");

    element.parentNode.replaceChild(iframe, element);
}

window.onload = function() {
    document.querySelectorAll('.item .body video').forEach(video => {
        let source = '';

        if (video.src) {
            source = video.src;
        } else {
            source = video.querySelector('source').src;
        }

        // tumblr feature: try full video size
        source = source.replace(/\/[0-9]+$/, '');

        let html = video.outerHTML;

        html += '<br/>🎞 <a href="'+source+'" class="video-source">Video source</a>';

        video.outerHTML = '<div class="video-wrap">'+html+'</div>';
    });
};


/**
 * HELPER FUNCTIONS
 */

/**
 * iterate over a NodeList
 * @param {Element} item
 * @param {string} selector
 * @param {function} func
 */
function applySelector(item, selector, func) {
    const items = item.querySelectorAll(selector);
    for (let i = 0; i < items.length; i++) {
        func(items[i]);
    }
}

/**
 * take a JSON object and reduce it to a query string
 * {"var1":"val1", "var2":"val2"} => "var1=val1&var2=val2"
 * @param {JSON} obj JSON object
 * @returns {string}
 */
function queryStringFromJSON(obj) {
    return Object.keys(obj).map(key => encodeURIComponent(key) + '=' + encodeURIComponent(obj[key])).join('&');
}

/**
 * get javascript code blocks from a string
 * @param str response string
 * @returns {string[]}
 */
function extractScripts(str) {
    const scriptRegEx = '<script[^>]*>([\\S\\s]*?)<\/script\\s*>';

    let matchAll = new RegExp(scriptRegEx, 'img'),
        matchOne = new RegExp(scriptRegEx, 'im');

    return (str.match(matchAll) || []).map(function(scriptTag) {
        return (scriptTag.match(matchOne) || ['', ''])[1];
    });
}

/**
 * evaluate javascript included in a string
 * @param str response string
 */
function evalScripts(str) {
    return extractScripts(str).map(function(script) { return eval(script); });
}

/**
 * javascript iterator
 * taken from Aristotle Pagaltzis @ http://plasmasturm.org/log/311/
 * @param iterable
 * @returns {function(): *}
 */
function iterate(iterable) {
    let i = -1;
    const getter = function() {
        return i < 0 ? null : i < iterable.length ? iterable[i] : null;
    };
    return function() {
        return ++i < iterable.length ? getter : null
    };
}