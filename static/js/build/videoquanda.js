(function e(t,n,r){function s(o,u){if(!n[o]){if(!t[o]){var a=typeof require=="function"&&require;if(!u&&a)return a(o,!0);if(i)return i(o,!0);var f=new Error("Cannot find module '"+o+"'");throw f.code="MODULE_NOT_FOUND",f}var l=n[o]={exports:{}};t[o][0].call(l.exports,function(e){var n=t[o][1][e];return s(n?n:e)},l,l.exports,e,t,n,r)}return n[o].exports}var i=typeof require=="function"&&require;for(var o=0;o<r.length;o++)s(r[o]);return s})({1:[function(require,module,exports){
'use strict';

/**
 * Videoquanda ideally needs to be rewritten.
 * Preferably in React, along with some Jasmine specs.
 * As part of which, the circular reference between videoPlayer and videoQuanda would be removed.
 */

function _interopRequireDefault(obj) { return obj && obj.__esModule ? obj : { 'default': obj }; }

var _video = require('./video');

var _video2 = _interopRequireDefault(_video);

var _videoquanda = require('./videoquanda');

var _videoquanda2 = _interopRequireDefault(_videoquanda);

_video2['default'].videoQuanda = _videoquanda2['default'];
_videoquanda2['default'].videoPlayer = _video2['default'];

},{"./video":3,"./videoquanda":4}],2:[function(require,module,exports){
'use strict';

/**
 * @returns {number} (current timestamp in seconds)
 */
Object.defineProperty(exports, '__esModule', {
  value: true
});
exports.time = time;
exports.lineBreakToTag = lineBreakToTag;
exports.tagToLineBreak = tagToLineBreak;
exports.imgTagToSrcAttr = imgTagToSrcAttr;
exports.removeAllTags = removeAllTags;

function time() {
  return Math.round(new Date() / 1e3);
}

/**
 * convert line breaks to a <br/> tag
 * @param {string} s
 * @returns {string}
 */

function lineBreakToTag(s) {
  return s.replace(/(\r\n|\n\r|\r|\n)/g, '<br/>');
}

/**
 * convert <br/> tags to a line break
 * @param {string} s
 * @returns {string}
 */

function tagToLineBreak(s) {
  return s.replace(/<br\/?>/ig, '\r\n');
}

/**
 * extract 'src' attribute from 'img' tag
 * @param {string} s
 * @returns {string}
 */

function imgTagToSrcAttr(s) {
  return s.replace(/<img([^>]+)src="(.*?)"([^>]*)>/ig, '$2');
}

/**
 * removes all tags
 * @param {string} s
 * @returns {string}
 */

function removeAllTags(s) {
  return s.replace(/(<([^>]+)>)/ig, '');
}

},{}],3:[function(require,module,exports){
'use strict';

Object.defineProperty(exports, '__esModule', {
    value: true
});
var videoPlayer = {

    videoElement: $('#jquery_jplayer_1'),
    // Object to store video files
    files: {},

    init: function init() {
        if (this.videoElement.length !== 0) {

            var videoElement = this.videoElement;

            // Find files
            $(videoElement).find('.source').each(function () {
                var type = $(this).data('video-type');
                // jPlayer accepts an MP4 only as M4V
                if (type === 'mp4') {
                    type = 'm4v';
                }
                // Add file to files object
                videoPlayer.files[type] = $(this).attr('data-src');
            });

            if (Object.keys(this.files).length !== 0) {
                // jPlayer wants to know what files types are supplied.
                var supplied = Object.keys(this.files).join(',');
            }

            // Init video player
            videoElement.jPlayer({

                // Gets called when video has ended
                ended: function ended() {},

                error: function error(e) {
                    // Add notification span if there is none yet
                    if (!videoElement.find('#notification').length) {
                        videoElement.append('<span id="notification"/>');
                    }

                    // If there is a video the browser can play remove any error message (Firefox shows an error even though it has a video to play)
                    if (e.jPlayer.status.srcSet !== true) {
                        videoElement.find('#notification').text('Error [' + e.jPlayer.error.type + ']: ' + e.jPlayer.error.message);
                    }
                },

                loadedmetadata: function loadedmetadata() {
                    // Set width and height
                    var width = $('#jp_container_1').width(); // Max width of video container
                    var height = $('video').get(0).videoHeight / ($('video').get(0).videoWidth / width); // Set height according to width (scale up / down)
                    $(this).jPlayer('option', 'size', {
                        width: width,
                        height: height
                    });
                },

                // Gets called when the video is paused
                pause: function pause() {},

                // Gets called when video starts to play
                play: function play() {},

                ready: function ready() {
                    $(this).jPlayer('setMedia', videoPlayer.files);
                    //videoPlayer.play();
                },

                // Update current playhead time
                timeupdate: function timeupdate(e) {
                    if (e.jPlayer.status.readyState !== 0) {
                        // Hide any notifications
                        videoElement.find('#notification').hide();

                        if (typeof videoPlayer.videoQuanda !== 'undefined') {
                            videoPlayer.videoQuanda.timeUpdate();
                        }
                    }
                },

                preload: 'auto',
                solution: 'html, flash',
                // Supplied file types
                supplied: supplied,
                // For browser that don't support HTML5 video this is a fallback on Flash player
                swfPath: '/mod/videoquanda/static/js/'

            });
        }
    },

    // Pause video
    pause: function pause(seconds) {
        this.videoElement.jPlayer('pause', seconds);
    },

    // Play video
    play: function play(seconds) {
        this.videoElement.jPlayer('play', seconds);
    },

    // Function to use jPlayers convertTime
    convertTime: function convertTime(seconds) {
        return $.jPlayer.convertTime(seconds);
    },

    // Get current play time
    currentTime: function currentTime() {
        if (typeof this.videoElement.data().jPlayer !== 'undefined') {
            return this.videoElement.data().jPlayer.status.currentTime;
        }
    },

    status: function status() {
        return this.videoElement.data().jPlayer.status;
    }

};

videoPlayer.init();
exports['default'] = videoPlayer;
module.exports = exports['default'];

},{}],4:[function(require,module,exports){
'use strict';

Object.defineProperty(exports, '__esModule', {
    value: true
});

function _interopRequireWildcard(obj) { if (obj && obj.__esModule) { return obj; } else { var newObj = {}; if (obj != null) { for (var key in obj) { if (Object.prototype.hasOwnProperty.call(obj, key)) newObj[key] = obj[key]; } } newObj['default'] = obj; return newObj; } }

var _lib = require('./lib');

var lib = _interopRequireWildcard(_lib);

// Todo: Remove / change output of errors / notifications etc.
// Todo: Add date to answers?

var videoQuanda = {

    init: function init() {
        this.requestAndUpdateQuestionsAndAnswers();
    },

    log: function log() {
        if ("development" !== 'production') {
            console.info('INTO: ', arguments);
        }
    },

    /**
     * adds an HTML template to display the given answer
     * @param {object} answer Values to populate the question element
     */
    addAnswerToDOM: function addAnswerToDOM(answer) {
        // Check all keys are present
        var keys = Object.keys(answer);
        if (typeof keys === 'undefined' || typeof keys !== 'undefined' && keys.join(',') !== 'id,questionid,userid,timecreated,timemodified,text,username') {
            return this.log('Data is empty or not valid.');
        }

        // convert line breaks to <br/> tags
        answer.text = lib.lineBreakToTag(answer.text);

        // Check if this question has already been loaded
        if ($('#answer-' + answer.id).length !== 0) {
            $('#answer-' + answer.id).find('.answer-text').html(answer.text).end().addClass('updated').find('.answer-post-date').text('| ' + answer.timemodified);
            return this.log('Answer %s has already been loaded!', answer.id);
        }

        // Clone element, add data and attributes and fill elements within
        var element = this.answerTemplate.clone().attr('id', 'answer-' + answer.id).data(answer).find('.answer-text').html(answer.text).end().find('.answer-author').text(answer.username).end().find('.answer-post-date').text('| ' + answer.timemodified).end().insertAfter($('#right-question-' + answer.questionid + ' .answers .answer-header')).slideDown('slow');

        // Check for capabilities and add manage buttons to right column question only
        if ($('#is-guest').val() !== '1') {
            if ($('#can-manage').val() === '1' || $('#userid').val() === answer.userid) {
                videoQuanda.log('You can manage answer %s', answer.id);
                videoQuanda.manageButtons.clone().insertBefore(element.find('.edit-input'));
            }
        }

        element.closest('.answers').show();
    },

    /**
     * Adds an HTML template to display the given question
     * @param {object} question Values to populate the question element
     */
    addQuestionToDOM: function addQuestionToDOM(question) {
        // Check all keys are present
        var keys = Object.keys(question);
        if (typeof keys === 'undefined' || typeof keys !== 'undefined' && !keys.join(',').match('id,userid,timecreated,timemodified,seconds,text,username')) {
            return this.log('Data is empty or not valid.', keys.join(','));
        }

        // convert line breaks to <br/> tags
        question.text = lib.lineBreakToTag(question.text);

        // Create snippet for left column question
        var shorttext = question.text.length > 120 ? question.text.substring(0, 120) + '...' : question.text;

        // Just updating
        if ($('#left-question-' + question.id).length === 1) {
            $('#left-question-' + question.id).find('.question-text').html(shorttext).end().addClass('updated');
            $('#right-question-' + question.id).find('.question-text').html(question.text).end().addClass('updated').find('.question-post-date').text(question.timemodified);

            return this.log('Question %s has already been loaded. Only updating.', question.id);
        }

        // Questions end up in two columns
        $(['left', 'right']).each(function (i, columnname) {
            var column = $('.' + columnname).find('.questions'),

            // Clone template and add a click handler to it.
            element = ('.' + columnname === '.left' ? videoQuanda.questionTemplateSmall : videoQuanda.questionTemplateBig).clone().attr({
                id: columnname + '-question-' + question.id,
                seconds: +question.seconds
            })
            // Add data to sort and to know what question id it is in order to load the correct answers on click
            .data(question),
                convertedTime = typeof videoQuanda.videoPlayer !== "undefined" ? videoQuanda.videoPlayer.convertTime(+question.seconds) : +question.seconds;

            // Add data to visible elements
            element.find('.question-text').html(columnname === 'left' ? shorttext : question.text).end().find('.question-author').text(question.username).end().find('.question-post-date').text(question.timemodified).end().find('.seconds').text(convertedTime);

            // Check for capabilities and add manage buttons to right column question only
            if ($('#is-guest').val() !== '1') {
                if ($('#can-manage').val() === '1' || $('#userid').val() === question.userid) {
                    videoQuanda.log('You can manage question %s', question.id);
                    videoQuanda.manageButtons.clone().prependTo(element.find('.question-content'));
                }
            }

            // If this is the first question being added to the DOM.
            if (column.find('.question').length === 0) {
                element.appendTo(column);
            } else {
                // Otherwise work out if it should be inserted before or after another question

                // Search closest related element by seconds (to order the elements by seconds)
                var closest = column.find('.question').filter(function () {
                    return $(this).data('seconds') >= +question.seconds;
                });

                // Check if the amount of seconds of this question are higher or lower then the closest found
                // If smaller this question should be inserted before, otherwise after.
                var l = closest.length;
                if (l) {
                    closest = l > 1 ? columnname === 'left' ? closest[0] : closest[l - 1] : closest.get(0);
                    // Insert element before or after closest found question
                    element[columnname === 'left' ? 'insertBefore' : 'insertAfter'](closest);
                } else {
                    // Left column has questions descending
                    if (columnname === 'left') {
                        element.insertAfter(column.find('.question:last-child'));

                        // Right column has them ascending
                    } else if (columnname === 'right') {
                            element.prependTo(column);
                        }
                }
            }

            // Question in the left column should always be visible,
            // question in the right column should appear when video time is on the same second
            if (columnname === 'left' || videoQuanda.seconds === +question.seconds || videoQuanda.revealAllClicked === true) {
                element.show();
            }
        });
    },

    /**
     * Delete question or answer
     * @param {object} e
     */
    requestDeleteQuestionOrAnswer: function requestDeleteQuestionOrAnswer(e) {
        var target = $(e.target),
            route = target.closest('.question').data('id'),
            type = target.closest('.answer').length === 1 ? 'answer' : 'question';

        if (type === 'answer') {
            route += '/answers/' + target.closest('.answer').data('id');
        }

        if (!confirm(M.util.get_string('confirm_delete_' + (type === 'answer' ? 'answer' : 'question'), this.plugin))) {
            return this.log('User chose not to delete.');
        }

        videoQuanda.sendDataToApi('DELETE', null, route, function () {
            var id = type + '-' + target.closest('.' + type).data('id');
            $('[id$="' + id + '"]').fadeOut('slow', function () {
                $(this).remove();
                videoQuanda.updateDOMElements();
            });
        });
    },

    /**
     * Send a GET request and update the DOM with new questions (or update the current questions)
     * @param {number} seconds If not undefined search only questions with given seconds
     */
    requestAndUpdateQuestionsAndAnswers: function requestAndUpdateQuestionsAndAnswers(seconds) {
        if (typeof seconds !== 'undefined' && typeof seconds !== 'number') {
            return this.log(seconds + ' is a wrong value for seconds parameter');
        }

        this.requestDataFromApi(seconds, function (questions) {
            if (typeof questions !== 'object') {
                return;
            }

            $.each(questions, function (index, question) {
                videoQuanda.addQuestionToDOM(question);

                // If question has answers
                if (question.answers.length) {
                    $.each(question.answers, function (_index, answer) {
                        videoQuanda.addAnswerToDOM(answer);
                    });
                }
            });

            // Remove deleted question and answers
            $('.question:not(.updated), .answer:not(.updated)').fadeOut('slow', function () {
                $(this).remove();
            });
            $('.question.updated, .answer.updated').removeClass('updated');

            videoQuanda.updateDOMElements();

            setTimeout(function () {
                videoQuanda.requestAndUpdateQuestionsAndAnswers();
            }, 10000);
        });
    },

    /**
     * Get data from REST Api and display it
     * @param {number|string} route What route should be used (see REST Api for possible routes)
     * @param {function} success_callback callback function after success
     * @param {function} error_callback callback function after error
     */
    requestDataFromApi: function requestDataFromApi(route, success_callback, error_callback) {
        if (!this.instanceId) {
            return this.log('Abort: No instanceId found.');
        }

        // Covert 'route' variable
        if (typeof route === 'number') {
            route = route.toString();
        }
        if (typeof route === 'string' && route !== '') {
            route = '/' + route.replace(/^\//, '');
        } else {
            route = '';
        }

        this.timeUpdated = lib.time();

        $.ajax({
            url: this.baseApiRoute + this.instanceId + '/questions' + route,
            method: 'GET',
            dataType: 'JSON',
            cache: false,
            success: function success(data) {
                if (typeof success_callback !== 'undefined' && typeof success_callback === 'function') {
                    success_callback(data);
                }
            },
            error: function error(data) {
                if (data.status === 405 && typeof data.responseText !== 'undefined') {
                    var responseText = JSON.parse(data.responseText);
                    return alert(responseText.message);
                }

                if (typeof error_callback !== 'undefined') {
                    error_callback('Error: ', data);
                }
            }
        });
    },

    /**
     * Send data to REST Api
     * @param {string} method POST, PUT, DELETE
     * @param {object} data will be converted to JSON string
     * @param {number|string} route What route should be used (see REST Api for possible routes)
     * @param {function} success_callback callback function after success
     * @param {function} error_callback callback function after error
     */
    sendDataToApi: function sendDataToApi(method, data, route, success_callback, error_callback) {
        if (!method || !method.match(/^(POST|PUT|DELETE)$/)) {
            return this.log('Abort: No or wrong method "' + method + '"');
        }

        if (!this.instanceId) {
            return this.log('Abort: No instanceId found.');
        }

        // Covert 'route' variable
        if (typeof route === 'number') {
            route = route.toString();
        }
        if (typeof route === 'string' && route !== '') {
            route = '/' + route.replace(/^\//, '');
        } else {
            route = '';
        }

        // Abort previous calls
        if (this.ajaxSendCall !== null) {
            this.ajaxSendCall.abort();
            this.log('Aborting send request. call: ', this.ajaxSendCall);
        }

        this.ajaxSendCall = $.ajax({
            url: this.baseApiRoute + this.instanceId + '/questions' + route,
            type: method,
            data: JSON.stringify(data || {}),
            dataType: 'JSON',
            cache: false,
            success: function success(d) {
                if (typeof success_callback !== 'undefined' && typeof success_callback === 'function') {
                    success_callback(d);
                }
            },
            error: function error(d) {
                if (d.status === 405 && typeof d.responseText !== 'undefined') {
                    var responseText = JSON.parse(d.responseText);
                    return alert(responseText.message);
                }

                if (typeof error_callback !== 'undefined') {
                    error_callback('Error: ', d);
                }
            }
        });
    },

    /**
     * Scroll to the active question
     */
    scrollToActiveQuestion: function scrollToActiveQuestion() {
        // Scroll to the question
        var scrollTop = $('.right .questions .question.active').position().top + $('.right .questions').scrollTop();
        $('.right .questions').stop().animate({
            scrollTop: scrollTop
        });
    },

    /**
     * gets called when video sends a time update during playback
     */
    timeUpdate: function timeUpdate() {
        if (typeof videoQuanda.videoPlayer !== "undefined") {
            this.currentVideoTime = Math.floor(videoQuanda.videoPlayer.currentTime());

            // Update seconds, convert the seconds to something readable and send update request
            this.seconds = this.currentVideoTime;
            this.convertedSeconds = videoQuanda.videoPlayer.convertTime(videoQuanda.videoPlayer.currentTime());
        }

        // Highlight question referenced to the current second
        if ($('.question[seconds="' + this.seconds + '"]').length !== 0) {
            $('.question').removeClass('active');
            // Add class only to question that is relevant to current second in the video
            $('.left .question[seconds="' + this.seconds + '"]:last, .right .question[seconds="' + this.seconds + '"]:first').addClass('active');
            $('.left, .right').find('.question[seconds="' + this.seconds + '"]').slideDown('slow', function () {
                videoQuanda.scrollToActiveQuestion();
            });
        }
    },

    /**
     * updates DOM elements
     */
    updateDOMElements: function updateDOMElements() {
        var questioncount = $('.left .question').length;

        // Remove message 'no questions posted yet'
        $('#no-questions-message')[questioncount >= 1 ? 'fadeOut' : 'fadeIn']();

        // Show button to reveal all questions
        $('#load-all-questions')[questioncount > 1 ? 'fadeIn' : 'fadeOut']();

        $('.right .question .answers').each(function () {
            // Updating amount of answers in question in left column
            var noOfAnswers = $(this).find('.answer').length,
                noOfAnswersText = noOfAnswers + ' ' + M.util.get_string(noOfAnswers === 1 ? 'answer' : 'answers', videoQuanda.plugin);

            $('#left-question-' + $(this).closest('.question').data('id') + ' .question-answer-count').text('(' + noOfAnswersText + ')');
        });
    },

    /**
     * Posts question or answer to REST Api
     * @param {object} e event
     * @param {string} method
     */
    validateAndSubmitInput: function validateAndSubmitInput(e, method) {
        // Question or answer
        var target = $(e.target),
            type = target.is('.submit-question') ? 'question' : 'answer',
            text = $.trim(target.siblings('textarea').val()),
            answerId = target.closest('.answer').data('id'),
            questionId = target.closest('.question').data('id'),
            route = '',
            data = {
            text: text
        };

        // Check if there is a proper question / answer
        if (text.length < 10) {
            target.siblings('textarea').focus();
            return alert(M.util.get_string('notify_empty_' + type, this.plugin));
        }

        // Updating question or answer
        if (method === 'PUT') {
            type = target.is('.edit-question') ? 'question' : 'answer';
            route = questionId;
        }

        // For an answer add the questionId to the route
        if (type === 'answer') {
            route = questionId + '/answers';

            if (typeof answerId !== 'undefined' && method === 'PUT') {
                route += '/' + answerId;
            }
        }

        // Add seconds to data to be send to RESTful Api only for question and on post.
        if (method === 'POST' && type === 'question') {
            data.seconds = this.seconds;
        }

        // Disable button to prevent multiple posts.
        target.prop('disabled', 'disabled');

        this.sendDataToApi(method, data, route, function () {
            // Add questions to .questions / answers to .answers
            videoQuanda.requestAndUpdateQuestionsAndAnswers();

            $('.textarea-' + type).val('');
            target.removeProp('disabled');

            if (method === 'PUT') {
                $('.edit-input').hide();
                $('.text').show();
                $('.author').show();
            }

            target.siblings('textarea').blur();

            // Put video back in to play if the video has only been paused when user focused on textarea
            if (videoQuanda.wasPaused === false) {
                videoQuanda.videoPlayer.play();
            }
        });
    },

    ajaxSendCall: null,
    answerTemplate: jQuery('.answers .answer').detach(),
    baseApiRoute: M.cfg.wwwroot + '/videoquanda/api/v1/',
    convertedSeconds: '00:00', // Converted seconds, e.g.: 00:10
    currentVideoTime: -1,
    instanceId: $('#instanceid').val(),
    manageButtons: $('.right .question .manage-buttons').detach(),
    plugin: 'mod_videoquanda', // Name for the plugin
    questionTemplateSmall: $('.left .questions .question').detach(),
    questionTemplateBig: $('.right .questions .question').detach(),
    revealAllClicked: false,
    seconds: 0, // Actual seconds
    timeUpdated: 0, // Timestamp to check for newly added question / answers when an AJAX call is made
    to: null,
    wasPaused: false // Check if video was paused by user (to resume video when question / answer is posted)
};

// Function to post question or answer
$(document).on('click', '.submit-question, .submit-answer', function (e) {
    videoQuanda.validateAndSubmitInput(e, 'POST');
});

// Function to change question or answer
$(document).on('click', '.edit-question, .edit-answer', function (e) {
    videoQuanda.validateAndSubmitInput(e, 'PUT');
});

// Function to open the edit panel
$(document).on('click', 'button.edit-button', function () {
    var type = $(this).closest('.answer').length !== 1 ? 'question' : 'answer',
        closest = type === 'question' ? $(this).closest('.question-content') : $(this).closest('.answer-content'),
        text = lib.removeAllTags(lib.imgTagToSrcAttr(lib.tagToLineBreak(closest.find('.text').html())));

    $('.edit-input').hide();
    $('.text, .answer-author').show();

    closest.find('.text, .answer-author').toggle().end().find('.edit-input').toggle().find('textarea').focus().val(text);
});

// Function to delete question or answer
$(document).on('click', 'button.delete-button', function (e) {
    videoQuanda.requestDeleteQuestionOrAnswer(e);
});

// Pause the video on given second when clicked on .question in left column or .seconds in the right column
$(document).on('click', '.left .question, .right .question .seconds', function () {
    var seconds = 0 + ($(this).is('.question') ? $(this) : $(this).closest('.question')).data('seconds');
    $('.question').removeClass('active');

    var questionid = $(this).closest('.question').data('id');
    $('[id$="question-' + questionid + '"]').addClass('active');
    videoQuanda.scrollToActiveQuestion();

    if (typeof videoQuanda.videoPlayer !== "undefined") {
        videoQuanda.videoPlayer.pause(seconds);

        // scroll to top of video of video not fully in view.
        if ($('body').scrollTop() > $('#videoquanda-activity').position().top) {
            $('body').stop().animate({
                scrollTop: $('#videoquanda-activity').position().top
            });
        }
    }
});

// Making all question in right column visible at once.
$(document).on('click', '#load-all-questions a', function () {
    $('.right .question').slideDown('slow');
    videoQuanda.revealAllClicked = true;
});

// On focus of one of the textarea's (#question or #answer) pause the video
// and show a text message with the amount of seconds in the video below the textarea (if focused textarea is question)
$(document).on('focus', 'textarea', function () {
    if (typeof videoQuanda.videoPlayer !== "undefined") {
        videoQuanda.wasPaused = videoQuanda.videoPlayer.status().paused;
        videoQuanda.videoPlayer.pause();
    }

    // If text area is #question then show text to inform at what time the question will appear
    if ($(this).is('.textarea-question')) {
        $('#question-time').show().find('.seconds').text(videoQuanda.convertedSeconds);
    }
}).on('blur', 'textarea', function () {
    if (typeof videoQuanda.videoPlayer !== 'undefined' && videoQuanda.wasPaused === false) {
        videoQuanda.videoPlayer.play();
    }
});

videoQuanda.init();
exports['default'] = videoQuanda;
module.exports = exports['default'];

},{"./lib":2}]},{},[1]);
