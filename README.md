Boilermake 2014 RESTful API
============================

I designed this RESTful API to be used alongside the boilermake2014-Android application.

https://github.com/MasonHerhusky/BoilerMake2014Android

RESTful PHP backend using MongoDB to store information.

| Type                  | URL                                            | Method | Parametrs                            | Description                             | Requires Authentification? |
|-----------------------|------------------------------------------------|--------|--------------------------------------|-----------------------------------------|----------------------------|
| Account Management    | http://167.88.118.116/register                 | POST   | name, email, password                | Register User.                          | No                         |
|                       | http://167.88.118.116/login                    | POST   | email, password                      | Login User.                             | No                         |
| Event Management      | http://167.88.118.116/events                   | POST   | name, desc, start, end               | Create Event                            | Yes                        |
|                       | http://167.88.118.116/events                   | GET    | page                                 | Returns a page of elements.             | Yes                        |
|                       | http://167.88.118.116/events/{id}              | GET    |                                      | Gets a single event.                    | Yes                        |
|                       | http://167.88.118.116/events/{id}              | PUT    | optional paramaters same as creating | Updates a single event.                 | No                         |
|                       | http://167.88.118.116/events/{id}              | DELETE |                                      | Deletes a single event.                 | Yes                        |
| Attendance Management | http://167.88.118.116/events/attend/{event_id} | POST   |                                      | Marks user as attending.                | Yes                        |
|                       | http://167.88.118.116/events/attend/{event_id} | DELETE |                                      | DELETEs users attendance.               | Yes                        |
|                       | http://167.88.118.116/events/attend/{event_id} | GET    |                                      | Returns users attending event.          | Yes                        |
|                       | http://167.88.118.116/events/attend            | GET    |                                      | Returns ALL events a user is attending. | Yes.                       |
