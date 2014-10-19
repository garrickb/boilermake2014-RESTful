<?php

require_once '../include/DbHandler.php';
require_once '../include/PassHash.php';
require '.././libs/Slim/Slim.php';

\Slim\Slim::registerAutoloader();

$app = new \Slim\Slim();

// User id from db - Global Variable
$user_id = NULL;

/**
 * Adding Middle Layer to authenticate every request
 * Checking if the request has valid api key in the 'Authorization' header
 */
function authenticate(\Slim\Route $route)
{
    // Getting request headers
    $headers = apache_request_headers();
    $response = array();
    $app = \Slim\Slim::getInstance();

    if (isset($headers['Authorization'])) {
        $db = new DbHandler();
        $api_key = $headers['Authorization'];
        if (!$db->isValidApiKey($api_key)) {
            $response["error"] = true;
            $response["message"] = "Access Denied: Invalid Api key: '".$api_key."'.";
            echoResponse(401, $response);
            $app->stop();
        } else {
            global $user_id;
            $user_id = $db->getUserId($api_key);
        }
    } else {
        $response["error"] = true;
        $response["message"] = "The Api key is missing.";
        echoResponse(400, $response);
        $app->stop();
    }
}

/**
 * User Registration
 * url - /register
 * method - POST
 * params - name, email, password
 */
$app->post('/register', function () use ($app) {
    // check for required params
    verifyRequiredParams(array('name', 'email', 'password'));

    $response = array();
    // reading post params
    $name = $app->request->post('name');
    $email = $app->request->post('email');
    $password = $app->request->post('password');

    // validating email address
    validateEmail($email);

    $db = new DbHandler();
    $res = $db->createUser($name, $email, $password);

    if ($res["status"] == CREATED_SUCCESSFULLY) {
        $response["error"] = false;
        $response["message"] = "You have successfully registered!";
        $response["apiKey"] = $res["apiKey"];
    } else if ($res["status"] == CREATE_FAILED) {
        $response["error"] = true;
        $response["message"] = "Oops! An error occurred while registering!";
    } else if ($res["status"] == ALREADY_EXISTED) {
        $response["error"] = true;
        $response["message"] = "Sorry, this email address is already registered!";
    }
    echoResponse(201, $response);
});

/**
 * User Login
 * url - /login
 * method - POST
 * params - email, password
 */
$app->post('/login', function () use ($app) {
    verifyRequiredParams(array('email', 'password'));

    $email = $app->request()->post('email');
    $password = $app->request()->post('password');
    $response = array();

    $db = new DbHandler();

    if ($db->checkLogin($email, $password)) {
        $user = $db->getUserByEmail($email);
        if ($user != NULL) {
            $response["error"] = false;
            $response["message"] = "Login successful!";
            $response['name'] = $user['name'];
            $response['email'] = $user['email'];
            $response['apiKey'] = $user['api_key'];
            $response['createdAt'] = $user['created_at'];
        } else {
            $response['error'] = true;
            $response['message'] = "An error has occurred. Please try again.";
        }
    } else {
        $response['error'] = true;
        $response['message'] = 'Login failed. Incorrect credentials.';
    }
    echoResponse(200, $response);
});

$app->post('/events/attend/:id', 'authenticate', function ($event_id) use ($app) {
    $response = array();

    global $user_id;
    $db = new DbHandler();
    $r = $db->setUserAttending($user_id, $event_id);
    if ($r == CREATED_SUCCESSFULLY) {
        $response["error"] = false;
        $response["message"] = "Attendance has been set successfully!";
        $response["event_id"] = $event_id;
        echoResponse(201, $response);
    } else if($r == CREATE_FAILED) {
        $response["error"] = true;
        $response["message"] = "Failed to attend the event.";
        echoResponse(200, $response);
    } else if($r == -1) {
        $response["error"] = true;
        $response["message"] = "Event does not exist.";
        echoResponse(200, $response);
    } else {
        $response["error"] = true;
        $response["message"] = "User is already attending event.";
        echoResponse(200, $response);
    }
});

$app->delete('/events/attend/:id', 'authenticate', function ($event_id) use ($app) {
    $response = array();

    global $user_id;
    $db = new DbHandler();
    if ($db->deleteAttending($user_id, $event_id)) {
        $response["error"] = false;
        $response["message"] = "Attendance has been deleted successfully.";
        echoResponse(200, $response);
    } else {
        $response["error"] = true;
        $response["message"] = "Failed to delete attendance.";
        echoResponse(200, $response);
    }
});

$app->get('/events/attend/:id', 'authenticate', function ($event_id) use ($app) {
    $response = array();
    $db = new DbHandler();
    if ($db->getEvent($event_id) != null) {
        $response["error"] = false;
        $response["message"] = "Successfully got users attending.";
        $response["attending"] = $db->getUsersAttending($event_id);
        $response["numAttending"] = count($response["attending"]);
    } else {
        $response["error"] = false;
        $response["attending"] = false;
        $response["message"] = "Event doesn't exist.";
    }
    echoResponse(200, $response);
});

$app->post('/events', 'authenticate', function () use ($app) {
    verifyRequiredParams(array('name', 'desc', 'start', 'end'));

    $response = array();
    $name = $app->request()->post('name');
    $desc = $app->request()->post('desc');
    $start = $app->request()->post('start');
    $end = $app->request()->post('end');

    //TODO: Validate start and end are valid dates, and are in the future.

    global $user_id;
    $db = new DbHandler();

    $event_id = $db->createEvent($user_id, $name, $desc, $start, $end);

    if ($event_id != NULL) {
        $response["error"] = false;
        $response["message"] = "Event has been created successfully.";
        $response["event_id"] = $event_id;
        echoResponse(201, $response);
    } else {
        $response["error"] = true;
        $response["message"] = "Failed to create event. Please try again.";
        echoResponse(200, $response);
    }
});

$app->get('/events', 'authenticate', function () use ($app) {
    $response = array();
    $db = new DbHandler();
    $page = $app->request()->get('page');
    if($page == null) $page = 0;
    $events = $db->getEvents($page);
    $element = $app->request()->get('element');
    if(isset($element)) {
        if (isset($events[$element])) {
            $response["error"] = false;
            $response["message"] = "You successfully fetched event data.";
            $response["page"] = $page;
            $response["event"] = $events[$element];
        } else {
            $response["error"] = true;
            $response["message"] = "There were no events fetched.";
        }
    } else {
        if (isset($events)) {
            $response["error"] = false;
            $response["message"] = "You successfully fetched event data.";
            $response["page"] = $page;
            $response["events"] = $events;
        } else {
            $response["error"] = true;
            $response["message"] = "There were no events fetched.";
        }
    }
    echoResponse(200, $response);
});

$app->get('/events/attend', 'authenticate', function () use ($app) {
    $response = array();
    global $user_id;

    $db = new DbHandler();
    $events = $db->userAttending($user_id);
    if (count($events) > 0) {
        $response["error"] = false;
        $response["message"] = "Successfully fetched events that user is attending.";
        $response["events"] = $events;
    } else {
        $response["error"] = false;
        $response["message"] = "User is not attending any events.";
        $response["events"] = $events;
    }
    echoResponse(200, $response);
});

/**
 * Verifying required params posted or not
 */
function verifyRequiredParams($required_fields) {
    $error = false;
    $error_fields = "";
    $request_params = array();
    $request_params = $_REQUEST;
    // Handling PUT request params
    if ($_SERVER['REQUEST_METHOD'] == 'PUT') {
        $app = \Slim\Slim::getInstance();
        parse_str($app->request()->getBody(), $request_params);
    }
    foreach ($required_fields as $field) {
        if (!isset($request_params[$field]) || strlen(trim($request_params[$field])) <= 0) {
            $error = true;
            $error_fields .= $field . ', ';
        }
    }
    if ($error) {
        $response = array();
        $app = \Slim\Slim::getInstance();
        $response["error"] = true;
        $response["message"] = 'Required field(s) ' . substr($error_fields, 0, -2) . ' is missing or empty';
        echoResponse(400, $response);
        $app->stop();
    }
}

/*
 * RESTful that interfere with above requests.
 */
$app->get('/events/:id', 'authenticate', function ($event_id) use ($app) {
    $response = array();
    global $user_id;
    $db = new DbHandler();
    $event = $db->getEvent($event_id);
    if (isset($event)) {
        $response["error"] = false;
        $response["message"] = "You successfully fetched the event data.";
        $response["isOwner"] = $event["user"]["_id"] == $user_id;
        $response["attending"] = ($db->isUserAttending($user_id, $event_id));
        $response["numAttending"] = count($db->getUsersAttending($event_id));
        $response["name"] = $event["name"];
        $response["desc"] = $event["desc"];
        $response["start"] = $event["start"];
        $response["end"] = $event["end"];
    } else {
        $response["error"] = true;
        $response["message"] = "There were no events fetched.";
    }
    echoResponse(200, $response);
});

$app->put('/events/:id', 'authenticate', function ($event_id) use ($app) {
    $response = array();
    global $user_id;
    $name = $app->request()->post('name');
    $desc = $app->request()->post('desc');
    $start = $app->request()->post('start');
    $end = $app->request()->post('end');

    //TODO: Validate start and end are valid dates, and are in the future.

    global $user_id;
    $db = new DbHandler();

    $result = $db->updateEvent($user_id, $event_id, $name, $desc, $start, $end);
    if ($result) {
        $response["error"] = false;
        $response["message"] = "You successfully updated the event data.";
    } else {
        $response["error"] = true;
        $response["message"] = "You don't have the right authorization to update this event.";
    }
    echoResponse(200, $response);
});

$app->delete('/events/:id', 'authenticate', function ($event_id) use ($app) {
    $response = array();
    global $user_id;
    $db = new DbHandler();

    $result = $db->deleteEvent($user_id, $event_id);
    if ($result) {
        $response["error"] = false;
        $response["message"] = "You successfully deleted the event data.";
    } else {
        $response["error"] = true;
        $response["message"] = "You don't have the right authorization to delete this event.";
    }
    echoResponse(200, $response);
});

/**
 * Validating email address
 */
//TODO: Check for PURDUE.EDU email.
//TODO: Email Verification
function validateEmail($email)
{
    $app = \Slim\Slim::getInstance();
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response["error"] = true;
        $response["message"] = 'Your email address is invalid.';
        echoResponse(400, $response);
        $app->stop();
    }
}

/**
 * Echoing json response to client
 * @param String $status_code Http response code
 * @param Int $response Json response
 */
function echoResponse($status_code, $response) {
    $app = \Slim\Slim::getInstance();
    $app->status($status_code);
    $app->contentType('application/json');
    echo json_encode($response);
}

$app->run();