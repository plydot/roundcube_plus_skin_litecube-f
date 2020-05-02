/**
 * Roundcube Plus Skin plugin.
 *
 * Copyright 2019, Tecorama LLC.
 *
 * @license Commercial. See the LICENSE file for details.
 */

/* global rcmail, encodeURIComponent, Infinity, UI, xskin, xframework */

/**
 * Create the console.log() shortcut if it doesn't exist.
 */
if (typeof(q) != "function") {
    function q(variable) { console.log(variable); };
}

$(document).ready(function() {
    xskin.afterReady();
});

var xskin = new function()
{
    /**
     * Executed on document ready.
     */
    this.afterReady = function() {
        this.addMailboxClasses();

        if ($("body.xmaterial-design").length) {
           xskin.enableMaterialDesign();
        }

        // make the ident_switch plugin work with rcp skins
        this.enableIdentSwitch();
    };

    this.applySetting = function(element, key, container, value) {
        element = $(element);

        if (value !== undefined) {
            element.val(value);
        } else {
            if (element.is(':checkbox')) {
                value = element.is(':checked') ? "yes" : "no";
            } else {
                value = element.val();
            }
        }

        $(container).alterClass(key + "-*", key + "-" + value);
        $(container, window.parent.document).alterClass(key + "-*", key + "-" + value);
        $(".xsave-hint").fadeIn();
    };

    /**
     * Copy classes from parent html to iframe. When saving, the classes are added to iframe first and only then the
     * new settings are saved, so the iframe refreshes with old settings. Copying the classes from parent html/body
     * helps keep the iframe in sync.
     */
    this.updateIFrameClasses = function() {
        // remove all x-classes from iframe html and body
        $.each($("html").attr("class").split(/\s+/), function(index, item) {
            if (item.indexOf("x") == 0) {
                $("html").removeClass(item);
            }
        });

        $.each($("body").attr("class").split(/\s+/), function(index, item) {
            if (item.indexOf("x") == 0) {
                $("body").removeClass(item);
            }
        });

        // add x-classes from the parent html to iframe
        $.each($("html", window.parent.document).attr("class").split(/\s+/), function(index, item) {
            if (item.indexOf("x") == 0) {
                $("html").addClass(item);
            }
        });

        $.each($("body", window.parent.document).attr("class").split(/\s+/), function(index, item) {
            if (item.indexOf("x") == 0) {
                $("body").addClass(item);
            }
        });
    };

    /**
     * Reloads the page adding the skin url parameter: triggered by the quick skin change select.
     *
     * @returns {undefined}
     */
    this.changeSkin = function() {
        var skin = $("#xshortcut-skins select").val();
        if (skin) {
            location.replace('//' + location.host + location.pathname + xframework.replaceUrlParam("skin", skin));
        }
    };

    /**
     * Adds the correct classes to all mailbox tree items.
     * @returns {undefined}
     */
    this.addMailboxClasses = function() {
        var classes = ["sent", "drafts", "trash", "archive", "junk", "spam"];
        $("#mailboxlist li.mailbox a").each(function() {
            var rel = $(this).attr("rel");

            if (rel !== undefined) {
                rel = rel.toLowerCase();

                for (var i = 0; i < classes.length; i++) {
                    if (rel.indexOf(classes[i]) != -1) {
                        $(this).parent("li.mailbox").addClass(classes[i]);
                    }
                }
            }
        });
    };

    this.enableMaterialDesign = function() {
        // create the wave effect: size is the initial size of the wave span, it could be 1, 2, 3... pixels, depending
        // on the size of the container, then we append the span to the container and transform it using css to 100
        // times its original size, fading it out at the same time, then we remove the span.
        $(".listing td.name, .listing td.section, .listing li.listitem, #directorylist li.addressbook, " +
          ".listing li.mailbox a, .toolbar a.button, .xmobile #taskbar > a")
            .addClass("wave-container")
            .on("click", function(event) {
                var container = $(this);
                var size = Math.ceil(Math.max(container.outerWidth(), container.outerHeight()) * 2 / 100);
                var wave = $("<span/>")
                    .addClass("wave")
                    .css({ height: size, width: size, left: (event.offsetX) + "px", top: (event.offsetY) + "px"});
                container.append(wave);
                setTimeout(function() { wave.remove(); }, 600);
            });
    };

    /**
     * The ident_switch plugin is hard coded to only work with larry, classic, and elastic. Make it work with our skins.
     */
    this.enableIdentSwitch = function() {
        if (!rcmail.env['rcp_skin']) {
            return;
        }

        var select = $('#plugin-ident_switch-account');

        if (!select.length || typeof plugin_switchIdent_addCbElastic !== 'function') {
            return;
        }

        if (plugin_switchIdent_addCbElastic(select)) {
            select.show();
        }
    }
};
