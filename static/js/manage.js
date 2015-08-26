jQuery.noConflict();
(function ($) {
    var plugin = 'mod_videoquanda';

    // Add delete button
    $('a.delete-video').on('click', function() {
        if (confirm(M.util.get_string('confirm_delete', plugin))) {
            // Remove link to video
            $(this).closest('p').find('.btn-info').remove();
            // Give the hidden video field a different name to delete from filesever and database
            $(this).closest('p').find('input[type="hidden"]').attr('name', 'delete[' + $(this).closest('p').data('video-type') + ']');
            // Remove delete button itself
            $(this).remove();
        }
    });

    $('input[type="file"]').on('change', function() {
        // Add name and if to file input
        $(this).attr({
            name: 'form[' + $(this).closest('p').data('video-type') + ']',
            id: 'form_' + $(this).closest('p').data('video-type')
        });
        $(this).closest('p').find('input[type="hidden"]').remove();
    });

})(jQuery);
