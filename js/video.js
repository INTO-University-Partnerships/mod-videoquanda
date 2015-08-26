'use strict';

var videoPlayer = {

    videoElement: $('#jquery_jplayer_1'),
    // Object to store video files
    files: {},

    init: function () {
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
                ended: function () {},

                error: function (e) {
                    // Add notification span if there is none yet
                    if (!videoElement.find('#notification').length) {
                        videoElement.append('<span id="notification"/>');
                    }

                    // If there is a video the browser can play remove any error message (Firefox shows an error even though it has a video to play)
                    if (e.jPlayer.status.srcSet !== true) {
                        videoElement.find('#notification').text('Error [' + e.jPlayer.error.type + ']: ' + e.jPlayer.error.message);
                    }
                },

                loadedmetadata: function () {
                    // Set width and height
                    var width = $('#jp_container_1').width(); // Max width of video container
                    var height = $('video').get(0).videoHeight / ($('video').get(0).videoWidth / width); // Set height according to width (scale up / down)
                    $(this).jPlayer('option', 'size', {
                        width: width,
                        height: height
                    });
                },

                // Gets called when the video is paused
                pause: function () {},

                // Gets called when video starts to play
                play: function () {},

                ready: function () {
                    $(this).jPlayer('setMedia', videoPlayer.files);
                    //videoPlayer.play();
                },

                // Update current playhead time
                timeupdate: function (e) {
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
    pause: function (seconds) {
        this.videoElement.jPlayer('pause', seconds);
    },

    // Play video
    play: function (seconds) {
        this.videoElement.jPlayer('play', seconds);
    },

    // Function to use jPlayers convertTime
    convertTime: function (seconds) {
        return $.jPlayer.convertTime(seconds);
    },

    // Get current play time
    currentTime: function () {
        if (typeof this.videoElement.data().jPlayer !== 'undefined') {
            return this.videoElement.data().jPlayer.status.currentTime;
        }
    },

    status: function () {
        return this.videoElement.data().jPlayer.status;
    }

};

videoPlayer.init();
export default videoPlayer;
