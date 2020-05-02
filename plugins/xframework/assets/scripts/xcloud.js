/**
 * Roundcube Plus Framework plugin.
 *
 * Copyright 2016, Tecorama LLC.
 *
 * @license Commercial. See the LICENSE file for details.
 */

/* global tinyMCE, rcmail, framework */

$(document).ready(function() { xcloud.init(); });

var xcloud = new function()
{
    this.init = function() {

        // when the dropdown menu on the attachment is open, save the mime id of the message it belongs to
        rcmail.addEventListener("beforemenu-open", function(p) {
            if (p.menu == "attachmentmenu") {
                // remove download items that might have been inserted to other menus
                $(".xcloud-attach-menu-container").remove();

                // iterate the cloud plugins and insert their save code to the menu
                for (var plugin in rcmail.env.xcloud_plugins) {
                    insertMenuAttachmentSaveCode(plugin, p['id']);
                }
            }
        });

        // iterate through all the cloud plugins and do the things that need to be done for each one
        for (var plugin in rcmail.env.xcloud_plugins) {
            insertImageAttachmentSaveCode(plugin);
        }

        // adjust the width of the attach buttons on the compose page so they're all the same
        var width = 0;
        var elements = $("#compose-attachments input[type=button]");
        elements.each(function() {
            if ($(this).outerWidth() > width) {
                width = $(this).outerWidth();
            }
        });
        elements.width(width);
    };



    this.selectSuccess = function(data, linkType, plugin, parameters) {
        x(linkType);
        x(data);
        x(plugin);
        x(parameters);
        if (linkType == "preview") {
            selectPreviewSuccess(data, plugin, parameters);
        } else {
            selectDirectSuccess(data, plugin, parameters);
        }
    };

    /**
     * Inserts save buttons next to the links displayed in the message body.
     */
    var insertImageAttachmentSaveCode = function(plugin) {
        if (!$("#aria-label-messageattachments").length || typeof window[plugin]["insertSaveButton"] !== "function") {
            return;
        }

        $("p.image-attachment").each(function() {
            // we're using one container for the buttons from all the cloud plugins, make sure it's there
            var container = $(this).find(".xcloud-save-button-container");
            if (!container.length) {
                container = $("<div>").addClass("xcloud-save-button-container").appendTo($(this).find("span.attachment-links"));
            }

            // get the mime id of the image
            var mimeId = $(this).find("a.image-link").first().attr("onclick").match(/\d+/)[0];

            var elementId = plugin + "-attachment-image-" + mimeId;
            $("<div>").addClass("xcloud-image-attachment-box").attr("id", elementId).appendTo(container);

            // find file name
            var name = $(this).find(".image-filename").text();

            // find download url
            var url = false;
            $(this).find("span.attachment-links > a").each(function() {
                var href = $(this).attr("href");
                if (href.indexOf("_download=1") != -1) {
                    url = href;
                }
            });

            if (!name || !url) {
                return true;
            }

            window[plugin]["insertSaveButton"](elementId, mimeId, name, url + "&download=1");
        });
    };

    var insertMenuAttachmentSaveCode = function(plugin, mimeId) {
        if (!$("#aria-label-messageattachments").length || typeof window[plugin]["insertSaveButton"] !== "function") {
            return;
        }

        var elementId = plugin + "-attachment-menu-" + mimeId;

        if ($("#" + elementId).length) {
            return;
        }

        // add menu item for this plugin
        $("<li>")
            .addClass("xcloud-attach-menu-container")
            .append($("<div>").attr("id", elementId).addClass("active"))
            .appendTo($("#attachmentmenu ul"));

        // find the name and the download url of the file
        var a = $("#attach" + mimeId + " a").first();
        if (!a.length) {
            return;
        }

        var url = a.attr("href");
        var name = "";

        // elastic
        var nameElement = a.find(".attachment-name");
        if (nameElement.length) {
            name = nameElement.text();
        } else {
            // larry
            var copy = a.clone();
            copy.find("span").remove();
            name = copy.text().trim();
        }

        if (!name || !url) {
            return;
        }

        window[plugin]["insertSaveButton"](elementId, mimeId, name, url + "&download=1");
    };

    /**
     * Inserts the selected file links to the body of the message.
     */
    var selectPreviewSuccess = function(data, plugin, parameters) {
        var html = $("input[name='_is_html']").val() == "1";
        var links = [];

        for (var key in data) {
            if (data.hasOwnProperty(key)) {
                if (html) {
                    links.push("<a class='xcloud-link " + plugin + "-link' href='" + data[key]['url'] + "'>" + data[key]['name'] + "</a>");
                } else {
                    links.push(data[key]['url']);
                }
            }
        }

        if (html) {
            tinyMCE.execCommand("mceInsertContent", false, " " + links.join(", ") + " ");
        } else {
            var element = $("#composebody");
            var value = element.val();
            element.val(
                value.substring(0, element.prop("selectionStart")) +
                "\n\n" + links.join("\n") + "\n\n" +
                value.substring(element.prop("selectionEnd"), 0)
            );
        }
    };

    /**
     * Downloads the selected files from Cloud and attaches them to the message.
     */
    var selectDirectSuccess = function(data, plugin, parameters) {
        var uploadId = new Date().getTime();

        rcmail.add2attachment_list(uploadId, {
            html : "<span>Uploading</span>",
            classname: "uploading",
            complete: false
        });

        // create the default parameters to send
        var send = { files: data, uploadId: uploadId, composeId: rcmail.env.compose_id };

        // add the parameters
        if (typeof parameters === "object") {
            for (var name in parameters) {
                send[name] = parameters[name];
            }
        }

        rcmail.http_post("plugin." + plugin + "_attach", send, rcmail.set_busy(true, 'uploading'));
    };

    /**
     * Call to backend that saves the attachment to temporary file using a randomly generated file name.
     * @param mimeId
     * @returns {{code: string, name: jQuery}}
     */
    this.saveAttachmentDeployFile = function(mimeId) {
        // generate a random attachment id code and get attachment file name
        var data = {
            code: xframework.getRandomCode(),
            name: $("#attachment-list #attach" + mimeId).find("a:not(.drop)").clone().children().remove().end().text().trim()
        };

        $.ajax({
            url: rcmail.url("SaveAttachmentDeployFile"),
            method: "POST",
            headers: { "x-csrf-token": rcmail.env.request_token },
            dataType: "json",
            //  async: false,
            data: {messageId: rcmail.env.uid, mbox: rcmail.env.mailbox, mimeId: mimeId, code: data.code}
        });

        return data;
    };

    this.removeAttachmentDeployFile = function(code) {
        $.ajax({
            url: rcmail.url("RemoveAttachmentDeployFile"),
            method: "POST",
            headers: { "x-csrf-token": rcmail.env.request_token },
            dataType: "json",
            data: {code: code}
        });
    };
};
