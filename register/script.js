$('document').ready(function() {

    var username_state = false;
    var email_state = false;

    $('#username').on('blur', function() {
        var username = $('#username').val();
        if (username == '') {
            username_state = false;
            return;
        }
        $.ajax({
            url: 'index.php',
            type: 'post',
            data: {
                'username_check': 1,
                'username': username
            },
            success: function(response) {
                if (response == 'taken') {
                    username_state = false;
                    $('#username').parent().removeClass();
                    $('#username').parent().addClass('form_error');
                    $('#username').siblings("span").text("Sorry... Username already taken");
                } else if (response == "not_taken") {
                    username_state = true;
                    $('#username').parent().removeClass();
                    $('#username').parent().addClass('form_success');
                    $('#username').siblings("span").text("Username available");
                }
            }
        })
    });

    $('#reg_btn').on("click", function(e) {
        var username = $("#username").val();
        var password = $("#password").val();
        if (username_state == false ) {
            e.preventDefault();
            $("#error_msg").text("Fix the errors in the form first");
        } else {
            $.ajax({
                url: 'index.php',
                type: 'post',
                data: {
                    'save': 1,
                    'username': username,
                    'password': password
                },
                success: function(response) {
                    alert('User saved');
                    $('#username').val('');
                    $('#password').val('');
                }
            })
        }
    });

});