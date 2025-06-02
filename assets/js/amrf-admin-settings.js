jQuery(document).ready(function($) {
    // Enhance the UI with some interactivity
    $('.user-role-settings h4').on('click', function() {
        $(this).nextUntil('h4').slideToggle();
    });
});