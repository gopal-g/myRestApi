<?php
/**
 * Web Service Request Handler - Using JWT:
 * 
 *
 * PHP version 5
 *
 * @category Base Request Handler
 * @package  RequestHandling_JWT
 * @author   BlackBurn027 <c4gopal@gmail.com>
 * @author   Anonymous < not sure from where half of this is taken >
 * @license  http://opensource.org/licenses/BSD-3-Clause 3-clause BSD
 * @link     Not Avaliable (atleast for now)
 */

#error_reporting(E_ALL);
#ini_set("display_errors", 1);
    include_once './Constants.php';
    include_once './Database.php';
    include_once './Rest.inc.php';
    include_once './JWT2.php';

class Request Extends REST {
    /* data string global for use in inherited class */

    public $data = "";

    /* No idea yet */
    public $app_user = array();

    /* used to hold the errors at the instance and pushed in the json formation stage */
    private $error = '';

    /* used to hold the messages at the instance and pushed in the json formation stage */
    private $message = '';

    /* db string private - will be filled once gets connected */
    private $db = NULL;

    /* private access token for every instance of Device Request */
    private $auth_state = false;

    /* holds userData */
    private $userData = array();

    /* Holds the access token data while request occurs */
    private $tempToken = array();

    /* Holds the exceptions occured while token validation  / other errors */
    private $exc = null;

    /**/
    private $assetUrl = 'http://www.example.dev/uploads/';

    public
            function __construct() {

        parent::__construct();

        $this->db = new Database(DB_HOST, DB_USER, DB_PASS, DB_NAME);

        if (!$this->db->isConnected) {
            $this->error = "Database Down";
            $this->response($this->getJson(), 503);
            exit();
            return;
        }
    }

    public
            function processApi() {

        $this->auth_state = false; /* Assuming the authorization is not valid */

        $requestAction = isset($_REQUEST['action']) ? $_REQUEST['action'] : 'not_found';

        $func = strtolower(trim(str_replace("/", "", $requestAction)));

        if ((int) method_exists($this, $func) > 0) {

            array_filter($_POST, array($this, 'trimValue'));

            $this->$func();
        } else {

            $this->error = 'Invlaid Request';

            $this->response($this->getJson(array()), 404);
        }
    }

    private
            function trimValue(&$value) {
        $value = trim($value);    // this removes whitespace and related characters from the beginning and end of the string
    }

    private
            function getJson($data = array()) {

        if (is_array($data)) {

            $this->payLoad = array(
                'data' => $data,
                'error' => $this->error,
                'message' => $this->message,
                'auth_state' => $this->auth_state,
                'asset_url' => $this->assetUrl
            );

            return json_encode($this->payLoad);
        }

        return json_encode($this->payLoad);
    }

    private
            function userLogin() {

        $user_eml = filter_input(INPUT_POST, 'user_email', FILTER_VALIDATE_EMAIL);

        $user_pwd = filter_input(INPUT_POST, 'user_password', FILTER_SANITIZE_STRING);

        if (!$user_eml || !$user_pwd || !$this->validateCredentials($user_eml, $user_pwd)) {

            $this->error = 'Invalid username / password';

            $this->response($this->getJson(), 403);

            return;
        }

        $this->error = '';

        $this->auth_state = true;

        $data = array('access_token' => $this->issueToken($this->userData), 'user' => $this->userData);

        $this->response($this->getJson($data), 200);
    }

    private
            function validateCredentials($inp_1, $inp_2) {

        $userDetails = array();

        $this->db->composeSelect('tb_user', array('email', 'name', 'id'), array('email', 'password', 'role'));

        $exe_check = $this->db->query(array(':email' => $inp_1, ':password' => md5($inp_2), ':role' => "USER"));

        $userDetails = ($exe_check) ? $this->getValidatedUser() : array();

        $this->message = (!$exe_check) ? $this->db->error_data() : '';

        $this->error = (!$exe_check) ? "Database down!" : $this->error;

        return ($userDetails) ? TRUE : FALSE;
    }

    private
            function getValidatedUser() {

        $res = array('rows' => 0, 'data' => array());

        $res = $this->db->get('fetch');

        $this->userData = ($res['rows']) ? $res['data'] : array();

        $this->error = ($res['rows']) ? "Invalid username / password" : $this->error;

        return $this->userData;

        exit();
    }

    private
            function getCategories() {

        if ($this->auth_state = $this->isValidAccessToken()) {

            $res = array();

            $this->db->composeSelect('tb_category', array());

            $exe_get = $this->db->query();

            if ($exe_get) {

                $fetch = $this->db->get('fetchAll');

                $res = array('list' => $fetch['data'], 'rows' => $fetch['rows']);
            }

            $this->message = (!$exe_get) ? $this->db->error_data() : '';

            $this->error = (!$exe_get) ? "Database down!" : '';

            $code = (!$exe_get) ? 500 : 200;

            $this->response($this->getJson($res), $code);
        }



        return;
    }

    private
            function getCategory($category_id = 0) {

        if ($this->auth_state = $this->isValidAccessToken()) {

            $res = array();

            $category_id = filter_input(INPUT_GET, "category_id", FILTER_SANITIZE_NUMBER_INT);

            $this->db->composeSelect('tb_dietmate', array(), array('category'));

            $exe_get = $this->db->query(array(':category' => $category_id));


            if ($exe_get) {

                $fetch = $this->db->get();

                $res['list'] = $fetch['data'];

                $res['rows'] = $fetch['rows'];
            }

            $this->message = (!$exe_get) ? $this->db->error_data() : '';

            $this->error = (!$exe_get) ? "Database down!" : '';

            $code = (!$exe_get) ? 500 : 200;

            $this->response($this->getJson($res), $code);
        }
        
        return;
    }

    /*     * **************************************************** */

    private
            function htmlToPlainText($str = '') {
        return (utf8_decode(strip_tags($str)));
    }

    private
            function issueToken($tokenPayLoad) {
        return JWT::encode($tokenPayLoad, KEY);
    }

    private
            function isValidAccessToken() {

        $access_token = filter_input(INPUT_POST, "access_token");

        $access = array();

        try {

            $access = JWT::decode($access_token, KEY);
        } catch (Exception $exc) {

            $this->error = "Access token invalid / expired. Please login again.";

            $this->message = $exc->getMessage();

            $this->logError($exc);

            $this->response($this->getJson(), 403);

            exit();
        }

        $this->tempToken = $access;

        return (isset($access[1]) ? TRUE : FALSE);
    }

    private
            function logError($exc = array()) {

        $this->exc = $exc;

        $log_string = "Code:" . $this->errorData('getCode') . "<br/>Message:" . $this->errorData('getMessage') . "<br/>Trace:" . $this->errorData('getTraceAsString');

        error_log($log_string);

        return $log_string;
    }

    private
            function errorData($method = 'getMessage') {

        return (method_exists($this->exc, $method) ? $this->exc->$method() : "No Error data available");
    }

}

$request = new Request();

$request->processApi();