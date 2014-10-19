<?php

define('CREATED_SUCCESSFULLY', 0);
define('CREATE_FAILED', 1);
define('ALREADY_EXISTED', 2);

class DbHandler {

    private $database;

    function __construct() {
        $mongo = new MongoClient();
        $this->database = $mongo->selectDB("boilermake");
    }

    /**
     * Creating new user
     * @param $name
     * @param String $email User email address
     * @param String $password User password
     * @internal param String $end User full name
     * @return status of success/failure
     */
    public function createUser($name, $email, $password) {
        require_once 'PassHash.php';
        $response = array();

        if (!$this->isUserExists($email)) {
            $password_hash = PassHash::hash($password);
            $collection = new MongoCollection($this->database, "users");
            $document = array(
                "name" => $name,
                "email" => $email,
                "password" => $password_hash,
                "api_key" => $this->generateApiKey(),
                "created_at" => new MongoDate()
            );
            $collection->insert($document);

            $cursor = $collection->find();
            $result = null;

            foreach ($cursor as $result) {
                if ($result["email"] == $document["email"]) {
                    $result = $result["api_key"];
                    break;
                }
            }
            if (isset($result)) {
                $response["status"] = CREATED_SUCCESSFULLY;
                $response["apiKey"] = $result;
                return $response;
            } else {
                $response["status"] = CREATE_FAILED;
                return $response;
            }
        } else {
            $response["status"] = ALREADY_EXISTED;
            return $response;
        }
    }

    /**
     * Checking user login
     * @param String $email User login email id
     * @param String $password User login password
     * @return boolean User login status success/fail
     */
    public function checkLogin($email, $password) {
        $collection = new MongoCollection($this->database, "users");
        $user = $collection->findOne(array('email' => $email));

        return ($user && PassHash::check_password($user["password"], $password));
    }

    /**
     * Checking for duplicate user by email address
     * @param String $email email to check in db
     * @return boolean
     */
    private function isUserExists($email) {
        $collection = new MongoCollection($this->database, "users");
        $cursor = $collection->find();
        foreach ($cursor as $result)
            if ($result["email"] == $email)
                return true;
        return false;
    }

    /**
     * Fetching user by email
     * @param String $email User email id
     * @return user
     */
    public function getUserByEmail($email) {
        $collection = new MongoCollection($this->database, "users");
        $cursor = $collection->find();
        foreach ($cursor as $result)
            if ($result["email"] == $email)
                return $result;
        return null;
    }

    /**
     * Fetching user by id
     * @param $id
     * @internal param String $email User email id
     * @return user
     */
    public function getUserById($id) {
        $collection = new MongoCollection($this->database, "users");
        $cursor = $collection->find(array(), array("name" => 1, "email" => 1));
        foreach ($cursor as $result)
            if ($result["_id"] == $id)
                return $result;
        return null;
    }

    /**
     * Fetching user api key
     * @param String $user_id user id primary key in user table
     * @return api key
     */
    public function getApiKeyById($user_id) {
        $collection = new MongoCollection($this->database, "users");
        $cursor = $collection->find();
        foreach ($cursor as $result)
            if ($result["_id"] == $user_id)
                return $result["api_key"];
        return null;
    }

    /**
     * Fetching user id by api key
     * @param String $api_key user api key
     * @return user id
     */
    public function getUserId($api_key) {
        $collection = new MongoCollection($this->database, "users");
        $cursor = $collection->find();
        foreach ($cursor as $result)
            if ($result["api_key"] == $api_key)
                return $result["_id"];
        return null;
    }

    /**
     * Validating user api key
     * If the api key is there in db, it is a valid key
     * @param String $api_key user api key
     * @return boolean
     */
    public function isValidApiKey($api_key) {
        $collection = new MongoCollection($this->database, "users");
        $cursor = $collection->find();
        foreach ($cursor as $result)
            if ($result["api_key"] == $api_key)
                return true;
        return false;
    }

    /**
     * Generating random Unique MD5 String for user Api key
     */
    private function generateApiKey() {
        return md5(uniqid(rand(), true));
    }

    /**
     * Creating new event
     * @param String $user_id user id to whom event belongs to
     * @param $name
     * @param $desc
     * @param $start
     * @param $end
     * @return null
     */
    public function createEvent($user_id, $name, $desc, $start, $end) {
        $collection = new MongoCollection($this->database, "events");
        $document = array(
            "user" => $this->getUserById($user_id),
            "name" => $name,
            "desc" => $desc,
            "start" => $start,
            "end" => $end,
            "created_on" => new MongoDate()
        );
        $document['_id'] = new MongoId();
        $collection->insert($document);

        $cursor = $collection->find();

        foreach ($cursor as $result) {
            if ($result["_id"] == $document['_id']) {
                return $result["_id"];
            }
        }
        return null;
    }

    /**
     * @param $page
     * @return array|null 1-10 events that belong to $page.
     */
    public function getEvents($page) {
        $collection = new MongoCollection($this->database, "events");
        $PAGE_SIZE = 250;
        $cursor = $collection->find()->skip($PAGE_SIZE * $page)->limit($PAGE_SIZE)->sort(array("created_on" => -1));
        $events = array();
        foreach ($cursor as $result)
            array_push($events, $result);
        if (count($events) != 0)
            return $events;
        return null;
    }

    /**
     * @param $id
     * @return The event with $id.
     */
    public function getEvent($id) {
        $collection = new MongoCollection($this->database, "events");
        $cursor = $collection->find();
        foreach ($cursor as $result)
            if ($result["_id"] == $id)
                return $result;
        return null;
    }

    /**
     * @param $user_id
     * @param $event_id
     * @param $name
     * @param $desc
     * @param $start
     * @param $end
     */
    public function updateEvent($user_id, $event_id, $name, $desc, $start, $end) {
        $collection = new MongoCollection($this->database, "events");
        if($user_id == new MongoId($this->getEvent($event_id)["user"]["_id"])) {
            if($name != null && $name != "") {
                $criteria = array("_id"=>new MongoId($event_id));
                $new_object = array('$set'=>array("name"=>$name));
                $collection->update($criteria, $new_object);
            }
            if($desc != null && $desc != "") {
                $criteria = array("_id"=>new MongoId($event_id));
                $new_object = array('$set'=>array("desc"=>$desc));
                $collection->update($criteria, $new_object);
            }
            if($start != null && $start != "") {
                $criteria = array("_id"=>new MongoId($event_id));
                $new_object = array('$set'=>array("start"=>$start));
                $collection->update($criteria, $new_object);
            }
            if($end != null && $end != "") {
                $criteria = array("_id"=>new MongoId($event_id));
                $new_object = array('$set'=>array("end"=>$end));
                $collection->update($criteria, $new_object);
            }
            return true;
        }
        return false;
    }

    /**
     * @param $user_id
     * @param $event_id
     * @return boolean If event was successfully deleted.
     */
    public function deleteEvent($user_id, $event_id) {
        if($this->getEvent($event_id)['user']['_id'] == $user_id) {
            $collection = new MongoCollection($this->database, "events");
            $collection->remove(array("_id"=>new MongoId($event_id)));
            return true;
        }
        return false;
    }

    /**
     * @param $user_id
     * @param $event_id Event to attend.
     * @return int Success or failure code.
     */
    public function setUserAttending($user_id, $event_id) {
        if($this->getEvent($event_id) == null)
            return -1;   //Event does not exist.
        if (!$this->isUserAttending($user_id, $event_id)) {
            $collection = new MongoCollection($this->database, "attendance");
            $document = array(
                "user_id" => $user_id,
                "event_id" => $event_id,
            );
            $collection->insert($document);

            $cursor = $collection->find();
            $result = false;
            foreach ($cursor as $result) {
                if ($result["user_id"] == $document["user_id"] && $result["event_id"] == $document["event_id"]) {
                    $result = true;
                    break;
                }
            }
            if ($result) {
                return CREATED_SUCCESSFULLY;
            } else {
                return CREATE_FAILED;
            }
        } else {
            return ALREADY_EXISTED;
        }
        return $response;
    }

    /**
     * @param $user_id
     * @param $event_id
     * @return boolean If the user is attending the event.
     */
    public function isUserAttending($user_id, $event_id) {
        $collection = new MongoCollection($this->database, "attendance");
        $cursor = $collection->find();
        foreach ($cursor as $result)
            if ($result["user_id"] == $user_id && $result["event_id"] == $event_id)
                return true;
        return false;
    }

    /**
     * @param $user_id
     * @return All of the events that the user is attending.
     */
    public function userAttending($user_id) {
        $collection = new MongoCollection($this->database, "attendance");
        $cursor = $collection->find();
        $events = array();
        foreach ($cursor as $result)
            if ($result["user_id"] == $user_id)
                array_push($events, $this->getEvent($result["event_id"]));
        return $events;
    }

    /**
     * @param $user_id
     * @return All of the users attending event.
     */
    public function getUsersAttending($event_id) {
        $collection = new MongoCollection($this->database, "attendance");
        $cursor = $collection->find();
        $users = array();
        array_push($users, $this->getEvent($event_id)["user"]["name"]);
        foreach ($cursor as $result)
            if ($result["event_id"] == $event_id)
                array_push($users, $this->getUserById($result["user_id"])["name"]);
        return $users;
    }

    /**
     * @param $user_id
     * @param $event_id
     * @return int If attendance was succesfully removed.
     */
    public function deleteAttending($user_id, $event_id) {
        if($this->isUserAttending($user_id, $event_id)) {
            $collection = new MongoCollection($this->database, "attendance");
            $collection->remove(array("user_id"=>new MongoId($user_id)), array("event_id" =>new MongoId($event_id)));
            return true;
        }
        return false;
    }
}
