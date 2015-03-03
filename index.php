<?php
error_reporting(0);

require "config.php";
require "oauthForHeroku.php";

$oauth = new oauth(CONSUMER_ID, CONSUMER_SECRET, CALLBACK_URL);
$oauth->auth_with_code();
?>

<!doctype html>
<html ng-app="trialforceSignup">
<head>
    <meta charset="utf-8">
    <title>Trialforce Signup</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.3.2/css/bootstrap.min.css" rel="stylesheet"></link>
</head>
<body>

<div ng-controller="signupRequestCtl" class="container">
    <form ng-submit="create(ui.signupRequest)" name="signup_form" class="form-signin">
        <h3>Trialforceサインアップ</h3>
        <div class="form-group">
            <input ng-model="ui.signupRequest.LastName" class="form-control" placeholder="姓" type="text" required />
        </div>
        <div class="form-group">
            <input ng-model="ui.signupRequest.FirstName" class="form-control" placeholder="名" type="text" />
        </div>
        <div class="form-group">
            <input ng-model="ui.signupRequest.Company" class="form-control" placeholder="会社名" type="text" required />
        </div>
        <div class="form-group">
            <input ng-model="ui.signupRequest.SignupEmail" class="form-control" placeholder="Email" type="email" required />
        </div>
        <div class="form-group" style="text-align:right;">
            <button ng-disabled="remoting.inProgress || !signup_form.$valid" class="btn btn-primary" type="submit"><span class="glyphicon glyphicon-ok"></span>&nbsp;送信</button>
        </div>
    </form>
</div>

<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/angular.js/1.3.14/angular.min.js"></script>
<script type="text/javascript" src="forcetk4ng.js"></script>
<script type="text/javascript">
angular.module('trialforceSignup', ['forcetk4ng'])
.controller('signupRequestCtl', function($scope, $filter, $q, $log, $window, force){
    $scope.create = function(signupRequest){
        signupRequest.TemplateId = '<?php echo TEMPLATE_ID; ?>';
        signupRequest.Country = 'JP';
        signupRequest.Username = signupRequest.SignupEmail.split('@')[0] + '+' + Math.floor(Math.random() * 999999) + '@<?php echo APP_DOMAIN; ?>';

        force.create('SignupRequest', signupRequest)
        .then(
            function(response){
                $window.alert("サインアップの受付が完了しました。");
                delete $scope.ui.signupRequest;
            },
            function(response){
                $log.error(response);
            }
        );
    }
    force.setAccessToken("<?php echo $oauth->access_token; ?>");
    force.setInstanceUrl("<?php echo $oauth->instance_url; ?>");
});
</script>

</body>
</html>
