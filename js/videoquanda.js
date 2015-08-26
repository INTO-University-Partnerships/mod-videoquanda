'use strict';

import * as lib from './lib';

// Todo: Remove / change output of errors / notifications etc.
// Todo: Add date to answers?

var videoQuanda = {

    init: function () {
        this.requestAndUpdateQuestionsAndAnswers();
    },

    log: function () {
        if (process.env.NODE_ENV !== 'production') {
            console.info('INTO: ', arguments);
        }
    },

    /**
     * adds an HTML template to display the given answer
     * @param {object} answer Values to populate the question element
     */
    addAnswerToDOM: function (answer) {
        // Check all keys are present
        var keys = Object.keys(answer);
        if (typeof keys === 'undefined' || (typeof keys !== 'undefined' && keys.join(',') !== 'id,questionid,userid,timecreated,timemodified,text,username')) {
            return this.log('Data is empty or not valid.');
        }

        // convert line breaks to <br/> tags
        answer.text = lib.lineBreakToTag(answer.text);

        // Check if this question has already been loaded
        if ($('#answer-' + answer.id).length !== 0) {
            $('#answer-' + answer.id)
                .find('.answer-text').html(answer.text).end()
                .addClass('updated')
                .find('.answer-post-date').text('| ' + answer.timemodified);
            return this.log('Answer %s has already been loaded!', answer.id);
        }

        // Clone element, add data and attributes and fill elements within
        var element = this.answerTemplate.clone()
            .attr('id', 'answer-' + answer.id)
            .data(answer)
            .find('.answer-text').html(answer.text).end()
            .find('.answer-author').text(answer.username).end()
            .find('.answer-post-date').text('| ' + answer.timemodified).end()
            .insertAfter($('#right-question-' + answer.questionid + ' .answers .answer-header'))
            .slideDown('slow');

        // Check for capabilities and add manage buttons to right column question only
        if ($('#is-guest').val() !== '1') {
            if (($('#can-manage').val() === '1' || $('#userid').val() === answer.userid)) {
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
    addQuestionToDOM: function (question) {
        // Check all keys are present
        var keys = Object.keys(question);
        if (typeof keys === 'undefined' || (typeof keys !== 'undefined' && !keys.join(',').match('id,userid,timecreated,timemodified,seconds,text,username'))) {
            return this.log('Data is empty or not valid.', keys.join(','));
        }

        // convert line breaks to <br/> tags
        question.text = lib.lineBreakToTag(question.text);

        // Create snippet for left column question
        var shorttext = question.text.length > 120 ? question.text.substring(0, 120) + '...' : question.text;

        // Just updating
        if ($('#left-question-' + question.id).length === 1) {
            $('#left-question-' + question.id).find('.question-text').html(shorttext).end().addClass('updated');
            $('#right-question-' + question.id)
                .find('.question-text').html(question.text).end()
                .addClass('updated')
                .find('.question-post-date').text(question.timemodified);

            return this.log('Question %s has already been loaded. Only updating.', question.id);
        }

        // Questions end up in two columns
        $(['left', 'right']).each(function (i, columnname) {
            var column = $('.' + columnname).find('.questions'),
            // Clone template and add a click handler to it.
                element = ('.' + columnname === '.left' ? videoQuanda.questionTemplateSmall : videoQuanda.questionTemplateBig)
                    .clone()
                    .attr({
                        id: columnname + '-question-' + question.id,
                        seconds: +question.seconds
                    })
                    // Add data to sort and to know what question id it is in order to load the correct answers on click
                    .data(question),

                convertedTime = typeof videoQuanda.videoPlayer !== "undefined" ? videoQuanda.videoPlayer.convertTime(+question.seconds) : +question.seconds;

            // Add data to visible elements
            element.find('.question-text').html(columnname === 'left' ? shorttext : question.text).end()
                .find('.question-author').text(question.username).end()
                .find('.question-post-date').text(question.timemodified).end()
                .find('.seconds').text(convertedTime);

            // Check for capabilities and add manage buttons to right column question only
            if ($('#is-guest').val() !== '1') {
                if (($('#can-manage').val() === '1' || $('#userid').val() === question.userid)) {
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
                    closest = l > 1 ? (columnname === 'left' ? closest[0] : closest[l - 1]) : closest.get(0);
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
    requestDeleteQuestionOrAnswer: function (e) {
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
    requestAndUpdateQuestionsAndAnswers: function (seconds) {
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
    requestDataFromApi: function (route, success_callback, error_callback) {
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
            success: function (data) {
                if (typeof success_callback !== 'undefined' && typeof success_callback === 'function') {
                    success_callback(data);
                }
            },
            error: function (data) {
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
    sendDataToApi: function (method, data, route, success_callback, error_callback) {
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
            success: function (d) {
                if (typeof success_callback !== 'undefined' && typeof success_callback === 'function') {
                    success_callback(d);
                }
            },
            error: function (d) {
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
    scrollToActiveQuestion: function () {
        // Scroll to the question
        var scrollTop = $('.right .questions .question.active').position().top + $('.right .questions').scrollTop();
        $('.right .questions').stop().animate({
            scrollTop: scrollTop
        });
    },

    /**
     * gets called when video sends a time update during playback
     */
    timeUpdate: function () {
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
    updateDOMElements: function () {
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
    validateAndSubmitInput: function (e, method) {
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

    closest
        .find('.text, .answer-author').toggle().end()
        .find('.edit-input').toggle()
        .find('textarea').focus().val(text);

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
export default videoQuanda;
