/**
 * Roundcube Plus Skin plugin.
 *
 * Copyright 2019, Tecorama LLC.
 *
 * @license Commercial. See the LICENSE file for details.
 */

/* global xskin */

$(document).ready(function() {
    xdesktop.afterReady();
});

$(window).resize(function() {
    xdesktop.windowResize();
});

var xdesktop = new function()
{
    /**
     * Executed after the document is ready.
     *
     * @returns {undefined}
     */
    this.afterReady = function()  {
        setTimeout(function() { xdesktop.windowResize(); }, 0);

        if ($(".compact-message-list #listoptions fieldset").length) {
            $("#listoptions fieldset:first").remove();
        }

        // add blured icons to the icloud login screen: the images are large, we wait till they load before showing them
        if ($("body.skin-icloud.login-page").length) {
            var iconDiv = $("<div/>").addClass("icl-icons");
            var icount = 0;

            for (i = 1; i <= 13; i++) {
                iconDiv.append(
                    $("<img/>")
                        .addClass("icl-icon-" + i)
                        .attr("src", "skins/icloud/assets/images/icon-" + i + ".png")
                        .on("load", function() {
                            icount++;
                            if (icount >= 13) {
                                setTimeout(function() { iconDiv.fadeIn(800); }, 500);
                            }
                        })
                );
            }

            $("body").append(iconDiv);
        }

        // make the ident_switch plugin work with rcp skins
        this.enableIdentSwitch();
    };

    /**
     * Executed on window resize and after document ready.
     *
     * @returns {undefined}
     */
    this.windowResize = function() {
        // hide the filter combo and quicksearch bar if they overlay the toolbar
        var toolbar = $(".toolbar");
        if (toolbar.length) {
            var width = $(".toolbar").width() + 5;

            var element = $("#searchfilter");
            if (element.length) {
                element.css("visibility", element.offset().left < width ? "hidden" : "visible");
            }

            element = $("#quicksearchbar");
            if (element.length) {
                element.css("visibility", element.offset().left < width ? "hidden" : "visible");
            }
        }
    };

    /**
     * The ident_switch plugin is hard coded to only work with larry, classic, and elastic. Make it work with our skins.
     */
    this.enableIdentSwitch = function() {
        if (!rcmail.env['rcp_skin']) {
            return;
        }

        var select = $('#plugin-ident_switch-account');

        if (!select.length || typeof plugin_switchIdent_addCbLarry !== 'function') {
            return;
        }

        if (plugin_switchIdent_addCbLarry(select)) {
            select.show();
        }
    }
};

