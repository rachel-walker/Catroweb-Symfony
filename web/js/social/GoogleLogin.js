$(document).ready(function () {
    var po = document.createElement('script');
    po.type = 'text/javascript';
    po.async = true;
    po.src = 'https://apis.google.com/js/client:plusone.js';
    var s = document.getElementsByTagName('script')[0];
    s.parentNode.insertBefore(po, s);
    $('#logout').click(function () {
        GoogleLogout();
    });
    $("#_submit_oauth").attr("disabled", true);
});

function triggerGoogleLogin(){
    gapi.signin.render('googleLoginButton', {
        'callback': 'signinCallback',
        'approvalprompt': $('#gplus_approval_prompt').val(), //'force' prevents auto g+-signin
        'clientid': '427226922034-r016ige5kb30q9vflqbt1h0i3arng8u1.apps.googleusercontent.com',
        'cookiepolicy': 'single_host_origin',
        'requestvisibleactions': 'http://schemas.google.com/AddActivity',
        'redirecturi': 'postmessage',
        'accesstype': 'offline',
        'scope': 'https://www.googleapis.com/auth/plus.login https://www.googleapis.com/auth/userinfo.email'
    })
}

function signinCallback(authResult) {
    /* sample result:
     authResult: Object
     access_token: "ya29.IgEVi-vKoOc-odeQ6W6fJLfNswoQviASJffYN6_U1EEZCTXEfBvgSNgYHW4h1DyCGjE-wTe3wnKBAA"
     authuser: "0"
     client_id: "92927377975-c92us1m9qh9ls3jp2lic5uppcltepi1l.apps.googleusercontent.com"
     code: "4/uj-gZXpcllfXK8pdty6hPfCuSUxtd2FQtGEtIh9jb2M.Qu4FFmi3pxERYFZr95uygvUZavNllwI"
     cookie_policy: "single_host_origin"
     expires_at: "1424640485"
     expires_in: "3600"
     g-oauth-window: Window
     g_user_cookie_policy: "single_host_origin"
     id_token: "eyJhbGciOiJSUzI1NiIsImtpZCI6IjExZjY0YjRiMjgzYTFmNjBiY2U3NjQ1NWJlZjMzOGFlZjE4YjE4NzkifQ.eyJpc3MiOiJhY2NvdW50cy5nb29nbGUuY29tIiwic3ViIjoiMTA4NTI5ODQyNjc4ODE5ODQ3ODIxIiwiYXpwIjoiOTI5MjczNzc5NzUtYzkydXMxbTlxaDlsczNqcDJsaWM1dXBwY2x0ZXBpMWwuYXBwcy5nb29nbGV1c2VyY29udGVudC5jb20iLCJlbWFpbCI6ImphaW5kbC5zdGVmYW5AZ21haWwuY29tIiwiYXRfaGFzaCI6Imc3ekhTSGdKSjdyOFh5ODA2a2RhRUEiLCJlbWFpbF92ZXJpZmllZCI6dHJ1ZSwiYXVkIjoiOTI5MjczNzc5NzUtYzkydXMxbTlxaDlsczNqcDJsaWM1dXBwY2x0ZXBpMWwuYXBwcy5nb29nbGV1c2VyY29udGVudC5jb20iLCJjX2hhc2giOiJNY0FYak43U0xGU2Y4Y3EzeWc5NUl3IiwiaWF0IjoxNDI0NjM2NTg0LCJleHAiOjE0MjQ2NDA0ODR9.nexJy0Ja3ayySc8-6NDH1VIYAOu2GVu2SwTrhhrpz4WSMkB_YU9Vqhs9vIAklg9tHGRBYiUjD70gmtVWzQtCFIBgPM5tqmPJfAczJsm8_0EvLJdm3Hoh00VGmok092RDJ9zdCMn-XGWQcZ7488CqNEXuuviw-3VUJRIXIc65RSg"
     issued_at: "1424636885"
     num_sessions: "1"
     prompt: "consent"
     response_type: "code token id_token gsession"
     scope: "https://www.googleapis.com/auth/plus.login https://www.googleapis.com/auth/userinfo.email https://www.googleapis.com/auth/plus.moments.write https://www.googleapis.com/auth/plus.me https://www.googleapis.com/auth/plus.profile.agerange.read https://www.googleapis.com/auth/plus.profile.language.read https://www.googleapis.com/auth/plus.circles.members.read"
     session_state: "393a428d4f3c4e1f26ef0bf104afabf829759552..b4bb"
     state: ""
     status: Object
     token_type: "Bearer"
     */

    if (authResult['code']) {
        console.log('auth: ' + authResult['code']);
        getGoogleUserInfo(authResult);
    } else if (authResult['error']) {
        if(authResult['error'] == 'access_denied') {
            console.log('User denied access to the app');
        } else if(authResult['error'] == 'immediate_failed') {
            console.log('Automatic sign-in of user failed');
        } else {
            console.log('error:' + authResult['error']);
        }
    }
}

function getGoogleUserInfo(authResult) {
    gapi.client.load('oauth2', 'v2', function () {
        var request = gapi.client.oauth2.userinfo.get();
        request.execute(getUserInfoCallback);
    });

    function getUserInfoCallback(obj) {
        var $email = '';
        var $username = '';
        var $id = '';

        if (obj['email']) {
            $email = obj['email'];
        }
        if (obj['name']) {
            $username = obj['name'];
        }
        if (obj['id']) {
            $id = obj['id'];
        }
        var $ajaxUrlCheckServerTokenAvailable = Routing.generate(
            'catrobat_oauth_login_google_servertoken_available', {flavor: 'pocketcode'}
        );
        $.post($ajaxUrlCheckServerTokenAvailable,
            {
                id: $id
            },
            function (data) {
                console.log(data);
                var $server_token_available = data['token_available'];
                if (!$server_token_available) {
                    sendCodeToServer(authResult['code'], $id, $username, $email);
                } else {
                    GoogleLogin($email, $username, $id);
                }
            });
    }
}


function sendCodeToServer($code, $gplus_id, $username, $email) {

    var $state = $('#csrf_token').val();
    var $ajaxUrl = Routing.generate(
        'catrobat_oauth_login_google_code', {flavor: 'pocketcode'}
    );

    $.post($ajaxUrl,
        {
            code: $code,
            id: $gplus_id,
            state: $state,
            username: $username,
            mail: $email
        },
        function (data, status) {

            $ajaxUrl = Routing.generate(
                'catrobat_oauth_login_google', {flavor: 'pocketcode'}
            );

            $.post($ajaxUrl,
                {
                    username: $username,
                    id: $gplus_id,
                    mail: $email
                },
                function (data, status) {
                    submitOAuthForm(data)
                });
        });
}

function GoogleLogin($email, $username, $id) {

    var $ajaxUrl = Routing.generate(
        'catrobat_oauth_login_google', {flavor: 'pocketcode'}
    );

    $.post($ajaxUrl,
        {
            username: $username,
            id: $id,
            mail: $email
        },
        function (data, status) {
            submitOAuthForm(data)
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

function GoogleLogout() {
    var sessionParams = {
        'client_id': '92927377975-c92us1m9qh9ls3jp2lic5uppcltepi1l.apps.googleusercontent.com',
        'session_state': null
    };
    gapi.auth.checkSessionState(sessionParams, function (connected) {
        if (connected) {
            gapi.auth.signOut()
        }
    });
}