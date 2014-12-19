$(document).ready(function () {
    $.ajaxSetup({ cache: true });
    $.getScript('//connect.facebook.net/en_US/sdk.js', function () {
        FB.init({
            appId: '1602051133371458',
            xfbml: true,
            status: true,
            cookie: true,  //allow the server to access the session
            version: 'v2.1'
        });
    });
    $('#logout').click(function () {
        FacebookLogout();
    });
    $("#_submit_oauth").attr("disabled", true);
});

function triggerFacebookLogin(){
    FB.login(function(response){
        if (response.authResponse) {
            console.log('Facebook Login successful');
            checkLoginState();
        } else {
            console.log('User cancelled login or did not fully authorize.');
        }
    }, {
        scope: 'public_profile,email,user_about_me',
        return_scopes: true,
        auth_type: $('#facebook_auth_type').val() //set to 'reauthenticate' to force re-authentication of the user
    });
}

function checkLoginState() {
    console.log("checkLoginState");
    FB.getLoginStatus(function (response) {
        statusChangeCallback(response);
    });
}

function statusChangeCallback(response) {

    /* sample response:
     authResponse: Object
     accessToken: "CAAOIZB44wfekBAMHBbWvbvDw35247pJoZBetJZAkYZAPeZBRz261pFy111gEgPCrVkjo0g1ZCni1krrVJ8RJCwA4ZA3LkWRDb5dWqhXm9hCnTWEidIN8wOoiOJ61QSIwV4G7EKUoswT7TLlfvx9BZCUoIfd04iAUT0026qjqWtWmZCn3jd5ZCpuidkHHL5hXZAHKgz9Mu5HZCgKsmGGct5vAkITi"
     expiresIn: 7155
     signedRequest: "aPlwRWEwa2YGXVa-wBJzZEwHYGbA0ViJXcLVM_zUj34.eyJhbGdvcml0aG0iOiJITUFDLVNIQTI1NiIsImNvZGUiOiJBUUNkTE9zRzI0cVZOY2dVaGVqVDBiVmNqbmtIMWZMaTdFZm9OMThQWl9lbmRfQlBNMVZzb0I0clBrenpSTjBRME1Jd1Fmb2htdm5RR1FmbS1XelIzZGg1SG9KN0toZVRkOTN6WndzWjFjSVJDN1JnOEIwcDg3YnMxMHJqTVl3SWJLTUxLcDdDck1sQTNxbkdfbmhRN3RvdldOUThYY0FodVB4eG1fOTNnOGNjN0hHUVdEWFVXQWVfYzZmTWtqd1BtSmZodDFiNC13aFdlR1pJa25pUEFoeDJnSXkxNnRrQkdtOGJKLS1nbW42TzNydEhZSHFyck1pTTNvWGQ4bmdZNHNMN3FBZHZWWkFRQXMyQlBuSFp2V2lKWWM4NTVoZG9pdlRiZnhZNmtyZWotekN0WFEtcUdfemQ0eVN4TmZPZm8yU2haTUlkZHF2Z2dhbm84Y3lRRDY4QyIsImlzc3VlZF9hdCI6MTQyNjM2Njg0NSwidXNlcl9pZCI6Ijg0NDUzMDY3MjI2OTc0MiJ9"
     userID: "844530672269742"
     __proto__: Object
     status: "connected"
     */
    if (response.status === 'connected') {
        getFacebookUserInfo(response['authResponse'].accessToken);
    } else if (response.status === 'not_authorized') {
        // The person is logged into Facebook, but not your app.
        document.getElementById('status').innerHTML = 'Please sign in to Pocket Code';
    } else {
        // The person is not logged into Facebook, so we're not sure if
        // they are logged into this app or not.
        document.getElementById('status').innerHTML = 'Please log into Facebook.';
    }
}

function getFacebookUserInfo($accessToken) {
    /* sample response:
     response: Object
     email: "buchkogler@hotmail.com"
     first_name: "Stefan"
     gender: "male"
     id: "844530672269742"
     last_name: "Jaindl"
     link: "https://www.facebook.com/app_scoped_user_id/844530672269742/"
     locale: "de_DE"
     name: "Stefan Jaindl"
     timezone: 1
     updated_time: "2015-01-31T00:11:17+0000"
     verified: true
     */
    console.log('Welcome!  Fetching your information.... ');
    FB.api('/me', function (response) {
        console.log('Successful login for: ' + response.name);
        $('#status').text('Thanks for logging in, ' + response.name + '!');

        console.log("First name:" + response.first_name);
        console.log("Last name:" + response.last_name);
        console.log("Name:" + response.name);
        console.log("Response ID:" + response.id);
        console.log("Country:" + response.locale);

        var $ajaxUrlCheckServerTokenAvailable = Routing.generate(
            'catrobat_oauth_login_facebook_servertoken_available', {flavor: 'pocketcode'}
        );

        $.post($ajaxUrlCheckServerTokenAvailable,
            {
                id: response.id
            },
            function (data, status) {
                console.log(data);
                var $server_token_available = data['token_available'];
                if (!$server_token_available) {
                    sendTokenToServer($accessToken, response.id, response.name, response.email);
                } else {
                    FacebookLogin(response.email, response.name, response.id);
                }
            });
    });
}


function sendTokenToServer($token, $facebook_id, $username, $email) {

    var $state = $('#csrf_token').val();

    var $ajaxUrl = Routing.generate(
        'catrobat_oauth_login_facebook_token', {flavor: 'pocketcode'}
    );

    console.log($ajaxUrl);

    $.post($ajaxUrl,
        {
            client_token: $token,
            id: $facebook_id,
            state: $state,
            username: $username,
            mail: $email
        },
        function () {

            $ajaxUrl = Routing.generate(
                'catrobat_oauth_login_facebook', {flavor: 'pocketcode'}
            );

            $.post($ajaxUrl,
                {
                    username: $username,
                    id: $facebook_id,
                    mail: $email
                },
                function (data) {
                    submitOAuthForm(data);
                });
        });
}


function FacebookLogin($email, $username, $id) {

    var $ajaxUrl = Routing.generate(
        'catrobat_oauth_login_facebook', {flavor: 'pocketcode'}
    );

    $.post($ajaxUrl,
        {
            username: $username,
            id: $id,
            mail: $email
        },
        function (data, status) {
            submitOAuthForm(data);
        });
}

function submitOAuthForm(data){
    var $username = data['username'];
    var $password = data['password'];
    $("#username_oauth").val($username);
    $("#password_oauth").val($password);
    $("#_submit_oauth").attr("disabled", false);
    $("#_submit_oauth").click();
    $("#_submit_oauth").attr("disabled", true);
}

function FacebookLogout() {
    FB.getLoginStatus(function (response) {
        if (response.status === 'connected') {
            FB.logout(function (logout_response) {
                console.log('User logged out of Facebook with response:');
                console.log(logout_response);
            });
        }
    }, true);
}
