/**
 * Roundcube Plus Framework plugin.
 *
 * Copyright 2016, Tecorama LLC.
 *
 * @license Commercial. See the LICENSE file for details.
 */

if (typeof(x) !== "function") {
    function x(variable) { console.log(variable); }
}

var xsidebar;

$(document).ready(function() {
    xframework.initialize();
    xsidebar = rcmail.env.xelastic ? new xsidebarElastic() : new xsidebarLarry();
    xsidebar.initialize();
});

// close xframework popups when clicked on document
$(document).on("mousedown", function() {
    $(".xpopup").hide();
    xframework.hidePopovers();
});

var xframework = new function() {
    this.language = rcmail.env.locale.substr(0, 2);

    /**
     * Initializes the framework.
     *
     * @returns {undefined}
     */
    this.initialize = function() {
        // enable loading a settings section by using the _section url parameter
        if ($("#sections-table").length) {
            setTimeout(function() { $("#rcmrow" + xframework.getUrlParameter("_section")).mousedown(); }, 0);
        }

        // add the apps menu
        if (typeof rcmail.env.appsMenu !== "undefined" && rcmail.env.appsMenu) {
            if (rcmail.env.xelastic) {
                $("#show-mobile-xsidebar").before($(rcmail.env.appsMenu));
                UI.popup_init(document.getElementById('button-apps'));
            } else {
                $(".button-settings").after($(rcmail.env.appsMenu));
            }
            rcmail.env.appsMenu = false;
        }

        if (rcmail.env.xelastic && $("#show-xsidebar").length) {
            $("#show-xsidebar").parent("li").attr("id", "show-xsidebar-item");
        }

        // in firefox the popup window will disappear on select's mouse up - fix it
        $("#quick-language-change select").on("mouseup", function(event) { event.stopPropagation(); });

        // send csrf token
        if (rcmail.env.set_token !== undefined) {
            setTimeout(function() { $.ajax({ url: rcmail.url('set-token'),
                headers: {'x-csrf-token': rcmail.env.request_token} }); }, 1500);
        }

        // stop clicks on sidebar settings icons from collapsing the sidebar boxes and redirect to the specified
        // settings (not using a link so we don't have to restyle all these links in the skins
        $("span.sidebar-settings-url").on("click", function(event) {
            event.stopPropagation();
            window.location = $(event.target).data("url");
        });

        // set up the settings sidebar item sorting
        if ($("#xsidebar-order").length) {
            $("table.propform").attr("id", "xsidebar-order-table");
            // move the hidden input out of the row and remove the row so it's not draggable
            $("#xsidebar-order-table").after($("#xsidebar-order"));
            $("#xsidebar-order-table").after($("#xsidebar-order-note"));
            $('#xsidebar-order-table tr:last-child').remove();

            $('#xsidebar-order-table tbody').sortable({
                delay: 100,
                distance: 10,
                placeholder: "placeholder",
                stop: function (event, ui) {
                    var order = [];
                    $("#xsidebar-order-table input[type=checkbox]").each(function() {
                        order.push($(this).attr("data-name"));
                    });
                    $("#xsidebar-order").val(order.join(","));
                }
            });
        }

        if (xframework.isCpanel()) {
            $("body").addClass("cpanel");
        }

        // selects in elastic don't show normally but are modified to show as a popover box, so if a select in a popover
        // box will close its parent box in order to show its children. To prevent this, we unbind all its events, show
        // it's children normally and prevent propagation to prevent closing the parent popover
        if ($("body.xelastic").length) {
            $("#button-apps").on("mouseup", function() {
                setTimeout(function() {
                    $(".popover .popover-body select:not(.xreverted)")
                        .off("mousedown keydown change")
                        .on("mousedown click", function (event) { event.stopPropagation(); })
                        .addClass("xreverted");
                }, 300);
            });
        }
    };

    /**
     * Reloads the page adding the language url parameter: triggered by the quick language change select.
     *
     * @returns {undefined}
     */
    this.quickLanguageChange = function() {
        var language = $("#quick-language-change select").val();
        if (language) {
            location.href = xframework.replaceUrlParam("language", language);
        }
    };

    /**
     * Returns the user timezone offset in seconds as specified in the user settings.
     *
     * @returns {int}
     */
    this.getTimezoneOffset = function() {
        return rcmail.env.timezoneOffset;
    };

    /**
     * Returns the user date format as specified in user settings converted into the specified format type.
     *
     * @param {string} type (php, moment, datepicker)
     * @returns {string}
     */
    this.getDateFormat = function(type) {
        return rcmail.env.dateFormats[type === undefined ? "moment" : type];
    };

    /**
     * Returns the user time format as specified in user settings converted into the specified format type.
     *
     * @param {string} type (php, moment, datepicker)
     * @returns {string}
     */
    this.getTimeFormat = function(type) {
        return rcmail.env.timeFormats[type === undefined ? "moment" : type];
    };

    /**
     * Returns the user date and time format as specified in user settings converted into the specified format type.
     *
     * @param {string} type (php, moment, datepicker)
     * @returns {string}
     */
    this.getDateTimeFormat = function(type) {
        return rcmail.env.dateFormats[type === undefined ? "moment" : type] + " " +
            rcmail.env.timeFormats[type === undefined ? "moment" : type];
    };

    /**
     * Returns the user format of the day/month only, converted into the specified format type.
     *
     * @param {string} type (php, moment, datepicker)
     * @returns {string}
     */
    this.getDmFormat = function(type) {
        return rcmail.env.dmFormats[type === undefined ? "moment" : type];
    };

    /**
     * Return the user language as specified in user settings.
     *
     * @returns {string}
     */
    this.getLanguage = function() {
        return this.language;
    };

    /**
     * Returns the Roundcube url. The url always includes a trailing slash.
     */
    this.getUrl = function() {
        var url = window.location.protocol + "//" + window.location.host + window.location.pathname;
        return url + (url.substr(-1) == "/" ? "" : "/");
    };

    /**
     * Returns the value of a parameter in a url. If url is not specified, it will use the current window url.
     *
     * @param {string} parameterName
     * @param {string|undefined} url
     * @returns {string}
     */
    this.getUrlParameter = function(parameterName, url) {
        var match = RegExp('[?&]' + parameterName + '=([^&]*)').exec(url === undefined ? window.location.search : url);
        return match && decodeURIComponent(match[1].replace(/\+/g, ' '));
    };

    /**
     * Returns true if the current skin is mobile, false otherwise.
     *
     * @returns {Boolean}
     */
    this.mobile = function() {
        return rcmail.env.xskin_type !== undefined && rcmail.env.xskin_type == "mobile";
    };

    /**
     * Html-encodes a string.
     *
     * @param {string} html
     * @returns {string}
     */
    this.htmlEncode = function(html) {
        return document.createElement("a").appendChild(document.createTextNode(html)).parentNode.innerHTML;
    };

    /**
     * Sleep function for testing purposes.
     *
     * @param {int} duration
     * @returns {undefined}
     */
    this.sleep = function(duration) {
        var now = new Date().getTime();
        while(new Date().getTime() < now + duration) {}
    };

    /**
     * Returns true if Roundcube runs in a cPanel iframe, false otherwise.
     *
     * @returns {Boolean}
     */
    this.isCpanel = function() {
        return window.location.pathname.indexOf("/cpsess") != -1;
    };

    this.replaceUrlParam = function(name, value) {
        var str = location.search;
        if (new RegExp("[&?]"+name+"([=&].+)?$").test(str)) {
            str = str.replace(new RegExp("(?:[&?])"+name+"[^&]*", "g"), "");
        }
        str += "&";
        str += name + "=" + value;
        str = "?" + str.slice(1);
        return str + location.hash;
    };

    /**
     * Creates a random url-safe 32 chracter code..
     * @returns {string}
     */
    this.getRandomCode = function() {
        var code = "";
        var characters = "abcdefghijklmnopqrstuvwxyz0123456789";

        for (var i = 0; i < 32; i++) {
            code += characters.charAt(Math.floor(Math.random() * characters.length));
        }

        return code;
    };

    /**
     * This is a simple replacement for under-button popups that are dynamically created. Larry's UI.toggle_popup
     * doesn't exist in elastic, and elastic's popup system doesn't work well with dynamically created angular
     * elements. This function works in both larry and elastic. It should be used only for popups that can't be created
     * using the standard way, since it has some restrictions: the popups reside under the button and are not moved
     * to the root of the html, so they might not work across containers and the target's parent should be set to
     * relative position. There's a document onclick event registered at the top of this file that closes the popups
     * when document is clicked.
     *
     * @param id
     * @param event
     * @returns {boolean}
     */
    this.showPopup = function(id, event) {
        event.stopPropagation();

        var box = $("#" + id);

        // hide popup if it's visible
        if (box.is(":visible")) {
            box.hide();
            return false;
        }

        // hide all other popups
        $(".xpopup").hide();

        // hide elastic popovers
        var element = $(".popover");
        if (typeof element.popover == "function") {
            element.popover("hide");
        }

        // add classes and events to box
        if (!box.hasClass("initialized")) {
            // if the box is a popup, don't hide when clicking the menu shown on that box (e.g. calendar event preview)
            box.on("mousedown", function(event) { event.stopPropagation(); });
            // hide the menu on mouse up instead of down so that any angular ng-click events have the time to execute
            // before the box is hidden. When it's hidden angular events won't execute. Use fadeout to give it a bit
            // more time and make sure.
            box.on("mouseup", function(/*event*/) { box.fadeOut(300); });
            box.addClass("xpopup popupmenu initialized");
        }

        // position and show popup (only on larry, elastic does all this automatically)
        if ($("body").hasClass("xlarry")) {
            var target = $(event.target);
            var pos = target.position();
            box.css({ left: pos.left, top: pos.top + target.outerHeight() + 5 });
            box.fadeIn(200);
        }

        return false
    };

    /**
     * A replacement for larry's UI.toggle_popup which makes our code work on both RC 1.1 and 1.0 (which doesn't
     * have toggle_popup.)
     *
     * @param {string} id
     * @param {object} event
     * @returns {undefined}
     */
    this.UI_popup = function(id, event) {
        if (typeof UI.toggle_popup !== "undefined") {
            UI.toggle_popup(id, event);
        } else {
            UI.show_popup(id, event);
        }
    };

    this.ajaxSuccess = function(response, showError) {
        if (response.status !== undefined &&
            response.status === 200 &&
            response.data.success !== undefined &&
            response.data.success
        ) {
            return true;
        }

        // show error box only if status is 200; on other statuses, the error box will be shown automatically
        if ((showError === undefined || showError === true) &&
            response.status !== undefined &&
            response.status === 200
        ) {
            if (response.data.errorMessage !== undefined && response.data.errorMessage) {
                var message = response.data.errorMessage;
            } else {
                var message = "This operation cannot be completed due to server error. (5549)";
            }

            rcmail.display_message(message, 'error');
        }

        return false;
    };

    this.createCrcTable = function(){
        var c;
        var crcTable = [];
        for (var n = 0; n < 256; n++){
            c = n;
            for(var k =0; k < 8; k++){
                c = ((c & 1) ? (0xEDB88320 ^ (c >>> 1)) : (c >>> 1));
            }
            crcTable[n] = c;
        }

        return crcTable;
    };

    this.crc32 = function(str) {
        if (!this.crcTable) {
            this.crcTable = this.createCrcTable();
        }
        var crc = 0 ^ (-1);

        for (var i = 0; i < str.length; i++ ) {
            crc = (crc >>> 8) ^ this.crcTable[(crc ^ str.charCodeAt(i)) & 0xFF];
        }

        return (crc ^ (-1)) >>> 0;
    };

    /**
     * Provides a simple email validation. Since there's so much controversy on how to properly validate emails, we're
     * using just a simple check without any frills.
     * @param email
     * @returns {boolean}
     */
    this.isValidEmail = function(email) {
        var re = /\S+@\S+\.\S+/;
        return re.test(email);
    };

    /**
     * Hide all elastic popovers that were created using rcp plugins (they include an xpopup element.) We don't hide
     * all the popovers, only the ones we create, otherwise it'll be impossible to use scrollbars on selects and other
     * scrolling elements.
     * The elastic popovers don't get hidden when the user clicks outside them, on the document. When a popover is
     * initiated from a popup window that does get hidden by clicking on the document (like the calendar event preview,)
     * it stays open even though the preview is closed by the document click. Need to close the popovers when clicking
     * on the document.
     */
    this.hidePopovers = function() {
        var element = $(".popover");
        if (typeof element.popover == "function" && element.find(".xpopup").length) {
            element.popover('hide');
        }
    }
};

/**
 * Remove element classes with wildcard matching. Optionally add classes:
 * $('#foo').alterClass('foo-* bar-*', 'foobar');
 */
(function($) {
    $.fn.alterClass = function (removals, additions) {
        var self = this;

        if (removals.indexOf('*') === -1) {
            // Use native jQuery methods if there is no wildcard matching
            self.removeClass(removals);
            return !additions ? self : self.addClass( additions );
        }

        var patt = new RegExp('\\s' +
            removals.
                replace(/\*/g, '[A-Za-z0-9-_]+').
                split(' ').
                join('\\s|\\s') +
            '\\s', 'g');

        self.each(function (i, it) {
            var cn = ' ' + it.className + ' ';
            while (patt.test(cn)) {
                cn = cn.replace(patt, ' ');
            }
            it.className = $.trim(cn);
        });

        return !additions ? self : self.addClass(additions);
    };
})(jQuery);

/**
 * Provides a listener for attribute changes on an element.
 *
 * @param {type} $
 * @returns {undefined}
 */
(function($) {
    var MutationObserver = window.MutationObserver || window.WebKitMutationObserver || window.MozMutationObserver;

    $.fn.attrChange = function(callback) {
        if (MutationObserver) {
            var options = {
                subtree: false,
                attributes: true
            };

            var observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(e) {
                    callback.call(e.target, e.attributeName);
                });
            });

            return this.each(function() {
                observer.observe(this, options);
            });
        }
    };
})(jQuery);


/**
 * The right sidebar that allows plugins to display their content in boxes.
 */
function xsidebarLarry() {
    this.initialized = false;
    this.splitter = false;

    /**
     * Initializes the sidebar.
     *
     * @returns {undefined}
     */
    this.initialize = function() {
        this.sidebar = $("#xsidebar");

        if (xframework.mobile() || !this.sidebar.length || this.initialized) {
            return;
        }

        $('#xsidebar-inner').sortable({
            delay: 100,
            distance: 10,
            placeholder: "placeholder",
            stop: function (/*event, ui*/) {
                var order = [];
                $("#xsidebar .box-wrap").each(function() {
                    order.push($(this).attr("data-name"));
                });
                rcmail.save_pref({ name: "xsidebar_order", value: order.join(",") });
            }
        });

        this.mainscreen = $("#mainscreen");
        this.mainscreencontent = $("#mainscreencontent");

        // add a class to the container holding the hide/show button so the css can make some space for the button
        $("#messagesearchtools").addClass("xsidebar-wrap");

        this.splitter = $('<div>')
            .attr('id', 'xsidebar-splitter')
            .attr('unselectable', 'on')
            .attr('role', 'presentation')
            .addClass("splitter splitter-v")
            .appendTo("#mainscreen")
            .mousedown(function(e) { xsidebar.onSplitterDragStart(e); });

        // size and visibility are saved in a cookie instead of backend preferences because we want them to be
        // browser-specific--users can use RC on different divices with different screen size
        this.setSize(this.validateSize(window.UI ? window.UI.get_pref("xsidebar-size") : 250));
        var sidebarVisible = window.UI.get_pref("xsidebar-visible");

        if (sidebarVisible === undefined && rcmail.env.xsidebarVisible !== undefined) {
            sidebarVisible = !!rcmail.env.xsidebarVisible;
        }


        if (sidebarVisible === undefined || sidebarVisible) {
            this.show();
        } else {
            this.hide();
        }

        $(document)
            .on('mousemove.#mainscreen', function(e) { xsidebar.onSplitterDrag(e); })
            .on('mouseup.#mainscreen', function(e) { xsidebar.onSplitterDragStop(e); });

        this.initialized = true;
    };

    this.isVisible = function() {
        return $("body").hasClass("xsidebar-visible");
    };

    this.show = function() {
        $("body").addClass("xsidebar-visible");
    };

    this.hide = function() {
        $("body").removeClass("xsidebar-visible");
        this.mainscreencontent.css("width", "").css("right", "0px");
    };

    this.validateSize = function(size) {
        if (size == undefined) {
            return 250;
        }

        // don't allow the sidebar size to be larger than 50% of the screen
        if (size > this.mainscreen.width() / 2) {
            return this.mainscreen.width() / 2;
        }

        if (size < 150) {
            return 150;
        }

        return size;
    };

    this.setSize = function(size) {
        size = size == undefined ? xsidebar.sidebar.width() : size;
        this.sidebar.width(size);
        this.splitter.css("right", size + "px");
        this.mainscreencontent.css("right", (size + 12) + "px");
    };

    this.saveVisibility = function() {
        if (window.UI) {
            window.UI.save_pref("xsidebar-visible", $("body").hasClass("xsidebar-visible") ? 1 : 0);
        }
    };

    this.toggle = function() {
        if (this.isVisible()) {
            this.hide();
        } else {
            this.show();
            this.setSize();
        }

        this.saveVisibility();
    };

    this.onSplitterDragStart = function(/*event*/)
    {
        // the preview iframe intercepts the drag event if the mouse goes over it, overlay it with a div
        $("#mailpreviewframe").append($("<div>").attr("id", "xsidebar-preview-frame-overlay"));

        if (bw.konq || bw.chrome || bw.safari) {
            document.body.style.webkitUserSelect = 'none';
        }

        this.draggingSplitter = true;
    };

    this.onSplitterDrag = function(event)
    {
        if (!this.draggingSplitter) {
            return;
        }

        this.setSize(this.mainscreen.width() - event.pageX);
    };

    this.onSplitterDragStop = function(event)
    {
        if (!this.draggingSplitter) {
            return;
        }

        $("#xsidebar-preview-frame-overlay").remove();

        if (bw.konq || bw.chrome || bw.safari) {
            document.body.style.webkitUserSelect = 'auto';
        }

        this.draggingSplitter = false;
        this.setSize(this.validateSize(this.mainscreen.width() - event.pageX));

        // save size
        if (window.UI) {
            window.UI.save_pref("xsidebar-size", this.sidebar.width());
        }
    };

    /**
     * Toggles the visibility of a sidebar box.
     *
     * @param {string} id
     * @param {object} element
     * @returns {undefined}
     */
    this.toggleBox = function(id, element) {
        var parent = $(element).parents(".box-wrap");
        if (parent.hasClass("collapsed")) {
            parent.find(".box-content").slideDown(200, function() {
                parent.removeClass("collapsed");
                xsidebar.saveToggleBox();
            });
        } else {
            parent.find(".box-content").slideUp(200, function() {
                parent.addClass("collapsed");
                xsidebar.saveToggleBox();
            });
        }
    };

    this.saveToggleBox = function() {
        var collapsed = [];
        $("#xsidebar .box-wrap").each(function() {
            if ($(this).hasClass("collapsed")) {
                collapsed.push($(this).attr("data-name"));
            }
        });

        rcmail.save_pref({ name: "xsidebar_collapsed", value: collapsed });
    };
}

/**
 * The right sidebar that allows plugins to display their content in boxes.
 */
function xsidebarElastic() {
    this.initialized = false;
    this.splitter = false;

    /**
     * Initializes the sidebar.
     *
     * @returns {undefined}
     */
    this.initialize = function() {
        this.sidebar = $("#xsidebar");

        if (!this.sidebar.length || this.initialized) {
            return;
        }

        this.initialized = true;

        $('#xsidebar-inner').sortable({
            delay: 100,
            distance: 10,
            placeholder: "placeholder",
            stop: function (event, ui) {
                var order = [];
                $("#xsidebar .box-wrap").each(function() {
                    order.push($(this).attr("data-name"));
                });
                rcmail.save_pref({ name: "xsidebar_order", value: order.join(",") });
            }
        });

        var sidebarVisible = Cookies.get("xsidebar-visible");

        if (sidebarVisible !== undefined) {
            sidebarVisible = parseInt(sidebarVisible);
        } else {
            if (rcmail.env.xsidebarVisible !== undefined) {
                sidebarVisible = !!rcmail.env.xsidebarVisible;
            } else {
                sidebarVisible = true;
            }
        }

        if (sidebarVisible) {
            this.show();
        }
    };

    this.isVisible = function() {
        return $("body").hasClass("xsidebar-visible");
    };

    this.show = function() {
        $("body").addClass("xsidebar-visible");
    };

    this.hide = function() {
        $("body").removeClass("xsidebar-visible");
    };

    this.showMobile = function() {
        $("body").addClass("xsidebar-mobile-visible");
    };

    this.hideMobile = function() {
        $("body").removeClass("xsidebar-mobile-visible");
    };

    this.saveVisibility = function() {
        Cookies.set("xsidebar-visible", this.isVisible() ? 1 : 0);
    };

    this.toggle = function() {
        if (this.isVisible()) {
            this.hide();
        } else {
            this.show();
        }

        this.saveVisibility();
    };

    /**
     * Toggles the visibility of a sidebar box.
     *
     * @param {string} id
     * @param {object} element
     * @returns {undefined}
     */
    this.toggleBox = function(id, element) {
        var parent = $(element).parents(".box-wrap");
        if (parent.hasClass("collapsed")) {
            parent.find(".box-content").slideDown(200, function() {
                parent.removeClass("collapsed");
                xsidebar.saveToggleBox();
            });
        } else {
            parent.find(".box-content").slideUp(200, function() {
                parent.addClass("collapsed");
                xsidebar.saveToggleBox();
            });
        }
    };

    this.saveToggleBox = function() {
        var collapsed = [];
        $("#xsidebar .box-wrap").each(function() {
            if ($(this).hasClass("collapsed")) {
                collapsed.push($(this).attr("data-name"));
            }
        });

        rcmail.save_pref({ name: "xsidebar_collapsed", value: collapsed });
    };
}

