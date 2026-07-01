@user
Feature:
  User

  Background:
    Given the following fixtures are loaded from the files:
      | user |

  Scenario: I can create fresh account by command with organization, and then I can login with it
    Given on the table from domain "App\Identity\Domain\User\User" I cannot find a row:
      | id          | NOT NULL       |
      | uuid        | NOT NULL       |
      | email.email | test@email.com |
      | password    | NOT NULL       |
    Then I run command "mw:create:account" with arguments:
      | email             | test@email.com |
      | password          | test1234       |
      | organization-name | test           |
    And on the table from domain "App\Identity\Domain\User\User" I can find a row:
      | id          | NOT NULL       |
      | uuid        | NOT NULL       |
      | email.email | test@email.com |
    And on the table from domain "App\Identity\Domain\Organization\Organization" I can find a row:
      | id   | NOT NULL |
      | uuid | NOT NULL |
      | name | test     |
    Then I am authorized as "test@email.com" with password "test1234"

  Scenario: I can create fresh account by command with default organization, and then I can login with it
    Given on the table from domain "App\Identity\Domain\User\User" I cannot find a row:
      | id          | NOT NULL       |
      | uuid        | NOT NULL       |
      | email.email | test@email.com |
      | password    | NOT NULL       |
    Then I run command "mw:create:account" with arguments:
      | email    | test@email.com |
      | password | test1234       |
    And on the table from domain "App\Identity\Domain\User\User" I can find a row:
      | id          | NOT NULL       |
      | uuid        | NOT NULL       |
      | email.email | test@email.com |
    And on the table from domain "App\Identity\Domain\Organization\Organization" I can find a row:
      | id   | NOT NULL             |
      | uuid | NOT NULL             |
      | name | Default Organization |
    Then I am authorized as "test@email.com" with password "test1234"

  Scenario: I cannot create account by command when email is already used
    Given on the table from domain "App\Identity\Domain\User\User" I can find a row:
      | id          | NOT NULL                     |
      | uuid        | NOT NULL                     |
      | email.email | owner@monitoring-webowi.test |
    Then I run command "mw:create:account" with arguments:
      | email    | owner@monitoring-webowi.test |
      | password | demo1234                     |
    Then I should see output containing "[ERROR] User with this email already exists."

  Scenario: I cannot create account by command without email
    Given I run command "mw:create:account" with arguments:
      | password | test |
    Then I should see output containing "[ERROR] Email is required."

  Scenario: I cannot create account by command without password
    Given I run command "mw:create:account" with arguments:
      | email | test |
    Then I should see output containing "[ERROR] Password is required."
