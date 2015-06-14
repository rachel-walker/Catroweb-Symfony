@api
Feature: Get the most downloaded programs

  Background:
    Given there are users:
      | name     | password | token      |
      | Catrobat | 12345    | cccccccccc |
      | User1    | vwxyz    | aaaaaaaaaa |
    And there are programs:
      | id | name      | description | owned by | downloads | RemixOf | RemixCount |
      | 1  | program 1 | p1          | Catrobat | 3         | null    | 10        |
      | 2  | program 2 |             | Catrobat | 333       | 1       | 100          |
      | 3  | program 3 |             | User1    | 133       | null    | 5         |
    And the current time is "01.08.2014 13:00"

      
  Scenario: show the most remixed program
    Given I have a parameter "limit" with value "1"
    When I GET "/pocketcode/api/programs/getMostRemixed.json" with these parameters
    Then I should get the json object:
      """
      {
          "CatrobatProjects":[{
                                "ProjectId": 2,
                                "ProjectName":"program 2",
                                "ProjectNameShort":"program 2",
                                "User":"Catrobat",
                                "UserId":1,
                                "Description":"",
                                "RemixOf":1,
                                "RemixCount":"100",
                                "ProjectUrl":"pocketcode/program/2",
                                "DownloadUrl":"pocketcode/download/2.catrobat"
                            }],
          "completeTerm":"",
          "preHeaderMessages":"",
          "CatrobatInformation": {
                                   "BaseUrl":"http://localhost/",
                                   "TotalProjects":3,
                                   "ProjectsExtension":".catrobat"
                                  }
      }
      """

  Scenario: show most remixed programs with limit and offset
    Given I have a parameter "limit" with value "2"
    When I GET "/pocketcode/api/programs/getMostRemixed.json" with these parameters
    Then I should get programs in the following order:
      | Name      |
      | program 2 |
      | program 1 |

  Scenario: get only visible programs
    Given program "program 1" is not visible
    And I have a parameter "limit" with value "2"
    When I GET "/pocketcode/api/programs/getMostRemixed.json" with these parameters
    Then I should get programs in the following order:
      | Name      |
      | program 2 |
      | program 3 |
