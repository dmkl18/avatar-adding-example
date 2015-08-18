(function($) {

    "use strict";

    function sendPostWithFormData(data, handler) {
        var request = new XMLHttpRequest(),
            defer = new $.Deferred(),
            param;
        request.open("POST", handler, true);
        request.setRequestHeader("X-Requested-With", "XMLHttpRequest");
        var formData = new FormData();
        for(param in data.params) {
            formData.append(param, data.params[param]);
        }
        request.onload = function(e) {
            var answer = JSON.parse(this.response);
            if (this.status == 200 && !answer.mistake) {
                defer.resolve(answer);
            }
            else {
                defer.reject(answer);
            }
        };
        if(data.needToShowProgress) {
            request.upload.onprogress = function (event) {
                if (event.lengthComputable) {
                    var value = (event.loaded / event.total) * 100;
                    data.progressSettings.textElement.text(Math.round(value) + ' %');
                    data.progressSettings.progressDiv.css('width', value + '%');
                }
            };
        }
        request.send(formData);
        return defer.promise();
    }

    var Avatar = function(settings) {
        this.$avatarBlock = settings.$avatarBlock;
        this.$buttonElement = this.$avatarBlock.find("." + Avatar.baseClasses.avButton);
        this.$dropElement = this.$avatarBlock.find("." + Avatar.baseClasses.avDropZone);
        this.$globalMessageBlock = settings.$globalMessage;
        this.ownerValue = this.$avatarBlock.find("." + Avatar.baseClasses.avOwner).val();

        var that  = this;

        this.$buttonElement.on('change', function(event){ that.prepareAddAvatar(event); });
        this.$dropElement.on('dragover', function(event){ that.handleDragOver(event); });
        this.$dropElement.on('dragenter', function(event){ that.handleDragOver(event); });
        this.$dropElement.on('drop', function(event){ that.prepareAddAvatar(event, true); });
        this.$globalMessageBlock.find(".close").on("click", function() { that.clearMessage(); });

        this.handler = settings.rightHandler;
        this.imagePath = settings.imagePath;
        this.maxFileSize = settings.maxFileSize;
    };

    Avatar.baseClasses = {
        avButton: "avatar-select",
        avDropZone: "avatar-drop-zone",
        avOwner: "avatar-owner",
        textDiv: "load-av",
        progressDiv: "load-av-prg",
        addSuccess: "avatar-success",
        addError: "avatar-error",
        addWarning: "avatar-warning"
    };
    Avatar.MAX_FILE_SIZE = 2*1024*1024;
    Avatar.LOAD_TEMPLATE = '<div class="' + Avatar.baseClasses.textDiv + '"><span>0 %</span><div class="' + Avatar.baseClasses.progressDiv + '"></div></div>';
    Avatar.SHOW_MESSAGE_TIME = 5000;

    Avatar.prototype.handleDragOver = function(event) {
        event.stopPropagation();
        event.preventDefault();
    };

    Avatar.prototype.prepareAddAvatar = function(event, drop) {
        if(drop)
        {
            event.stopPropagation();
            event.preventDefault();
        }
        var files = drop ? event.originalEvent.dataTransfer.files : event.target.files;
        if(files.length > 1) {
            this.createWarningMessage({
                message: "Вы пытались добавить на аватарку несколько изображений, что не логично. Было добавлено только первое"
            });
        }
        this.pic = files[0];
        if(!this.pic) {
            this.createWarningMessage({
                message: "Вами так и не был загружен файл."
            });
            return;
        }
        if(this.pic.size > this.maxFileSize) {
            this.createResultError({
                mistake: "Размер переданного Вами файла превышает предельно допустимое значение."
            });
            return;
        }
        if(this.pic.type != "image/png" && this.pic.type != "image/jpg" && this.pic.type != "image/jpeg" && this.pic.type != "image/gif") {
            this.createResultError({
                mistake: "Загружаемый файл должен быть изображением и должен быть JPG, PNG или GIF."
            });
            return;
        }
        if('FormData' in window) {
            this.addAvatar();
        }
        else {
            this.createAddAvatarButton();
        }
    };

    Avatar.prototype.addAvatar = function() {
        this.addLoad();
        if(!this.$textProgress) {
            this.$textProgress = this.$avatarBlock.find("." + Avatar.baseClasses.textDiv + " > span");
            this.$divProgress = this.$avatarBlock.find("." + Avatar.baseClasses.progressDiv);
        }
        var that = this,
            data = {
                params: {
                    material: that.ownerValue,
                },
                needToShowProgress: true,
                progressSettings: {
                    textElement: that.$textProgress,
                    progressDiv: that.$divProgress
                }
            };
        data.params[this.$buttonElement.attr('name')] = this.pic;
        var promise = sendPostWithFormData(data, this.handler);
        promise.then(that.createResultSuccess.bind(that), that.createResultError.bind(that));
    };

    Avatar.prototype.addLoad = function() {
        if(this.$buttonElement) {
            this.$buttonElement.parent().hide();
        }
        this.$dropElement.length ? this.$dropElement.append(Avatar.LOAD_TEMPLATE) : this.$avatarBlock.append(Avatar.LOAD_TEMPLATE);
    };

    Avatar.prototype.loadFailed = function() {
        if(this.$textProgress) {
            this.$textProgress.parent().remove();
        }
        this.$buttonElement.parent().show();
    };

    Avatar.prototype.prepareMessage = function() {
        if(this.isMessage) {
            this.clearMessage();
        }
    };

    Avatar.prototype.createResultSuccess = function(result) {
        if(result.mistake) {
            this.createResultError(result);
        }
        this.$buttonElement.off('change');
        this.$dropElement.off('dragover');
        this.$dropElement.off('dragenter');
        this.$dropElement.off('drop');
        this.endCreateResult('addSuccess', result.message);
        var that = this;
        this.$dropElement.html('<img src="' + that.imagePath + result.fileName + '" alt="avatar" />').css("background", "none");
    };

    Avatar.prototype.createResultError = function(result) {
        this.endCreateResult('addError', result.mistake);
    };

    Avatar.prototype.createWarningMessage = function(result) {
        this.endCreateResult('addWarning', result.message);
    };

    Avatar.prototype.endCreateResult = function(className, message) {
        var that = this;
        if(this.isMessage) {
            this.clearMessage();
        }
        console.log(this.$globalMessageBlock);
        if(this.$globalMessageBlock.length) {
            this.$globalMessageBlock.addClass(Avatar.baseClasses[className]).children("div").eq(0).append("<p>" + message + "</p>");
        }
        this.$globalMessageBlock.fadeIn(300);
        this.messageTime = setTimeout(function() { that.clearMessage(); },
            Avatar.SHOW_MESSAGE_TIME);
        this.isMessage = true;
    };

    Avatar.prototype.clearMessage = function() {
        this.$globalMessageBlock.removeClass().hide().children("div").eq(0).html("");
        clearTimeout(this.messageTime);
        this.isMessage = false;
    };

    Avatar.prototype.createAddAvatarButton = function() {
        this.$avatarBlock.find("label:first").css("display", "none");
        this.$avatarBlock.find("label:last").css("display", "block");
    };

    function addAvatar(settings) {
        var $avatarBlocks = $(this),
            $globalMessage = $("#" + settings.globalMessageBlockId),
            currentHandler;
        if($avatarBlocks.length && $globalMessage.length) {
            settings.$globalMessage = $globalMessage;
            if (!settings.maxFileSize) {
                settings.maxFileSize = Avatar.MAX_FILE_SIZE;
            }
            for (var i = 0, lh = $avatarBlocks.length; i < lh; i++) {
                if (!settings.handler) {
                    currentHandler = $avatarBlocks.eq(i).find("form").attr("action");
                }
                settings.rightHandler = currentHandler || settings.handler;
                if (!settings.rightHandler) {
                    break;
                }
                settings.$avatarBlock = $avatarBlocks.eq(i);
                new Avatar(settings);
            }
        }
    }

    $.fn.DlcAvatar = addAvatar;

}(jQuery));


$(document).ready(function(){

    $("#addAvatar").DlcAvatar({
        handler: "handlers/avatar.php",
        globalMessageBlockId: "globalMessage",
        imagePath: "images/",
    });

});