<?php
ini_set('display_errors', 'stdout');
error_reporting(E_ALL);

require "config.php";
require "oauthForHeroku.php";

$oauth = new oauth(CONSUMER_ID, CONSUMER_SECRET, CALLBACK_URL);
$oauth->auth_with_code();
?>

<!doctype html>
<html ng-app>
<head>
    <meta charset="utf-8">
    <title>Trialforce Signup</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/2.3.2/css/bootstrap.min.css" rel="stylesheet"></link>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/2.3.2/css/bootstrap-responsive.min.css" rel="stylesheet"></link>
    <style>
      body {
        padding-top: 40px;
        padding-bottom: 40px;
        background-color: #f5f5f5;
      }

      .form-signin {
        max-width: 400px;
        padding: 19px 29px 29px;
        margin: 0 auto 20px;
        background-color: #fff;
        border: 1px solid #e5e5e5;
        -webkit-border-radius: 5px;
           -moz-border-radius: 5px;
                border-radius: 5px;
        -webkit-box-shadow: 0 1px 2px rgba(0,0,0,.05);
           -moz-box-shadow: 0 1px 2px rgba(0,0,0,.05);
                box-shadow: 0 1px 2px rgba(0,0,0,.05);
      }
      .form-signin .form-signin-heading,
      .form-signin .checkbox {
        margin-bottom: 10px;
      }
      .form-signin input[type="text"],
      .form-signin input[type="password"] {
        font-size: 16px;
        height: auto;
        margin-bottom: 15px;
        padding: 7px 9px;
      }
      .form-signin input[type="email"] {
        font-size: 16px;
        height: auto;
        margin-bottom: 15px;
        padding: 7px 9px;
      }
    </style>
</head>
<body>

<div ng-controller="signup_ctrl" class="container">
    <form ng-submit="submit()" name="signup_form" class="form-signin">
        <h3>Trialforceサインアップ</h3>
        <div ng-show="result != 'success'">
            <input ng-model="signup.LastName" class="input-block-level" placeholder="姓" type="text" required />
            <input ng-model="signup.FirstName" class="input-block-level" placeholder="名" type="text" />
            <input ng-model="signup.Company" class="input-block-level" placeholder="会社名" type="text" required />
            <input ng-model="signup.SignupEmail" class="input-block-level" placeholder="Email" type="email" required />
            <div ng-show="status != 'processing'" style="margin-top: 10px;">
                <button ng-disabled="!signup_form.$valid" class="btn btn-large btn-primary" type="submit">送信</button>
            </div>
        </div>
        <div ng-show="status == 'processing'" class="progress progress-striped active" style="margin: 20px 0;">
            <div class="bar" style="width: 100%;"></div>
        </div>
        <div ng-show="result == 'success'" class="alert alert-success" style="margin: 20px 0;">
            <strong>完了！</strong> ログイン情報をメールでお送りします。
        </div>
        <div ng-show="result == 'fail'" class="alert alert-error" style="margin: 20px 0;">
            <div>
                <strong>エラー</strong>
            </div>
            <ul>
                <li ng-repeat="message in messages">
                    {{ message }}
                </li>
            </ul>
        </div>
    </form>
</div>

<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jquery/2.0.3/jquery.min.js"></script>
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/angular.js/1.1.5/angular.min.js"></script>
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/2.3.2/js/bootstrap.min.js"></script>
<script type="text/javascript" src="forcetk.js"></script>

<script type="text/javascript">
j$ = jQuery.noConflict();

function signup_ctrl($scope, $filter){
    $scope.submit = function(){
        var update_view = function(){
            $scope.$apply();
        };

        var callback_success = function(){
            delete $scope.signup.LastName;
            delete $scope.signup.FirstName;
            delete $scope.signup.Company;
            delete $scope.signup.Username;
            delete $scope.signup.SignupEmail;
            delete $scope.status;
            update_view();
        };

        var callback_fail = function(){
            delete $scope.status;
            update_view();
        };

        var force = new forcetk.Client('<?php echo CONSUMER_ID; ?>', 'https://login.salesforce.com', '/proxy.php');
        force.setSessionToken('<?php echo $oauth->access_token; ?>', null, '<?php echo $oauth->instance_url; ?>');
        $scope.signup.TemplateId = '<?php echo TEMPLATE_ID; ?>';
        $scope.signup.Country = 'JP';
        console.log($scope.signup.SignupEmail);
        $scope.signup.Username = $scope.signup.SignupEmail.split('@')[0] + '+' + Math.floor(Math.random() * 9999) + '@<?php echo APP_DOMAIN; ?>';
        $scope.status = 'processing';
        delete $scope.result;
        delete $scope.messages;
        force.create(
            'SignupRequest', 
            $scope.signup, 
            function(response){
                if (response.status.http_code == 201){
                    $scope.result = 'success';
                    $scope.messages = [];
                    $scope.messages.push('サインアップが完了しました。ログイン情報がメールで送信されます。');
                    callback_success();
                } else {
                    $scope.result = 'fail';
                    $scope.messages = [];
                    angular.forEach(response.contents, function(v, k){
                        $scope.messages.push(v.message);
                    });
                    callback_fail();
                }
            },
            function(response){
                $scope.result = 'fail';
                $scope.messages = [];
                $scope.messages.push('APIの呼び出しに失敗しました。サイト管理者にお問い合わせください。');
                callback_fail();
            }
        );
    };
}

</script>

</body>
</html>
